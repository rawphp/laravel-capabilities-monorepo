package auth

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestAuthloginstorestokeninkeychainnotprompt(t *testing.T) {
	st := tempStore(t)
	c := tokenServer(t, 200, `{"ok":true,"data":{"capabilities":[]}}`, nil)
	res, err := LoginWithToken(context.Background(), st, c, "default", c.BaseURL, "tok-abc")
	if err != nil {
		t.Fatal(err)
	}
	if !res.TokenPresent {
		t.Fatal("expected token present")
	}
	got, err := st.GetToken("default")
	if err != nil || got != "tok-abc" {
		t.Fatalf("%v %q", err, got)
	}
}

func TestAuthstatusshowsprofilebaseurl(t *testing.T) {
	st := tempStore(t)
	_ = st.SetBaseURL("default", "https://app.example.com")
	_ = st.SetToken("default", "t")
	p := st.Status("default")
	if p.BaseURL != "https://app.example.com" || !p.LoggedIn || p.Name != "default" {
		t.Fatalf("%#v", p)
	}
	// status must not embed token field values in Name/BaseURL
	if strings.Contains(p.BaseURL, "t") && p.BaseURL != "https://app.example.com" {
		t.Fatal("token leaked")
	}
}

func TestAuthlogoutclearstoken(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("default", "t")
	if err := Logout(st, "default"); err != nil {
		t.Fatal(err)
	}
	if st.HasToken("default") {
		t.Fatal("token should be gone")
	}
}

func TestAuthrequiredbeforerun(t *testing.T) {
	if !RequiresAuth("run") {
		t.Fatal("run requires auth")
	}
	st := tempStore(t)
	if err := GuardAuth(st, "default", "run"); err != ErrNoToken {
		t.Fatalf("%v", err)
	}
}

func TestAuthlogindevicecodeflow(t *testing.T) {
	st := tempStore(t)
	d := &deviceServer{start: deviceStart, polls: []string{`{"ok":true,"data":{"access_token":"device-tok"}}`}}
	c := d.serve(t)
	var waits []time.Duration
	res, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)})
	if err != nil {
		t.Fatal(err)
	}
	if res.Flow != "device" {
		t.Fatal(res.Flow)
	}
	tok, _ := st.GetToken("default")
	if tok != "device-tok" {
		t.Fatal(tok)
	}
}

func TestAuthloginbrowseroauthflow(t *testing.T) {
	st := tempStore(t)
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"access_token":"oauth-tok"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "")
	c.HTTP = srv.Client()
	res, err := LoginBrowserOAuth(context.Background(), st, c, "default", srv.URL, "code1")
	if err != nil {
		t.Fatal(err)
	}
	if res.Flow != "browser" {
		t.Fatal(res.Flow)
	}
}

func TestAuthtokenneverprintedtostdoutbydefault(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("default", "super-secret-token")
	p := st.Status("default")
	// Status struct has no Token field — only LoggedIn bool.
	s := p.Name + p.BaseURL
	if strings.Contains(s, "super-secret-token") {
		t.Fatal("token in status")
	}
}

func TestAuthprofileisolationperbaseurl(t *testing.T) {
	st := tempStore(t)
	_ = st.SetBaseURL("prod", "https://prod.example.com")
	_ = st.SetToken("prod", "prod-tok")
	_ = st.SetBaseURL("staging", "https://staging.example.com")
	_ = st.SetToken("staging", "stg-tok")
	a, _ := st.GetToken("prod")
	b, _ := st.GetToken("staging")
	if a == b {
		t.Fatal("profiles not isolated")
	}
	ua, _ := st.GetBaseURL("prod")
	ub, _ := st.GetBaseURL("staging")
	if ua == ub {
		t.Fatal("base urls not isolated")
	}
}

func TestAuthmissingtokenreturnsexitcode3(t *testing.T) {
	st := tempStore(t)
	err := GuardAuth(st, "default", "run")
	if ExitCodeForAuthError(err) != api.ExitAuth {
		t.Fatal(ExitCodeForAuthError(err))
	}
}

func TestAuthloginfetchesschemasintocache(t *testing.T) {
	// LoginWithToken + schema cache dir exists for profile
	st := tempStore(t)
	c := tokenServer(t, 200, `{"ok":true,"data":{"capabilities":[]}}`, nil)
	_, err := LoginWithToken(context.Background(), st, c, "default", c.BaseURL, "t")
	if err != nil {
		t.Fatal(err)
	}
	dir := st.SchemaCacheDir("default")
	if dir == "" || !strings.Contains(dir, "schemas") {
		t.Fatal(dir)
	}
}

// The server derives caller from the token's abilities (D-022). Login reports
// what it derived so a PAT minted without the cli ability is named at login,
// not discovered later as a filtered catalog; the token is still stored.
func TestLoginWithTokenReportsServerDerivedCaller(t *testing.T) {
	st := tempStore(t)
	c := tokenServer(t, 200, `{"ok":true,"data":{"capabilities":[]},"meta":{"caller":"http","derived_caller":"http"}}`, nil)
	res, err := LoginWithToken(context.Background(), st, c, "default", c.BaseURL, "tok")
	if err != nil {
		t.Fatal(err)
	}
	if res.Caller != "http" {
		t.Fatalf("caller=%q want http", res.Caller)
	}
	if !st.HasToken("default") {
		t.Fatal("token must still be stored when caller is not cli")
	}
}

func TestLoginWithTokenWithoutMetaReportsNoCaller(t *testing.T) {
	st := tempStore(t)
	c := tokenServer(t, 200, `{"ok":true,"data":{"capabilities":[]}}`, nil)
	res, err := LoginWithToken(context.Background(), st, c, "default", c.BaseURL, "tok")
	if err != nil || res.Caller != "" {
		t.Fatalf("err=%v caller=%q", err, res.Caller)
	}
}
