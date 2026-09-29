package run

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestValidateLocalTypesAndArrays(t *testing.T) {
	schema := []byte(`{
		"type":"object",
		"required":["s","n","i","b","a","o"],
		"properties":{
			"s":{"type":"string"},
			"n":{"type":"number"},
			"i":{"type":"integer"},
			"b":{"type":"boolean"},
			"a":{"type":"array","items":{"type":"string"}},
			"o":{"type":"object","properties":{"x":{"type":"null"}}},
			"z":{"type":"null"}
		}
	}`)
	good := []byte(`{"s":"hi","n":1.5,"i":2,"b":true,"a":["x"],"o":{"x":null},"z":null}`)
	if err := ValidateLocal(schema, good); err != nil {
		t.Fatal(err)
	}
	// empty schema ok
	if err := ValidateLocal(nil, good); err != nil {
		t.Fatal(err)
	}
	// invalid schema doc
	if err := ValidateLocal([]byte(`{`), good); err == nil {
		t.Fatal()
	}
	// invalid json input
	if err := ValidateLocal(schema, []byte(`{`)); err == nil {
		t.Fatal()
	}
	// type failures
	for _, bad := range []string{
		`{"s":1,"n":1,"i":2,"b":true,"a":[],"o":{}}`,
		`{"s":"hi","n":"x","i":2,"b":true,"a":[],"o":{}}`,
		`{"s":"hi","n":1,"i":1.5,"b":true,"a":[],"o":{}}`,
		`{"s":"hi","n":1,"i":2,"b":"x","a":[],"o":{}}`,
		`{"s":"hi","n":1,"i":2,"b":true,"a":"x","o":{}}`,
		`{"s":"hi","n":1,"i":2,"b":true,"a":[1],"o":{}}`,
		`{"s":"hi","n":1,"i":2,"b":true,"a":[],"o":[]}`,
	} {
		if err := ValidateLocal(schema, []byte(bad)); err == nil {
			t.Fatalf("expected fail for %s", bad)
		}
	}
	// required missing
	if err := ValidateLocal(schema, []byte(`{}`)); err == nil {
		t.Fatal()
	}
	// ValidationError.Error message path includes field-level summary for humans.
	err := ValidateLocal(schema, []byte(`{}`))
	ve := err.(*ValidationError)
	if ve.Error() == "" {
		t.Fatal()
	}
	if !strings.Contains(ve.Error(), "is required") || !strings.Contains(ve.Error(), "s:") {
		t.Fatalf("stderr-facing error should name the field: %q", ve.Error())
	}
	ve2 := &ValidationError{}
	if ve2.Error() != "validation_failed" {
		t.Fatal(ve2.Error())
	}
}

func TestValidateLocalFormatDate(t *testing.T) {
	schema := []byte(`{
		"type":"object",
		"required":["date"],
		"properties":{"date":{"type":"string","format":"date"}}
	}`)
	if err := ValidateLocal(schema, []byte(`{"date":"2026-01-15"}`)); err != nil {
		t.Fatal(err)
	}
	err := ValidateLocal(schema, []byte(`{"date":"example"}`))
	if err == nil {
		t.Fatal("expected format fail for date=example")
	}
	ve := err.(*ValidationError)
	if !strings.Contains(ve.Error(), "date") || !strings.Contains(strings.ToLower(ve.Error()), "date") {
		t.Fatalf("expected date field in error: %q", ve.Error())
	}
	if !strings.Contains(ve.Error(), "invalid date format") {
		t.Fatalf("expected format message: %q", ve.Error())
	}
	// No network implication: invalid format fails closed locally.
	if err := ValidateLocal(schema, []byte(`{"date":"2026-13-40"}`)); err == nil {
		t.Fatal("expected calendar validation fail")
	}
}

func TestValidateNestedArrayObjects(t *testing.T) {
	schema := []byte(`{"type":"object","properties":{"items":{"type":"array","items":{"type":"object","required":["id"],"properties":{"id":{"type":"integer"}}}}}}`)
	if err := ValidateLocal(schema, []byte(`{"items":[{"id":1},{"id":2}]}`)); err != nil {
		t.Fatal(err)
	}
	if err := ValidateLocal(schema, []byte(`{"items":[{"id":"x"}]}`)); err == nil {
		t.Fatal("expected fail")
	}
}

func TestJoinPathArray(t *testing.T) {
	if joinPath("a", "[0]") != "a[0]" {
		t.Fatal(joinPath("a", "[0]"))
	}
	if joinPath("", "b") != "b" {
		t.Fatal()
	}
}

func TestTypeMatchesJSONNumberNullAndUnknownTypes(t *testing.T) {
	cases := []struct {
		typ  string
		v    any
		want bool
	}{
		{"number", json.Number("1.2"), true},
		{"integer", json.Number("3"), true},
		{"integer", json.Number("1.5"), false},
		{"integer", "3", false},
		{"number", "3", false},
		{"null", nil, true},
		{"null", 1.0, false},
		{"string", nil, false},
		{"unknown", 1.0, true}, // unknown keywords are the server's job
	}
	for _, tc := range cases {
		if got := typeMatches(tc.typ, tc.v); got != tc.want {
			t.Fatalf("typeMatches(%q, %#v) = %v", tc.typ, tc.v, got)
		}
	}
}

func TestValidateLocalSkipsMalformedSchemaKeywords(t *testing.T) {
	// Non-string required entries and non-object property schemas are ignored,
	// not reported: the server remains the law for schemas the CLI cannot read.
	schema := []byte(`{"type":"object","required":["a",7,""],"properties":{"a":{"type":"string"},"b":"junk"}}`)
	if err := ValidateLocal(schema, []byte(`{"a":"x","b":1}`)); err != nil {
		t.Fatal(err)
	}
	if err := ValidateLocal(schema, []byte(`{"b":1}`)); err == nil || !strings.Contains(err.Error(), "a: is required") {
		t.Fatalf("required string entry still enforced: %v", err)
	}
}

func TestValidationErrorMessageListsFieldlessViolations(t *testing.T) {
	ve := &ValidationError{Message: "bad", Violations: []api.Violation{{Message: "whole body"}, {Field: "f", Message: "nope"}}}
	if got := ve.Error(); got != "bad (whole body; f: nope)" {
		t.Fatal(got)
	}
	// Violations with no text add nothing.
	ve = &ValidationError{Violations: []api.Violation{{}}}
	if got := ve.Error(); got != "validation_failed" {
		t.Fatal(got)
	}
}
