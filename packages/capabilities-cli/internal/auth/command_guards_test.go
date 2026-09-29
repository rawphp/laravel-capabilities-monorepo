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

func TestUnlistedCommandRequiresAuth(t *testing.T) {
	// Fail closed: any command not explicitly exempted needs a token,
	// including reserved-but-not-runnable tokens like mcp and future subcommands.
	for _, cmd := range []string{"mcp", "some-new-command", ""} {
		if !RequiresAuth(cmd) {
			t.Fatalf("%q must require auth", cmd)
		}
		st := tempStore(t)
		if ExitCodeForAuthError(GuardAuth(st, "default", cmd)) != api.ExitAuth {
			t.Fatalf("%q without token must exit %d", cmd, api.ExitAuth)
		}
	}
}

func TestExemptCommandsDoNotRequireAuth(t *testing.T) {
	for _, cmd := range []string{"help", "version", "self-update", "auth"} {
		if RequiresAuth(cmd) {
			t.Fatalf("%q must not require auth", cmd)
		}
		if err := GuardAuth(tempStore(t), "default", cmd); err != nil {
			t.Fatalf("%q: unexpected %v", cmd, err)
		}
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
