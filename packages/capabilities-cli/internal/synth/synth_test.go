package synth

import (
	"sort"
	"testing"
)

func TestBuildMappingPriority(t *testing.T) {
	tests := []struct {
		name           string
		entries        []Entry
		wantDomain     string
		wantVerb       string
		wantCanonical  string
		wantMapped     bool
		lookupDomain   string
		lookupVerb     string
		wantRowError   string
		wantMappedCmd  string
	}{
		{
			name: "metadata cli.domain+cli.verb wins",
			entries: []Entry{
				{Name: "create-invoice", CLI: &CLI{Domain: "invoices", Verb: "create"}},
			},
			lookupDomain:  "invoices",
			lookupVerb:    "create",
			wantDomain:    "invoices",
			wantVerb:      "create",
			wantCanonical: "create-invoice",
			wantMapped:    true,
			wantMappedCmd: "invoices create",
		},
		{
			name: "mechanical split on first dot",
			entries: []Entry{
				{Name: "invoices.create"},
			},
			lookupDomain:  "invoices",
			lookupVerb:    "create",
			wantDomain:    "invoices",
			wantVerb:      "create",
			wantCanonical: "invoices.create",
			wantMapped:    true,
			wantMappedCmd: "invoices create",
		},
		{
			name: "mechanical split on first slash",
			entries: []Entry{
				{Name: "invoices/create"},
			},
			lookupDomain:  "invoices",
			lookupVerb:    "create",
			wantDomain:    "invoices",
			wantVerb:      "create",
			wantCanonical: "invoices/create",
			wantMapped:    true,
			wantMappedCmd: "invoices create",
		},
		{
			name: "kebab-only without metadata is unmapped",
			entries: []Entry{
				{Name: "create-invoice"},
			},
			lookupDomain:  "create-invoice",
			lookupVerb:    "",
			wantMapped:    false,
			wantMappedCmd: "",
		},
		{
			name: "metadata beats mechanical name shape",
			entries: []Entry{
				// Name looks mechanical but metadata redirects.
				{Name: "billing.create", CLI: &CLI{Domain: "invoices", Verb: "create"}},
			},
			lookupDomain:  "invoices",
			lookupVerb:    "create",
			wantDomain:    "invoices",
			wantVerb:      "create",
			wantCanonical: "billing.create",
			wantMapped:    true,
			wantMappedCmd: "invoices create",
		},
		{
			name: "incomplete cli only domain is unmapped",
			entries: []Entry{
				{Name: "create-invoice", CLI: &CLI{Domain: "invoices", Verb: ""}},
			},
			lookupDomain: "invoices",
			lookupVerb:   "create",
			wantMapped:   false,
			wantRowError: ErrIncompleteCLI,
		},
		{
			name: "incomplete cli only verb is unmapped",
			entries: []Entry{
				{Name: "create-invoice", CLI: &CLI{Domain: "", Verb: "create"}},
			},
			lookupDomain: "invoices",
			lookupVerb:   "create",
			wantMapped:   false,
			wantRowError: ErrIncompleteCLI,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			idx := Build(tt.entries)
			got, ok := idx.Lookup(tt.lookupDomain, tt.lookupVerb)
			if ok != tt.wantMapped {
				t.Fatalf("Lookup ok=%v want %v", ok, tt.wantMapped)
			}
			if tt.wantMapped && got != tt.wantCanonical {
				t.Fatalf("canonical=%q want %q", got, tt.wantCanonical)
			}
			row := idx.Rows[tt.entries[0].Name]
			if tt.wantMapped {
				if row.Domain != tt.wantDomain || row.Verb != tt.wantVerb {
					t.Fatalf("row domain/verb=%q/%q want %q/%q", row.Domain, row.Verb, tt.wantDomain, tt.wantVerb)
				}
				if row.MappedCommand != tt.wantMappedCmd {
					t.Fatalf("mapped_command=%q want %q", row.MappedCommand, tt.wantMappedCmd)
				}
				if !row.Synthesized {
					t.Fatal("expected Synthesized=true")
				}
				if row.MappingError != "" {
					t.Fatalf("unexpected MappingError %q", row.MappingError)
				}
			}
			if tt.wantRowError != "" && row.MappingError != tt.wantRowError {
				t.Fatalf("MappingError=%q want %q", row.MappingError, tt.wantRowError)
			}
			if !tt.wantMapped && row.Synthesized {
				t.Fatal("expected Synthesized=false for unmapped")
			}
		})
	}
}

func TestBuildCollisionDisablesBoth(t *testing.T) {
	entries := []Entry{
		{Name: "create-invoice", CLI: &CLI{Domain: "invoices", Verb: "create"}},
		{Name: "invoice-create", CLI: &CLI{Domain: "invoices", Verb: "create"}},
		{Name: "invoices.list"}, // unaffected
	}
	idx := Build(entries)

	if _, ok := idx.Lookup("invoices", "create"); ok {
		t.Fatal("collision pair must not be synthesized")
	}
	for _, name := range []string{"create-invoice", "invoice-create"} {
		row := idx.Rows[name]
		if row.MappingError != ErrCollision {
			t.Fatalf("%s MappingError=%q want %q", name, row.MappingError, ErrCollision)
		}
		if row.Synthesized {
			t.Fatalf("%s must not be synthesized", name)
		}
		// Domain/verb still recorded so agents see the intended pair.
		if row.Domain != "invoices" || row.Verb != "create" {
			t.Fatalf("%s row domain/verb=%q/%q", name, row.Domain, row.Verb)
		}
	}
	// Unaffected pair remains.
	got, ok := idx.Lookup("invoices", "list")
	if !ok || got != "invoices.list" {
		t.Fatalf("unaffected list map: ok=%v name=%q", ok, got)
	}
}

func TestBuildReservedDomainsNeverSynthesized(t *testing.T) {
	reserved := []string{"auth", "catalog", "describe", "run", "mcp", "approvals", "version", "help"}
	for _, domain := range reserved {
		t.Run("metadata_"+domain, func(t *testing.T) {
			idx := Build([]Entry{
				{Name: "x-cap", CLI: &CLI{Domain: domain, Verb: "doit"}},
			})
			if _, ok := idx.Lookup(domain, "doit"); ok {
				t.Fatalf("reserved domain %q must not synthesize", domain)
			}
			row := idx.Rows["x-cap"]
			if row.MappingError != ErrReservedDomain {
				t.Fatalf("MappingError=%q want %q", row.MappingError, ErrReservedDomain)
			}
			if row.Synthesized {
				t.Fatal("Synthesized should be false")
			}
		})
		t.Run("mechanical_"+domain, func(t *testing.T) {
			name := domain + ".doit"
			idx := Build([]Entry{{Name: name}})
			if _, ok := idx.Lookup(domain, "doit"); ok {
				t.Fatalf("reserved mechanical domain %q must not synthesize", domain)
			}
			row := idx.Rows[name]
			if row.MappingError != ErrReservedDomain {
				t.Fatalf("MappingError=%q want %q", row.MappingError, ErrReservedDomain)
			}
		})
	}
}

func TestBuildNoNLPPluralization(t *testing.T) {
	// create-invoice must never invent invoices/create without metadata or mechanical separators.
	idx := Build([]Entry{{Name: "create-invoice"}})
	if _, ok := idx.Lookup("invoices", "create"); ok {
		t.Fatal("must not invent domain/verb via NLP/pluralization")
	}
	if idx.Rows["create-invoice"].Synthesized {
		t.Fatal("kebab-only must stay unmapped")
	}
}

func TestBuildInvalidTokensUnmapped(t *testing.T) {
	tests := []struct {
		name    string
		entry   Entry
		wantErr string
	}{
		{
			name:    "uppercase domain",
			entry:   Entry{Name: "x", CLI: &CLI{Domain: "Invoices", Verb: "create"}},
			wantErr: ErrInvalidToken,
		},
		{
			name:    "domain starting with digit",
			entry:   Entry{Name: "x", CLI: &CLI{Domain: "1nvoices", Verb: "create"}},
			wantErr: ErrInvalidToken,
		},
		{
			name:    "verb with underscore",
			entry:   Entry{Name: "x", CLI: &CLI{Domain: "invoices", Verb: "create_now"}},
			wantErr: ErrInvalidToken,
		},
		{
			name:    "mechanical multi-segment verb rejected",
			entry:   Entry{Name: "a.b.c"},
			wantErr: ErrInvalidToken,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			idx := Build([]Entry{tt.entry})
			row := idx.Rows[tt.entry.Name]
			if row.Synthesized {
				t.Fatal("invalid tokens must not synthesize")
			}
			if row.MappingError != tt.wantErr {
				t.Fatalf("MappingError=%q want %q", row.MappingError, tt.wantErr)
			}
		})
	}
}

func TestBuildCLISurfaceFilter(t *testing.T) {
	idx := Build([]Entry{
		{Name: "on-cli", CLI: &CLI{Domain: "invoices", Verb: "create"}, Surfaces: []string{"cli", "http"}},
		{Name: "off-cli", CLI: &CLI{Domain: "invoices", Verb: "void"}, Surfaces: []string{"http", "mcp"}},
		{Name: "no-surfaces", CLI: &CLI{Domain: "payments", Verb: "charge"}}, // empty = eligible (server-filtered)
	})
	if _, ok := idx.Lookup("invoices", "create"); !ok {
		t.Fatal("cli surface should synthesize")
	}
	if _, ok := idx.Lookup("invoices", "void"); ok {
		t.Fatal("non-cli surface must not synthesize")
	}
	if _, ok := idx.Lookup("payments", "charge"); !ok {
		t.Fatal("empty surfaces should still synthesize")
	}
}

func TestBuildDomainAndVerbLists(t *testing.T) {
	idx := Build([]Entry{
		{Name: "invoices.create"},
		{Name: "invoices.list"},
		{Name: "payments.charge"},
	})
	domains := idx.DomainNames()
	sort.Strings(domains)
	wantDomains := []string{"invoices", "payments"}
	if len(domains) != 2 || domains[0] != wantDomains[0] || domains[1] != wantDomains[1] {
		t.Fatalf("domains=%v want %v", domains, wantDomains)
	}
	verbs := idx.Verbs("invoices")
	sort.Strings(verbs)
	if len(verbs) != 2 || verbs[0] != "create" || verbs[1] != "list" {
		t.Fatalf("verbs=%v", verbs)
	}
}

func TestResolveMappingUnit(t *testing.T) {
	// Direct unit coverage of priority helper used by Build.
	tests := []struct {
		name   string
		cap    string
		cli    *CLI
		domain string
		verb   string
		ok     bool
		err    string
	}{
		{"meta", "create-invoice", &CLI{Domain: "invoices", Verb: "create"}, "invoices", "create", true, ""},
		{"incomplete", "create-invoice", &CLI{Domain: "invoices"}, "invoices", "", false, ErrIncompleteCLI},
		{"dot", "invoices.create", nil, "invoices", "create", true, ""},
		{"slash", "invoices/create", nil, "invoices", "create", true, ""},
		{"kebab", "create-invoice", nil, "", "", false, ""},
		{"reserved", "x", &CLI{Domain: "run", Verb: "x"}, "run", "x", false, ErrReservedDomain},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			d, v, errCode, ok := ResolveMapping(tt.cap, tt.cli)
			if ok != tt.ok || d != tt.domain || v != tt.verb || errCode != tt.err {
				t.Fatalf("got domain=%q verb=%q err=%q ok=%v want %q %q %q %v",
					d, v, errCode, ok, tt.domain, tt.verb, tt.err, tt.ok)
			}
		})
	}
}

func TestIsReservedDomain(t *testing.T) {
	for _, d := range []string{"auth", "catalog", "describe", "run", "mcp", "approvals", "version", "help"} {
		if !IsReservedDomain(d) {
			t.Fatalf("%q should be reserved", d)
		}
	}
	if IsReservedDomain("invoices") {
		t.Fatal("invoices must not be reserved")
	}
}
