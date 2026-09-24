package api

import (
	"context"
	"net/http"
	"testing"
)

// A 2xx invoke must carry a D-018 success envelope ({"ok":true,…}); anything
// else is a client-side internal error so callers fail closed.
func TestInvokeRejectsMalformedSuccessBody(t *testing.T) {
	cases := map[string]string{
		"not json":          `<html>ok</html>`,
		"empty body":        ``,
		"json array":        `[{"invoice_id":1}]`,
		"object without ok": `{"data":{"invoice_id":1}}`,
		"ok false no error": `{"ok":false,"data":{"invoice_id":1}}`,
		"ok not boolean":    `{"ok":"true","data":{}}`,
	}
	for name, body := range cases {
		t.Run(name, func(t *testing.T) {
			_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
				_, _ = w.Write([]byte(body))
			})
			res, err := c.InvokeCapability(context.Background(), "create-invoice", nil, "k")
			if err != nil {
				t.Fatal(err)
			}
			if res.Err == nil {
				t.Fatalf("expected malformed-envelope error for %q", body)
			}
			if res.Err.Code != CodeInternal || res.Err.ExitCode != ExitInternal || res.Err.HTTPStatus != 200 {
				t.Fatalf("got %+v", res.Err)
			}
			if string(res.Err.Body) != body {
				t.Fatalf("raw body not kept: %q", res.Err.Body)
			}
		})
	}
}

func TestInvokeAcceptsSuccessEnvelope(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusCreated)
		_, _ = w.Write([]byte(`{"ok":true,"data":{"invoice_id":1},"meta":{"request_id":"r1"}}`))
	})
	res, err := c.InvokeCapability(context.Background(), "create-invoice", nil, "k")
	if err != nil || res.Err != nil {
		t.Fatal(err, res.Err)
	}
}

func TestInvokeKeepsServerErrorEnvelopeOn2xx(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusAccepted)
		_, _ = w.Write([]byte(`{"ok":false,"error":{"code":"approval_required","message":"need","approval_id":"ap1"}}`))
	})
	res, err := c.InvokeCapability(context.Background(), "create-invoice", nil, "k")
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeApprovalRequired {
		t.Fatalf("got %+v", res.Err)
	}
}

func TestInvokeTransportErrorReturnsError(t *testing.T) {
	srv, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {})
	srv.Close()
	res, err := c.InvokeCapability(context.Background(), "create-invoice", nil, "k")
	if err == nil || res != nil {
		t.Fatal(err, res)
	}
}
