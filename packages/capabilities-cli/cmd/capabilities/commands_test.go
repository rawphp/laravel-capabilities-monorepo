package main

import (
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func TestCommandexistsAuthlogin(t *testing.T) {
	if !CommandExists("auth login") {
		t.Fatal()
	}
}
func TestCommandhelpAuthlogin(t *testing.T) {
	if !strings.Contains(CommandHelp("auth login"), "login") {
		t.Fatal()
	}
}
func TestCommandexistsAuthlogout(t *testing.T) {
	if !CommandExists("auth logout") {
		t.Fatal()
	}
}
func TestCommandhelpAuthlogout(t *testing.T) {
	if !strings.Contains(CommandHelp("auth logout"), "logout") {
		t.Fatal()
	}
}
func TestCommandexistsAuthstatus(t *testing.T) {
	if !CommandExists("auth status") {
		t.Fatal()
	}
}
func TestCommandhelpAuthstatus(t *testing.T) {
	if !strings.Contains(CommandHelp("auth status"), "status") {
		t.Fatal()
	}
}
func TestCommandexistsCatalog(t *testing.T) {
	if !CommandExists("catalog") {
		t.Fatal()
	}
}
func TestCommandhelpCatalog(t *testing.T) {
	if !strings.Contains(CommandHelp("catalog"), "catalog") {
		t.Fatal()
	}
}
func TestCommandexistsDescribe(t *testing.T) {
	if !CommandExists("describe") {
		t.Fatal()
	}
}
func TestCommandhelpDescribe(t *testing.T) {
	if !strings.Contains(CommandHelp("describe"), "describe") {
		t.Fatal()
	}
}
func TestCommandexistsRun(t *testing.T) {
	if !CommandExists("run") {
		t.Fatal()
	}
}
func TestCommandhelpRun(t *testing.T) {
	if !strings.Contains(CommandHelp("run"), "run") {
		t.Fatal()
	}
}
func TestCommandexistsMcpFalse(t *testing.T) {
	// mcp is reserved as a domain token forever, but not a runnable command (ORI-791).
	if CommandExists("mcp") {
		t.Fatal("mcp must not be a registered command")
	}
}
func TestCommandhelpMcpNotStdioBridge(t *testing.T) {
	h := CommandHelp("mcp")
	if strings.Contains(h, "MCP stdio") || strings.Contains(h, "tools/list") {
		t.Fatal(h)
	}
}
func TestCommandexistsVersion(t *testing.T) {
	if !CommandExists("version") {
		t.Fatal()
	}
}
func TestCommandhelpVersion(t *testing.T) {
	if !strings.Contains(CommandHelp("version"), "version") {
		t.Fatal()
	}
}
func TestCommandexistsSelfupdate(t *testing.T) {
	if !CommandExists("self-update") {
		t.Fatal()
	}
}
func TestCommandhelpSelfupdate(t *testing.T) {
	if !strings.Contains(CommandHelp("self-update"), "self-update") {
		t.Fatal()
	}
}
func TestCommandexistsHelp(t *testing.T) {
	if !CommandExists("help") {
		t.Fatal()
	}
}
func TestCommandhelpHelp(t *testing.T) {
	if !strings.Contains(CommandHelp("help"), "capabilities") {
		t.Fatal()
	}
}

func TestAuthUnknownSubcommand(t *testing.T) {
	code, _, errb := CaptureExecute([]string{"auth", "wat"}, t.TempDir(), nil)
	if code == 0 || !strings.Contains(errb, "unknown") {
		t.Fatal(code, errb)
	}
}

func TestAuthLoginInvalidBase(t *testing.T) {
	code, _, errb := CaptureExecute([]string{"auth", "login", "--base-url=ftp://bad"}, t.TempDir(), nil)
	if code == 0 || !strings.Contains(errb, "invalid base URL") {
		t.Fatal(code, errb)
	}
}

func TestApprovalsUnknownAction(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", url, "tok")
	code, _, _ := CaptureExecute([]string{"approvals", "shrug", "1"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestApprovalsMissingArgs(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", url, "tok")
	code, out, _ := CaptureExecute([]string{"approvals"}, root, newClientFactory(srv))
	// Bare approvals prints usage and exits 0 (help), not validation_failed.
	if code != api.ExitOK {
		t.Fatal(code)
	}
	if !strings.Contains(out, "USAGE:") {
		t.Fatal(out)
	}
}

func TestBareAuthPrintsAuthHelp(t *testing.T) {
	code, out, _ := CaptureExecute([]string{"auth"}, t.TempDir(), nil)
	if code != api.ExitOK || out != CommandHelp("auth") {
		t.Fatalf("exit %d out %q", code, out)
	}
}

func TestAuthListWithoutProfilesPointsAtLogin(t *testing.T) {
	code, out, _ := CaptureExecute([]string{"auth", "list"}, t.TempDir(), nil)
	if code != api.ExitOK || !strings.Contains(out, "No auth profiles yet") || !strings.Contains(out, "auth login") {
		t.Fatalf("exit %d out %q", code, out)
	}
}
