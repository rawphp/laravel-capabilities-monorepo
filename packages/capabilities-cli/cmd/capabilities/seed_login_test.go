package main

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

// seedProfile stores credentials directly (test setup; the login command
// verifies tokens against the API, which setup does not need).
func seedProfile(st *auth.Store, profile, baseURL, token string) error {
	if err := st.SetBaseURL(profile, baseURL); err != nil {
		return err
	}
	return st.SetToken(profile, token)
}

func seedLogin(t *testing.T, st *auth.Store, profile, baseURL, token string) {
	t.Helper()
	if err := seedProfile(st, profile, baseURL, token); err != nil {
		t.Fatal(err)
	}
}

func TestAuthLoginTokenRejectedExits3AndKeepsProfile(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
		w.Write([]byte(`{"ok":false,"error":{"code":"unauthenticated","message":"bad token","cli_exit":3}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", "https://good.example", "good-tok")

	code, out, _ := CaptureExecute([]string{"auth", "login", "--base-url=" + srv.URL, "--token=typo"}, root, newClientFactory(srv))
	if code != api.ExitAuth {
		t.Fatalf("exit %d", code)
	}
	if strings.Contains(out, "logged in") {
		t.Fatalf("claimed success: %q", out)
	}
	if tok, _ := st.GetToken("default"); tok != "good-tok" {
		t.Fatalf("token clobbered %q", tok)
	}
	if base, _ := st.GetBaseURL("default"); base != "https://good.example" {
		t.Fatalf("base clobbered %q", base)
	}
}
