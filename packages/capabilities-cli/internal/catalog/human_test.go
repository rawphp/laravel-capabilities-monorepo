package catalog

import (
	"strings"
	"testing"
)

func TestFormatHumanDomainIndex_listsDomains(t *testing.T) {
	list := EnrichSummaries([]CapabilitySummary{
		{Name: "program_update", CLI: &CLIMeta{Domain: "program", Verb: "update"}, Surfaces: []string{"cli"}},
		{Name: "get_active_program", CLI: &CLIMeta{Domain: "program", Verb: "read"}, Surfaces: []string{"cli"}},
		{Name: "add_meal_food", CLI: &CLIMeta{Domain: "meal", Verb: "add-food"}, Surfaces: []string{"cli"}},
		{Name: "kebab-only", Surfaces: []string{"cli"}}, // unmapped
	})
	text := FormatHumanDomainIndex(list)
	if !strings.Contains(text, "Domains (from remote catalog):") {
		t.Fatalf("missing header: %s", text)
	}
	if !strings.Contains(text, "meal") || !strings.Contains(text, "program") {
		t.Fatalf("missing domains: %s", text)
	}
	if !strings.Contains(text, "2 verbs") { // program has update+read
		// meal has 1 verb — check either
		if !strings.Contains(text, "1 verb") {
			t.Fatalf("expected verb counts: %s", text)
		}
	}
	if !strings.Contains(text, "catalog --flat") {
		t.Fatalf("missing next steps: %s", text)
	}
	// Must not dump flat capability names as the primary list
	if strings.Contains(text, "program_update →") {
		t.Fatalf("default should not be flat list: %s", text)
	}
}

func TestFormatHumanDomainIndex_empty(t *testing.T) {
	text := FormatHumanDomainIndex(nil)
	if !strings.Contains(text, "No synthesizable domains yet") {
		t.Fatalf("empty message: %s", text)
	}
	if !strings.Contains(text, "run <name>") {
		t.Fatalf("missing run hint: %s", text)
	}
}

func TestFormatHumanFlat_previousShape(t *testing.T) {
	list := EnrichSummaries([]CapabilitySummary{
		{Name: "program_update", CLI: &CLIMeta{Domain: "program", Verb: "update"}, Surfaces: []string{"cli"}},
	})
	text := FormatHumanFlat(list)
	if !strings.Contains(text, "program_update → program update") {
		t.Fatalf("flat line: %s", text)
	}
}

func TestDomainIndex_sorted(t *testing.T) {
	list := EnrichSummaries([]CapabilitySummary{
		{Name: "z_cap", CLI: &CLIMeta{Domain: "zeta", Verb: "a"}, Surfaces: []string{"cli"}},
		{Name: "a_cap", CLI: &CLIMeta{Domain: "alpha", Verb: "b"}, Surfaces: []string{"cli"}},
	})
	idx := DomainIndex(list)
	if len(idx) != 2 || idx[0].Domain != "alpha" || idx[1].Domain != "zeta" {
		t.Fatalf("sorted domains: %#v", idx)
	}
}
