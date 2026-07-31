// Package synth builds a domain→verb→canonical capability index from catalog entries.
//
// Mapping priority (design Name mapping):
//  1. Catalog metadata cli.domain + cli.verb
//  2. Mechanical split on the first '.' or '/' in the capability name
//  3. None (unmapped — use run <name>)
//
// Client collision policy: if two names map to the same (domain, verb) pair, neither
// is registered for synthesis; both rows carry mapping_error=collision.
// Reserved meta-command names never become synthesizable domains.
// There is no NLP/pluralization of kebab-case names.
package synth

import (
	"regexp"
	"sort"
	"strings"
)

// Reserved domain tokens — always win as meta-commands; never synthesized as domains.
var reservedDomains = map[string]struct{}{
	"auth":      {},
	"catalog":   {},
	"describe":  {},
	"run":       {},
	"mcp":       {},
	"approvals": {},
	"version":   {},
	"help":      {},
}

// MappingError codes for catalog row enrichment.
const (
	ErrCollision      = "collision"
	ErrIncompleteCLI  = "incomplete_cli"
	ErrReservedDomain = "reserved_domain"
	ErrInvalidToken   = "invalid_token"
	ErrNotCLISurface  = "not_cli_surface"
)

// tokenRE matches domain and verb tokens: lowercase [a-z][a-z0-9-]*.
var tokenRE = regexp.MustCompile(`^[a-z][a-z0-9-]*$`)

// CLI is optional catalog routing metadata (wire: entry.cli).
type CLI struct {
	Domain string `json:"domain,omitempty"`
	Verb   string `json:"verb,omitempty"`
}

// Entry is one catalog capability used when building the synthesis index.
type Entry struct {
	Name     string
	CLI      *CLI
	Surfaces []string // when non-empty, "cli" must be present to participate
}

// Row is the per-capability mapping result for catalog enrichment.
type Row struct {
	Name          string `json:"name"`
	Domain        string `json:"domain,omitempty"`
	Verb          string `json:"verb,omitempty"`
	MappedCommand string `json:"mapped_command,omitempty"`
	MappingError  string `json:"mapping_error,omitempty"`
	Synthesized   bool   `json:"synthesized"`
}

// Index is domain → verb → canonical name after collision policy.
type Index struct {
	// Domains maps domain → verb → canonical capability name.
	Domains map[string]map[string]string
	// Rows maps capability name → mapping row.
	Rows map[string]Row
}

// candidate is an intermediate mapping before collision resolution.
type candidate struct {
	name   string
	domain string
	verb   string
	err    string // non-empty → not synthesizable
}

// IsReservedDomain reports whether domain is a reserved meta-command name.
func IsReservedDomain(domain string) bool {
	_, ok := reservedDomains[domain]
	return ok
}

// ValidToken reports whether s is a valid domain or verb token.
func ValidToken(s string) bool {
	return tokenRE.MatchString(s)
}

// ResolveMapping applies mapping priority for one capability name + optional CLI metadata.
// ok is true only when domain+verb are valid, non-reserved, and ready for synthesis
// (collision is handled later by Build).
// When metadata is incomplete, err is ErrIncompleteCLI and ok is false.
// When domain is reserved or tokens invalid, err is set and ok is false; domain/verb may still be filled.
func ResolveMapping(name string, cli *CLI) (domain, verb, err string, ok bool) {
	if cli != nil {
		d := strings.TrimSpace(cli.Domain)
		v := strings.TrimSpace(cli.Verb)
		if d != "" || v != "" {
			if d == "" || v == "" {
				return d, v, ErrIncompleteCLI, false
			}
			return validatePair(d, v)
		}
		// Empty CLI object falls through to mechanical mapping.
	}

	d, v, splitOK := mechanicalSplit(name)
	if !splitOK {
		return "", "", "", false
	}
	return validatePair(d, v)
}

func validatePair(domain, verb string) (string, string, string, bool) {
	if !ValidToken(domain) || !ValidToken(verb) {
		return domain, verb, ErrInvalidToken, false
	}
	if IsReservedDomain(domain) {
		return domain, verb, ErrReservedDomain, false
	}
	return domain, verb, "", true
}

// mechanicalSplit splits name on the first '.' or '/' only.
func mechanicalSplit(name string) (domain, verb string, ok bool) {
	iDot := strings.IndexByte(name, '.')
	iSlash := strings.IndexByte(name, '/')
	i := -1
	switch {
	case iDot < 0 && iSlash < 0:
		return "", "", false
	case iDot < 0:
		i = iSlash
	case iSlash < 0:
		i = iDot
	default:
		if iDot < iSlash {
			i = iDot
		} else {
			i = iSlash
		}
	}
	if i <= 0 || i >= len(name)-1 {
		return "", "", false
	}
	return name[:i], name[i+1:], true
}

// hasCLISurface returns true when surfaces is empty (server already filtered)
// or explicitly lists "cli".
func hasCLISurface(surfaces []string) bool {
	if len(surfaces) == 0 {
		return true
	}
	for _, s := range surfaces {
		if s == "cli" {
			return true
		}
	}
	return false
}

// Build constructs the synthesis index from catalog entries.
func Build(entries []Entry) *Index {
	idx := &Index{
		Domains: make(map[string]map[string]string),
		Rows:    make(map[string]Row, len(entries)),
	}

	// pairKey → list of capability names that map to it
	type pair struct{ domain, verb string }
	byPair := make(map[pair][]candidate)
	order := make([]candidate, 0, len(entries))

	for _, e := range entries {
		row := Row{Name: e.Name}
		if e.Name == "" {
			idx.Rows[e.Name] = row
			continue
		}
		if !hasCLISurface(e.Surfaces) {
			row.MappingError = ErrNotCLISurface
			idx.Rows[e.Name] = row
			continue
		}

		d, v, errCode, ok := ResolveMapping(e.Name, e.CLI)
		row.Domain = d
		row.Verb = v
		if errCode != "" {
			row.MappingError = errCode
			idx.Rows[e.Name] = row
			continue
		}
		if !ok {
			// Unmapped without error (e.g. kebab-only).
			idx.Rows[e.Name] = row
			continue
		}

		c := candidate{name: e.Name, domain: d, verb: v}
		order = append(order, c)
		p := pair{domain: d, verb: v}
		byPair[p] = append(byPair[p], c)
		// Tentative row; may flip to collision.
		row.MappedCommand = d + " " + v
		idx.Rows[e.Name] = row
	}

	// Collision policy: two+ names for same pair → register neither.
	for p, cs := range byPair {
		if len(cs) > 1 {
			for _, c := range cs {
				row := idx.Rows[c.name]
				row.MappingError = ErrCollision
				row.Synthesized = false
				// Keep domain/verb/mapped_command for agent diagnostics.
				if row.MappedCommand == "" {
					row.MappedCommand = p.domain + " " + p.verb
				}
				idx.Rows[c.name] = row
			}
			continue
		}
		c := cs[0]
		if idx.Domains[c.domain] == nil {
			idx.Domains[c.domain] = make(map[string]string)
		}
		idx.Domains[c.domain][c.verb] = c.name
		row := idx.Rows[c.name]
		row.Synthesized = true
		row.MappedCommand = c.domain + " " + c.verb
		idx.Rows[c.name] = row
	}

	return idx
}

// Lookup returns the canonical capability name for domain+verb when synthesized.
func (idx *Index) Lookup(domain, verb string) (string, bool) {
	if idx == nil {
		return "", false
	}
	verbs, ok := idx.Domains[domain]
	if !ok {
		return "", false
	}
	name, ok := verbs[verb]
	return name, ok
}

// DomainNames returns domains that have at least one synthesizable verb (unsorted).
func (idx *Index) DomainNames() []string {
	if idx == nil {
		return nil
	}
	out := make([]string, 0, len(idx.Domains))
	for d := range idx.Domains {
		out = append(out, d)
	}
	return out
}

// Verbs returns synthesizable verbs for domain (unsorted).
func (idx *Index) Verbs(domain string) []string {
	if idx == nil {
		return nil
	}
	verbs, ok := idx.Domains[domain]
	if !ok {
		return nil
	}
	out := make([]string, 0, len(verbs))
	for v := range verbs {
		out = append(out, v)
	}
	return out
}

// SortedDomainNames returns domain names in lexicographic order.
func (idx *Index) SortedDomainNames() []string {
	out := idx.DomainNames()
	sort.Strings(out)
	return out
}

// SortedVerbs returns verbs for domain in lexicographic order.
func (idx *Index) SortedVerbs(domain string) []string {
	out := idx.Verbs(domain)
	sort.Strings(out)
	return out
}
