package helpfmt

import (
	"encoding/json"
	"testing"
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

func TestFlagName_kebabCase(t *testing.T) {
	cases := map[string]string{
		"customer_id":  "--customer-id",
		"amountCents":  "--amount-cents",
		"currency":     "--currency",
		"line_items":   "--line-items",
		"Already-Kebab": "--already-kebab",
	}
	for in, want := range cases {
		if got := FlagName(in); got != want {
			t.Fatalf("FlagName(%q)=%q want %q", in, got, want)
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
