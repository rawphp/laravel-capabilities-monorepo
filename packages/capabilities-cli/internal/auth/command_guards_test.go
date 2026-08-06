package auth

import (
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestRunrequiresauth(t *testing.T) {
	if !RequiresAuth("run") {
		t.Fatal()
	}
}

func TestRunfailswithexit3whennotoken(t *testing.T) {
	st := tempStore(t)
	err := GuardAuth(st, "default", "run")
	if ExitCodeForAuthError(err) != api.ExitAuth {
		t.Fatal()
	}
}

func TestCatalogrequiresauth(t *testing.T) {
	if !RequiresAuth("catalog") {
		t.Fatal()
	}
}

func TestCatalogfailswithexit3whennotoken(t *testing.T) {
	st := tempStore(t)
	if ExitCodeForAuthError(GuardAuth(st, "default", "catalog")) != api.ExitAuth {
		t.Fatal()
	}
}

func TestDescriberequiresauth(t *testing.T) {
	if !RequiresAuth("describe") {
		t.Fatal()
	}
}

func TestDescribefailswithexit3whennotoken(t *testing.T) {
	st := tempStore(t)
	if ExitCodeForAuthError(GuardAuth(st, "default", "describe")) != api.ExitAuth {
		t.Fatal()
	}
}

func TestMcpDoesNotRequireAuthAsCommand(t *testing.T) {
	// mcp is not a runnable command; GuardAuth should not treat it as auth-gated meta.
	if RequiresAuth("mcp") {
		t.Fatal("mcp must not be in CommandsRequiringAuth")
	}
	st := tempStore(t)
	if err := GuardAuth(st, "default", "mcp"); err != nil {
		t.Fatalf("unexpected: %v", err)
	}
}

func TestApprovalsrequiresauth(t *testing.T) {
	if !RequiresAuth("approvals") {
		t.Fatal()
	}
}

func TestApprovalsfailswithexit3whennotoken(t *testing.T) {
	st := tempStore(t)
	if ExitCodeForAuthError(GuardAuth(st, "default", "approvals")) != api.ExitAuth {
		t.Fatal()
	}
}
