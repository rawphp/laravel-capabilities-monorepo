package auth

import (
	"context"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestSanitizeProfileAndLastRunPath(t *testing.T) {
	st := tempStore(t)
	if sanitizeProfile("") != "default" {
		t.Fatal()
	}
	if sanitizeProfile("a/b c!") != "a_b_c_" && sanitizeProfile("a/b c!") == "" {
		t.Fatal(sanitizeProfile("a/b c!"))
	}
	p := st.LastRunPath("default")
	if p == "" {
		t.Fatal()
	}
	// corrupt config
	dir := st.profileDir("bad")
	_ = os.MkdirAll(dir, 0o700)
	_ = os.WriteFile(filepath.Join(dir, "config.json"), []byte("{"), 0o600)
	if _, err := st.GetBaseURL("bad"); err == nil {
		t.Fatal("expected corrupt config error")
	}
}

func TestLoginFailures(t *testing.T) {
	st := tempStore(t)
	if _, err := LoginWithToken(st, "default", "https://x", ""); err == nil {
		t.Fatal("empty token")
	}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(401)
		w.Write([]byte(`{"ok":false,"error":{"code":"unauthenticated","message":"no"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "")
	c.HTTP = srv.Client()
	if _, err := LoginDeviceCode(context.Background(), st, c, "default", srv.URL); err == nil {
		t.Fatal("expected device fail")
	}
	if _, err := LoginBrowserOAuth(context.Background(), st, c, "default", srv.URL, "c"); err == nil {
		t.Fatal("expected oauth fail")
	}
	// missing token in success envelope
	srv2 := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{}}`))
	}))
	t.Cleanup(srv2.Close)
	c2 := api.NewClient(srv2.URL, "")
	c2.HTTP = srv2.Client()
	if _, err := LoginDeviceCode(context.Background(), st, c2, "default", srv2.URL); err == nil {
		t.Fatal("missing token")
	}
	// extractToken top-level
	if extractToken(map[string]any{"access_token": "z"}) != "z" {
		t.Fatal()
	}
}

func TestExitCodeForAuthErrorNil(t *testing.T) {
	if ExitCodeForAuthError(nil) != api.ExitOK {
		t.Fatal()
	}
	if ExitCodeForAuthError(os.ErrNotExist) != api.ExitInternal {
		t.Fatal()
	}
	if RequiresAuth("version") {
		t.Fatal()
	}
	if GuardAuth(tempStore(t), "default", "version") != nil {
		t.Fatal()
	}
}
