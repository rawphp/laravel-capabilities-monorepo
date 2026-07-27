package api

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"
)

func TestCodeFromHTTPMatrix(t *testing.T) {
	cases := map[int]string{
		401: CodeUnauthenticated,
		403: CodeForbidden,
		404: CodeNotFound,
		409: CodeConflict,
		422: CodeValidationFailed,
		429: CodeRateLimited,
		500: CodeInternal,
		418: CodeInternal,
	}
	for status, want := range cases {
		if got := codeFromHTTP(status); got != want {
			t.Fatalf("%d: %s want %s", status, got, want)
		}
	}
}

func TestStructuredErrorErrorMethods(t *testing.T) {
	var n *StructuredError
	if n.Error() != "nil structured error" {
		t.Fatal(n.Error())
	}
	e := &StructuredError{Code: "x", Message: "m"}
	if e.Error() != "m" {
		t.Fatal(e.Error())
	}
	e2 := &StructuredError{Code: "only"}
	if e2.Error() != "only" {
		t.Fatal(e2.Error())
	}
}

func TestParseErrorEnvelopeNilWhenOK(t *testing.T) {
	if ParseErrorEnvelope(ErrorEnvelope{OK: true}, 200, nil) != nil {
		t.Fatal()
	}
	if ParseErrorEnvelope(ErrorEnvelope{OK: false}, 500, nil) != nil {
		t.Fatal("no error body")
	}
	se := ParseErrorEnvelope(ErrorEnvelope{OK: false, Error: &ErrorBody{Message: "x"}}, 500, []byte(`{}`))
	if se.Code != CodeInternal {
		t.Fatal(se.Code)
	}
}

func TestClientHTTPClientDefaults(t *testing.T) {
	c := &Client{Timeout: 0}
	hc := c.httpClient()
	if hc == nil {
		t.Fatal()
	}
	c2 := &Client{Timeout: 5 * time.Millisecond, HTTP: nil}
	_ = c2.httpClient()
	c3 := &Client{Accept: ""}
	if c3.accept() != AcceptJSON {
		t.Fatal()
	}
}

func TestClientNonEnvelopeHTTPError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(403)
		w.Write([]byte("forbidden plain"))
	}))
	t.Cleanup(srv.Close)
	c := NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	res, err := c.ListCapabilities(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeForbidden {
		t.Fatalf("%#v", res.Err)
	}
}

func TestClientDoWithBody(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true}`))
	}))
	t.Cleanup(srv.Close)
	c := NewClient(srv.URL, "")
	c.HTTP = srv.Client()
	_, err := c.do(context.Background(), http.MethodPost, "/capabilities", []byte(`{}`), nil)
	if err != nil {
		t.Fatal(err)
	}
}

func TestMapAllCodes(t *testing.T) {
	for code := range map[string]int{
		CodeValidationFailed: 2, CodeUnauthenticated: 3, CodeForbidden: 3,
		CodeApprovalRequired: 4, CodeDomainError: 5, CodeRateLimited: 6,
		CodeConflict: 5, CodeNotFound: 5, CodeOutputInvalid: 5, CodeInternal: 1,
	} {
		_ = MapErrorCode(code)
		_ = HTTPStatus(code)
		_ = ExitCode(code)
	}
	_ = json.RawMessage(`{}`)
}
