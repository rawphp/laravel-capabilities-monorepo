package main

import (
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/run"
)

func TestBinarynameiscapabilities(t *testing.T) {
	if BinaryName != "capabilities" {
		t.Fatal(BinaryName)
	}
}

func TestHelplistsauthcatalogrunmcpapprovals(t *testing.T) {
	h := RootHelp()
	for _, c := range []string{"auth", "catalog", "run", "mcp", "approvals"} {
		if !strings.Contains(h, c) {
			t.Fatalf("missing %s", c)
		}
	}
}

func TestBinaryisnotartisan(t *testing.T) {
	h := RootHelp()
	if strings.Contains(h, "php artisan") && !strings.Contains(h, "not artisan") {
		t.Fatal("must not present as artisan")
	}
	if !strings.Contains(h, "not artisan") {
		t.Fatal(h)
	}
}

func TestHelpdocumentsexitcodes(t *testing.T) {
	h := RootHelp()
	if !strings.Contains(h, "Exit codes") {
		t.Fatal(h)
	}
}

func TestHelpdocumentsjsonflag(t *testing.T) {
	if !strings.Contains(RootHelp(), "--json") {
		t.Fatal()
	}
}

func TestRootcommandrequiressubcommand(t *testing.T) {
	code, _, _ := CaptureExecute(nil, t.TempDir(), nil)
	if code == 0 {
		t.Fatal("root without subcommand should fail")
	}
}

func TestVersioncommandexists(t *testing.T) {
	code, out, _ := CaptureExecute([]string{"version"}, t.TempDir(), nil)
	if code != 0 || !strings.Contains(out, BinaryName) {
		t.Fatal(code, out)
	}
	_ = run.DocsExitCodes
}

// TestDefaultversionnonempty asserts the package-level Version default used when
// release builds do not inject -ldflags -X main.Version=...
func TestDefaultversionnonempty(t *testing.T) {
	if strings.TrimSpace(Version) == "" {
		t.Fatal("default Version must be non-empty for dev builds without ldflags")
	}
}

// TestVersioncommandoutputcontainsversion asserts `capabilities version` prints
// BinaryName and the Version var (overridable via -X main.Version at link time).
func TestVersioncommandoutputcontainsversion(t *testing.T) {
	code, out, _ := CaptureExecute([]string{"version"}, t.TempDir(), nil)
	if code != 0 {
		t.Fatalf("version exit code: %d", code)
	}
	if !strings.Contains(out, BinaryName) {
		t.Fatalf("version output missing BinaryName %q: %q", BinaryName, out)
	}
	if !strings.Contains(out, Version) {
		t.Fatalf("version output missing Version %q: %q", Version, out)
	}
	want := BinaryName + " " + Version
	if !strings.Contains(out, want) {
		t.Fatalf("expected %q in version output, got %q", want, out)
	}
}
