package main

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestReleasePathDocClosesTagToReleasePath is the path-unit (REQ-059) closure check:
// entry monorepo v* tag → split → package-owned GoReleaser → GitHub Release on
// rawphp/capabilities-cli with version from tag. Implementation is owned by
// REQ-060..064; this package-owned doc proves the composed path is documented
// and self-contained after split (no monorepo-only relative links required).
func TestReleasePathDocClosesTagToReleasePath(t *testing.T) {
	root := moduleRoot(t)
	path := filepath.Join(root, "docs", "release-path.md")
	b, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("docs/release-path.md must exist for path-unit closure: %v", err)
	}
	s := string(b)
	needles := []string{
		"v*",
		"split",
		".goreleaser.yml",
		".github/workflows/release.yml",
		"GitHub Release",
		"rawphp/capabilities-cli",
		"capabilities version",
		"replace",
		"secret-gated",
	}
	for _, n := range needles {
		if !strings.Contains(s, n) {
			t.Errorf("docs/release-path.md missing path marker %q", n)
		}
	}
	// Self-contained after split: no relative monorepo-only docs links.
	if strings.Contains(s, "](../../docs/") {
		t.Error("docs/release-path.md must not link into monorepo-only ../../docs/")
	}
}

// TestReleaseAutomationFilesOnDisk ensures path-unit terminal prerequisites:
// GoReleaser config + tag-triggered workflow live under the package tree so they
// mirror into rawphp/capabilities-cli (not PHP package remotes).
func TestReleaseAutomationFilesOnDisk(t *testing.T) {
	root := moduleRoot(t)
	for _, rel := range []string{
		".goreleaser.yml",
		filepath.Join(".github", "workflows", "release.yml"),
	} {
		p := filepath.Join(root, rel)
		if _, err := os.Stat(p); err != nil {
			t.Errorf("path-unit terminal prerequisite missing: %s (%v)", rel, err)
		}
	}
}
