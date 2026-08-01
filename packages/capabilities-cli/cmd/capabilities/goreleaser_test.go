package main

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestGoreleaserConfigExists asserts package-root .goreleaser.yml is present
// (release automation for multi-arch capabilities binaries).
func TestGoreleaserConfigExists(t *testing.T) {
	root := moduleRoot(t)
	path := filepath.Join(root, ".goreleaser.yml")
	if _, err := os.Stat(path); err != nil {
		t.Fatalf(".goreleaser.yml must exist at package root: %v", err)
	}
}

// TestGoreleaserConfigBuildsCapabilitiesMatrix checks binary name, main path,
// GOOS×GOARCH matrix, version ldflags (-X main.Version), and checksums —
// matching REQ-060 / docs/build-matrix.md and acceptance for REQ-061.
func TestGoreleaserConfigBuildsCapabilitiesMatrix(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, ".goreleaser.yml"))
	if err != nil {
		t.Fatalf("read .goreleaser.yml: %v", err)
	}
	s := string(b)

	// Binary + main package (cmd/capabilities → binary name capabilities).
	for _, needle := range []string{
		"capabilities",
		"./cmd/capabilities",
		"main.Version",
	} {
		if !strings.Contains(s, needle) {
			t.Errorf(".goreleaser.yml missing %q", needle)
		}
	}

	// Multi-arch matrix from docs/build-matrix.md.
	for _, osName := range []string{"darwin", "linux", "windows"} {
		if !strings.Contains(s, osName) {
			t.Errorf(".goreleaser.yml missing goos %q", osName)
		}
	}
	for _, arch := range []string{"amd64", "arm64"} {
		if !strings.Contains(s, arch) {
			t.Errorf(".goreleaser.yml missing goarch %q", arch)
		}
	}

	// Version injection: -X main.Version ({{.Version}} strips leading v).
	if !strings.Contains(s, "-X main.Version") && !strings.Contains(s, "-X main.Version=") {
		// Accept either "-X main.Version={{.Version}}" style.
		if !strings.Contains(s, "main.Version={{.Version}}") && !strings.Contains(s, "main.Version={{ .Version }}") {
			t.Error(".goreleaser.yml must set ldflags -X main.Version from {{.Version}}")
		}
	}
	if !strings.Contains(s, "{{.Version}}") && !strings.Contains(s, "{{ .Version }}") {
		t.Error(".goreleaser.yml must use {{.Version}} for tag-without-v injection")
	}

	// Checksums produced for GitHub Release assets.
	if !strings.Contains(s, "checksum") && !strings.Contains(s, "checksums") {
		t.Error(".goreleaser.yml must configure checksums")
	}

	// No hard dependency on signing secrets in base config (REQ-063 gates signing).
	// Reject env lookups that would hard-fail when secrets are missing in common patterns.
	hardSecretMarkers := []string{
		"{{ .Env.COSIGN_PASSWORD }}",
		"{{.Env.COSIGN_PASSWORD}}",
		"{{ .Env.MACOS_SIGN_P12 }}",
		"{{.Env.MACOS_SIGN_P12}}",
		"{{ .Env.WINDOWS_CERT }}",
		"{{.Env.WINDOWS_CERT}}",
	}
	for _, m := range hardSecretMarkers {
		if strings.Contains(s, m) {
			t.Errorf(".goreleaser.yml must not hard-require signing secret %q (REQ-063)", m)
		}
	}
}

// TestGoreleaserVersionPinDocumented ensures CI/schema version pin is noted
// (goreleaser v2-style) in the config comments or dist notes.
func TestGoreleaserVersionPinDocumented(t *testing.T) {
	root := moduleRoot(t)
	gr, err := os.ReadFile(filepath.Join(root, ".goreleaser.yml"))
	if err != nil {
		t.Fatalf("read .goreleaser.yml: %v", err)
	}
	s := string(gr)
	// v2 schema / version pin comment, or version: 2 top-level.
	hasPin := strings.Contains(s, "version: 2") ||
		strings.Contains(s, "version:2") ||
		strings.Contains(strings.ToLower(s), "goreleaser v2") ||
		strings.Contains(s, "schema-pro.json") ||
		strings.Contains(s, "goreleaser.com/static/schema")
	if !hasPin {
		// Fall back: docs/build-matrix.md may document the pin.
		dist, derr := os.ReadFile(filepath.Join(root, "dist", "README.md"))
		if derr != nil {
			t.Fatalf("neither config nor dist documents goreleaser v2 pin: %v", derr)
		}
		ds := strings.ToLower(string(dist))
		if !strings.Contains(ds, "goreleaser") || (!strings.Contains(ds, "v2") && !strings.Contains(ds, "version 2")) {
			t.Error("document goreleaser v2 schema pin in .goreleaser.yml comments or docs/build-matrix.md")
		}
	}
}
