package catalog

import (
	"encoding/json"
	"strings"
	"testing"
)

func TestEnrichSummariesAddsMappedCommand(t *testing.T) {
	list := []CapabilitySummary{
		{
			Name:        "create-invoice",
			Description: "Create",
			CLI:         &CLIMeta{Domain: "invoices", Verb: "create"},
			Surfaces:    []string{"cli", "http"},
		},
		{
			Name: "invoices.list", // mechanical split
		},
		{
			Name: "kebab-only", // unmapped
		},
	}
	out := EnrichSummaries(list)
	if len(out) != 3 {
		t.Fatalf("len=%d", len(out))
	}
	if out[0].MappedCommand != "invoices create" {
		t.Fatalf("mapped=%q", out[0].MappedCommand)
	}
	if out[0].CLI == nil || out[0].CLI.Domain != "invoices" || out[0].CLI.Verb != "create" {
		t.Fatalf("cli=%#v", out[0].CLI)
	}
	if out[1].MappedCommand != "invoices list" {
		t.Fatalf("mechanical mapped=%q", out[1].MappedCommand)
	}
	if out[2].MappedCommand != "" {
		t.Fatalf("kebab should stay unmapped, got %q", out[2].MappedCommand)
	}
}

func TestEnrichSummariesCollisionSetsMappingError(t *testing.T) {
	list := []CapabilitySummary{
		{Name: "a", CLI: &CLIMeta{Domain: "invoices", Verb: "create"}},
		{Name: "b", CLI: &CLIMeta{Domain: "invoices", Verb: "create"}},
	}
	out := EnrichSummaries(list)
	for _, row := range out {
		if row.MappingError != "collision" {
			t.Fatalf("%s MappingError=%q want collision", row.Name, row.MappingError)
		}
		if row.MappedCommand != "invoices create" {
			t.Fatalf("%s mapped=%q", row.Name, row.MappedCommand)
		}
	}
}

func TestEnvelopeJSONIncludesEnrichmentFields(t *testing.T) {
	list := EnrichSummaries([]CapabilitySummary{
		{Name: "create-invoice", CLI: &CLIMeta{Domain: "invoices", Verb: "create"}},
		{Name: "x", CLI: &CLIMeta{Domain: "invoices", Verb: "create"}}, // collision with above after both map
	})
	// Force collision pair
	list = EnrichSummaries([]CapabilitySummary{
		{Name: "create-invoice", CLI: &CLIMeta{Domain: "invoices", Verb: "create"}},
		{Name: "make-invoice", CLI: &CLIMeta{Domain: "invoices", Verb: "create"}},
	})
	raw := EnvelopeJSON(list)
	s := string(raw)
	if !strings.Contains(s, `"mapped_command"`) {
		t.Fatalf("missing mapped_command: %s", s)
	}
	if !strings.Contains(s, `"mapping_error"`) {
		t.Fatalf("missing mapping_error: %s", s)
	}
	if !strings.Contains(s, `"cli"`) {
		t.Fatalf("missing cli: %s", s)
	}
	var env map[string]any
	if err := json.Unmarshal(raw, &env); err != nil {
		t.Fatal(err)
	}
	caps := env["data"].(map[string]any)["capabilities"].([]any)
	if len(caps) != 2 {
		t.Fatalf("caps=%d", len(caps))
	}
}
