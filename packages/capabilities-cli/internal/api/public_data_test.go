package api

import (
	"encoding/json"
	"strings"
	"testing"
)

func TestStructuredErrorPublicDataWireKeys(t *testing.T) {
	se := &StructuredError{
		Code:       CodeValidationFailed,
		Message:    "JSON Schema validation failed.",
		HTTPStatus: 422,
		ExitCode:   ExitValidation,
		Retryable:  false,
		Violations: []Violation{{Field: "date", Message: "required property missing"}},
		Body:       []byte(`{"ok":false}`),
	}
	m := se.PublicData()
	if m["code"] != CodeValidationFailed {
		t.Fatal(m)
	}
	if m["message"] != se.Message {
		t.Fatal(m)
	}
	if _, ok := m["Code"]; ok {
		t.Fatal("must not use Go-exported field names")
	}
	b, err := json.Marshal(m)
	if err != nil {
		t.Fatal(err)
	}
	s := string(b)
	if strings.Contains(s, `"Body"`) || strings.Contains(s, "ok\":false") {
		t.Fatalf("raw body leaked: %s", s)
	}
	if !strings.Contains(s, `"violations"`) || !strings.Contains(s, `"date"`) {
		t.Fatal(s)
	}
}

func TestPublicDataIncludesRetryAndApprovalOnlyWhenSet(t *testing.T) {
	var nilErr *StructuredError
	if nilErr.PublicData() != nil {
		t.Fatal("nil error has no public data")
	}
	id := "apr_1"
	m := (&StructuredError{Code: CodeApprovalRequired, ApprovalID: &id, RetryAfter: 30, RequestID: "req_1"}).PublicData()
	if m["approval_id"] != "apr_1" || m["retry_after"] != 30 || m["request_id"] != "req_1" {
		t.Fatalf("%v", m)
	}
	m = (&StructuredError{Code: CodeInternal}).PublicData()
	if v, ok := m["approval_id"]; !ok || v != nil {
		t.Fatalf("approval_id must be explicit null: %v", m)
	}
	if _, ok := m["retry_after"]; ok {
		t.Fatalf("retry_after only when positive: %v", m)
	}
}
