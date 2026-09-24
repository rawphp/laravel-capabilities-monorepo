package flagschema

import (
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"reflect"
	"sort"
	"strings"
	"testing"
)

// testdata/snapshots/*.schema.json are D-020 SchemaSnapshot documents
// ({"input_schema": …, "output_schema": …}) exported by the server's
// CapabilityData::jsonSchema() + SchemaSnapshot::document(). They carry real
// wire shapes the inline fixtures do not: $schema, additionalProperties:false,
// nullable unions, nested/array DTOs, int|float numerics, and the empty-DTO
// `"properties": []` list. When the server's schema export changes shape,
// regenerate these files; the tables below then show what the CLI does with it.

type snapshotFieldWant struct {
	flag     string
	pass     PassMode
	typ      string // asserted for PassFlag fields only (drives scalar parsing)
	required bool
}

var snapshotWants = map[string]map[string]snapshotFieldWant{
	"create-invoice": {
		"customer_id":     {flag: "customer-id", pass: PassFlag, typ: "integer", required: true},
		"amount_cents":    {flag: "amount-cents", pass: PassFlag, typ: "integer", required: true},
		"currency":        {flag: "currency", pass: PassFlag, typ: "string", required: true},
		"line_items":      {flag: "line-items", pass: PassJSONOnly, required: true},
		"due_on":          {flag: "due-on", pass: PassFlag, typ: "string"},
		"note":            {flag: "note", pass: PassFlag, typ: "string"},
		"send_email":      {flag: "send-email", pass: PassFlag, typ: "boolean"},
		"discount_rate":   {flag: "discount-rate", pass: PassFlag, typ: "number"},
		"billing_address": {flag: "billing-address", pass: PassJSONOnly},
		"metadata":        {flag: "metadata", pass: PassJSONOnly},
		"reference":       {flag: "reference", pass: PassJSONOnly},
	},
	"void-subscription": {
		"subscription_id": {flag: "subscription-id", pass: PassFlag, typ: "string", required: true},
		"reason":          {flag: "reason", pass: PassFlag, typ: "string", required: true},
		"priority":        {flag: "priority", pass: PassFlag, typ: "integer"},
		"immediate":       {flag: "immediate", pass: PassFlag, typ: "boolean"},
	},
	"list-plans": {},
}

func loadSnapshotInputSchema(t *testing.T, capability string) []byte {
	t.Helper()
	raw, err := os.ReadFile(filepath.Join("testdata", "snapshots", capability+".schema.json"))
	if err != nil {
		t.Fatal(err)
	}
	var doc map[string]json.RawMessage
	if err := json.Unmarshal(raw, &doc); err != nil {
		t.Fatalf("%s: %v", capability, err)
	}
	for _, side := range []string{"input_schema", "output_schema"} {
		if _, ok := doc[side]; !ok {
			t.Fatalf("%s: snapshot missing %q (D-020 envelope)", capability, side)
		}
	}
	return doc["input_schema"]
}

func TestSnapshotFixtures_everyFixtureHasExpectations(t *testing.T) {
	paths, err := filepath.Glob(filepath.Join("testdata", "snapshots", "*.schema.json"))
	if err != nil {
		t.Fatal(err)
	}
	var onDisk []string
	for _, p := range paths {
		onDisk = append(onDisk, strings.TrimSuffix(filepath.Base(p), ".schema.json"))
	}
	var wanted []string
	for name := range snapshotWants {
		wanted = append(wanted, name)
	}
	sort.Strings(onDisk)
	sort.Strings(wanted)
	if !reflect.DeepEqual(onDisk, wanted) {
		t.Fatalf("snapshot fixtures %v != expectation tables %v", onDisk, wanted)
	}
}

func TestSnapshotFixtures_fieldModel(t *testing.T) {
	for capability, wants := range snapshotWants {
		t.Run(capability, func(t *testing.T) {
			s, err := FromJSONSchema(loadSnapshotInputSchema(t, capability))
			if err != nil {
				t.Fatal(err)
			}
			if len(s.Fields) != len(wants) {
				t.Fatalf("fields %v, want %d (every exported property needs an expectation)", fieldNames(s.Fields), len(wants))
			}
			for name, want := range wants {
				f, ok := s.LookupName(name)
				if !ok {
					t.Errorf("missing field %q", name)
					continue
				}
				if f.FlagName != want.flag || f.Pass != want.pass || f.Required != want.required {
					t.Errorf("%s: flag=%q pass=%q required=%v; want flag=%q pass=%q required=%v",
						name, f.FlagName, f.Pass, f.Required, want.flag, want.pass, want.required)
				}
				if want.pass == PassFlag && f.Type != want.typ {
					t.Errorf("%s: Type=%q want %q", name, f.Type, want.typ)
				}
			}
		})
	}
}

func TestSnapshotFixtures_merge(t *testing.T) {
	cases := []struct {
		name       string
		capability string
		base       string
		flags      map[string]string
		want       string
		wantErr    error
	}{
		{
			name:       "flags fill scalars around json-only base",
			capability: "create-invoice",
			base:       `{"line_items":[{"description":"Setup","quantity":1,"unit_cents":500}],"note":"from json"}`,
			flags: map[string]string{
				"customer-id": "42", "amount-cents": "500", "currency": "AUD",
				"discount-rate": "0.1", "send-email": "false", "note": "from flag", "due-on": "2026-10-01",
			},
			want: `{"customer_id":42,"amount_cents":500,"currency":"AUD","discount_rate":0.1,"send_email":false,
				"note":"from flag","due_on":"2026-10-01","line_items":[{"description":"Setup","quantity":1,"unit_cents":500}]}`,
		},
		{
			name:       "int|float accepts a whole number",
			capability: "create-invoice",
			flags:      map[string]string{"discount-rate": "2"},
			want:       `{"discount_rate":2}`,
		},
		{name: "enum rejects value outside export", capability: "create-invoice", flags: map[string]string{"currency": "EUR"}, wantErr: ErrInvalidScalar},
		{name: "nullable nested DTO is json-only", capability: "create-invoice", flags: map[string]string{"billing-address": "x"}, wantErr: ErrJSONOnlyFlag},
		{name: "string|int union is json-only", capability: "create-invoice", flags: map[string]string{"reference": "INV-1"}, wantErr: ErrJSONOnlyFlag},
		{name: "snake_case property name is not a flag", capability: "create-invoice", flags: map[string]string{"customer_id": "42"}, wantErr: ErrUnknownFlag},
		{
			name:       "integer enum + bare boolean",
			capability: "void-subscription",
			flags:      map[string]string{"subscription-id": "sub_1", "reason": "fraud", "priority": "3", "immediate": ""},
			want:       `{"subscription_id":"sub_1","reason":"fraud","priority":3,"immediate":true}`,
		},
		{name: "integer enum rejects value outside export", capability: "void-subscription", flags: map[string]string{"priority": "4"}, wantErr: ErrInvalidScalar},
		{name: "empty DTO (properties: []) takes no flags", capability: "list-plans", want: `{}`},
		{name: "empty DTO rejects any flag", capability: "list-plans", flags: map[string]string{"plan": "pro"}, wantErr: ErrUnknownFlag},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			s, err := FromJSONSchema(loadSnapshotInputSchema(t, tc.capability))
			if err != nil {
				t.Fatal(err)
			}
			got, err := s.MergeJSON([]byte(tc.base), tc.flags)
			if tc.wantErr != nil {
				if !errors.Is(err, tc.wantErr) {
					t.Fatalf("err=%v want %v", err, tc.wantErr)
				}
				return
			}
			if err != nil {
				t.Fatal(err)
			}
			var gotV, wantV any
			if err := json.Unmarshal(got, &gotV); err != nil {
				t.Fatal(err)
			}
			if err := json.Unmarshal([]byte(tc.want), &wantV); err != nil {
				t.Fatal(err)
			}
			if !reflect.DeepEqual(gotV, wantV) {
				t.Fatalf("merged %s want %s", got, tc.want)
			}
		})
	}
}
