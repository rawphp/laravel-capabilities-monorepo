package auth

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"

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
