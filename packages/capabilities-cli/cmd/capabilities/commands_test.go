package main

import (
	"strings"
	"testing"
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
