package run

import (
	"context"
	"net/http"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func healthHandler(apiVersion string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet && r.URL.Path == api.PathHealth {
			w.Write([]byte(`{"ok":true,"data":{"ok":true,"surfaces":{},"api_version":` + apiVersion + `}}`))
			return
		}
		w.Write([]byte(`{"ok":true,"data":{"invoice_id":1}}`))
	}
}

func TestRunRefusesServerWithOtherAPIVersionBeforePost(t *testing.T) {
	opts, rec := harness(t, healthHandler("2"))
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitInternal {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	if rec.N != 0 || res.HTTPCalled {
		t.Fatal("must not POST invoke when the server speaks another API version")
	}
	if !strings.Contains(res.Stderr, "capabilities self-update") {
		t.Fatal(res.Stderr)
	}
	if !strings.Contains(string(res.Envelope), `"code":"internal"`) {
		t.Fatal(string(res.Envelope))
	}
}

func TestRunProceedsWhenServerAPIVersionMatches(t *testing.T) {
	opts, rec := harness(t, healthHandler("1"))
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitOK || rec.N != 1 {
		t.Fatal(res.ExitCode, rec.N, res.Stderr)
	}
}
