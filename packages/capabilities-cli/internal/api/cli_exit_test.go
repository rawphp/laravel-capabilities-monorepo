package api

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

// Server envelopes carry error.cli_exit (core ErrorCodeMap). The CLI must use
// it instead of remapping codes it does not know to exit 1.
func TestParseErrorEnvelopeHonoursServerCLIExit(t *testing.T) {
	cases := []struct {
		body string
		want int
	}{
		{`{"ok":false,"error":{"code":"gone","message":"sunset","cli_exit":5}}`, ExitDomain},
		{`{"ok":false,"error":{"code":"expired","message":"x","cli_exit":5}}`, ExitDomain},
		{`{"ok":false,"error":{"code":"capability_not_in_profile","message":"x","cli_exit":3}}`, ExitAuth},
		{`{"ok":false,"error":{"code":"not_configured","message":"x","cli_exit":5}}`, ExitDomain},
		// server overrides the local table for a known code
		{`{"ok":false,"error":{"code":"conflict","message":"x","cli_exit":6}}`, ExitRateLimit},
		// out of range or absent → local table fallback
		{`{"ok":false,"error":{"code":"validation_failed","message":"x","cli_exit":0}}`, ExitValidation},
		{`{"ok":false,"error":{"code":"validation_failed","message":"x","cli_exit":42}}`, ExitValidation},
		{`{"ok":false,"error":{"code":"forbidden","message":"x"}}`, ExitAuth},
		{`{"ok":false,"error":{"code":"brand_new","message":"x"}}`, ExitInternal},
	}
	for _, tc := range cases {
		var env ErrorEnvelope
		if err := json.Unmarshal([]byte(tc.body), &env); err != nil {
			t.Fatal(err)
		}
		se := ParseErrorEnvelope(env, 400, []byte(tc.body))
		if se.ExitCode != tc.want {
			t.Fatalf("%s: exit %d want %d", tc.body, se.ExitCode, tc.want)
		}
	}
}

func TestNonEnvelope410IsDomainExit(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusGone)
		w.Write([]byte(`gone`))
	}))
	t.Cleanup(srv.Close)
	c := NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	res, err := c.ListCapabilities(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeGone || res.Err.ExitCode != ExitDomain {
		t.Fatalf("%#v", res.Err)
	}
	if HTTPStatus(CodeGone) != http.StatusGone {
		t.Fatal(HTTPStatus(CodeGone))
	}
}
