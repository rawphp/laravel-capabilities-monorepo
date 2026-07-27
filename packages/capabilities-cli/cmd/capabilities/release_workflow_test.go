package main

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestReleaseWorkflowExists asserts package-owned GitHub Actions release
// workflow lives under .github/workflows/ so it appears at repo root after split
// (REQ-062).
func TestReleaseWorkflowExists(t *testing.T) {
	root := moduleRoot(t)
	path := filepath.Join(root, ".github", "workflows", "release.yml")
	if _, err := os.Stat(path); err != nil {
		t.Fatalf(".github/workflows/release.yml must exist at package root: %v", err)
	}
}

// TestReleaseWorkflowOnTagRunsGoReleaser checks tag trigger (v*), GoReleaser
// release step, contents: write, and replace/update behaviour for retags
// (REQ-062 acceptance).
func TestReleaseWorkflowOnTagRunsGoReleaser(t *testing.T) {
	root := moduleRoot(t)
	path := filepath.Join(root, ".github", "workflows", "release.yml")
	b, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("read release.yml: %v", err)
	}
	s := string(b)
	lower := strings.ToLower(s)

	// Trigger on push of tags v* (and optionally workflow_dispatch).
	if !strings.Contains(s, "tags:") && !strings.Contains(s, "tags :") {
		t.Error("release.yml must trigger on tags (on.push.tags)")
	}
	if !strings.Contains(s, "v*") && !strings.Contains(s, "'v*'") && !strings.Contains(s, `"v*"`) {
		t.Error(`release.yml must filter tags with "v*"`)
	}

	// Runs GoReleaser release (package-owned binary publish, not PHP split).
	if !strings.Contains(lower, "goreleaser") {
		t.Error("release.yml must invoke goreleaser")
	}
	// Prefer release (not only check/snapshot) for tag publishes.
	if !strings.Contains(lower, "release") {
		t.Error("release.yml must run goreleaser release (or document release mode)")
	}

	// Minimal permissions: contents: write for GitHub Releases API via GITHUB_TOKEN.
	if !strings.Contains(lower, "contents:") || !strings.Contains(lower, "write") {
		t.Error("release.yml must set permissions contents: write")
	}
	// Child workflow must not consume monorepo SPLIT_GITHUB_TOKEN (comments OK).
	if strings.Contains(s, "secrets.SPLIT_GITHUB_TOKEN") {
		t.Error("release.yml must not require monorepo secret secrets.SPLIT_GITHUB_TOKEN")
	}

	// Replace/update existing release for same tag (retag after force-push split).
	// Config may live in workflow args or rely on .goreleaser.yml mode: replace —
	// workflow comments or goreleaser config must make replace intent clear.
	grPath := filepath.Join(root, ".goreleaser.yml")
	gr, gerr := os.ReadFile(grPath)
	if gerr != nil {
		t.Fatalf("read .goreleaser.yml: %v", gerr)
	}
	grs := string(gr)
	hasReplace := strings.Contains(lower, "replace") ||
		strings.Contains(grs, "mode: replace") ||
		strings.Contains(grs, "replace_existing_artifacts")
	if !hasReplace {
		t.Error("release path must update/replace existing release for same tag (workflow or .goreleaser.yml mode: replace)")
	}
}

// TestReadmeDocumentsAutomatedReleases ensures CLI package README mentions
// automated GitHub Releases on v* tags after monorepo split (REQ-062).
func TestReadmeDocumentsAutomatedReleases(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, "README.md"))
	if err != nil {
		t.Fatalf("read README.md: %v", err)
	}
	s := strings.ToLower(string(b))
	hasRelease := strings.Contains(s, "github release") || strings.Contains(s, "github releases") ||
		(strings.Contains(s, "release") && strings.Contains(s, "goreleaser"))
	hasTag := strings.Contains(s, "v*") || strings.Contains(s, "tag")
	hasSplit := strings.Contains(s, "split") || strings.Contains(s, "mirror")
	if !hasRelease || !hasTag {
		t.Error("README.md must document automated GitHub Releases on v* tags")
	}
	if !hasSplit {
		t.Error("README.md must mention monorepo split/mirror before package release runs")
	}
}
