package run

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestRunRefusesMultiSegmentCapabilityBeforeHTTP(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		t.Errorf("unexpected request %s %s", r.Method, r.URL.Path)
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "tok")
	res := Run(context.Background(), Options{Capability: "approvals/x/accept", InputJSON: []byte(`{}`), Client: c, LastRunPath: t.TempDir() + "/last.json"})
	if res.ExitCode != ExitValidation || res.HTTPCalled {
		t.Fatalf("%+v", res)
	}
	var env api.ErrorEnvelope
	if err := json.Unmarshal(res.Envelope, &env); err != nil || env.Error == nil || env.Error.Code != api.CodeValidationFailed {
		t.Fatalf("%s", res.Envelope)
	}
}
