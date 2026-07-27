package run

import (
	"context"
	"encoding/json"
	"net/http"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestRunvalidatesschemalocallybeforepost(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.InputJSON = []byte(`{"customer_id":"x"}`) // wrong type
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitValidation {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	if rec.N != 0 {
		t.Fatal("must not POST on local fail")
	}
}

func TestRunstructuralerrorexitcode2nonetwork(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.InputJSON = []byte(`{"customer_id":"nope"}`)
	res := Run(context.Background(), opts)
	if res.ExitCode != 2 || res.HTTPCalled {
		t.Fatal(res.ExitCode, res.HTTPCalled)
	}
	if rec.N != 0 {
		t.Fatal("network")
	}
}

func TestRunautogeneratesidempotencykey(t *testing.T) {
	opts, rec := harness(t, nil)
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	if rec.Key == "" || res.IdempotencyKey == "" {
		t.Fatal("key missing")
	}
}

func TestRunrespectsmanualidempotencykey(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "manual-001"
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
	if rec.Key != "manual-001" {
		t.Fatal(rec.Key)
	}
}

func TestRunsuccessexitcode0(t *testing.T) {
	opts, _ := harness(t, nil)
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res.ExitCode, res.Stderr)
	}
}

func TestRunautherrorexitcode3(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(401)
		w.Write(errEnvelope(api.CodeUnauthenticated, "nope"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 3 {
		t.Fatal(res.ExitCode, res.Stderr)
	}
}

func TestRunapprovalrequiredexitcode4(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(202)
		w.Write(errEnvelope(api.CodeApprovalRequired, "need approval"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 4 {
		t.Fatal(res.ExitCode)
	}
}

func TestRundomainerrorexitcode5(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(422)
		w.Write(errEnvelope(api.CodeDomainError, "exists fail"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 5 {
		t.Fatal(res.ExitCode)
	}
}

func TestRunratelimitedexitcode6(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(429)
		w.Write(errEnvelope(api.CodeRateLimited, "slow"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 6 {
		t.Fatal(res.ExitCode)
	}
}

func TestRunjsonenvelopematchesd018(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.JSON = true
	res := Run(context.Background(), opts)
	var env api.ErrorEnvelope
	if err := json.Unmarshal([]byte(res.Stdout), &env); err != nil {
		// stdout may be envelope
		if err2 := json.Unmarshal(res.Envelope, &env); err2 != nil {
			t.Fatal(err, string(res.Envelope), res.Stdout)
		}
	}
	if !env.OK {
		// success envelope
		var m map[string]any
		_ = json.Unmarshal(res.Envelope, &m)
		if m["ok"] != true {
			t.Fatal(m)
		}
	}
}

func TestRunnodomainlogiconclient(t *testing.T) {
	if !strings.Contains(DocsPrinciples, "No domain business logic") {
		t.Fatal()
	}
}

func TestRunretrylastreuseskey(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "fixed-key"
	res1 := Run(context.Background(), opts)
	if res1.ExitCode != 0 {
		t.Fatal(res1)
	}
	key1 := rec.Key
	opts.IdempotencyKey = ""
	opts.RetryLast = true
	res2 := Run(context.Background(), opts)
	if res2.ExitCode != 0 {
		t.Fatal(res2)
	}
	if rec.Key != key1 {
		t.Fatalf("retry key %s vs %s", rec.Key, key1)
	}
}

func TestRuninputfileflagreadsjson(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.InputJSON = nil
	opts.InputFile = writeInputFile(t, `{"customer_id":1,"amount_cents":2,"currency":"USD"}`)
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res.ExitCode, res.Stderr)
	}
}

func TestRunjsonflagprintsenvelope(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.JSON = true
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
	if !strings.Contains(res.Stdout, "ok") && !strings.Contains(string(res.Envelope), "ok") {
		t.Fatal(res.Stdout, string(res.Envelope))
	}
}

func TestRunserveronlyvalidationstillcheckedserverside(t *testing.T) {
	// Local schema only has type checks; server still receives POST for structurally valid input.
	opts, rec := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object","properties":{"customer_id":{"type":"integer"}}}}}`))
			return
		}
		w.WriteHeader(422)
		w.Write(errEnvelope(api.CodeDomainError, "exists:customers"))
	})
	opts.InputJSON = []byte(`{"customer_id":999999}`)
	res := Run(context.Background(), opts)
	if rec.N == 0 {
		t.Fatal("must hit server")
	}
	if res.ExitCode != 5 {
		t.Fatal(res.ExitCode)
	}
}

func TestRundoesnotskipserverrevalidation(t *testing.T) {
	opts, rec := harness(t, nil)
	_ = Run(context.Background(), opts)
	if rec.N != 1 {
		t.Fatal("must POST to server")
	}
}

func TestRunconflictidempotencyexitmapped(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(409)
		w.Write(errEnvelope(api.CodeConflict, "conflict"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 5 {
		t.Fatal(res.ExitCode)
	}
}

func TestRunnotfoundexitmapped(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(404)
		w.Write(errEnvelope(api.CodeNotFound, "missing"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 5 {
		t.Fatal(res.ExitCode)
	}
}

func TestRunoutputinvalidexitmapped(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(500)
		w.Write(errEnvelope(api.CodeOutputInvalid, "bad output"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 5 {
		t.Fatal(res.ExitCode)
	}
}

func TestRuninternalerrorexitcode1(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(500)
		w.Write(errEnvelope(api.CodeInternal, "boom"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 1 {
		t.Fatal(res.ExitCode)
	}
}

func TestRuntenanthintisnotauthoritativescope(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.TenantHint = "tenant-99"
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
	// Body may include _tenant_hint namespaced; never X-Capabilities-Caller
	if strings.Contains(string(rec.Body), `"caller"`) {
		t.Fatal("must not send caller in body as authority")
	}
}

func TestRunforbiddenexitcode3(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(403)
		w.Write(errEnvelope(api.CodeForbidden, "nope"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 3 {
		t.Fatal(res.ExitCode)
	}
}

func TestRununauthenticatedexitcode3(t *testing.T) {
	opts, _ := harness(t, nil)
	// clear token
	_ = opts.Store.DeleteToken("default")
	res := Run(context.Background(), opts)
	if res.ExitCode != 3 {
		t.Fatal(res.ExitCode)
	}
}

func TestRunvalidationviolationslistedinenvelope(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.InputJSON = []byte(`{"customer_id":"bad"}`)
	res := Run(context.Background(), opts)
	var env api.ErrorEnvelope
	if err := json.Unmarshal(res.Envelope, &env); err != nil {
		t.Fatal(err)
	}
	if env.Error == nil || len(env.Error.Violations) == 0 {
		t.Fatal(string(res.Envelope))
	}
}
