package helpfmt

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/flagschema"
)

// sampleInputSchema matches design Schema help UX examples (invoice-like).
const sampleInputSchema = `{
  "type": "object",
  "required": ["customer_id", "currency"],
  "properties": {
    "customer_id": {"type": "integer", "minimum": 1},
    "amount_cents": {"type": "integer"},
    "currency": {"type": "string", "enum": ["USD", "EUR"]},
    "line_items": {"type": "array", "items": {"type": "object"}},
    "meta": {"type": "object"},
    "notes": {"type": "string", "maxLength": 500},
    "active": {"type": "boolean"}
  }
}`

func parseSchema(t *testing.T, raw string) map[string]any {
	t.Helper()
	var m map[string]any
	if err := json.Unmarshal([]byte(raw), &m); err != nil {
		t.Fatal(err)
	}
	return m
}

func fieldByName(fields []Field, name string) (Field, bool) {
	for _, f := range fields {
		if f.Name == name {
			return f, true
		}
	}
	return Field{}, false
}

func TestDeriveFields_exposesNameTypeRequiredPassMode(t *testing.T) {
	fields := DeriveFields(parseSchema(t, sampleInputSchema))
	if len(fields) == 0 {
		t.Fatal("expected fields from sample schema")
	}
	for _, f := range fields {
		if f.Name == "" {
			t.Fatal("field name empty")
		}
		if f.Type == "" {
			t.Fatalf("%s: type empty", f.Name)
		}
		if f.Pass != PassFlag && f.Pass != PassJSONOnly {
			t.Fatalf("%s: pass mode %q", f.Name, f.Pass)
		}
		if f.Pass == PassFlag {
			if f.Flag == nil || *f.Flag == "" {
				t.Fatalf("%s: flag pass requires non-null flag", f.Name)
			}
		}
		if f.Pass == PassJSONOnly {
			if f.Flag != nil {
				t.Fatalf("%s: json-only must have flag null, got %v", f.Name, f.Flag)
			}
		}
	}
}

func TestDeriveFields_scalarAndEnumAreFlags(t *testing.T) {
	fields := DeriveFields(parseSchema(t, sampleInputSchema))

	cid, ok := fieldByName(fields, "customer_id")
	if !ok {
		t.Fatal("missing customer_id")
	}
	if cid.Type != "integer" || !cid.Required || cid.Pass != PassFlag {
		t.Fatalf("customer_id: %+v", cid)
	}
	if cid.Flag == nil || *cid.Flag != "--customer-id" {
		t.Fatalf("customer_id flag: %v", cid.Flag)
	}
	if cid.Constraints["minimum"] == nil {
		t.Fatalf("customer_id constraints: %+v", cid.Constraints)
	}

	cur, ok := fieldByName(fields, "currency")
	if !ok {
		t.Fatal("missing currency")
	}
	if cur.Type != "string" || !cur.Required || cur.Pass != PassFlag {
		t.Fatalf("currency: %+v", cur)
	}
	if cur.Flag == nil || *cur.Flag != "--currency" {
		t.Fatalf("currency flag: %v", cur.Flag)
	}
	enum, ok := cur.Constraints["enum"].([]any)
	if !ok || len(enum) != 2 {
		t.Fatalf("currency enum constraints: %+v", cur.Constraints)
	}

	notes, ok := fieldByName(fields, "notes")
	if !ok {
		t.Fatal("missing notes")
	}
	if notes.Required || notes.Pass != PassFlag {
		t.Fatalf("notes: %+v", notes)
	}
	if notes.Constraints["maxLength"] == nil {
		t.Fatalf("notes maxLength: %+v", notes.Constraints)
	}

	active, ok := fieldByName(fields, "active")
	if !ok {
		t.Fatal("missing active")
	}
	if active.Type != "boolean" || active.Pass != PassFlag {
		t.Fatalf("active: %+v", active)
	}
}

func TestDeriveFields_objectAndArrayAreJSONOnly(t *testing.T) {
	fields := DeriveFields(parseSchema(t, sampleInputSchema))

	li, ok := fieldByName(fields, "line_items")
	if !ok {
		t.Fatal("missing line_items")
	}
	if li.Type != "array" || li.Pass != PassJSONOnly || li.Flag != nil || li.Required {
		t.Fatalf("line_items: %+v", li)
	}

	meta, ok := fieldByName(fields, "meta")
	if !ok {
		t.Fatal("missing meta")
	}
	if meta.Type != "object" || meta.Pass != PassJSONOnly || meta.Flag != nil {
		t.Fatalf("meta: %+v", meta)
	}
}

func TestDeriveFields_nullableScalarIsFlag(t *testing.T) {
	schema := parseSchema(t, `{
		"type": "object",
		"properties": {
			"label": {"type": ["string", "null"]}
		}
	}`)
	fields := DeriveFields(schema)
	f, ok := fieldByName(fields, "label")
	if !ok {
		t.Fatal("missing label")
	}
	if f.Pass != PassFlag || f.Flag == nil || *f.Flag != "--label" {
		t.Fatalf("nullable string should be flag: %+v", f)
	}
}

func TestDeriveFields_freeFormMapIsJSONOnly(t *testing.T) {
	schema := parseSchema(t, `{
		"type": "object",
		"properties": {
			"tags": {"type": "object", "additionalProperties": true}
		}
	}`)
	fields := DeriveFields(schema)
	f, ok := fieldByName(fields, "tags")
	if !ok {
		t.Fatal("missing tags")
	}
	if f.Pass != PassJSONOnly || f.Flag != nil {
		t.Fatalf("additionalProperties bag should be json-only: %+v", f)
	}
}

// Help advertises exactly the flags invoke accepts: flag names and pass modes
// come from flagschema, so a help example pasted back into the CLI never hits
// "unknown flag" or "json-only" rejections.
func TestDeriveFields_flagsMatchWhatInvokeAccepts(t *testing.T) {
	raw := `{
		"type": "object",
		"properties": {
			"customer_id": {"type": "integer"},
			"amountCents": {"type": "integer"},
			"Already-Kebab": {"type": "string"},
			"picked": {"type": "string", "oneOf": [{"const": "a"}, {"const": "b"}]},
			"line_no": {"type": "integer"},
			"line-no": {"type": "integer"},
			"weird": "not-a-schema"
		}
	}`
	fields := DeriveFields(parseSchema(t, raw))
	fs, err := flagschema.FromJSONSchema([]byte(raw))
	if err != nil {
		t.Fatal(err)
	}

	want := map[string]string{
		"customer_id":   "--customer-id",
		"amountCents":   "--amountCents",
		"Already-Kebab": "--Already-Kebab",
	}
	for name, flag := range want {
		f, ok := fieldByName(fields, name)
		if !ok || f.Pass != PassFlag || f.Flag == nil || *f.Flag != flag {
			t.Fatalf("%s: want flag %s, got %+v", name, flag, f)
		}
		// Round-trip: the advertised flag merges into the same property.
		merged, err := fs.Merge(nil, map[string]string{strings.TrimPrefix(flag, "--"): "7"})
		if err != nil {
			t.Fatalf("invoke rejects advertised %s: %v", flag, err)
		}
		if _, ok := merged[name]; !ok {
			t.Fatalf("%s did not land on %s: %v", flag, name, merged)
		}
	}

	// oneOf, kebab collisions, and non-object property schemas are json-only in
	// invoke, so help must not advertise a flag for them.
	for _, name := range []string{"picked", "line_no", "line-no", "weird"} {
		f, ok := fieldByName(fields, name)
		if !ok || f.Pass != PassJSONOnly || f.Flag != nil {
			t.Fatalf("%s: want json-only, got %+v", name, f)
		}
	}
}

func TestDeriveFieldsFromJSON(t *testing.T) {
	fields, err := DeriveFieldsFromJSON([]byte(sampleInputSchema))
	if err != nil {
		t.Fatal(err)
	}
	if len(fields) < 5 {
		t.Fatalf("got %d fields", len(fields))
	}
	_, err = DeriveFieldsFromJSON([]byte(`not-json`))
	if err == nil {
		t.Fatal("expected error for invalid JSON")
	}
}

func TestDeriveFields_noPropertiesYieldsNoRows(t *testing.T) {
	if got := DeriveFields(nil); got != nil {
		t.Fatalf("nil schema: %+v", got)
	}
	if got := DeriveFields(parseSchema(t, `{"type":"object"}`)); got != nil {
		t.Fatalf("no properties: %+v", got)
	}
	got, err := DeriveFieldsFromJSON(nil)
	if err != nil || got != nil {
		t.Fatalf("empty schema JSON: %+v %v", got, err)
	}
}

func TestDeriveFields_typeLabelsForUnionsAndUntypedShapes(t *testing.T) {
	fields := DeriveFields(parseSchema(t, `{
		"type": "object",
		"required": ["label", 7, ""],
		"properties": {
			"label": {"type": ["string", "null"]},
			"odd": {"type": [1, 2]},
			"status": {"enum": ["open", "closed"]},
			"address": {"properties": {"city": {"type": "string"}}},
			"tags": {"items": {"type": "string"}},
			"free": {}
		}
	}`))
	want := map[string]string{
		"label":   "string|null",
		"odd":     "any",
		"status":  "enum",
		"address": "object",
		"tags":    "array",
		"free":    "any",
	}
	for name, typ := range want {
		f, ok := fieldByName(fields, name)
		if !ok || f.Type != typ {
			t.Fatalf("%s: want type %q, got %+v", name, typ, f)
		}
	}
	// Non-string and empty entries in "required" are ignored.
	for _, f := range fields {
		if f.Required != (f.Name == "label") {
			t.Fatalf("%s: required=%v", f.Name, f.Required)
		}
	}
}
