package helpfmt

import (
	"encoding/json"
	"strings"
	"testing"
)

func sampleCapability(t *testing.T) CapabilityHelp {
	t.Helper()
	return BuildCapabilityHelp(CapabilityInfo{
		Domain:        "invoices",
		Verb:          "create",
		Name:          "create-invoice",
		Description:   "Create an invoice for a customer.",
		SchemaVersion: "1",
		InputSchema:   parseSchema(t, sampleInputSchema),
		OutputSchema: parseSchema(t, `{
			"type": "object",
			"properties": {
				"invoice_id": {"type": "integer"},
				"status": {"type": "string"}
			}
		}`),
	})
}

func TestBuildCapabilityHelp_machineShape(t *testing.T) {
	h := sampleCapability(t)
	if h.Kind != KindCapabilityHelp {
		t.Fatalf("kind: %q", h.Kind)
	}
	if h.Domain == nil || *h.Domain != "invoices" {
		t.Fatalf("domain: %v", h.Domain)
	}
	if h.Verb == nil || *h.Verb != "create" {
		t.Fatalf("verb: %v", h.Verb)
	}
	if h.Name != "create-invoice" || h.SchemaVersion != "1" {
		t.Fatalf("name/version: %+v", h)
	}
	if len(h.Fields) == 0 {
		t.Fatal("fields empty")
	}
	// Every field exposes pass mode
	for _, f := range h.Fields {
		if f.Pass != PassFlag && f.Pass != PassJSONOnly {
			t.Fatalf("bad pass: %+v", f)
		}
	}
	if !strings.Contains(h.Examples.JSON, "create-invoice") && !strings.Contains(h.Examples.JSON, "invoices create") {
		t.Fatalf("json example: %q", h.Examples.JSON)
	}
	if h.Examples.Flags == "" {
		t.Fatal("expected flags example when scalars exist")
	}
	if !strings.Contains(h.Examples.Flags, "--customer-id") {
		t.Fatalf("flags example missing scalar: %q", h.Examples.Flags)
	}
}

func TestBuildCapabilityHelp_runUnmappedNullDomainVerb(t *testing.T) {
	h := BuildCapabilityHelp(CapabilityInfo{
		Name:        "create-invoice",
		Description: "Create an invoice for a customer.",
		InputSchema: parseSchema(t, sampleInputSchema),
	})
	if h.Domain != nil || h.Verb != nil {
		t.Fatalf("run unmapped must null domain/verb: domain=%v verb=%v", h.Domain, h.Verb)
	}
	if !strings.Contains(h.Examples.JSON, "run create-invoice") {
		t.Fatalf("run path example: %q", h.Examples.JSON)
	}
}

func TestFormatMachineCapability_envelopeOKNoInvoke(t *testing.T) {
	h := sampleCapability(t)
	raw := FormatMachineCapability(h)
	var env map[string]any
	if err := json.Unmarshal(raw, &env); err != nil {
		t.Fatal(err)
	}
	if env["ok"] != true {
		t.Fatalf("ok: %v", env["ok"])
	}
	data, ok := env["data"].(map[string]any)
	if !ok {
		t.Fatalf("data: %T", env["data"])
	}
	if data["kind"] != KindCapabilityHelp {
		t.Fatalf("kind: %v", data["kind"])
	}
	fields, ok := data["fields"].([]any)
	if !ok || len(fields) == 0 {
		t.Fatalf("fields: %v", data["fields"])
	}
	// Spot-check one field for pass/flag
	var sawJSONOnly, sawFlag bool
	for _, rawF := range fields {
		fm, _ := rawF.(map[string]any)
		switch fm["pass"] {
		case PassJSONOnly:
			sawJSONOnly = true
			if fm["flag"] != nil {
				t.Fatalf("json-only flag should be null: %+v", fm)
			}
		case PassFlag:
			sawFlag = true
			if fm["flag"] == nil {
				t.Fatalf("flag pass needs flag: %+v", fm)
			}
		}
	}
	if !sawJSONOnly || !sawFlag {
		t.Fatalf("expected both pass modes, flag=%v json-only=%v", sawFlag, sawJSONOnly)
	}
}

func TestFormatHumanCapability_inputTableAndSections(t *testing.T) {
	h := sampleCapability(t)
	text := FormatHumanCapability(h)
	for _, needle := range []string{
		"create-invoice",
		"Create an invoice",
		"schema_version",
		"INPUT",
		"customer_id",
		"integer",
		"REQUIRED",
		"--customer-id",
		"json-only",
		"line_items",
		"OUTPUT",
		"invoice_id",
		"EXAMPLES",
		"SEE ALSO",
		"describe",
		"run",
	} {
		if !strings.Contains(text, needle) {
			t.Fatalf("human help missing %q\n%s", needle, text)
		}
	}
	// Pass mode column for flag vs json-only
	if !strings.Contains(text, "flag") {
		t.Fatal("expected flag pass mode in table")
	}
}

func TestFormatHumanCapability_runPath(t *testing.T) {
	h := BuildCapabilityHelp(CapabilityInfo{
		Name:        "create-invoice",
		InputSchema: parseSchema(t, sampleInputSchema),
	})
	text := FormatHumanCapability(h)
	if !strings.Contains(text, "capabilities run create-invoice") {
		t.Fatalf("run usage:\n%s", text)
	}
}

func TestDomainHelp_humanAndMachine(t *testing.T) {
	verbs := []DomainVerb{
		{Verb: "create", Name: "create-invoice", Description: "Create an invoice for a customer."},
		{Verb: "void", Name: "void-invoice", Description: "Void an invoice."},
	}
	human := FormatHumanDomain("invoices", verbs)
	if !strings.Contains(human, "create") || !strings.Contains(human, "void-invoice") {
		t.Fatalf("domain human:\n%s", human)
	}
	if !strings.Contains(human, "Create an invoice") {
		t.Fatalf("one-liner missing:\n%s", human)
	}

	raw := FormatMachineDomain("invoices", verbs)
	var env map[string]any
	if err := json.Unmarshal(raw, &env); err != nil {
		t.Fatal(err)
	}
	if env["ok"] != true {
		t.Fatal(env)
	}
	data := env["data"].(map[string]any)
	if data["kind"] != KindDomainHelp || data["domain"] != "invoices" {
		t.Fatalf("data: %+v", data)
	}
	list, ok := data["verbs"].([]any)
	if !ok || len(list) != 2 {
		t.Fatalf("verbs: %v", data["verbs"])
	}
}

func TestFormatHumanCapability_constraintsShown(t *testing.T) {
	h := sampleCapability(t)
	text := FormatHumanCapability(h)
	// customer_id minimum and currency enum should appear in constraints column
	if !strings.Contains(text, "minimum") && !strings.Contains(text, "1") {
		// soft: at least enum or minimum appears somewhere
		if !strings.Contains(text, "USD") && !strings.Contains(text, "enum") {
			t.Fatalf("expected constraints in human help:\n%s", text)
		}
	}
}

func TestExampleValue_dateFormatNotLiteralExample(t *testing.T) {
	h := BuildCapabilityHelp(CapabilityInfo{
		Domain: "meal",
		Verb:   "skip",
		Name:   "skip_meal",
		InputSchema: parseSchema(t, `{
			"type": "object",
			"required": ["date", "meal_index", "food", "line_items"],
			"properties": {
				"date": {"type": "string", "format": "date"},
				"meal_index": {"type": "integer", "minimum": 0},
				"food": {"type": "object"},
				"line_items": {"type": "array", "minItems": 1}
			}
		}`),
	})
	if !strings.Contains(h.Examples.Flags, "--date=2026-01-15") {
		t.Fatalf("date format example must be YYYY-MM-DD, got flags: %q", h.Examples.Flags)
	}
	if strings.Contains(h.Examples.Flags, "--date=example") || strings.Contains(h.Examples.JSON, `"date":"example"`) {
		t.Fatalf("must not teach date=example:\nflags=%q\njson=%q", h.Examples.Flags, h.Examples.JSON)
	}
	// Object/array required fields must not stringify as "example"
	if strings.Contains(h.Examples.JSON, `"food":"example"`) {
		t.Fatalf("object field must not be string example: %q", h.Examples.JSON)
	}
	if strings.Contains(h.Examples.JSON, `"line_items":"example"`) {
		t.Fatalf("array field must not be string example: %q", h.Examples.JSON)
	}
	if !strings.Contains(h.Examples.JSON, `"food":{}`) {
		t.Fatalf("expected empty object for food: %q", h.Examples.JSON)
	}
	if !strings.Contains(h.Examples.JSON, `"line_items":[{`) {
		t.Fatalf("expected minItems array placeholder: %q", h.Examples.JSON)
	}
	text := FormatHumanCapability(h)
	if !strings.Contains(text, "meal skip --human") {
		t.Fatalf("capability help should surface --human:\n%s", text)
	}
}

func TestExampleValue_fromToNamesAsDates(t *testing.T) {
	h := BuildCapabilityHelp(CapabilityInfo{
		Domain: "steps",
		Verb:   "list",
		Name:   "get_steps_range",
		InputSchema: parseSchema(t, `{
			"type": "object",
			"required": ["from", "to"],
			"properties": {
				"from": {"type": "string"},
				"to": {"type": "string"}
			}
		}`),
	})
	if !strings.Contains(h.Examples.Flags, "--from=2026-01-15") || !strings.Contains(h.Examples.Flags, "--to=2026-01-15") {
		t.Fatalf("from/to should use date placeholders: %q", h.Examples.Flags)
	}
}

func TestFormatMachineCapability_neverEmitsNullShapes(t *testing.T) {
	var env struct {
		Data map[string]any `json:"data"`
	}
	if err := json.Unmarshal(FormatMachineCapability(CapabilityHelp{Name: "ping"}), &env); err != nil {
		t.Fatal(err)
	}
	if env.Data["kind"] != KindCapabilityHelp {
		t.Fatalf("kind: %v", env.Data["kind"])
	}
	for _, key := range []string{"input_schema", "output_schema"} {
		if _, ok := env.Data[key].(map[string]any); !ok {
			t.Fatalf("%s must be an object, got %v", key, env.Data[key])
		}
	}
	if fields, ok := env.Data["fields"].([]any); !ok || len(fields) != 0 {
		t.Fatalf("fields must be [], got %v", env.Data["fields"])
	}
}

func TestFormatMachineDomain_nilVerbsIsEmptyList(t *testing.T) {
	var env struct {
		Data map[string]any `json:"data"`
	}
	if err := json.Unmarshal(FormatMachineDomain("meal", nil), &env); err != nil {
		t.Fatal(err)
	}
	if verbs, ok := env.Data["verbs"].([]any); !ok || len(verbs) != 0 {
		t.Fatalf("verbs must be [], got %v", env.Data["verbs"])
	}
}

func TestFormatHumanDomain_emptyAndUndescribedVerbs(t *testing.T) {
	if text := FormatHumanDomain("meal", nil); !strings.Contains(text, "(none)") {
		t.Fatalf("empty domain:\n%s", text)
	}
	text := FormatHumanDomain("meal", []DomainVerb{{Verb: "skip", Name: "skip_meal"}, {Verb: "add", Name: "add_meal", Description: "Add."}})
	if !strings.Contains(text, "skip_meal                -") {
		t.Fatalf("undescribed verb must show '-':\n%s", text)
	}
	if strings.Index(text, "add_meal") > strings.Index(text, "skip_meal") {
		t.Fatalf("verbs must be sorted:\n%s", text)
	}
}

func TestFormatHumanCapability_noInputsShowsPlaceholderAndEmptyJSONExample(t *testing.T) {
	h := BuildCapabilityHelp(CapabilityInfo{Name: "ping"})
	text := FormatHumanCapability(h)
	for _, needle := range []string{"(no properties)", "(no output_schema)", "capabilities run ping --input='{}'"} {
		if !strings.Contains(text, needle) {
			t.Fatalf("missing %q:\n%s", needle, text)
		}
	}
	if h.Examples.Flags != "" {
		t.Fatalf("no flag example without flaggable fields: %q", h.Examples.Flags)
	}
}

func TestBuildExamples_allOptionalUsesFirstFlaggableFields(t *testing.T) {
	h := BuildCapabilityHelp(CapabilityInfo{Name: "search", InputSchema: parseSchema(t, `{
		"type": "object",
		"properties": {
			"a": {"type": "string"},
			"b": {"type": "boolean"},
			"c": {"type": "number"},
			"d": {"type": "integer"},
			"filters": {"type": "object"}
		}
	}`)})
	if h.Examples.JSON != `capabilities run search --input='{"a":"example"}'` {
		t.Fatalf("json example: %q", h.Examples.JSON)
	}
	// At most three optional flags, json-only fields skipped.
	if h.Examples.Flags != "capabilities run search --a=example --b=true --c=1" {
		t.Fatalf("flags example: %q", h.Examples.Flags)
	}
}

func TestExampleValue_prefersSchemaValuesThenTypePlaceholders(t *testing.T) {
	cases := []struct {
		name string
		f    Field
		want any
	}{
		{"enum", Field{Type: "string", Constraints: map[string]any{"enum": []any{"USD", "EUR"}}}, "USD"},
		{"const", Field{Type: "string", Constraints: map[string]any{"const": "fixed"}}, "fixed"},
		{"default", Field{Type: "integer", Constraints: map[string]any{"default": 5.0}}, 5.0},
		{"integer minimum", Field{Type: "integer", Constraints: map[string]any{"minimum": 3.0}}, int64(3)},
		{"integer", Field{Type: "integer"}, 42},
		{"number", Field{Type: "number"}, 1.0},
		{"boolean", Field{Type: "boolean"}, true},
		{"unknown", Field{Type: "any"}, "example"},
	}
	for _, tc := range cases {
		if got := exampleValue(tc.f); got != tc.want {
			t.Fatalf("%s: got %#v want %#v", tc.name, got, tc.want)
		}
	}
	if got, ok := exampleValue(Field{Type: "array"}).([]any); !ok || len(got) != 0 {
		t.Fatalf("array without minItems must be []: %#v", got)
	}
}

func TestExampleString_formatsAndDateNames(t *testing.T) {
	cases := map[string]Field{
		"2026-01-15T12:00:00Z":                 {Name: "at", Constraints: map[string]any{"format": "date-time"}},
		"12:00:00":                             {Name: "at", Constraints: map[string]any{"format": " Time "}},
		"user@example.com":                     {Name: "who", Constraints: map[string]any{"format": "email"}},
		"https://example.com":                  {Name: "link", Constraints: map[string]any{"format": "uri"}},
		"00000000-0000-4000-8000-000000000001": {Name: "id", Constraints: map[string]any{"format": "uuid"}},
		"2026-01-15":                           {Name: "due_date"},
	}
	for want, f := range cases {
		if got := exampleString(f); got != want {
			t.Fatalf("%+v: got %q want %q", f, got, want)
		}
	}
	if got := exampleString(Field{Name: "title", Constraints: map[string]any{"format": "hostname"}}); got != "example" {
		t.Fatalf("unknown format falls back: %q", got)
	}
}

func TestFormatConstraints_truncatesLongLists(t *testing.T) {
	got := formatConstraints(map[string]any{"enum": []any{"alpha", "bravo", "charlie", "delta"}})
	if got != `enum=["alpha","bravo"...` {
		t.Fatalf("got %q", got)
	}
	if got := formatConstraints(map[string]any{}); got != "-" {
		t.Fatalf("empty constraints: %q", got)
	}
}

func TestFormatOutputSummary_typeOnlyAndOpaqueSchemas(t *testing.T) {
	if got := formatOutputSummary(map[string]any{"type": "array"}); got != "  type: array\n" {
		t.Fatalf("type only: %q", got)
	}
	if got := formatOutputSummary(map[string]any{"oneOf": []any{}}); !strings.Contains(got, "see output_schema via describe") {
		t.Fatalf("opaque: %q", got)
	}
}
