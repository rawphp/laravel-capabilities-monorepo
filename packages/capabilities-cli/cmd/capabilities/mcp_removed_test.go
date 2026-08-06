package main

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/synth"
)

// ORI-791: hard-remove CLI MCP stdio. bare `mcp` is unknown, not a bridge.

func TestMcpIsNotARunnableCommand(t *testing.T) {
	// With an empty synth index (no network), bare mcp must fail closed — non-zero.
	// Must not start a silent stdio bridge success (exit 0 with empty IO).
	idx := synth.Build(nil)
	code, out, errb := captureDispatch(t, []string{"mcp"}, idx, nil, nil)
	if code == api.ExitOK {
		t.Fatalf("bare mcp must not succeed: exit=0 out=%q err=%q", out, errb)
	}
	if code != api.ExitDomain {
		t.Fatalf("bare mcp exit=%d want %d (unknown command path) out=%q err=%q",
			code, api.ExitDomain, out, errb)
	}
	var env api.ErrorEnvelope
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatalf("stdout not not_found envelope: %v %q", err, out)
	}
	if env.OK || env.Error == nil || env.Error.Code != api.CodeNotFound {
		t.Fatalf("envelope=%+v", env)
	}
	// No functional MCP stdio help path.
	combined := out + errb
	if strings.Contains(combined, "MCP stdio") {
		t.Fatalf("must not advertise MCP stdio: %q", combined)
	}
}

func TestMcpHelpIsNotWorkingStdioBridge(t *testing.T) {
	// help mcp / mcp --help must not document a working stdio bridge.
	if strings.Contains(CommandHelp("mcp"), "MCP stdio") {
		t.Fatal("CommandHelp(mcp) must not describe MCP stdio bridge")
	}
	if strings.Contains(CommandHelp("mcp"), "tools/list") {
		t.Fatal("CommandHelp(mcp) must not document tools/list bridge")
	}
	if CommandExists("mcp") {
		t.Fatal("mcp must not be a registered runnable command")
	}
	// mcp --help with empty index → unknown, non-zero (not exit 0 help success).
	idx := synth.Build(nil)
	code, out, errb := captureDispatch(t, []string{"mcp", "--help"}, idx, nil, nil)
	if code == api.ExitOK {
		t.Fatalf("mcp --help must not be a success help path: out=%q err=%q", out, errb)
	}
	if strings.Contains(out+errb, "MCP stdio") {
		t.Fatalf("mcp --help must not advertise MCP stdio: %q", out+errb)
	}
}

func TestRootHelpDoesNotListWorkingMcpStdio(t *testing.T) {
	h := RootHelp()
	if strings.Contains(h, "MCP stdio") {
		t.Fatalf("root help must not list MCP stdio:\n%s", h)
	}
	// Do not present mcp as a reserved working command line.
	for _, line := range strings.Split(h, "\n") {
		trim := strings.TrimSpace(line)
		if strings.HasPrefix(trim, "mcp ") || trim == "mcp" {
			t.Fatalf("root help must not list mcp as a reserved command: %q", line)
		}
	}
	if strings.Contains(h, "capabilities mcp") {
		t.Fatalf("root help must not show capabilities mcp example:\n%s", h)
	}
}

func TestMcpUnauthenticatedAlsoNonZero(t *testing.T) {
	// Without injected index, bare mcp hits auth/catalog load or unknown — never exit 0.
	code, _, _ := CaptureExecute([]string{"mcp"}, t.TempDir(), nil)
	if code == 0 {
		t.Fatal("unauthenticated bare mcp must exit non-zero")
	}
}
