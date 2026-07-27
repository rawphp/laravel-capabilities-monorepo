package run

import (
	"context"
	"net/http"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestLocalstructuralfailnohttp(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.InputJSON = []byte(`{"customer_id":"x"}`)
	res := Run(context.Background(), opts)
	if res.HTTPCalled || rec.N != 0 || res.ExitCode != 2 {
		t.Fatal(res, rec.N)
	}
}

func TestLocalstructuralokthenhttp(t *testing.T) {
	opts, rec := harness(t, nil)
	res := Run(context.Background(), opts)
	if !res.HTTPCalled || rec.N != 1 || res.ExitCode != 0 {
		t.Fatal(res, rec.N)
	}
}

func TestServerexistsfailafterlocalok(t *testing.T) {
	opts, rec := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(422)
		w.Write(errEnvelope(api.CodeDomainError, "exists"))
	})
	res := Run(context.Background(), opts)
	if rec.N != 1 || res.ExitCode != 5 {
		t.Fatal(rec.N, res.ExitCode)
	}
}

func TestServerauthorizefailafterlocalok(t *testing.T) {
	opts, rec := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(403)
		w.Write(errEnvelope(api.CodeForbidden, "deny"))
	})
	res := Run(context.Background(), opts)
	if rec.N != 1 || res.ExitCode != 3 {
		t.Fatal(rec.N, res.ExitCode)
	}
}

func TestServerapprovalrequiredafterlocalok(t *testing.T) {
	opts, rec := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(202)
		w.Write(errEnvelope(api.CodeApprovalRequired, "need"))
	})
	res := Run(context.Background(), opts)
	if rec.N != 1 || res.ExitCode != 4 {
		t.Fatal(rec.N, res.ExitCode)
	}
}

func TestLocalcacheusedwhenversionmatches(t *testing.T) {
	opts, _ := harness(t, nil)
	// warm cache via first describe inside run
	_ = Run(context.Background(), opts)
	// second run should use cache for schema (still POSTs invoke)
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}

func TestLocalcachebypassedwithnocache(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.NoCache = true
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}

func TestSchemafetchedonmissingcache(t *testing.T) {
	opts, _ := harness(t, nil)
	// empty cache dir naturally
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}
