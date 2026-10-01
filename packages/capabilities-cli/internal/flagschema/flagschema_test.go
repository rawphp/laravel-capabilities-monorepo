package flagschema

import (
	"encoding/json"
	"errors"
	"reflect"
	"sort"
	"strings"
	"testing"
)

// fixtureSchema covers scalar, enum, object, array, and optional fields.
const fixtureSchema = `{
  "type": "object",
  "required": ["customer_id", "currency"],
  "properties": {
    "customer_id": { "type": "integer" },
    "amount_cents": { "type": "integer" },
    "currency": { "type": "string" },
    "note": { "type": "string" },
    "active": { "type": "boolean" },
    "rate": { "type": "number" },
    "status": { "type": "string", "enum": ["draft", "open", "paid"] },
    "priority": { "enum": [1, 2, 3] },
    "meta": { "type": "object", "additionalProperties": true },
    "line_items": {
      "type": "array",
      "items": { "type": "object" }
    },
    "tags": {
      "type": "array",
      "items": { "type": "string" }
    }
  }
}`

func TestFromJSONSchema_scalarVsJSONOnly(t *testing.T) {
	s, err := FromJSONSchema([]byte(fixtureSchema))
	if err != nil {
		t.Fatal(err)
	}

	wantPass := map[string]PassMode{
		"customer_id":  PassFlag,
		"amount_cents": PassFlag,
		"currency":     PassFlag,
		"note":         PassFlag,
		"active":       PassFlag,
		"rate":         PassFlag,
		"status":       PassFlag,
		"priority":     PassFlag,
		"meta":         PassJSONOnly,
		"line_items":   PassJSONOnly,
		"tags":         PassJSONOnly,
	}

	got := map[string]Field{}
	for _, f := range s.Fields {
		got[f.Name] = f
	}
	if len(got) != len(wantPass) {
		t.Fatalf("field count: got %d want %d (%v)", len(got), len(wantPass), fieldNames(s.Fields))
	}
	for name, mode := range wantPass {
		f, ok := got[name]
		if !ok {
			t.Errorf("missing field %q", name)
			continue
		}
		if f.Pass != mode {
			t.Errorf("%s: Pass=%q want %q", name, f.Pass, mode)
		}
	}

	// kebab-case flag names (canonical)
	if got["customer_id"].FlagName != "customer-id" {
		t.Errorf("customer_id FlagName=%q want customer-id", got["customer_id"].FlagName)
	}
	if got["amount_cents"].FlagName != "amount-cents" {
		t.Errorf("amount_cents FlagName=%q want amount-cents", got["amount_cents"].FlagName)
	}
	if got["line_items"].FlagName != "line-items" {
		t.Errorf("line_items FlagName=%q want line-items", got["line_items"].FlagName)
	}
	if got["customer_id"].Required != true {
		t.Error("customer_id should be required")
	}
	if got["note"].Required != false {
		t.Error("note should be optional")
	}
	if !reflect.DeepEqual(got["status"].Enum, []any{"draft", "open", "paid"}) {
		t.Errorf("status enum = %#v", got["status"].Enum)
	}
}

func TestToKebab(t *testing.T) {
	cases := []struct {
		in, want string
	}{
		{"customer_id", "customer-id"},
		{"amount_cents", "amount-cents"},
		{"currency", "currency"},
		{"line_items", "line-items"},
		{"already-kebab", "already-kebab"},
	}
	for _, tc := range cases {
		if got := ToKebab(tc.in); got != tc.want {
			t.Errorf("ToKebab(%q)=%q want %q", tc.in, got, tc.want)
		}
	}
}

func TestMerge_table(t *testing.T) {
	s, err := FromJSONSchema([]byte(fixtureSchema))
	if err != nil {
		t.Fatal(err)
	}

	type row struct {
		name    string
		base    string // JSON or "" for no base
		flags   map[string]string
		want    map[string]any
		wantErr error // sentinel; nil = success
	}

	rows := []row{
		{
			name:  "missing input becomes empty object",
			base:  "",
			flags: nil,
			want:  map[string]any{},
		},
		{
			name:  "base only",
			base:  `{"customer_id":1,"currency":"USD"}`,
			flags: nil,
			want:  map[string]any{"customer_id": json.Number("1"), "currency": "USD"},
		},
		{
			name:  "flags only",
			base:  "",
			flags: map[string]string{"customer-id": "42", "currency": "EUR"},
			want:  map[string]any{"customer_id": int64(42), "currency": "EUR"},
		},
		{
			name:  "flag wins on key conflict",
			base:  `{"customer_id":1,"currency":"USD","note":"from-json"}`,
			flags: map[string]string{"customer-id": "99", "currency": "GBP"},
			want: map[string]any{
				"customer_id": int64(99),
				"currency":    "GBP",
				"note":        "from-json",
			},
		},
		{
			name:  "absent optional flag omits property",
			base:  `{"customer_id":1,"currency":"USD"}`,
			flags: map[string]string{},
			want:  map[string]any{"customer_id": json.Number("1"), "currency": "USD"},
		},
		{
			name:  "boolean true/false",
			base:  "",
			flags: map[string]string{"active": "true"},
			want:  map[string]any{"active": true},
		},
		{
			name:  "boolean false",
			base:  `{"active":true}`,
			flags: map[string]string{"active": "false"},
			want:  map[string]any{"active": false},
		},
		{
			name:  "number flag",
			base:  "",
			flags: map[string]string{"rate": "1.5"},
			want:  map[string]any{"rate": json.Number("1.5")},
		},
		{
			name:  "string enum accepted",
			base:  "",
			flags: map[string]string{"status": "open"},
			want:  map[string]any{"status": "open"},
		},
		{
			name:  "integer enum accepted",
			base:  "",
			flags: map[string]string{"priority": "2"},
			want:  map[string]any{"priority": int64(2)},
		},
		{
			name:  "json-only nested stays from base",
			base:  `{"meta":{"a":1},"tags":["x"]}`,
			flags: map[string]string{"currency": "USD"},
			want:  map[string]any{"meta": map[string]any{"a": json.Number("1")}, "tags": []any{"x"}, "currency": "USD"},
		},
		// rejects
		{
			name:    "unknown flag",
			base:    "",
			flags:   map[string]string{"not-a-field": "1"},
			wantErr: ErrUnknownFlag,
		},
		{
			name:    "flag targeting object json-only",
			base:    "",
			flags:   map[string]string{"meta": `{"a":1}`},
			wantErr: ErrJSONOnlyFlag,
		},
		{
			name:    "flag targeting array json-only",
			base:    "",
			flags:   map[string]string{"line-items": `[]`},
			wantErr: ErrJSONOnlyFlag,
		},
		{
			name:    "flag targeting tags array json-only",
			base:    "",
			flags:   map[string]string{"tags": "a,b"},
			wantErr: ErrJSONOnlyFlag,
		},
		{
			name:    "invalid integer",
			base:    "",
			flags:   map[string]string{"customer-id": "abc"},
			wantErr: ErrInvalidScalar,
		},
		{
			name:    "invalid number",
			base:    "",
			flags:   map[string]string{"rate": "nope"},
			wantErr: ErrInvalidScalar,
		},
		{
			name:    "invalid boolean",
			base:    "",
			flags:   map[string]string{"active": "yes"},
			wantErr: ErrInvalidScalar,
		},
		{
			name:    "enum not in list",
			base:    "",
			flags:   map[string]string{"status": "void"},
			wantErr: ErrInvalidScalar,
		},
		{
			name:    "integer enum not in list",
			base:    "",
			flags:   map[string]string{"priority": "9"},
			wantErr: ErrInvalidScalar,
		},
		{
			name:    "invalid base JSON",
			base:    `{`,
			flags:   nil,
			wantErr: ErrInvalidBaseJSON,
		},
	}

	for _, tc := range rows {
		t.Run(tc.name, func(t *testing.T) {
			var base []byte
			if tc.base != "" {
				base = []byte(tc.base)
			}
			got, err := s.Merge(base, tc.flags)
			if tc.wantErr != nil {
				if err == nil {
					t.Fatalf("expected error %v, got nil (result=%v)", tc.wantErr, got)
				}
				if !errors.Is(err, tc.wantErr) {
					t.Fatalf("error=%v want Is(%v)", err, tc.wantErr)
				}
				return
			}
			if err != nil {
				t.Fatalf("unexpected error: %v", err)
			}
			if !reflect.DeepEqual(got, tc.want) {
				// dump both for debugging
				gb, _ := json.Marshal(got)
				wb, _ := json.Marshal(tc.want)
				t.Fatalf("got %s\nwant %s", gb, wb)
			}
		})
	}
}

func TestMerge_rejectsSnakeCaseAlias(t *testing.T) {
	// v1: kebab is canonical; snake_case aliases not accepted
	s, err := FromJSONSchema([]byte(fixtureSchema))
	if err != nil {
		t.Fatal(err)
	}
	_, err = s.Merge(nil, map[string]string{"customer_id": "1"})
	if !errors.Is(err, ErrUnknownFlag) {
		t.Fatalf("snake_case flag should be unknown in v1, got %v", err)
	}
}

func TestFromJSONSchema_nonPlainUnionIsJSONOnly(t *testing.T) {
	schema := `{
      "type": "object",
      "properties": {
        "plain": { "type": "string" },
        "union": { "type": ["string", "object"] },
        "nullable_string": { "type": ["string", "null"] }
      }
    }`
	s, err := FromJSONSchema([]byte(schema))
	if err != nil {
		t.Fatal(err)
	}
	byName := map[string]Field{}
	for _, f := range s.Fields {
		byName[f.Name] = f
	}
	if byName["plain"].Pass != PassFlag {
		t.Error("plain should be flag")
	}
	if byName["union"].Pass != PassJSONOnly {
		t.Error("multi-type union should be json-only")
	}
	// nullable scalar still flag-eligible
	if byName["nullable_string"].Pass != PassFlag {
		t.Error("string|null should still be a flag")
	}
}

func TestFromJSONSchema_emptyProperties(t *testing.T) {
	s, err := FromJSONSchema([]byte(`{"type":"object"}`))
	if err != nil {
		t.Fatal(err)
	}
	if len(s.Fields) != 0 {
		t.Fatalf("expected no fields, got %v", s.Fields)
	}
	got, err := s.Merge([]byte(`{"x":1}`), nil)
	if err != nil {
		t.Fatal(err)
	}
	// base is still merged as object even without declared fields
	if !reflect.DeepEqual(got, map[string]any{"x": json.Number("1")}) {
		t.Fatalf("got %#v", got)
	}
}

func fieldNames(fs []Field) []string {
	out := make([]string, len(fs))
	for i, f := range fs {
		out[i] = f.Name
	}
	sort.Strings(out)
	return out
}

// Two properties that kebab-case to the same flag must not silently shadow
// each other (winner would depend on map iteration order). Both are demoted
// to json-only and the shared flag is rejected as ambiguous.
func TestFromJSONSchema_kebabCollisionIsAmbiguous(t *testing.T) {
	const collide = `{
	  "type": "object",
	  "properties": {
	    "customer_id": { "type": "integer" },
	    "customer-id": { "type": "string" },
	    "note": { "type": "string" }
	  }
	}`
	for i := 0; i < 20; i++ {
		s, err := FromJSONSchema([]byte(collide))
		if err != nil {
			t.Fatal(err)
		}
		for _, name := range []string{"customer_id", "customer-id"} {
			f, ok := s.LookupName(name)
			if !ok {
				t.Fatalf("missing field %s", name)
			}
			if f.Pass != PassJSONOnly {
				t.Fatalf("%s: Pass=%q want json-only", name, f.Pass)
			}
		}
		if _, ok := s.LookupFlag("customer-id"); ok {
			t.Fatal("ambiguous flag must not resolve to a field")
		}
		if f, ok := s.LookupFlag("note"); !ok || f.Pass != PassFlag {
			t.Fatal("non-colliding flag must stay a flag")
		}

		_, err = s.Merge(nil, map[string]string{"customer-id": "5"})
		if !errors.Is(err, ErrAmbiguousFlag) {
			t.Fatalf("err=%v want ErrAmbiguousFlag", err)
		}
		if want := `ambiguous flag: --customer-id (properties "customer-id", "customer_id"; pass via --input/--input-file)`; err.Error() != want {
			t.Fatalf("err=%q want %q", err.Error(), want)
		}

		got, err := s.Merge([]byte(`{"customer_id":5,"customer-id":"x"}`), map[string]string{"note": "hi"})
		if err != nil {
			t.Fatal(err)
		}
		want := map[string]any{"customer_id": json.Number("5"), "customer-id": "x", "note": "hi"}
		if !reflect.DeepEqual(got, want) {
			t.Fatalf("got %v want %v", got, want)
		}
	}
}

func TestFromJSONSchema_emptyAndInvalidInput(t *testing.T) {
	s, err := FromJSONSchema(nil)
	if err != nil || len(s.Fields) != 0 {
		t.Fatalf("empty schema: %+v %v", s, err)
	}
	if _, ok := s.LookupFlag("anything"); ok {
		t.Fatal("empty schema must know no flags")
	}
	if _, err := FromJSONSchema([]byte(`{`)); !errors.Is(err, ErrInvalidSchema) {
		t.Fatalf("want ErrInvalidSchema, got %v", err)
	}
	s, err = FromSchemaMap(nil)
	if err != nil || len(s.Fields) != 0 {
		t.Fatalf("nil map: %+v %v", s, err)
	}
}

func TestFromJSONSchema_classifiesCompositesEnumsAndUnions(t *testing.T) {
	s, err := FromJSONSchema([]byte(`{
		"type": "object",
		"properties": {
			"choice":   {"type": "string", "oneOf": [{"const": "a"}]},
			"shape":    {"enum": [{"x": 1}, "flat"]},
			"nullable": {"enum": ["a", null], "type": ["string", "null"]},
			"flag":     {"enum": [true, false]},
			"ratio":    {"enum": [0.5, 1.5]},
			"blank":    {"enum": [null, "x"]},
			"nothing":  {"type": ["null"]},
			"loose":    {"description": "no type"},
			"dup":      {"type": ["string", "string", 7]},
			"bad":      "not-a-schema"
		}
	}`))
	if err != nil {
		t.Fatal(err)
	}
	want := map[string]struct {
		typ  string
		pass PassMode
	}{
		"choice":   {"oneOf", PassJSONOnly},
		"shape":    {"enum", PassJSONOnly},
		"nullable": {"string", PassFlag},
		"flag":     {"boolean", PassFlag},
		"ratio":    {"number", PassFlag},
		"blank":    {"enum", PassFlag},
		"nothing":  {"null", PassJSONOnly},
		"loose":    {"unknown", PassJSONOnly},
		"dup":      {"string", PassFlag},
	}
	for name, w := range want {
		f, ok := s.LookupName(name)
		if !ok || f.Type != w.typ || f.Pass != w.pass {
			t.Fatalf("%s: want %s/%s, got %+v", name, w.typ, w.pass, f)
		}
	}
	if _, ok := s.LookupName("bad"); ok {
		t.Fatal("non-object property schema must be skipped")
	}
}

func TestSchema_nilReceiverLookupsAndMerge(t *testing.T) {
	var s *Schema
	if _, ok := s.LookupFlag("x"); ok {
		t.Fatal("nil LookupFlag")
	}
	if _, ok := s.LookupName("x"); ok {
		t.Fatal("nil LookupName")
	}
	got, err := s.Merge([]byte(`{"a":1}`), nil)
	if err != nil || !reflect.DeepEqual(got, map[string]any{"a": json.Number("1")}) {
		t.Fatalf("nil schema keeps base: %v %v", got, err)
	}
	if _, err := s.Merge(nil, map[string]string{"a": "1"}); !errors.Is(err, ErrUnknownFlag) {
		t.Fatalf("nil schema knows no flags: %v", err)
	}
}

func TestMergeJSON_preservesIntegersAbove2To53(t *testing.T) {
	s, err := FromJSONSchema([]byte(fixtureSchema))
	if err != nil {
		t.Fatal(err)
	}
	const big = "9007199254740993"
	got, err := s.MergeJSON([]byte(`{"customer_id":`+big+`,"meta":{"nested":`+big+`},"line_items":[{"qty":`+big+`}]}`), nil)
	if err != nil {
		t.Fatal(err)
	}
	raw := string(got)
	if !strings.Contains(raw, big) {
		t.Fatalf("integer above 2^53 was rewritten: %s", raw)
	}
	if strings.Contains(raw, "9007199254740992") {
		t.Fatalf("integer rounded through float64: %s", raw)
	}

	flagged, err := s.MergeJSON(nil, map[string]string{"rate": big})
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(flagged), big) {
		t.Fatalf("number flag rounded through float64: %s", flagged)
	}
}

func TestMerge_baseMustBeAJSONObject(t *testing.T) {
	s, _ := FromJSONSchema([]byte(fixtureSchema))
	for _, base := range []string{`[1,2]`, `"text"`, `{`} {
		if _, err := s.Merge([]byte(base), nil); !errors.Is(err, ErrInvalidBaseJSON) {
			t.Fatalf("base %s: want ErrInvalidBaseJSON, got %v", base, err)
		}
	}
}

func TestMerge_enumFlagsParseToTheMatchingEnumValue(t *testing.T) {
	s, err := FromJSONSchema([]byte(`{
		"type": "object",
		"properties": {
			"on":    {"enum": [true]},
			"off":   {"enum": [false]},
			"ratio": {"enum": [0.5, 2]},
			"maybe": {"enum": ["x", null]}
		}
	}`))
	if err != nil {
		t.Fatal(err)
	}
	cases := []struct {
		flag, raw string
		want      any
	}{
		{"on", "", true},
		{"on", "true", true},
		{"off", "false", false},
		{"ratio", "0.5", 0.5},
		{"ratio", "2", int64(2)},
		{"maybe", "null", nil},
	}
	for _, tc := range cases {
		got, err := s.Merge(nil, map[string]string{tc.flag: tc.raw})
		if err != nil {
			t.Fatalf("--%s=%s: %v", tc.flag, tc.raw, err)
		}
		if v, ok := got[tc.flag]; !ok || v != tc.want {
			t.Fatalf("--%s=%s: got %#v want %#v", tc.flag, tc.raw, got[tc.flag], tc.want)
		}
	}
	for _, bad := range []map[string]string{{"on": "false"}, {"ratio": "3"}, {"maybe": "y"}} {
		if _, err := s.Merge(nil, bad); !errors.Is(err, ErrInvalidScalar) {
			t.Fatalf("%v: want ErrInvalidScalar, got %v", bad, err)
		}
	}
}
