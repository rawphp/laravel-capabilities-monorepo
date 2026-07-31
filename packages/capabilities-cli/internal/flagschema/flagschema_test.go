package flagschema

import (
	"encoding/json"
	"errors"
	"reflect"
	"sort"
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
			want:  map[string]any{"customer_id": float64(1), "currency": "USD"},
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
			want:  map[string]any{"customer_id": float64(1), "currency": "USD"},
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
			want:  map[string]any{"rate": 1.5},
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
			name:    "json-only nested stays from base",
			base:    `{"meta":{"a":1},"tags":["x"]}`,
			flags:   map[string]string{"currency": "USD"},
			want:    map[string]any{"meta": map[string]any{"a": float64(1)}, "tags": []any{"x"}, "currency": "USD"},
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
	if !reflect.DeepEqual(got, map[string]any{"x": float64(1)}) {
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
