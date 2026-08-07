// Package selfupdate resolves, downloads, verifies, and atomically replaces
// the capabilities CLI binary from GitHub Releases (rawphp/capabilities-cli).
//
// Conventions mirror scripts/install.sh: asset naming, latest-only resolve
// (redirect preferred, API fallback), darwin/linux only. Checksums are required
// and fail closed on missing/mismatch. Unit tests inject HTTP via httptest.
package selfupdate

import (
	"archive/tar"
	"bytes"
	"compress/gzip"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

const (
	// DefaultRepo is the GitHub owner/name for release assets.
	DefaultRepo = "rawphp/capabilities-cli"
	// BinaryName is the archive entry and installed binary name.
	BinaryName = "capabilities"
	// ChecksumsName is the release checksums asset (GoReleaser).
	ChecksumsName = "checksums.txt"

	defaultGitHubBase = "https://github.com"
	defaultAPIBase    = "https://api.github.com"
)

// Outcome is the success result of Update (no error).
type Outcome int

const (
	// OutcomeUpdated means a newer release was installed at TargetPath.
	OutcomeUpdated Outcome = iota
	// OutcomeAlreadyLatest means CurrentVersion equals the latest release;
	// no download or replace was performed.
	OutcomeAlreadyLatest
)

// Result is returned on successful Update.
type Result struct {
	Outcome        Outcome
	CurrentVersion string
	LatestVersion  string
	TargetPath     string
	AssetName      string
}

// Options configures Update. HTTP bases and GOOS/GOARCH are injectable for tests.
type Options struct {
	// CurrentVersion is the running binary version (leading "v" optional).
	CurrentVersion string
	// TargetPath is the absolute or relative path of the binary to replace.
	TargetPath string
	// Repo is owner/name (default rawphp/capabilities-cli).
	Repo string
	// HTTPClient is used for all requests; when nil, a 60s client is used.
	HTTPClient *http.Client
	// GitHubBaseURL defaults to https://github.com (override for httptest).
	GitHubBaseURL string
	// APIBaseURL defaults to https://api.github.com (override for httptest).
	APIBaseURL string
	// GOOS/GOARCH override runtime (for tests). Empty → runtime.GOOS/GOARCH.
	GOOS   string
	GOARCH string
}

// Sentinel errors for clear fail-closed outcomes.
var (
	ErrUnsupportedOS    = errors.New("selfupdate: unsupported OS (darwin and linux only; Windows is not supported)")
	ErrUnsupportedArch  = errors.New("selfupdate: unsupported architecture (need amd64 or arm64)")
	ErrUnwritable       = errors.New("selfupdate: target path is not writable")
	ErrChecksumMissing  = errors.New("selfupdate: checksums.txt missing or has no entry for release asset")
	ErrChecksumMismatch = errors.New("selfupdate: downloaded asset checksum does not match checksums.txt")
	ErrHTTP             = errors.New("selfupdate: HTTP request failed")
	ErrNetwork          = errors.New("selfupdate: network error")
	ErrResolve          = errors.New("selfupdate: could not resolve latest release")
	ErrExtract          = errors.New("selfupdate: archive did not contain capabilities binary")
)

// NormalizeVersion strips a leading "v" (install.sh / tag convention).
func NormalizeVersion(v string) string {
	return strings.TrimPrefix(strings.TrimSpace(v), "v")
}

// AssetName matches scripts/install.sh:
// capabilities_${VERSION}_${os}_${arch}.tar.gz
func AssetName(version, goos, goarch string) string {
	return fmt.Sprintf("%s_%s_%s_%s.tar.gz", BinaryName, NormalizeVersion(version), goos, goarch)
}

// Update resolves latest, optionally downloads + verifies, and atomically replaces TargetPath.
func Update(ctx context.Context, opt Options) (*Result, error) {
	if opt.TargetPath == "" {
		return nil, fmt.Errorf("%w: empty target path", ErrUnwritable)
	}
	goos := opt.GOOS
	if goos == "" {
		goos = runtime.GOOS
	}
	goarch := opt.GOARCH
	if goarch == "" {
		goarch = runtime.GOARCH
	}
	if err := supportPlatform(goos, goarch); err != nil {
		return nil, err
	}

	repo := opt.Repo
	if repo == "" {
		repo = DefaultRepo
	}
	client := opt.HTTPClient
	if client == nil {
		client = &http.Client{Timeout: 60 * time.Second}
	}
	ghBase := strings.TrimRight(opt.GitHubBaseURL, "/")
	if ghBase == "" {
		ghBase = defaultGitHubBase
	}
	apiBase := strings.TrimRight(opt.APIBaseURL, "/")
	if apiBase == "" {
		apiBase = defaultAPIBase
	}

	current := NormalizeVersion(opt.CurrentVersion)

	// Fail early on unwritable parent so we do not download only to fail at replace.
	if err := ensureWritable(opt.TargetPath); err != nil {
		return nil, err
	}

	latest, err := resolveLatest(ctx, client, ghBase, apiBase, repo)
	if err != nil {
		return nil, err
	}
	latest = NormalizeVersion(latest)

	res := &Result{
		CurrentVersion: current,
		LatestVersion:  latest,
		TargetPath:     opt.TargetPath,
	}
	if current != "" && current == latest {
		res.Outcome = OutcomeAlreadyLatest
		return res, nil
	}

	asset := AssetName(latest, goos, goarch)
	res.AssetName = asset

	archiveURL := fmt.Sprintf("%s/%s/releases/download/v%s/%s", ghBase, repo, latest, asset)
	checksumsURL := fmt.Sprintf("%s/%s/releases/download/v%s/%s", ghBase, repo, latest, ChecksumsName)

	archiveBody, err := download(ctx, client, archiveURL)
	if err != nil {
		return nil, err
	}
	checksumsBody, err := download(ctx, client, checksumsURL)
	if err != nil {
		// HTTP 404 and empty → missing checksums fail closed.
		if errors.Is(err, ErrHTTP) {
			return nil, fmt.Errorf("%w: %v", ErrChecksumMissing, err)
		}
		return nil, err
	}
	wantSum, err := findChecksum(string(checksumsBody), asset)
	if err != nil {
		return nil, err
	}
	gotSum := sha256.Sum256(archiveBody)
	if !strings.EqualFold(hex.EncodeToString(gotSum[:]), wantSum) {
		return nil, fmt.Errorf("%w: asset %s", ErrChecksumMismatch, asset)
	}

	bin, err := extractBinary(archiveBody)
	if err != nil {
		return nil, err
	}
	if err := atomicReplace(opt.TargetPath, bin); err != nil {
		return nil, err
	}

	res.Outcome = OutcomeUpdated
	return res, nil
}

func supportPlatform(goos, goarch string) error {
	switch goos {
	case "darwin", "linux":
		// ok
	case "windows":
		return ErrUnsupportedOS
	default:
		return fmt.Errorf("%w: %s", ErrUnsupportedOS, goos)
	}
	switch goarch {
	case "amd64", "arm64":
		return nil
	default:
		return fmt.Errorf("%w: %s", ErrUnsupportedArch, goarch)
	}
}

// ensureWritable checks that the target file can be replaced (parent dir writable).
func ensureWritable(target string) error {
	dir := filepath.Dir(target)
	if dir == "" || dir == "." {
		dir = "."
	}
	info, err := os.Stat(dir)
	if err != nil {
		if os.IsNotExist(err) {
			// Parent missing — try create later; for now fail if we cannot write probe.
			return fmt.Errorf("%w: parent directory %s: %v", ErrUnwritable, dir, err)
		}
		return fmt.Errorf("%w: %v", ErrUnwritable, err)
	}
	if !info.IsDir() {
		return fmt.Errorf("%w: parent is not a directory", ErrUnwritable)
	}
	// Probe write by creating a temp file in the same directory.
	f, err := os.CreateTemp(dir, ".selfupdate-write-probe-*")
	if err != nil {
		return fmt.Errorf("%w: %v", ErrUnwritable, err)
	}
	name := f.Name()
	_ = f.Close()
	_ = os.Remove(name)
	return nil
}

func resolveLatest(ctx context.Context, client *http.Client, ghBase, apiBase, repo string) (string, error) {
	// Prefer redirect Location from /releases/latest (install.sh).
	latestURL := fmt.Sprintf("%s/%s/releases/latest", ghBase, repo)
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, latestURL, nil)
	if err != nil {
		return "", fmt.Errorf("%w: %v", ErrNetwork, err)
	}
	// Do not follow redirects so we can read Location; if followed, parse final URL.
	noRedirect := *client
	noRedirect.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	resp, err := noRedirect.Do(req)
	if err != nil {
		return "", fmt.Errorf("%w: resolve latest: %v", ErrNetwork, err)
	}
	defer resp.Body.Close()
	_, _ = io.Copy(io.Discard, resp.Body)

	if loc := resp.Header.Get("Location"); loc != "" {
		if v := versionFromTagURL(loc); v != "" {
			return v, nil
		}
		// Relative Location
		if u, err := url.Parse(loc); err == nil {
			if !u.IsAbs() {
				base, _ := url.Parse(latestURL)
				u = base.ResolveReference(u)
			}
			if v := versionFromTagURL(u.String()); v != "" {
				return v, nil
			}
		}
	}
	// Some clients/servers may return 200 on final URL if redirects were followed
	// on a different client; try Request.URL if available is not on resp for no-follow.

	// API fallback (install.sh).
	apiURL := fmt.Sprintf("%s/repos/%s/releases/latest", apiBase, repo)
	req2, err := http.NewRequestWithContext(ctx, http.MethodGet, apiURL, nil)
	if err != nil {
		return "", fmt.Errorf("%w: %v", ErrNetwork, err)
	}
	req2.Header.Set("Accept", "application/vnd.github+json")
	resp2, err := client.Do(req2)
	if err != nil {
		return "", fmt.Errorf("%w: resolve latest API: %v", ErrNetwork, err)
	}
	defer resp2.Body.Close()
	body, err := io.ReadAll(io.LimitReader(resp2.Body, 1<<20))
	if err != nil {
		return "", fmt.Errorf("%w: %v", ErrNetwork, err)
	}
	if resp2.StatusCode < 200 || resp2.StatusCode >= 300 {
		return "", fmt.Errorf("%w: API status %d", ErrResolve, resp2.StatusCode)
	}
	var payload struct {
		TagName string `json:"tag_name"`
	}
	if err := json.Unmarshal(body, &payload); err != nil {
		return "", fmt.Errorf("%w: parse API: %v", ErrResolve, err)
	}
	v := NormalizeVersion(payload.TagName)
	if v == "" {
		return "", fmt.Errorf("%w: empty tag_name", ErrResolve)
	}
	return v, nil
}

func versionFromTagURL(u string) string {
	// Match .../tag/v1.2.3 or .../tag/1.2.3
	const marker = "/tag/"
	i := strings.LastIndex(u, marker)
	if i < 0 {
		return ""
	}
	tag := u[i+len(marker):]
	if j := strings.IndexAny(tag, "/?#"); j >= 0 {
		tag = tag[:j]
	}
	return NormalizeVersion(tag)
}

func download(ctx context.Context, client *http.Client, rawURL string) ([]byte, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, rawURL, nil)
	if err != nil {
		return nil, fmt.Errorf("%w: %v", ErrNetwork, err)
	}
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("%w: %v", ErrNetwork, err)
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(io.LimitReader(resp.Body, 256<<20)) // 256 MiB cap
	if err != nil {
		return nil, fmt.Errorf("%w: read body: %v", ErrNetwork, err)
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, fmt.Errorf("%w: GET %s → %d", ErrHTTP, path.Base(rawURL), resp.StatusCode)
	}
	return body, nil
}

// findChecksum parses goreleaser checksums.txt lines: "<sha256>  <filename>".
func findChecksum(contents, asset string) (string, error) {
	if strings.TrimSpace(contents) == "" {
		return "", ErrChecksumMissing
	}
	base := path.Base(asset)
	for _, line := range strings.Split(contents, "\n") {
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		// Fields: hash then filename (one or more spaces).
		fields := strings.Fields(line)
		if len(fields) < 2 {
			continue
		}
		sum, name := fields[0], fields[len(fields)-1]
		name = path.Base(name)
		if name == base || name == asset {
			if len(sum) != 64 {
				continue
			}
			if _, err := hex.DecodeString(sum); err != nil {
				continue
			}
			return strings.ToLower(sum), nil
		}
	}
	return "", fmt.Errorf("%w: no entry for %s", ErrChecksumMissing, base)
}

func extractBinary(archive []byte) ([]byte, error) {
	gr, err := gzip.NewReader(bytes.NewReader(archive))
	if err != nil {
		return nil, fmt.Errorf("%w: gzip: %v", ErrExtract, err)
	}
	defer gr.Close()
	tr := tar.NewReader(gr)
	var found []byte
	for {
		hdr, err := tr.Next()
		if err == io.EOF {
			break
		}
		if err != nil {
			return nil, fmt.Errorf("%w: tar: %v", ErrExtract, err)
		}
		if hdr.Typeflag != tar.TypeReg && hdr.Typeflag != tar.TypeRegA {
			continue
		}
		name := path.Base(hdr.Name)
		if name != BinaryName {
			continue
		}
		data, err := io.ReadAll(io.LimitReader(tr, 256<<20))
		if err != nil {
			return nil, fmt.Errorf("%w: read binary: %v", ErrExtract, err)
		}
		found = data
		if len(found) > 0 {
			break
		}
	}
	if found == nil {
		return nil, ErrExtract
	}
	return found, nil
}

// atomicReplace writes binary to a temp file in the same directory, fsyncs, then renames.
// Never leaves a corrupt final binary: rename is atomic on the same filesystem.
func atomicReplace(target string, data []byte) error {
	dir := filepath.Dir(target)
	if dir == "" {
		dir = "."
	}
	tmp, err := os.CreateTemp(dir, ".capabilities-selfupdate-*")
	if err != nil {
		return fmt.Errorf("%w: create temp: %v", ErrUnwritable, err)
	}
	tmpName := tmp.Name()
	cleanup := true
	defer func() {
		if cleanup {
			_ = os.Remove(tmpName)
		}
	}()

	if _, err := tmp.Write(data); err != nil {
		_ = tmp.Close()
		return fmt.Errorf("%w: write temp: %v", ErrUnwritable, err)
	}
	// Best-effort mode bits; some filesystems ignore Chmod.
	_ = tmp.Chmod(0o755)
	// Sync before rename so a crash mid-update leaves the old binary intact.
	if err := tmp.Sync(); err != nil {
		_ = tmp.Close()
		return fmt.Errorf("selfupdate: fsync temp: %w", err)
	}
	if err := tmp.Close(); err != nil {
		return fmt.Errorf("selfupdate: close temp: %w", err)
	}

	// Atomic on same filesystem: never leave a half-written final path.
	if err := os.Rename(tmpName, target); err != nil {
		return fmt.Errorf("%w: rename into place: %v", ErrUnwritable, err)
	}
	cleanup = false

	// Best-effort dir fsync for durability (ignore errors).
	if d, err := os.Open(dir); err == nil {
		_ = d.Sync()
		_ = d.Close()
	}
	return nil
}
