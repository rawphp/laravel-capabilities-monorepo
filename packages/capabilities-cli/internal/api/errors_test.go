package api

import (
	"encoding/json"
	"testing"
)

func assertMap(t *testing.T, code string, http, exit int) {
	t.Helper()
	se := MapErrorCode(code)
	if se.ExitCode != exit {
		t.Fatalf("exit: got %d want %d for %s", se.ExitCode, exit, code)
	}
	if se.HTTPStatus != http {
		t.Fatalf("http: got %d want %d for %s", se.HTTPStatus, http, code)
	}
	if ExitCode(code) != exit {
		t.Fatalf("ExitCode mismatch")
	}
	if HTTPStatus(code) != http {
		t.Fatalf("HTTPStatus mismatch")
	}
}

func TestMaperrorcodeValidationFailedHttp422Exit2(t *testing.T) {
	assertMap(t, "validation_failed", 422, 2)
}

func TestMaperrorcodeUnauthenticatedHttp401Exit3(t *testing.T) {
	assertMap(t, "unauthenticated", 401, 3)
}

func TestMaperrorcodeForbiddenHttp403Exit3(t *testing.T) {
	assertMap(t, "forbidden", 403, 3)
}

func TestMaperrorcodeApprovalRequiredHttp202Exit4(t *testing.T) {
	assertMap(t, "approval_required", 202, 4)
}

func TestMaperrorcodeDomainErrorHttp422Exit5(t *testing.T) {
	assertMap(t, "domain_error", 422, 5)
}

func TestMaperrorcodeRateLimitedHttp429Exit6(t *testing.T) {
	assertMap(t, "rate_limited", 429, 6)
}

func TestMaperrorcodeConflictHttp409Exit5(t *testing.T) {
	assertMap(t, "conflict", 409, 5)
}

func TestMaperrorcodeNotFoundHttp404Exit5(t *testing.T) {
	assertMap(t, "not_found", 404, 5)
}

func TestMaperrorcodeOutputInvalidHttp500Exit5(t *testing.T) {
	assertMap(t, "output_invalid", 500, 5)
}

func TestMaperrorcodeInternalHttp500Exit1(t *testing.T) {
	assertMap(t, "internal", 500, 1)
}

func TestUnknownerrorcodedefaultstointernalexit1(t *testing.T) {
	if ExitCode("totally_unknown") != ExitInternal {
		t.Fatal("unknown should map to exit 1")
	}
	se := MapErrorCode("totally_unknown")
	if se.ExitCode != ExitInternal {
		t.Fatal("MapErrorCode unknown")
	}
}

func TestRetryableflagparsedfromenvelope(t *testing.T) {
	raw := []byte(`{"ok":false,"error":{"code":"rate_limited","message":"slow down","retryable":true,"request_id":"r1"}}`)
	var env ErrorEnvelope
	if err := json.Unmarshal(raw, &env); err != nil {
		t.Fatal(err)
	}
	se := ParseErrorEnvelope(env, 429, raw)
	if se == nil || !se.Retryable {
		t.Fatalf("expected retryable true, got %#v", se)
	}
	if se.RequestID != "r1" {
		t.Fatalf("request id %q", se.RequestID)
	}
	if se.ExitCode != ExitRateLimit {
		t.Fatalf("exit %d", se.ExitCode)
	}
}

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
