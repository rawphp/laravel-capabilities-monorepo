package catalog

import (
	"fmt"
	"sort"
	"strings"
)

// DomainCount is one domain row in the human catalog index.
type DomainCount struct {
	Domain string
	Verbs  int
}

// DomainIndex builds sorted domain → synthesizable verb counts from an enriched list.
func DomainIndex(list []CapabilitySummary) []DomainCount {
	idx := BuildIndex(list)
	if idx == nil || len(idx.Domains) == 0 {
		return nil
	}
	names := idx.DomainNames()
	sort.Strings(names)
	out := make([]DomainCount, 0, len(names))
	for _, d := range names {
		out = append(out, DomainCount{
			Domain: d,
			Verbs:  len(idx.Domains[d]),
		})
	}
	return out
}

// FormatHumanDomainIndex is the default human catalog view (domain-first).
func FormatHumanDomainIndex(list []CapabilitySummary) string {
	domains := DomainIndex(list)
	var b strings.Builder
	if len(domains) == 0 {
		b.WriteString("No synthesizable domains yet.\n")
		b.WriteString("\n")
		b.WriteString("  capabilities auth status\n")
		b.WriteString("  capabilities catalog --json     # check cli.domain / cli.verb\n")
		b.WriteString("  capabilities run <name>         # always works by canonical name\n")
		return b.String()
	}

	b.WriteString("Domains (from remote catalog):\n")
	b.WriteString("\n")
	// Align verb counts in a simple column.
	maxName := 0
	for _, d := range domains {
		if len(d.Domain) > maxName {
			maxName = len(d.Domain)
		}
	}
	for _, d := range domains {
		pad := maxName - len(d.Domain)
		verbWord := "verbs"
		if d.Verbs == 1 {
			verbWord = "verb"
		}
		fmt.Fprintf(&b, "  %s%s  %d %s\n", d.Domain, strings.Repeat(" ", pad), d.Verbs, verbWord)
	}
	b.WriteString("\n")
	b.WriteString("Next:\n")
	b.WriteString("  capabilities <domain> --help          list verbs\n")
	b.WriteString("  capabilities <domain> <verb> --help    schema + how to pass fields\n")
	b.WriteString("  capabilities <domain> <verb> --human   invoke with one-line summary on stderr\n")
	b.WriteString("  capabilities catalog --flat            name → domain verb list\n")
	b.WriteString("  capabilities catalog --json            agent machine map\n")
	return b.String()
}

// FormatHumanFlat is the previous flat name → mapped_command listing.
func FormatHumanFlat(list []CapabilitySummary) string {
	var b strings.Builder
	if len(list) == 0 {
		b.WriteString("(empty catalog)\n")
		return b.String()
	}
	for _, cap := range list {
		line := cap.Name
		if cap.MappedCommand != "" {
			line += " → " + cap.MappedCommand
		}
		if cap.Deprecated {
			line += " (deprecated)"
		}
		if cap.MappingError != "" {
			line += " [mapping_error=" + cap.MappingError + "]"
		}
		b.WriteString(line)
		b.WriteByte('\n')
	}
	return b.String()
}
