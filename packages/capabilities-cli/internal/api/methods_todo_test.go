package api

import (
	"context"
	"encoding/json"
	"net/http"
	"testing"
)

func TestListcapabilities(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet || r.URL.Path != PathCapabilities {
			t.Fatalf("%s %s", r.Method, r.URL.Path)
		}
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"a"}]}}`))
	})
	res, err := c.ListCapabilities(context.Background())
	if err != nil || res.StatusCode != 200 {
		t.Fatal(err, res)
	}
}

func TestDescribecapability(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/capabilities/create-invoice" {
			t.Fatal(r.URL.Path)
		}
		w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","input_schema":{"type":"object"}}}`))
	})
	res, err := c.DescribeCapability(context.Background(), "create-invoice")
	if err != nil {
		t.Fatal(err)
	}
	if !res.Envelope.OK {
		t.Fatal(res.Envelope)
	}
}

func TestInvokecapability(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Fatal(r.Method)
		}
		w.Write([]byte(`{"ok":true,"data":{"invoice_id":1}}`))
	})
	res, err := c.InvokeCapability(context.Background(), "create-invoice", json.RawMessage(`{"customer_id":1}`), "k")
	if err != nil || res.Err != nil {
		t.Fatal(err, res.Err)
	}
}

func TestAcceptapproval(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/capabilities/approvals/ap1/accept" {
			t.Fatal(r.URL.Path)
		}
		w.Write([]byte(`{"ok":true}`))
	})
	_, err := c.AcceptApproval(context.Background(), "ap1")
	if err != nil {
		t.Fatal(err)
	}
}

func TestRejectapproval(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/capabilities/approvals/ap1/reject" {
			t.Fatal(r.URL.Path)
		}
		w.Write([]byte(`{"ok":true}`))
	})
	_, err := c.RejectApproval(context.Background(), "ap1")
	if err != nil {
		t.Fatal(err)
	}
}

func TestHealth(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != PathHealth {
			t.Fatal(r.URL.Path)
		}
		w.Write([]byte(`{"ok":true,"data":{"ready":true}}`))
	})
	_, err := c.Health(context.Background())
	if err != nil {
		t.Fatal(err)
	}
}

func TestLogindevice(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != PathAuthDevice {
			t.Fatal(r.URL.Path)
		}
		w.Write([]byte(`{"ok":true,"data":{"device_code":"d","access_token":"t1"}}`))
	})
	res, err := c.LoginDevice(context.Background(), map[string]any{"client_id": "capabilities-cli"})
	if err != nil {
		t.Fatal(err)
	}
	if res.StatusCode != 200 {
		t.Fatal(res.StatusCode)
	}
}

func TestLogintoken(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != PathAuthToken {
			t.Fatal(r.URL.Path)
		}
		w.Write([]byte(`{"ok":true,"data":{"access_token":"t2"}}`))
	})
	_, err := c.LoginToken(context.Background(), map[string]any{"grant_type": "authorization_code"})
	if err != nil {
		t.Fatal(err)
	}
}
