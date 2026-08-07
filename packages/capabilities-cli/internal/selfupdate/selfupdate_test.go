package selfupdate

import (
	"archive/tar"
	"bytes"
	"compress/gzip"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"testing"
)

const testRepo = "rawphp/capabilities-cli"
const testVersion = "1.2.3"
const testBinaryPayload = "#!/bin/sh\necho capabilities-new\n"

func assetName(version, goos, goarch string) string {
	return fmt.Sprintf("capabilities_%s_%s_%s.tar.gz", version, goos, goarch)
}

func makeTarGz(t *testing.T, binaryName string, content []byte) []byte {
	t.Helper()
	var buf bytes.Buffer
	gw := gzip.NewWriter(&buf)
	tw := tar.NewWriter(gw)
	hdr := &tar.Header{
		Name: binaryName,
		Mode: 0o755,
		Size: int64(len(content)),
	}
	if err := tw.WriteHeader(hdr); err != nil {
		t.Fatal(err)
	}
	if _, err := tw.Write(content); err != nil {
		t.Fatal(err)
	}
	if err := tw.Close(); err != nil {
		t.Fatal(err)
	}
	if err := gw.Close(); err != nil {
		t.Fatal(err)
	}
	return buf.Bytes()
}

func sha256Hex(b []byte) string {
	sum := sha256.Sum256(b)
	return hex.EncodeToString(sum[:])
}

// testReleaseServer serves install.sh-shaped GitHub release endpoints on one host:
// redirect /repo/releases/latest → tag URL; assets under /repo/releases/download/vX/...
// API fallback at /repos/repo/releases/latest.
type releaseFixture struct {
	Version      string
	OS           string
	Arch         string
	Archive      []byte
	Checksums    string // empty → 404 for checksums.txt
	AssetStatus  int    // 0 → 200
	LatestMode   string // "redirect" (default), "api", "fail"
	DownloadHits int
}

func (f *releaseFixture) handler(t *testing.T) http.Handler {
	t.Helper()
	mux := http.NewServeMux()
	repoPath := "/" + testRepo
	tagPath := repoPath + "/releases/tag/v" + f.Version
	asset := assetName(f.Version, f.OS, f.Arch)
	assetPath := repoPath + "/releases/download/v" + f.Version + "/" + asset
	checksumsPath := repoPath + "/releases/download/v" + f.Version + "/checksums.txt"
	apiPath := "/repos/" + testRepo + "/releases/latest"

	mux.HandleFunc(repoPath+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		switch f.LatestMode {
		case "fail":
			http.Error(w, "gone", http.StatusNotFound)
		case "api":
			// No useful redirect; clients should fall back to API.
			http.Error(w, "no redirect", http.StatusNotFound)
		default:
			http.Redirect(w, r, tagPath, http.StatusFound)
		}
	})
	mux.HandleFunc(tagPath, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte("release page"))
	})
	mux.HandleFunc(apiPath, func(w http.ResponseWriter, r *http.Request) {
		if f.LatestMode == "fail" {
			http.Error(w, "api fail", http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = fmt.Fprintf(w, `{"tag_name":"v%s"}`, f.Version)
	})
	mux.HandleFunc(assetPath, func(w http.ResponseWriter, r *http.Request) {
		f.DownloadHits++
		if f.AssetStatus != 0 {
			w.WriteHeader(f.AssetStatus)
			return
		}
		w.Header().Set("Content-Type", "application/gzip")
		_, _ = w.Write(f.Archive)
	})
	mux.HandleFunc(checksumsPath, func(w http.ResponseWriter, r *http.Request) {
		if f.Checksums == "" {
			http.NotFound(w, r)
			return
		}
		w.Header().Set("Content-Type", "text/plain")
		_, _ = io.WriteString(w, f.Checksums)
	})
	return mux
}

func startFixture(t *testing.T, f *releaseFixture) *httptest.Server {
	t.Helper()
	srv := httptest.NewServer(f.handler(t))
	t.Cleanup(srv.Close)
	return srv
}

func defaultOSArch(t *testing.T) (string, string) {
	t.Helper()
	goos := runtime.GOOS
	goarch := runtime.GOARCH
	if goos != "darwin" && goos != "linux" {
		t.Skip("host OS not supported by selfupdate tests")
	}
	if goarch != "amd64" && goarch != "arm64" {
		t.Skip("host arch not supported by selfupdate tests")
	}
	return goos, goarch
}

func writableTarget(t *testing.T) string {
	t.Helper()
	dir := t.TempDir()
	path := filepath.Join(dir, "capabilities")
	if err := os.WriteFile(path, []byte("old-binary"), 0o755); err != nil {
		t.Fatal(err)
	}
	return path
}

func optsFor(srv *httptest.Server, current, target, goos, goarch string) Options {
	return Options{
		CurrentVersion: current,
		TargetPath:     target,
		Repo:           testRepo,
		HTTPClient:     srv.Client(),
		GitHubBaseURL:  srv.URL,
		APIBaseURL:     srv.URL,
		GOOS:           goos,
		GOARCH:         goarch,
	}
}

func TestUpdateHappyPathRedirectAndAtomicReplace(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	sum := sha256Hex(archive)
	asset := assetName(testVersion, goos, goarch)
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: sum + "  " + asset + "\n",
	}
	srv := startFixture(t, f)
	target := writableTarget(t)

	res, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if err != nil {
		t.Fatalf("Update: %v", err)
	}
	if res.Outcome != OutcomeUpdated {
		t.Fatalf("outcome %v want Updated", res.Outcome)
	}
	if res.LatestVersion != testVersion {
		t.Fatalf("latest %q", res.LatestVersion)
	}
	got, err := os.ReadFile(target)
	if err != nil {
		t.Fatal(err)
	}
	if string(got) != testBinaryPayload {
		t.Fatalf("target content %q", got)
	}
	if f.DownloadHits != 1 {
		t.Fatalf("download hits %d", f.DownloadHits)
	}
	// No corrupt partial left beside target.
	entries, _ := os.ReadDir(filepath.Dir(target))
	for _, e := range entries {
		if strings.HasSuffix(e.Name(), ".partial") || strings.HasSuffix(e.Name(), ".tmp") {
			t.Fatalf("leftover temp file %s", e.Name())
		}
	}
}

func TestUpdateAlreadyLatestNoDownload(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	sum := sha256Hex(archive)
	asset := assetName(testVersion, goos, goarch)
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: sum + "  " + asset + "\n",
	}
	srv := startFixture(t, f)
	target := writableTarget(t)
	before, _ := os.ReadFile(target)

	res, err := Update(context.Background(), optsFor(srv, "v"+testVersion, target, goos, goarch))
	if err != nil {
		t.Fatalf("Update: %v", err)
	}
	if res.Outcome != OutcomeAlreadyLatest {
		t.Fatalf("outcome %v want AlreadyLatest", res.Outcome)
	}
	if f.DownloadHits != 0 {
		t.Fatalf("must not download when already latest; hits=%d", f.DownloadHits)
	}
	after, _ := os.ReadFile(target)
	if !bytes.Equal(before, after) {
		t.Fatal("target must be unchanged when already latest")
	}
}

func TestUpdateBadChecksumFailClosed(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	asset := assetName(testVersion, goos, goarch)
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: strings.Repeat("0", 64) + "  " + asset + "\n",
	}
	srv := startFixture(t, f)
	target := writableTarget(t)
	before, _ := os.ReadFile(target)

	_, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if err == nil {
		t.Fatal("expected checksum error")
	}
	if !errors.Is(err, ErrChecksumMismatch) {
		t.Fatalf("want ErrChecksumMismatch, got %v", err)
	}
	after, _ := os.ReadFile(target)
	if !bytes.Equal(before, after) {
		t.Fatal("target must remain unchanged on checksum failure")
	}
}

func TestUpdateMissingChecksumsFailClosed(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: "", // 404
	}
	srv := startFixture(t, f)
	target := writableTarget(t)
	before, _ := os.ReadFile(target)

	_, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if err == nil {
		t.Fatal("expected missing checksums error")
	}
	if !errors.Is(err, ErrChecksumMissing) {
		t.Fatalf("want ErrChecksumMissing, got %v", err)
	}
	after, _ := os.ReadFile(target)
	if !bytes.Equal(before, after) {
		t.Fatal("target must remain unchanged when checksums missing")
	}
}

func TestUpdateMissingChecksumEntryFailClosed(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: sha256Hex(archive) + "  other-file.tar.gz\n",
	}
	srv := startFixture(t, f)
	target := writableTarget(t)

	_, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if err == nil {
		t.Fatal("expected missing checksum entry error")
	}
	if !errors.Is(err, ErrChecksumMissing) {
		t.Fatalf("want ErrChecksumMissing, got %v", err)
	}
}

func TestUpdateUnwritableTarget(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	sum := sha256Hex(archive)
	asset := assetName(testVersion, goos, goarch)
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: sum + "  " + asset + "\n",
	}
	srv := startFixture(t, f)

	// Directory without write permission.
	dir := t.TempDir()
	roDir := filepath.Join(dir, "ro")
	if err := os.Mkdir(roDir, 0o555); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = os.Chmod(roDir, 0o755) })
	target := filepath.Join(roDir, "capabilities")
	// Pre-create file as root of unwritable scenario: parent dir not writable.
	// If we cannot create file, Update should still fail closed on replace/write check.

	_, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if err == nil {
		t.Fatal("expected unwritable error")
	}
	if !errors.Is(err, ErrUnwritable) {
		t.Fatalf("want ErrUnwritable, got %v", err)
	}
}

func TestUpdateUnsupportedOS(t *testing.T) {
	// No network needed; fail before download.
	target := writableTarget(t)
	_, err := Update(context.Background(), Options{
		CurrentVersion: "1.0.0",
		TargetPath:     target,
		Repo:           testRepo,
		HTTPClient:     http.DefaultClient,
		GOOS:           "windows",
		GOARCH:         "amd64",
	})
	if err == nil {
		t.Fatal("expected unsupported OS error")
	}
	if !errors.Is(err, ErrUnsupportedOS) {
		t.Fatalf("want ErrUnsupportedOS, got %v", err)
	}
}

func TestUpdateAPIFallbackWhenRedirectMissing(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	sum := sha256Hex(archive)
	asset := assetName(testVersion, goos, goarch)
	f := &releaseFixture{
		Version:    testVersion,
		OS:         goos,
		Arch:       goarch,
		Archive:    archive,
		Checksums:  sum + "  " + asset + "\n",
		LatestMode: "api",
	}
	srv := startFixture(t, f)
	target := writableTarget(t)

	res, err := Update(context.Background(), optsFor(srv, "0.9.0", target, goos, goarch))
	if err != nil {
		t.Fatalf("Update: %v", err)
	}
	if res.Outcome != OutcomeUpdated {
		t.Fatalf("outcome %v", res.Outcome)
	}
	if res.LatestVersion != testVersion {
		t.Fatalf("latest %q", res.LatestVersion)
	}
}

func TestUpdateNetworkFailure(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	// Closed server → connection refused on resolve.
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	url := srv.URL
	srv.Close()

	target := writableTarget(t)
	_, err := Update(context.Background(), Options{
		CurrentVersion: "1.0.0",
		TargetPath:     target,
		Repo:           testRepo,
		HTTPClient:     &http.Client{},
		GitHubBaseURL:  url,
		APIBaseURL:     url,
		GOOS:           goos,
		GOARCH:         goarch,
	})
	if err == nil {
		t.Fatal("expected network/HTTP error")
	}
	if !errors.Is(err, ErrHTTP) && !errors.Is(err, ErrNetwork) {
		// Accept either typed wrapper; message must be clear.
		if !strings.Contains(strings.ToLower(err.Error()), "http") &&
			!strings.Contains(strings.ToLower(err.Error()), "network") &&
			!strings.Contains(strings.ToLower(err.Error()), "connect") &&
			!strings.Contains(strings.ToLower(err.Error()), "resolve") {
			t.Fatalf("unclear network error: %v", err)
		}
	}
}

func TestNormalizeVersion(t *testing.T) {
	if got := NormalizeVersion("v1.2.3"); got != "1.2.3" {
		t.Fatalf("got %q", got)
	}
	if got := NormalizeVersion("1.2.3"); got != "1.2.3" {
		t.Fatalf("got %q", got)
	}
}

func TestAssetNameMatchesInstallSh(t *testing.T) {
	// Mirror scripts/install.sh: capabilities_${VERSION}_${os}_${arch}.tar.gz
	got := AssetName("0.4.0", "darwin", "arm64")
	want := "capabilities_0.4.0_darwin_arm64.tar.gz"
	if got != want {
		t.Fatalf("got %q want %q", got, want)
	}
}
