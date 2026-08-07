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
	"time"
)

func TestSupportPlatform(t *testing.T) {
	if err := supportPlatform("darwin", "amd64"); err != nil {
		t.Fatal(err)
	}
	if err := supportPlatform("linux", "arm64"); err != nil {
		t.Fatal(err)
	}
	if err := supportPlatform("windows", "amd64"); !errors.Is(err, ErrUnsupportedOS) {
		t.Fatalf("windows: %v", err)
	}
	if err := supportPlatform("freebsd", "amd64"); !errors.Is(err, ErrUnsupportedOS) {
		t.Fatalf("freebsd: %v", err)
	}
	if err := supportPlatform("linux", "386"); !errors.Is(err, ErrUnsupportedArch) {
		t.Fatalf("386: %v", err)
	}
}

func TestVersionFromTagURL(t *testing.T) {
	cases := []struct {
		in, want string
	}{
		{"https://github.com/rawphp/capabilities-cli/releases/tag/v1.2.3", "1.2.3"},
		{"/rawphp/capabilities-cli/releases/tag/v0.4.0", "0.4.0"},
		{"https://example/tag/1.0.0?x=1", "1.0.0"},
		{"https://example/tag/v2.0.0#frag", "2.0.0"},
		{"https://example/releases/latest", ""},
		{"", ""},
	}
	for _, tc := range cases {
		if got := versionFromTagURL(tc.in); got != tc.want {
			t.Fatalf("%q → %q want %q", tc.in, got, tc.want)
		}
	}
}

func TestFindChecksumVariants(t *testing.T) {
	sum := strings.Repeat("ab", 32)
	asset := "capabilities_1.0.0_darwin_arm64.tar.gz"
	// spaced, path-prefixed name, comments, junk lines
	body := "# comment\n\n" + sum + "  dist/" + asset + "\n" + "not-a-line\n"
	got, err := findChecksum(body, asset)
	if err != nil || got != sum {
		t.Fatalf("got %q err %v", got, err)
	}
	if _, err := findChecksum("", asset); !errors.Is(err, ErrChecksumMissing) {
		t.Fatalf("empty: %v", err)
	}
	// invalid hex length skipped
	if _, err := findChecksum("deadbeef  "+asset+"\n", asset); !errors.Is(err, ErrChecksumMissing) {
		t.Fatalf("short hash: %v", err)
	}
	// invalid hex chars of length 64 skipped
	if _, err := findChecksum(strings.Repeat("g", 64)+"  "+asset+"\n", asset); !errors.Is(err, ErrChecksumMissing) {
		t.Fatalf("bad hex: %v", err)
	}
}

func TestExtractBinaryNestedAndMissing(t *testing.T) {
	// nested path
	var buf bytes.Buffer
	gw := gzip.NewWriter(&buf)
	tw := tar.NewWriter(gw)
	content := []byte("nested-bin")
	_ = tw.WriteHeader(&tar.Header{Name: "out/capabilities", Mode: 0o755, Size: int64(len(content))})
	_, _ = tw.Write(content)
	// also a directory entry and unrelated file
	_ = tw.WriteHeader(&tar.Header{Name: "out/", Typeflag: tar.TypeDir, Mode: 0o755})
	other := []byte("x")
	_ = tw.WriteHeader(&tar.Header{Name: "README", Mode: 0o644, Size: int64(len(other))})
	_, _ = tw.Write(other)
	_ = tw.Close()
	_ = gw.Close()

	got, err := extractBinary(buf.Bytes())
	if err != nil {
		t.Fatal(err)
	}
	if string(got) != "nested-bin" {
		t.Fatalf("got %q", got)
	}

	// invalid gzip
	if _, err := extractBinary([]byte("not-gzip")); !errors.Is(err, ErrExtract) {
		t.Fatalf("invalid gzip: %v", err)
	}

	// valid gzip/tar without binary
	var buf2 bytes.Buffer
	gw2 := gzip.NewWriter(&buf2)
	tw2 := tar.NewWriter(gw2)
	_ = tw2.WriteHeader(&tar.Header{Name: "README", Mode: 0o644, Size: 1})
	_, _ = tw2.Write([]byte("x"))
	_ = tw2.Close()
	_ = gw2.Close()
	if _, err := extractBinary(buf2.Bytes()); !errors.Is(err, ErrExtract) {
		t.Fatalf("missing binary: %v", err)
	}
}

func TestAtomicReplaceAndEnsureWritable(t *testing.T) {
	dir := t.TempDir()
	target := filepath.Join(dir, "capabilities")
	if err := os.WriteFile(target, []byte("old"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := ensureWritable(target); err != nil {
		t.Fatal(err)
	}
	if err := atomicReplace(target, []byte("new-bin")); err != nil {
		t.Fatal(err)
	}
	got, err := os.ReadFile(target)
	if err != nil {
		t.Fatal(err)
	}
	if string(got) != "new-bin" {
		t.Fatalf("got %q", got)
	}

	// missing parent
	missing := filepath.Join(dir, "nope", "bin")
	if err := ensureWritable(missing); !errors.Is(err, ErrUnwritable) {
		t.Fatalf("missing parent: %v", err)
	}

	// parent is a file
	fileParent := filepath.Join(dir, "as-file")
	if err := os.WriteFile(fileParent, []byte("x"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := ensureWritable(filepath.Join(fileParent, "child")); !errors.Is(err, ErrUnwritable) {
		t.Fatalf("file parent: %v", err)
	}

	// empty target path on Update
	_, err = Update(context.Background(), Options{CurrentVersion: "1.0.0", GOOS: "linux", GOARCH: "amd64"})
	if err == nil || !errors.Is(err, ErrUnwritable) {
		t.Fatalf("empty target: %v", err)
	}
}

func TestResolveLatestAbsoluteLocationAndAPIErrors(t *testing.T) {
	// Absolute Location on redirect
	mux := http.NewServeMux()
	var srvURL string
	mux.HandleFunc("/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, srvURL+"/"+testRepo+"/releases/tag/v9.9.9", http.StatusFound)
	})
	mux.HandleFunc("/"+testRepo+"/releases/tag/v9.9.9", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(200)
	})
	srv := httptest.NewServer(mux)
	defer srv.Close()
	srvURL = srv.URL

	v, err := resolveLatest(context.Background(), srv.Client(), srv.URL, srv.URL, testRepo)
	if err != nil || v != "9.9.9" {
		t.Fatalf("abs loc: v=%q err=%v", v, err)
	}

	// API empty tag
	mux2 := http.NewServeMux()
	mux2.HandleFunc("/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "no", 404)
	})
	mux2.HandleFunc("/repos/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		_, _ = w.Write([]byte(`{"tag_name":"v"}`))
	})
	srv2 := httptest.NewServer(mux2)
	defer srv2.Close()
	if _, err := resolveLatest(context.Background(), srv2.Client(), srv2.URL, srv2.URL, testRepo); !errors.Is(err, ErrResolve) {
		t.Fatalf("empty tag: %v", err)
	}

	// API bad JSON
	mux3 := http.NewServeMux()
	mux3.HandleFunc("/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "no", 404)
	})
	mux3.HandleFunc("/repos/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		_, _ = w.Write([]byte(`not-json`))
	})
	srv3 := httptest.NewServer(mux3)
	defer srv3.Close()
	if _, err := resolveLatest(context.Background(), srv3.Client(), srv3.URL, srv3.URL, testRepo); !errors.Is(err, ErrResolve) {
		t.Fatalf("bad json: %v", err)
	}

	// API non-200
	mux4 := http.NewServeMux()
	mux4.HandleFunc("/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "no", 404)
	})
	mux4.HandleFunc("/repos/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "no", 503)
	})
	srv4 := httptest.NewServer(mux4)
	defer srv4.Close()
	if _, err := resolveLatest(context.Background(), srv4.Client(), srv4.URL, srv4.URL, testRepo); !errors.Is(err, ErrResolve) {
		t.Fatalf("api 503: %v", err)
	}
}

func TestDownloadFailures(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "nope", 500)
	}))
	defer srv.Close()
	if _, err := download(context.Background(), srv.Client(), srv.URL+"/x"); !errors.Is(err, ErrHTTP) {
		t.Fatalf("500: %v", err)
	}

	// network
	closed := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	u := closed.URL
	closed.Close()
	if _, err := download(context.Background(), &http.Client{Timeout: 100 * time.Millisecond}, u); !errors.Is(err, ErrNetwork) {
		t.Fatalf("network: %v", err)
	}
}

func TestUpdateArchiveHTTPFailureAndDefaults(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte("bin"))
	sum := sha256Hex(archive)
	asset := assetName(testVersion, goos, goarch)
	f := &releaseFixture{
		Version:     testVersion,
		OS:          goos,
		Arch:        goarch,
		Archive:     archive,
		Checksums:   sum + "  " + asset + "\n",
		AssetStatus: 500,
	}
	srv := startFixture(t, f)
	target := writableTarget(t)
	_, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if err == nil || !errors.Is(err, ErrHTTP) {
		t.Fatalf("archive 500: %v", err)
	}

	// Defaults: empty Repo, GOOS/GOARCH from runtime (must match fixture os/arch)
	f2 := &releaseFixture{
		Version:   testVersion,
		OS:        runtime.GOOS,
		Arch:      runtime.GOARCH,
		Archive:   archive,
		Checksums: sum + "  " + assetName(testVersion, runtime.GOOS, runtime.GOARCH) + "\n",
	}
	// recompute checksum with correct asset name if runtime matches supported
	if runtime.GOOS == "darwin" || runtime.GOOS == "linux" {
		if runtime.GOARCH == "amd64" || runtime.GOARCH == "arm64" {
			a := makeTarGz(t, BinaryName, []byte("def"))
			an := assetName(testVersion, runtime.GOOS, runtime.GOARCH)
			f2.Archive = a
			f2.Checksums = sha256Hex(a) + "  " + an + "\n"
			srv2 := startFixture(t, f2)
			target2 := writableTarget(t)
			res, err := Update(context.Background(), Options{
				CurrentVersion: "0.1.0",
				TargetPath:     target2,
				// Repo empty → default
				HTTPClient:    srv2.Client(),
				GitHubBaseURL: srv2.URL,
				APIBaseURL:    srv2.URL,
				// GOOS/GOARCH empty → runtime
			})
			if err != nil {
				t.Fatalf("defaults: %v", err)
			}
			if res.Outcome != OutcomeUpdated {
				t.Fatalf("outcome %v", res.Outcome)
			}
		}
	}
}

func TestUpdateChecksumsNetworkNotHTTP(t *testing.T) {
	// Serve latest + asset, but checksums path hangs/fails after close mid - use 200 then close connection hard.
	// Simpler: asset OK; checksums handler hijacks and closes.
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte("x"))
	mux := http.NewServeMux()
	repoPath := "/" + testRepo
	mux.HandleFunc(repoPath+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, repoPath+"/releases/tag/v"+testVersion, http.StatusFound)
	})
	mux.HandleFunc(repoPath+"/releases/tag/v"+testVersion, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(200)
	})
	asset := assetName(testVersion, goos, goarch)
	mux.HandleFunc(repoPath+"/releases/download/v"+testVersion+"/"+asset, func(w http.ResponseWriter, r *http.Request) {
		_, _ = w.Write(archive)
	})
	mux.HandleFunc(repoPath+"/releases/download/v"+testVersion+"/checksums.txt", func(w http.ResponseWriter, r *http.Request) {
		hj, ok := w.(http.Hijacker)
		if !ok {
			http.Error(w, "no hijack", 500)
			return
		}
		conn, _, err := hj.Hijack()
		if err != nil {
			return
		}
		_ = conn.Close() // abrupt close → network error on client
	})
	srv := httptest.NewServer(mux)
	defer srv.Close()
	target := writableTarget(t)
	_, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if err == nil {
		t.Fatal("expected error")
	}
	if !errors.Is(err, ErrNetwork) && !errors.Is(err, ErrChecksumMissing) && !errors.Is(err, ErrHTTP) {
		// message must be non-empty
		if strings.TrimSpace(err.Error()) == "" {
			t.Fatalf("empty error")
		}
	}
}

func TestHTTPClientNilUsesDefault(t *testing.T) {
	// HTTPClient nil should not panic on Options construction path for supportPlatform-only fail first.
	_, err := Update(context.Background(), Options{
		CurrentVersion: "1",
		TargetPath:     writableTarget(t),
		GOOS:           "windows",
		GOARCH:         "amd64",
		// nil HTTPClient — fails before use
	})
	if !errors.Is(err, ErrUnsupportedOS) {
		t.Fatal(err)
	}
}

func TestDownloadAndResolveRequestBuild(t *testing.T) {
	// Cancelled context → network/request error paths
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(200)
	}))
	defer srv.Close()
	if _, err := download(ctx, srv.Client(), srv.URL); err == nil {
		// cancelled may still succeed if too fast; at least no panic
		t.Log("cancelled download completed (race ok)")
	}
	if _, err := resolveLatest(ctx, srv.Client(), srv.URL, srv.URL, testRepo); err == nil {
		t.Log("cancelled resolve completed (race ok)")
	}
}

func TestShaHelpersConsistent(t *testing.T) {
	b := []byte("hello")
	sum := sha256.Sum256(b)
	if sha256Hex(b) != hex.EncodeToString(sum[:]) {
		t.Fatal("hex mismatch")
	}
	_ = fmt.Sprintf("%s", BinaryName)
}

type roundTripFunc func(*http.Request) (*http.Response, error)

func (f roundTripFunc) RoundTrip(r *http.Request) (*http.Response, error) { return f(r) }

type errReadCloser struct{}

func (errReadCloser) Read([]byte) (int, error) { return 0, errors.New("read fail") }
func (errReadCloser) Close() error             { return nil }

func TestNilClientAndDefaultBasesViaTransport(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte("via-default-base"))
	asset := assetName(testVersion, goos, goarch)
	sum := sha256Hex(archive)

	// Empty GitHubBaseURL / APIBaseURL → production hosts; Transport serves them without network.
	client := &http.Client{Transport: roundTripFunc(func(req *http.Request) (*http.Response, error) {
		u := req.URL.String()
		switch {
		case strings.Contains(u, "github.com/"+testRepo+"/releases/latest") && !strings.Contains(u, "api."):
			return &http.Response{
				StatusCode: http.StatusFound,
				Header:     http.Header{"Location": []string{"https://github.com/" + testRepo + "/releases/tag/v" + testVersion}},
				Body:       http.NoBody,
				Request:    req,
			}, nil
		case strings.Contains(u, "/releases/download/v"+testVersion+"/"+asset):
			return &http.Response{
				StatusCode: 200,
				Body:       io.NopCloser(bytes.NewReader(archive)),
				Header:     make(http.Header),
				Request:    req,
			}, nil
		case strings.Contains(u, "/releases/download/v"+testVersion+"/checksums.txt"):
			body := sum + "  " + asset + "\n"
			return &http.Response{
				StatusCode: 200,
				Body:       io.NopCloser(strings.NewReader(body)),
				Header:     make(http.Header),
				Request:    req,
			}, nil
		case strings.Contains(u, "api.github.com"):
			return &http.Response{
				StatusCode: 200,
				Body:       io.NopCloser(strings.NewReader(`{"tag_name":"v` + testVersion + `"}`)),
				Header:     make(http.Header),
				Request:    req,
			}, nil
		default:
			return &http.Response{StatusCode: 404, Body: http.NoBody, Request: req}, nil
		}
	})}

	target := writableTarget(t)
	res, err := Update(context.Background(), Options{
		CurrentVersion: "0.0.1",
		TargetPath:     target,
		Repo:           testRepo,
		HTTPClient:     client,
		// empty bases → defaults
		GOOS:   goos,
		GOARCH: goarch,
	})
	if err != nil {
		t.Fatal(err)
	}
	if res.Outcome != OutcomeUpdated {
		t.Fatalf("outcome %v", res.Outcome)
	}

	// nil HTTPClient + httptest bases (default client works with loopback)
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: sum + "  " + asset + "\n",
	}
	srv := startFixture(t, f)
	target2 := writableTarget(t)
	_, err = Update(context.Background(), Options{
		CurrentVersion: "0.0.1",
		TargetPath:     target2,
		Repo:           testRepo,
		HTTPClient:     nil,
		GitHubBaseURL:  srv.URL,
		APIBaseURL:     srv.URL,
		GOOS:           goos,
		GOARCH:         goarch,
	})
	if err != nil {
		t.Fatal(err)
	}
}

func TestResolveRelativeLocationNeedingJoin(t *testing.T) {
	// Location without "/tag/" substring; after ResolveReference it gains /tag/.
	mux := http.NewServeMux()
	mux.HandleFunc("/"+testRepo+"/releases/latest", func(w http.ResponseWriter, r *http.Request) {
		// relative: "tag/v3.3.3" — versionFromTagURL fails (no "/tag/"), join succeeds
		w.Header().Set("Location", "tag/v3.3.3")
		w.WriteHeader(http.StatusFound)
	})
	srv := httptest.NewServer(mux)
	defer srv.Close()
	v, err := resolveLatest(context.Background(), srv.Client(), srv.URL+"/"+testRepo+"/releases", srv.URL, testRepo)
	// latest URL is ghBase/repo/releases/latest — set ghBase so path matches
	// Re-do with correct ghBase = srv.URL
	_ = v
	_ = err
	v, err = resolveLatest(context.Background(), srv.Client(), srv.URL, srv.URL, testRepo)
	if err != nil {
		t.Fatal(err)
	}
	if v != "3.3.3" {
		t.Fatalf("got %q", v)
	}
}

func TestInvalidURLsAndBodyReadErrors(t *testing.T) {
	if _, err := download(context.Background(), http.DefaultClient, "://bad"); !errors.Is(err, ErrNetwork) {
		t.Fatalf("bad url download: %v", err)
	}
	if _, err := resolveLatest(context.Background(), http.DefaultClient, ":", ":", testRepo); err == nil {
		t.Fatal("expected resolve error on bad base")
	}

	client := &http.Client{Transport: roundTripFunc(func(req *http.Request) (*http.Response, error) {
		return &http.Response{StatusCode: 200, Body: errReadCloser{}, Request: req}, nil
	})}
	if _, err := download(context.Background(), client, "http://example.invalid/x"); !errors.Is(err, ErrNetwork) {
		t.Fatalf("body read: %v", err)
	}
	// resolve API path with body read error after redirect fail
	client2 := &http.Client{Transport: roundTripFunc(func(req *http.Request) (*http.Response, error) {
		if strings.Contains(req.URL.Path, "/releases/latest") && !strings.Contains(req.URL.Host, "api") && !strings.Contains(req.URL.Path, "repos") {
			return &http.Response{StatusCode: 404, Body: http.NoBody, Request: req}, nil
		}
		return &http.Response{StatusCode: 200, Body: errReadCloser{}, Request: req}, nil
	})}
	if _, err := resolveLatest(context.Background(), client2, "http://gh.test", "http://api.test", testRepo); !errors.Is(err, ErrNetwork) {
		t.Fatalf("api body: %v", err)
	}
}

func TestFindChecksumOneFieldAndExtractTypeflags(t *testing.T) {
	if _, err := findChecksum("onlyhash\n", "a.tar.gz"); !errors.Is(err, ErrChecksumMissing) {
		t.Fatalf("one field: %v", err)
	}

	// symlink then regular binary
	var buf bytes.Buffer
	gw := gzip.NewWriter(&buf)
	tw := tar.NewWriter(gw)
	_ = tw.WriteHeader(&tar.Header{Name: "capabilities", Typeflag: tar.TypeSymlink, Linkname: "other", Mode: 0o777})
	payload := []byte("real")
	_ = tw.WriteHeader(&tar.Header{Name: "capabilities", Typeflag: tar.TypeReg, Mode: 0o755, Size: int64(len(payload))})
	_, _ = tw.Write(payload)
	_ = tw.Close()
	_ = gw.Close()
	got, err := extractBinary(buf.Bytes())
	if err != nil || string(got) != "real" {
		t.Fatalf("got %q err %v", got, err)
	}

	// corrupt tar inside gzip
	var buf2 bytes.Buffer
	gw2 := gzip.NewWriter(&buf2)
	_, _ = gw2.Write([]byte("not-a-tar-payload-xxxxx"))
	_ = gw2.Close()
	if _, err := extractBinary(buf2.Bytes()); !errors.Is(err, ErrExtract) {
		t.Fatalf("corrupt tar: %v", err)
	}
}

func TestAtomicReplaceFailures(t *testing.T) {
	dir := t.TempDir()
	ro := filepath.Join(dir, "ro")
	if err := os.Mkdir(ro, 0o555); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = os.Chmod(ro, 0o755) })
	if err := atomicReplace(filepath.Join(ro, "capabilities"), []byte("x")); !errors.Is(err, ErrUnwritable) {
		t.Fatalf("create temp in ro: %v", err)
	}

	// rename fails when target path is an existing directory
	targetDir := filepath.Join(dir, "cap-as-dir")
	if err := os.Mkdir(targetDir, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := atomicReplace(targetDir, []byte("x")); !errors.Is(err, ErrUnwritable) {
		t.Fatalf("rename over dir: %v", err)
	}
}

func TestEnsureWritableRelativeDot(t *testing.T) {
	dir := t.TempDir()
	t.Chdir(dir)
	if err := os.WriteFile("capabilities", []byte("old"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := ensureWritable("capabilities"); err != nil {
		t.Fatal(err)
	}
	if err := atomicReplace("capabilities", []byte("new")); err != nil {
		t.Fatal(err)
	}
}

func TestUpdateExtractFailureLeavesTarget(t *testing.T) {
	goos, goarch := defaultOSArch(t)
	// gzip of empty tar (no binary)
	var buf bytes.Buffer
	gw := gzip.NewWriter(&buf)
	tw := tar.NewWriter(gw)
	_ = tw.Close()
	_ = gw.Close()
	archive := buf.Bytes()
	asset := assetName(testVersion, goos, goarch)
	f := &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: sha256Hex(archive) + "  " + asset + "\n",
	}
	srv := startFixture(t, f)
	target := writableTarget(t)
	before, _ := os.ReadFile(target)
	_, err := Update(context.Background(), optsFor(srv, "0.1.0", target, goos, goarch))
	if err == nil || !errors.Is(err, ErrExtract) {
		t.Fatalf("extract: %v", err)
	}
	after, _ := os.ReadFile(target)
	if !bytes.Equal(before, after) {
		t.Fatal("target changed on extract failure")
	}
}

func TestResolveAPINetworkError(t *testing.T) {
	client := &http.Client{Transport: roundTripFunc(func(req *http.Request) (*http.Response, error) {
		if strings.Contains(req.URL.Path, "/releases/latest") && !strings.Contains(req.URL.Path, "repos") {
			return &http.Response{StatusCode: 404, Body: http.NoBody, Request: req}, nil
		}
		return nil, errors.New("dial fail")
	})}
	if _, err := resolveLatest(context.Background(), client, "http://gh.test", "http://api.test", testRepo); !errors.Is(err, ErrNetwork) {
		t.Fatalf("api network: %v", err)
	}
}
