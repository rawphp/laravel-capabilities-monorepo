package auth

import (
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestRunwithoutauthfails(t *testing.T) {
	st := tempStore(t)
	if err := GuardAuth(st, "default", "run"); err != ErrNoToken {
		t.Fatal(err)
	}
}

func TestCatalogwithoutauthfails(t *testing.T) {
	st := tempStore(t)
	if err := GuardAuth(st, "default", "catalog"); err != ErrNoToken {
		t.Fatal(err)
	}
}

func TestDescribewithoutauthfails(t *testing.T) {
	st := tempStore(t)
	if err := GuardAuth(st, "default", "describe"); err != ErrNoToken {
		t.Fatal(err)
	}
}

func TestMcpwithoutauthfails(t *testing.T) {
	st := tempStore(t)
	if err := GuardAuth(st, "default", "mcp"); err != ErrNoToken {
		t.Fatal(err)
	}
}

func TestLogoutidempotentwhenalreadyloggedout(t *testing.T) {
	st := tempStore(t)
	if err := Logout(st, "default"); err != nil {
		t.Fatal(err)
	}
	if err := Logout(st, "default"); err != nil {
		t.Fatal(err)
	}
}

func TestStatusshowsloggedout(t *testing.T) {
	st := tempStore(t)
	p := st.Status("default")
	if p.LoggedIn {
		t.Fatal("expected logged out")
	}
}

func TestStatusshowsloggedin(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("default", "t")
	_ = st.SetBaseURL("default", "https://x.example")
	p := st.Status("default")
	if !p.LoggedIn {
		t.Fatal("expected logged in")
	}
}

func TestLoginfailsonnetworkerror(t *testing.T) {
	st := tempStore(t)
	c := api.NewClient("http://127.0.0.1:1", "")
	_, err := LoginDeviceCode(t.Context(), st, c, "default", "http://127.0.0.1:1")
	if err == nil {
		t.Fatal("expected network error")
	}
}

func TestLoginfailsoninvalidbaseurl(t *testing.T) {
	st := tempStore(t)
	_, err := LoginWithToken(st, "default", "not-a-url", "t")
	if err != ErrInvalidBaseURL {
		t.Fatal(err)
	}
}
