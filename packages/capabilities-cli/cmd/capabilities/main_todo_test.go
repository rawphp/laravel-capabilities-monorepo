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
