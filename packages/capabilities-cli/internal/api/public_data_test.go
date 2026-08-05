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
