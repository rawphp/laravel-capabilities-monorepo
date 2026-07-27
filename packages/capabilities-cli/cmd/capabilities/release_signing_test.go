package main

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

// TestReleaseSigningDocExists asserts in-package signing docs for secret names
// and maintainer setup (REQ-063; self-contained after split).
func TestReleaseSigningDocExists(t *testing.T) {
	root := moduleRoot(t)
	path := filepath.Join(root, "docs", "release-signing.md")
	if _, err := os.Stat(path); err != nil {
		t.Fatalf("docs/release-signing.md must exist: %v", err)
	}
}

// TestReleaseSigningDocDocumentsSecrets checks Apple (codesign/notary) and
// Windows Authenticode secret names are documented (REQ-063 AC1).
func TestReleaseSigningDocDocumentsSecrets(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, "docs", "release-signing.md"))
	if err != nil {
		t.Fatalf("read docs/release-signing.md: %v", err)
	}
	s := string(b)

	// Apple / notarization secret names (examples from AC; names must appear).
	appleNeedles := []string{
		"APPLE_CERTIFICATE_BASE64",
		"APPLE_CERTIFICATE_PASSWORD",
		"APPLE_TEAM_ID",
		// Notary: Apple ID path and/or API key path.
		"APPLE_ID",
		"APPLE_ID_PASSWORD",
		"APPLE_API_KEY_ID",
		"APPLE_API_ISSUER",
		"APPLE_API_KEY_P8",
	}
	for _, n := range appleNeedles {
		if !strings.Contains(s, n) {
			t.Errorf("docs/release-signing.md must document secret name %q", n)
		}
	}

	// Windows Authenticode.
	winNeedles := []string{
		"WINDOWS_CERT_BASE64",
		"WINDOWS_CERT_PASSWORD",
	}
	for _, n := range winNeedles {
		if !strings.Contains(s, n) {
			t.Errorf("docs/release-signing.md must document secret name %q", n)
		}
	}

	// Soft path when secrets missing must be documented.
	lower := strings.ToLower(s)
	if !strings.Contains(lower, "skip") && !strings.Contains(lower, "unsigned") {
		t.Error("docs/release-signing.md must describe soft path (skip/unsigned) when secrets missing")
	}
}

// TestReleaseSigningNoPrivateKeysCommitted ensures no embedded PEM private keys
// or certificate bodies in signing docs / release config (REQ-063).
func TestReleaseSigningNoPrivateKeysCommitted(t *testing.T) {
	root := moduleRoot(t)
	paths := []string{
		filepath.Join(root, "docs", "release-signing.md"),
		filepath.Join(root, ".goreleaser.yml"),
		filepath.Join(root, ".github", "workflows", "release.yml"),
	}
	// Also scan scripts if present.
	scriptsDir := filepath.Join(root, "scripts")
	if entries, err := os.ReadDir(scriptsDir); err == nil {
		for _, e := range entries {
			if !e.IsDir() {
				paths = append(paths, filepath.Join(scriptsDir, e.Name()))
			}
		}
	}

	// PEM private key / cert body patterns — must not appear in tracked scaffold.
	re := regexp.MustCompile(`-----BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY-----|-----BEGIN CERTIFICATE-----`)
	for _, p := range paths {
		b, err := os.ReadFile(p)
		if err != nil {
			// Missing files fail other tests; skip soft for optional scripts.
			if strings.Contains(p, "scripts") {
				continue
			}
			t.Fatalf("read %s: %v", p, err)
		}
		if re.Match(b) {
			t.Errorf("%s must not embed private keys or certificate PEM bodies", p)
		}
	}
}

// TestReleaseWorkflowSecretGatedSigning asserts workflow conditions / skip logs
// for signing when secrets absent (REQ-063 AC2–AC3).
func TestReleaseWorkflowSecretGatedSigning(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, ".github", "workflows", "release.yml"))
	if err != nil {
		t.Fatalf("read release.yml: %v", err)
	}
	s := string(b)
	lower := strings.ToLower(s)

	// Secret names referenced (for env wiring when present).
	for _, name := range []string{
		"APPLE_CERTIFICATE_BASE64",
		"WINDOWS_CERT_BASE64",
	} {
		if !strings.Contains(s, name) {
			t.Errorf("release.yml must reference signing secret %q (secret-gated)", name)
		}
	}

	// Conditional steps: if: secrets... != '' pattern (or equivalent emptiness check).
	hasIfSecrets := strings.Contains(s, "secrets.") &&
		(strings.Contains(s, "!=") || strings.Contains(s, "!= ''") || strings.Contains(s, "!= \"\""))
	hasSkipLog := strings.Contains(lower, "signing skipped") ||
		(strings.Contains(lower, "skip") && strings.Contains(lower, "sign"))
	if !hasIfSecrets && !hasSkipLog {
		t.Error("release.yml must secret-gate signing (if: secrets != '') and/or log signing skipped")
	}
	if !hasSkipLog {
		t.Error(`release.yml must log clearly when signing is skipped (e.g. "signing skipped")`)
	}

	// Soft path: release still runs goreleaser (unsigned) without requiring secrets.
	if !strings.Contains(lower, "goreleaser") {
		t.Error("release.yml must still run goreleaser for unsigned soft path")
	}
}

// TestGoreleaserSigningHooksSoftPath checks GoReleaser wires signing hooks/scripts
// that soft-skip without secrets; checksums remain (REQ-063 AC4, AC checksums).
func TestGoreleaserSigningHooksSoftPath(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, ".goreleaser.yml"))
	if err != nil {
		t.Fatalf("read .goreleaser.yml: %v", err)
	}
	s := string(b)

	// Must not hard-require signing secrets via mandatory .Env lookups that break CI.
	hardMarkers := []string{
		"{{ .Env.APPLE_CERTIFICATE_BASE64 }}",
		"{{.Env.APPLE_CERTIFICATE_BASE64}}",
		"{{ .Env.WINDOWS_CERT_BASE64 }}",
		"{{.Env.WINDOWS_CERT_BASE64}}",
		"{{ .Env.APPLE_CERTIFICATE_PASSWORD }}",
		"{{.Env.WINDOWS_CERT_PASSWORD}}",
	}
	for _, m := range hardMarkers {
		if strings.Contains(s, m) {
			t.Errorf(".goreleaser.yml must not hard-require %q (soft path when secrets missing)", m)
		}
	}

	// Signing integration: real hook wiring to soft-gated script (not comments only).
	// Require scripts/sign-binary (or sign-binary.sh) as an active hook command.
	hasHookOrScript := strings.Contains(s, "scripts/sign-binary") ||
		strings.Contains(s, "sign-binary.sh")
	if !hasHookOrScript {
		t.Error(".goreleaser.yml must wire secret-gated signing via scripts/sign-binary.sh post-build hooks")
	}

	// Checksums remain published regardless of signing.
	if !strings.Contains(s, "checksum") && !strings.Contains(s, "checksums") {
		t.Error(".goreleaser.yml must still produce checksums when signing is gated")
	}
}

// TestSignBinaryScriptSoftSkip asserts the sign helper no-ops without secrets
// and prints a clear skip message (unit-level behaviour for CI soft path).
func TestSignBinaryScriptSoftSkip(t *testing.T) {
	root := moduleRoot(t)
	script := filepath.Join(root, "scripts", "sign-binary.sh")
	if _, err := os.Stat(script); err != nil {
		t.Fatalf("scripts/sign-binary.sh must exist for soft-gated signing: %v", err)
	}
	b, err := os.ReadFile(script)
	if err != nil {
		t.Fatalf("read sign-binary.sh: %v", err)
	}
	s := string(b)
	lower := strings.ToLower(s)

	if !strings.Contains(lower, "signing skipped") {
		t.Error(`scripts/sign-binary.sh must print "signing skipped" when secrets absent`)
	}
	// Must check for Windows and Apple secret env names (soft gate).
	if !strings.Contains(s, "WINDOWS_CERT_BASE64") {
		t.Error("sign-binary.sh must gate Windows signing on WINDOWS_CERT_BASE64")
	}
	if !strings.Contains(s, "APPLE_CERTIFICATE_BASE64") {
		t.Error("sign-binary.sh must gate Apple signing on APPLE_CERTIFICATE_BASE64")
	}
	// Script must not embed real key material.
	if strings.Contains(s, "-----BEGIN") {
		t.Error("sign-binary.sh must not embed PEM material")
	}
}

// TestReadmeLinksReleaseSigning ensures README links to the signing doc (REQ-063).
func TestReadmeLinksReleaseSigning(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, "README.md"))
	if err != nil {
		t.Fatalf("read README.md: %v", err)
	}
	s := string(b)
	if !strings.Contains(s, "release-signing") && !strings.Contains(s, "docs/release-signing.md") {
		t.Error("README.md must link to docs/release-signing.md")
	}
}
