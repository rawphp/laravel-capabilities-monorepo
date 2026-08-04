package main

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
	"github.com/rawphp/capabilities-cli/internal/helpfmt"
	"github.com/rawphp/capabilities-cli/internal/synth"
)

func cmdDomainOrUnknown(env Env, domain string, args []string) int {
	idx, summaries, code := loadSynthIndex(env)
	if code != 0 {
		return code
	}
	// Known domain?
	verbs := idx.Domains[domain]
	if verbs == nil {
		return writeNotFound(env, fmt.Sprintf("unknown command or domain %q", domain),
			"try: capabilities catalog  |  capabilities run <name>  |  capabilities help")
	}

	profile, base, args := profileAndBase(args)
	jsonOut, args := flagBool(args, "--json")
	noCache, args := flagBool(args, "--no-cache")
	helpWanted := wantsHelp(args)
	args = stripHelpFlags(args)

	verb, rest := takeFirstPositional(args)
	if verb == "" {
		// Domain-level help (missing verb, or domain --help only).
		return writeDomainHelp(env, domain, idx, summaries, jsonOut)
	}
	if helpWanted {
		canonical, ok := idx.Lookup(domain, verb)
		if !ok {
			return writeNotFound(env, fmt.Sprintf("unknown verb %q for domain %q", verb, domain),
				fmt.Sprintf("try: capabilities %s --help", domain))
		}
		return writeCapabilityHelp(env, domain, verb, canonical, jsonOut, profile, base, noCache)
	}

	// Capability invoke — same pipeline as `run` (flagschema merge + HTTP).
	canonical, ok := idx.Lookup(domain, verb)
	if !ok {
		return writeNotFound(env, fmt.Sprintf("unknown verb %q for domain %q", verb, domain),
			fmt.Sprintf("try: capabilities %s --help", domain))
	}
	input, rest := flagValue(rest, "--input")
	inputFile, rest := flagValue(rest, "--input-file")
	idem, rest := flagValue(rest, "--idempotency-key")
	tenant, rest := flagValue(rest, "--tenant")
	human, rest := flagBool(rest, "--human")
	retryLast, rest := flagBool(rest, "--retry-last")
	// jsonOut/noCache/profile/base already peeled above
	st := store(env)
	return invokeCapability(env, st, profile, base, canonical, input, inputFile, idem, tenant, jsonOut, human, noCache, retryLast, rest)
}

// loadSynthIndex returns the synthesis index and optional summaries.
// Prefers env.Index (tests); otherwise loads catalog when authenticated.
// When unauthenticated and no Index, returns empty index so unknown tokens → exit 5.
func loadSynthIndex(env Env) (*synth.Index, []catalog.CapabilitySummary, int) {
	if env.Index != nil {
		return env.Index, env.Summaries, 0
	}
	st := store(env)
	profile := "default"
	// Best-effort profile from args is not available here; default is fine for index load.
	if err := auth.GuardAuth(st, profile, "catalog"); err != nil {
		// No remote index — treat non-reserved as unknown (exit 5 at caller).
		return &synth.Index{Domains: map[string]map[string]string{}, Rows: map[string]synth.Row{}}, nil, 0
	}
	c, err := clientFor(env, st, profile, "")
	if err != nil {
		return &synth.Index{Domains: map[string]map[string]string{}, Rows: map[string]synth.Row{}}, nil, 0
	}
	svc := &catalog.Service{Client: c, Cache: catalog.NewCache(st.SchemaCacheDir(profile))}
	list, _, err := svc.List(context.Background())
	if err != nil {
		if se, ok := err.(*api.StructuredError); ok {
			fmt.Fprintln(env.Stderr, se.Error())
			return nil, nil, se.ExitCode
		}
		fmt.Fprintln(env.Stderr, err.Error())
		return nil, nil, api.ExitInternal
	}
	return catalog.BuildIndex(list), list, 0
}

func writeDomainHelp(env Env, domain string, idx *synth.Index, summaries []catalog.CapabilitySummary, jsonOut bool) int {
	byName := make(map[string]catalog.CapabilitySummary, len(summaries))
	for _, s := range summaries {
		byName[s.Name] = s
	}
	verbNames := idx.SortedVerbs(domain)
	verbs := make([]helpfmt.DomainVerb, 0, len(verbNames))
	for _, v := range verbNames {
		name := idx.Domains[domain][v]
		dv := helpfmt.DomainVerb{Verb: v, Name: name}
		if s, ok := byName[name]; ok {
			dv.Description = s.Description
		}
		verbs = append(verbs, dv)
	}
	if jsonOut {
		fmt.Fprint(env.Stdout, string(DomainHelpJSON(domain, verbs)))
	} else {
		fmt.Fprint(env.Stdout, DomainHelpHuman(domain, verbs))
	}
	return api.ExitOK
}

func writeCapabilityHelp(env Env, domain, verb, canonical string, jsonOut bool, profile, base string, noCache bool) int {
	info := helpfmt.CapabilityInfo{
		Domain: domain,
		Verb:   verb,
		Name:   canonical,
	}
	if env.SchemaFor != nil {
		desc, ver, in, out := env.SchemaFor(canonical)
		info.Description = desc
		info.SchemaVersion = ver
		info.InputSchema = in
		info.OutputSchema = out
	} else {
		// Load schema via describe (auth required).
		st := store(env)
		if profile == "" {
			profile = "default"
		}
		if err := auth.GuardAuth(st, profile, "describe"); err != nil {
			fmt.Fprintln(env.Stderr, err.Error())
			return api.ExitAuth
		}
		c, err := clientFor(env, st, profile, base)
		if err != nil {
			fmt.Fprintln(env.Stderr, err.Error())
			return api.ExitAuth
		}
		svc := &catalog.Service{Client: c, Cache: catalog.NewCache(st.SchemaCacheDir(profile)), NoCache: noCache}
		entry, _, err := svc.Describe(context.Background(), canonical)
		if err != nil {
			if se, ok := err.(*api.StructuredError); ok {
				fmt.Fprintln(env.Stderr, se.Error())
				return se.ExitCode
			}
			fmt.Fprintln(env.Stderr, err.Error())
			return api.ExitInternal
		}
		info.Description = "" // describe wire may not always include description
		if entry.CLI != nil {
			// prefer index domain/verb already set
		}
		info.SchemaVersion = entry.SchemaVersion
		info.Name = entry.Name
		if entry.Canonical != "" {
			info.Name = entry.Canonical
		}
		info.InputSchema = rawToMap(entry.InputSchema)
		info.OutputSchema = rawToMap(entry.OutputSchema)
	}
	if jsonOut {
		fmt.Fprint(env.Stdout, string(CapabilityHelpJSON(info)))
	} else {
		fmt.Fprint(env.Stdout, CapabilityHelpHuman(info))
	}
	return api.ExitOK
}

func rawToMap(raw json.RawMessage) map[string]any {
	if len(raw) == 0 {
		return map[string]any{}
	}
	var m map[string]any
	if err := json.Unmarshal(raw, &m); err != nil {
		return map[string]any{}
	}
	return m
}

func writeNotFound(env Env, message, hint string) int {
	envBody := api.ErrorEnvelope{
		OK: false,
		Error: &api.ErrorBody{
			Code:      api.CodeNotFound,
			Message:   message,
			Retryable: false,
		},
	}
	b, _ := json.MarshalIndent(envBody, "", "  ")
	fmt.Fprintln(env.Stdout, string(b))
	fmt.Fprintln(env.Stderr, message)
	if hint != "" {
		fmt.Fprintln(env.Stderr, "Hint:", hint)
	}
	return api.ExitDomain
}

// stripHelpFlags removes -h / --help from args.
func stripHelpFlags(args []string) []string {
	out := make([]string, 0, len(args))
	for _, a := range args {
		if a == "-h" || a == "--help" {
			continue
		}
		out = append(out, a)
	}
	return out
}

// takeFirstPositional returns the first non-flag argument and remaining args (flags + later positionals).
func takeFirstPositional(args []string) (pos string, rest []string) {
	rest = make([]string, 0, len(args))
	for i, a := range args {
		if strings.HasPrefix(a, "-") {
			rest = append(rest, a)
			continue
		}
		// First positional is the verb (or subcommand token).
		pos = a
		rest = append(rest, args[i+1:]...)
		return pos, rest
	}
	return "", rest
}
