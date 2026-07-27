package run

import (
	"context"
	"net/http"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestFaillocalschema(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.InputJSON = []byte(`{"customer_id":"x"}`)
	res := Run(context.Background(), opts)
	if res.ExitCode != 2 || rec.N != 0 {
		t.Fatal(res.ExitCode, rec.N)
	}
}

func TestFailauth(t *testing.T) {
	opts, _ := harness(t, nil)
	_ = opts.Store.DeleteToken("default")
	res := Run(context.Background(), opts)
	if res.ExitCode != 3 {
		t.Fatal(res.ExitCode)
	}
}

func TestFailnetwork(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.Client = api.NewClient("http://127.0.0.1:1", "t")
	// bypass schema fetch network by clearing catalog
	opts.Catalog = nil
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitInternal {
		t.Fatal(res.ExitCode, res.Stderr)
	}
}

func TestFailservervalidation(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(422)
		w.Write(errEnvelope(api.CodeValidationFailed, "server val"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != 2 {
		t.Fatal(res.ExitCode)
	}
}

func TestFailforbidden(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(403)
		w.Write(errEnvelope(api.CodeForbidden, "f"))
	})
	if Run(context.Background(), opts).ExitCode != 3 {
		t.Fatal()
	}
}

func TestFailapprovalrequired(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(202)
		w.Write(errEnvelope(api.CodeApprovalRequired, "a"))
	})
	if Run(context.Background(), opts).ExitCode != 4 {
		t.Fatal()
	}
}

func TestFailratelimited(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(429)
		w.Write(errEnvelope(api.CodeRateLimited, "r"))
	})
	if Run(context.Background(), opts).ExitCode != 6 {
		t.Fatal()
	}
}

func TestFailconflict(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(409)
		w.Write(errEnvelope(api.CodeConflict, "c"))
	})
	if Run(context.Background(), opts).ExitCode != 5 {
		t.Fatal()
	}
}

func TestFailnotfound(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(404)
		w.Write(errEnvelope(api.CodeNotFound, "n"))
	})
	if Run(context.Background(), opts).ExitCode != 5 {
		t.Fatal()
	}
}

func TestFailinternal(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(500)
		w.Write(errEnvelope(api.CodeInternal, "i"))
	})
	if Run(context.Background(), opts).ExitCode != 1 {
		t.Fatal()
	}
}

func TestFailoutputinvalid(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(500)
		w.Write(errEnvelope(api.CodeOutputInvalid, "o"))
	})
	if Run(context.Background(), opts).ExitCode != 5 {
		t.Fatal()
	}
}

func TestFaildomain(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(422)
		w.Write(errEnvelope(api.CodeDomainError, "d"))
	})
	if Run(context.Background(), opts).ExitCode != 5 {
		t.Fatal()
	}
}
