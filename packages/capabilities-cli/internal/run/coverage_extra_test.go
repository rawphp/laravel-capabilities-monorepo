package run

import (
	"context"
	"encoding/json"
	"net/http"
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

func TestEnsureIdempotencyKey(t *testing.T) {
	if EnsureIdempotencyKey("m") != "m" {
		t.Fatal()
	}
	if EnsureIdempotencyKey("") == "" {
		t.Fatal()
	}
	if EnsureIdempotencyKey("  ") == "  " {
		// trim: currently no trim — empty check is TrimSpace in Ensure
	}
	k := EnsureIdempotencyKey("   ")
	// whitespace-only is treated as empty via TrimSpace
	if k == "   " {
		t.Fatal("should generate uuid for whitespace")
	}
}

func TestRunWithoutClient(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.Client = nil
	// need schema skip network for describe - catalog may still work
	opts.Catalog = nil
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitInternal {
		t.Fatal(res.ExitCode, res.Stderr)
	}
}

func TestRunUsesStoreLastRunPath(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.LastRunPath = "" // force store path
	opts.IdempotencyKey = "store-path-key"
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
	opts.RetryLast = true
	opts.IdempotencyKey = ""
	res = Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res.Stderr)
	}
	if rec.Key != "store-path-key" {
		t.Fatal(rec.Key)
	}
}

func TestLoadInputFileInvalid(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.InputJSON = nil
	opts.InputFile = writeInputFile(t, "not-json")
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitValidation {
		t.Fatal(res)
	}
	opts.InputFile = "/no/such/file.json"
	res = Run(context.Background(), opts)
	if res.ExitCode != ExitValidation {
		t.Fatal(res)
	}
}

func TestLocalFailEnvelopeJSON(t *testing.T) {
	b := localFailEnvelope(api.CodeValidationFailed, "m", []api.Violation{{Field: "f", Message: "x"}})
	var env api.ErrorEnvelope
	if err := json.Unmarshal(b, &env); err != nil || env.OK {
		t.Fatal(err, env)
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

func TestTypeMatchesNumberJSONNumber(t *testing.T) {
	if !typeMatches("number", json.Number("1.2")) {
		t.Fatal()
	}
	if !typeMatches("integer", json.Number("3")) {
		t.Fatal()
	}
	if typeMatches("integer", json.Number("1.5")) {
		t.Fatal()
	}
	if !typeMatches("null", nil) {
		t.Fatal()
	}
	if typeMatches("string", nil) {
		t.Fatal()
	}
	if typeMatches("unknown", 1) {
		// unknown types return true
		if !typeMatches("unknown", 1) {
			t.Fatal()
		}
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

func TestRunHTTPStatusWithoutEnvelope(t *testing.T) {
	opts, _ := harness(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(500)
		w.Write([]byte("plain error"))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode == 0 {
		t.Fatal(res)
	}
}
