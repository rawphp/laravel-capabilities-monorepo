package catalog

import (
	"github.com/rawphp/capabilities-cli/internal/synth"
)

// EnrichSummaries applies client-side synthesis mapping onto catalog list rows:
// mapped_command, mapping_error, and cli domain/verb when resolved.
// Server wire cli (when present) is preserved; mechanical mapping may fill cli when absent.
func EnrichSummaries(list []CapabilitySummary) []CapabilitySummary {
	if len(list) == 0 {
		return list
	}
	idx := BuildIndex(list)
	out := make([]CapabilitySummary, len(list))
	for i, s := range list {
		out[i] = s
		row, ok := idx.Rows[s.Name]
		if !ok {
			continue
		}
		if row.MappedCommand != "" {
			out[i].MappedCommand = row.MappedCommand
		}
		if row.MappingError != "" {
			out[i].MappingError = row.MappingError
		}
		// Surface domain/verb on the row when known (wire cli or resolved mapping).
		if out[i].CLI == nil && row.Domain != "" && row.Verb != "" {
			out[i].CLI = &CLIMeta{Domain: row.Domain, Verb: row.Verb}
		}
	}
	return out
}

// BuildIndex converts catalog summaries into a synth domain/verb index.
func BuildIndex(list []CapabilitySummary) *synth.Index {
	entries := make([]synth.Entry, 0, len(list))
	for _, s := range list {
		var cli *synth.CLI
		if s.CLI != nil {
			cli = &synth.CLI{Domain: s.CLI.Domain, Verb: s.CLI.Verb}
		}
		entries = append(entries, synth.Entry{
			Name:     s.Name,
			CLI:      cli,
			Surfaces: s.Surfaces,
		})
	}
	return synth.Build(entries)
}
