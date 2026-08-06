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
	// Resolve profile before catalog load so multi-profile laptops hit the right store.
	profile, base, args := profileAndBase(args)
	idx, summaries, code := loadSynthIndex(env, profile)
	if code != 0 {
		return code
	}
	// Known domain?
	verbs := idx.Domains[domain]
	if verbs == nil {
		hint := "try: capabilities catalog  |  capabilities run <name>  |  capabilities help"
		if sug := suggestReservedOrDomain(domain, idx); sug != "" {
			hint = "did you mean: " + sug + "  |  " + hint
		}
		return writeNotFound(env, fmt.Sprintf("unknown command or domain %q", domain), hint)
	}

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
// Prefers env.Index (tests); otherwise loads catalog when authenticated for profile.
// When unauthenticated, fails closed with exit 3 — never pretends domains are unknown.
func loadSynthIndex(env Env, profile string) (*synth.Index, []catalog.CapabilitySummary, int) {
	if env.Index != nil {
		return env.Index, env.Summaries, 0
	}
	if profile == "" {
		profile = "default"
	}
	st := store(env)
	if err := auth.GuardAuth(st, profile, "catalog"); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return nil, nil, api.ExitAuth
	}
	c, err := clientFor(env, st, profile, "")
	if err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return nil, nil, api.ExitAuth
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

// suggestReservedOrDomain returns a close match for typos (catalg → catalog, mele → meal).
func suggestReservedOrDomain(token string, idx *synth.Index) string {
	token = strings.ToLower(strings.TrimSpace(token))
	if token == "" {
		return ""
	}
	// Domains first so product names beat reserved tokens at equal edit distance
	// (e.g. mele → meal over help).
	var candidates []string
	if idx != nil {
		for d := range idx.Domains {
			candidates = append(candidates, d)
		}
	}
	// Runnable reserved meta only (mcp is reserved forever as a domain token but not a command).
	candidates = append(candidates, "auth", "catalog", "describe", "run", "approvals", "version", "help")
	best, bestDist, bestPrefix := "", 3, -1 // only suggest distance 1–2
	for _, c := range candidates {
		cl := strings.ToLower(c)
		d := levenshtein(token, cl)
		if d == 0 || d > 2 {
			continue
		}
		p := commonPrefixLen(token, cl)
		if d < bestDist || (d == bestDist && p > bestPrefix) {
			bestDist, bestPrefix, best = d, p, c
		}
	}
	return best
}

func commonPrefixLen(a, b string) int {
	n := len(a)
	if len(b) < n {
		n = len(b)
	}
	i := 0
	for i < n && a[i] == b[i] {
		i++
	}
	return i
}

func levenshtein(a, b string) int {
	if a == b {
		return 0
	}
	if a == "" {
		return len(b)
	}
	if b == "" {
		return len(a)
	}
	// Two-row DP
	prev := make([]int, len(b)+1)
	cur := make([]int, len(b)+1)
	for j := range prev {
		prev[j] = j
	}
	for i := 1; i <= len(a); i++ {
		cur[0] = i
		for j := 1; j <= len(b); j++ {
			cost := 0
			if a[i-1] != b[j-1] {
				cost = 1
			}
			del := prev[j] + 1
			ins := cur[j-1] + 1
			sub := prev[j-1] + cost
			cur[j] = del
			if ins < cur[j] {
				cur[j] = ins
			}
			if sub < cur[j] {
				cur[j] = sub
			}
		}
		prev, cur = cur, prev
	}
	return prev[len(b)]
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
