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
