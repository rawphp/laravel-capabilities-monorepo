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

func TestMcprequiresauth(t *testing.T) {
	if !RequiresAuth("mcp") {
		t.Fatal()
	}
}

func TestMcpfailswithexit3whennotoken(t *testing.T) {
	st := tempStore(t)
	if ExitCodeForAuthError(GuardAuth(st, "default", "mcp")) != api.ExitAuth {
		t.Fatal()
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
