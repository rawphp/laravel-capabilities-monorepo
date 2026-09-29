package auth

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestLoginDeviceFailureDoesNotClobberExistingBaseURL(t *testing.T) {
	st := tempStore(t)
	if err := st.SetBaseURL("default", "https://good.example"); err != nil {
		t.Fatal(err)
	}
	if err := st.SetToken("default", "good-tok"); err != nil {
		t.Fatal(err)
	}
	c := api.NewClient("http://127.0.0.1:1", "")
	_, err := LoginDeviceCode(context.Background(), st, c, "default", "https://evil.example", DeviceFlow{})
	if err == nil {
		t.Fatal("expected network failure")
	}
	base, err := st.GetBaseURL("default")
	if err != nil {
		t.Fatal(err)
	}
	if base != "https://good.example" {
		t.Fatalf("base clobbered to %q", base)
	}
	tok, err := st.GetToken("default")
	if err != nil || tok != "good-tok" {
		t.Fatalf("token changed: %v %q", err, tok)
	}
}

func TestLoginDeviceFailureDoesNotWriteBaseURLOnFreshProfile(t *testing.T) {
	st := tempStore(t)
	c := api.NewClient("http://127.0.0.1:1", "")
	_, err := LoginDeviceCode(context.Background(), st, c, "default", "https://nowhere.example", DeviceFlow{})
	if err == nil {
		t.Fatal("expected failure")
	}
	if _, err := st.GetBaseURL("default"); err == nil {
		t.Fatal("base URL should not be written on failed login")
	}
}

func tokenServer(t *testing.T, status int, body string, gotAuth *string) *api.Client {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet || r.URL.Path != api.PathCapabilities {
			t.Fatalf("unexpected %s %s", r.Method, r.URL.Path)
		}
		if gotAuth != nil {
			*gotAuth = r.Header.Get("Authorization")
		}
		w.WriteHeader(status)
		w.Write([]byte(body))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient("http://placeholder.invalid", "")
	c.HTTP = srv.Client()
	c.BaseURL = srv.URL
	return c
}

func TestLoginTokenVerifiesAgainstAPIBeforeWriting(t *testing.T) {
	st := tempStore(t)
	var auth string
	c := tokenServer(t, 200, `{"ok":true,"data":{"capabilities":[]}}`, &auth)
	base := c.BaseURL + "/"
	res, err := LoginWithToken(context.Background(), st, c, "default", base, "pat-1")
	if err != nil {
		t.Fatal(err)
	}
	if auth != "Bearer pat-1" {
		t.Fatalf("verification did not send the new token: %q", auth)
	}
	if res.Flow != "token" || !res.TokenPresent {
		t.Fatalf("%#v", res)
	}
	if tok, _ := st.GetToken("default"); tok != "pat-1" {
		t.Fatal(tok)
	}
}

func TestLoginTokenRejectedDoesNotClobberProfile(t *testing.T) {
	cases := map[string]struct {
		status int
		body   string
		exit   int
	}{
		"401 envelope": {401, `{"ok":false,"error":{"code":"unauthenticated","message":"bad token"}}`, api.ExitAuth},
		"html 200":     {200, `<!doctype html><html>marketing</html>`, api.ExitInternal},
	}
	for name, tc := range cases {
		t.Run(name, func(t *testing.T) {
			st := tempStore(t)
			_ = st.SetBaseURL("default", "https://good.example")
			_ = st.SetToken("default", "good-tok")
			c := tokenServer(t, tc.status, tc.body, nil)
			_, err := LoginWithToken(context.Background(), st, c, "default", c.BaseURL, "typo")
			var se *api.StructuredError
			if !errors.As(err, &se) || se.ExitCode != tc.exit {
				t.Fatalf("err %v", err)
			}
			if base, _ := st.GetBaseURL("default"); base != "https://good.example" {
				t.Fatalf("base clobbered %q", base)
			}
			if tok, _ := st.GetToken("default"); tok != "good-tok" {
				t.Fatalf("token clobbered %q", tok)
			}
		})
	}
}

// failingTransport fails every request, standing in for an unreachable host.
func unreachableClient() *api.Client {
	c := api.NewClient("https://unreachable.invalid", "")
	c.HTTP = &http.Client{Transport: roundTripFunc(func(*http.Request) (*http.Response, error) {
		return nil, errors.New("dial: connection refused")
	})}
	return c
}

type roundTripFunc func(*http.Request) (*http.Response, error)

func (f roundTripFunc) RoundTrip(r *http.Request) (*http.Response, error) { return f(r) }

func oauthServer(t *testing.T, body string) *api.Client {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(body))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "")
	c.HTTP = srv.Client()
	return c
}

func TestLoginTransportFailuresWriteNothing(t *testing.T) {
	ctx := context.Background()
	st := tempStore(t)
	if _, err := LoginWithToken(ctx, st, unreachableClient(), "default", "https://x.example", "tok"); err == nil {
		t.Fatal("token login: expected transport error")
	}
	if _, err := LoginBrowserOAuth(ctx, st, unreachableClient(), "default", "https://x.example", "code"); err == nil {
		t.Fatal("browser login: expected transport error")
	}
	if _, err := st.GetBaseURL("default"); err == nil {
		t.Fatal("base URL written after transport failure")
	}
}

func TestLoginRejectsEmptyTokenAndBadBaseURL(t *testing.T) {
	ctx := context.Background()
	st := tempStore(t)
	if _, err := LoginWithToken(ctx, st, unreachableClient(), "default", "https://x.example", ""); err == nil {
		t.Fatal("empty token must be rejected")
	}
	if _, err := LoginDeviceCode(ctx, st, unreachableClient(), "default", "ftp://x", DeviceFlow{}); !errors.Is(err, ErrInvalidBaseURL) {
		t.Fatalf("device: want ErrInvalidBaseURL, got %v", err)
	}
	if _, err := LoginBrowserOAuth(ctx, st, unreachableClient(), "default", "", "code"); !errors.Is(err, ErrInvalidBaseURL) {
		t.Fatalf("browser: want ErrInvalidBaseURL, got %v", err)
	}
}

func TestBrowserLoginFailsClosedWithoutAccessToken(t *testing.T) {
	st := tempStore(t)
	c := oauthServer(t, `{"ok":true,"data":{}}`)
	if _, err := LoginBrowserOAuth(context.Background(), st, c, "default", c.BaseURL, "code"); err == nil {
		t.Fatal("expected missing access_token error")
	}
	c = oauthServer(t, `{"ok":false,"error":{"code":"unauthenticated","message":"bad code"}}`)
	if _, err := LoginBrowserOAuth(context.Background(), st, c, "default", c.BaseURL, "code"); err == nil {
		t.Fatal("expected error envelope to fail login")
	}
	if st.HasToken("default") {
		t.Fatal("token written on failed login")
	}
}

func TestBrowserLoginAcceptsTopLevelAccessToken(t *testing.T) {
	st := tempStore(t)
	c := oauthServer(t, `{"access_token":"top-level"}`)
	if _, err := LoginBrowserOAuth(context.Background(), st, c, "default", c.BaseURL, "code"); err != nil {
		t.Fatal(err)
	}
	if tok, _ := st.GetToken("default"); tok != "top-level" {
		t.Fatalf("token %q", tok)
	}
}

// A login that reaches the server but cannot persist must report the failure,
// not claim success.
func TestLoginReportsStoreWriteFailures(t *testing.T) {
	ctx := context.Background()
	okList := `{"ok":true,"data":[]}`
	grant := `{"ok":true,"data":{"access_token":"t"}}`
	noSleep := DeviceFlow{Sleep: func(context.Context, time.Duration) error { return nil }}

	logins := map[string]func(st *Store) error{
		"token": func(st *Store) error {
			c := tokenServer(t, http.StatusOK, okList, nil)
			_, err := LoginWithToken(ctx, st, c, "default", c.BaseURL, "tok")
			return err
		},
		"device": func(st *Store) error {
			c := (&deviceServer{start: deviceStart, polls: []string{grant}}).serve(t)
			_, err := LoginDeviceCode(ctx, st, c, "default", c.BaseURL, noSleep)
			return err
		},
		"browser": func(st *Store) error {
			c := oauthServer(t, grant)
			_, err := LoginBrowserOAuth(ctx, st, c, "default", c.BaseURL, "code")
			return err
		},
	}
	for name, login := range logins {
		t.Run(name+"/base url", func(t *testing.T) {
			if err := login(unwritableStore(t)); err == nil {
				t.Fatal("expected base URL write failure")
			}
		})
		t.Run(name+"/token", func(t *testing.T) {
			st := tempStore(t)
			blockTokenFile(t, st, "default")
			if err := login(st); err == nil {
				t.Fatal("expected token write failure")
			}
		})
	}
}

func TestDeviceLoginPollTransportErrorAborts(t *testing.T) {
	st := tempStore(t)
	d := &deviceServer{start: deviceStart, polls: []string{`{"ok":true,"data":{"status":"authorization_pending"}}`}}
	c := d.serve(t)
	started := false
	c.HTTP = &http.Client{Transport: roundTripFunc(func(r *http.Request) (*http.Response, error) {
		if r.URL.Path == api.PathAuthDevice && !started {
			started = true
			return http.DefaultTransport.RoundTrip(r)
		}
		return nil, errors.New("connection reset")
	})}
	_, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: func(context.Context, time.Duration) error { return nil }})
	if err == nil || st.HasToken("default") {
		t.Fatalf("expected poll transport error and no token, got %v", err)
	}
}

func TestDeviceLoginExpiresBeforeFirstPollWhenWindowIsShorterThanInterval(t *testing.T) {
	st := tempStore(t)
	d := &deviceServer{start: `{"ok":true,"data":{"device_code":"dc","user_code":"U","verification_uri":"https://e.test","expires_in":5}}`,
		polls: []string{`{"ok":true,"data":{"access_token":"t"}}`}}
	c := d.serve(t)
	_, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{}) // real sleeper never runs
	var se *api.StructuredError
	if !errors.As(err, &se) || se.Code != api.CodeUnauthenticated {
		t.Fatalf("want expired unauthenticated error, got %v", err)
	}
	if len(d.pollBodies) != 0 {
		t.Fatal("polled although the code expires before the first interval")
	}
}
