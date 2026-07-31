package main

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"strings"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
	"github.com/rawphp/capabilities-cli/internal/helpfmt"
	"github.com/rawphp/capabilities-cli/internal/mcpstdio"
	"github.com/rawphp/capabilities-cli/internal/run"
	"github.com/rawphp/capabilities-cli/internal/synth"
)

// Version is the CLI version string printed by `capabilities version`.
// Release builds override it at link time (package main → symbol main.Version):
//
//	go build -ldflags "-X main.Version=0.2.0" ./cmd/capabilities
//
// Prefer the git tag without a leading "v" (e.g. tag v0.2.0 → 0.2.0).
// Default remains a sensible dev string when ldflags are not injected.
var Version = "0.2.0"

// BinaryName is the product binary name (D-016).
const BinaryName = "capabilities"

// SchemaLookup returns describe-like schema data for capability help (tests inject this).
// Returns empty maps when the capability is unknown to the lookup.
type SchemaLookup func(canonical string) (description, schemaVersion string, inputSchema, outputSchema map[string]any)

// Env wires IO and config roots for testability.
type Env struct {
	Args   []string
	Stdout io.Writer
	Stderr io.Writer
	Stdin  io.Reader
	// ConfigRoot overrides ~/.config/capabilities
	ConfigRoot string
	// HTTP client factory override for tests.
	NewClient func(baseURL, token string) *api.Client
	// Now for deprecation checks.
	Now time.Time
	// Index optional synth index override (tests / pre-built cache). When set,
	// domain/verb dispatch uses it without loading the remote catalog.
	Index *synth.Index
	// Summaries optional catalog rows for domain-help descriptions (tests).
	Summaries []catalog.CapabilitySummary
	// SchemaFor optional schema provider for capability --help without HTTP.
	SchemaFor SchemaLookup
}

// Execute parses args and runs a subcommand. Returns process exit code.
//
// Argv dispatch (design):
//  1. Reserved meta-commands always win (auth catalog describe run mcp approvals version help).
//  2. Else known domain in catalog synth index → domain help or capability command / --help.
//  3. Else unknown → not_found envelope + catalog/run hint, exit 5.
func Execute(env Env) int {
	if env.Stdout == nil {
		env.Stdout = os.Stdout
	}
	if env.Stderr == nil {
		env.Stderr = os.Stderr
	}
	if env.Stdin == nil {
		env.Stdin = os.Stdin
	}
	args := env.Args
	if len(args) == 0 {
		fmt.Fprint(env.Stderr, RootHelp())
		return api.ExitValidation
	}
	cmd := args[0]
	rest := args[1:]

	// 1) Reserved meta always wins over domain tokens of the same name.
	switch cmd {
	case "help", "-h", "--help":
		if len(rest) > 0 {
			fmt.Fprint(env.Stdout, CommandHelp(rest[0]))
		} else {
			fmt.Fprint(env.Stdout, RootHelp())
		}
		return api.ExitOK
	case "version", "--version", "-v":
		fmt.Fprintf(env.Stdout, "%s %s\n", BinaryName, Version)
		return api.ExitOK
	case "auth":
		return cmdAuth(env, rest)
	case "catalog":
		return cmdCatalog(env, rest)
	case "describe":
		return cmdDescribe(env, rest)
	case "run":
		return cmdRun(env, rest)
	case "mcp":
		return cmdMcp(env, rest)
	case "approvals":
		return cmdApprovals(env, rest)
	default:
		// 2/3) Domain/verb synthesis or unknown.
		return cmdDomainOrUnknown(env, cmd, rest)
	}
}

func store(env Env) *auth.Store {
	root := env.ConfigRoot
	if root == "" {
		home, _ := os.UserHomeDir()
		root = home + "/.config/capabilities"
	}
	return auth.NewStore(root)
}

func clientFor(env Env, store *auth.Store, profile, baseOverride string) (*api.Client, error) {
	token, err := store.RequireToken(profile)
	if err != nil {
		return nil, err
	}
	base := baseOverride
	if base == "" {
		base, err = store.GetBaseURL(profile)
		if err != nil || base == "" {
			return nil, fmt.Errorf("missing base URL: run auth login --base-url=...")
		}
	}
	if env.NewClient != nil {
		return env.NewClient(base, token), nil
	}
	return api.NewClient(base, token), nil
}

func flagValue(args []string, names ...string) (string, []string) {
	out := make([]string, 0, len(args))
	var val string
	for i := 0; i < len(args); i++ {
		a := args[i]
		matched := false
		for _, n := range names {
			if a == n && i+1 < len(args) {
				val = args[i+1]
				i++
				matched = true
				break
			}
			if strings.HasPrefix(a, n+"=") {
				val = strings.TrimPrefix(a, n+"=")
				matched = true
				break
			}
		}
		if !matched {
			out = append(out, a)
		}
	}
	return val, out
}

func flagBool(args []string, names ...string) (bool, []string) {
	out := make([]string, 0, len(args))
	found := false
	for _, a := range args {
		match := false
		for _, n := range names {
			if a == n {
				found = true
				match = true
				break
			}
		}
		if !match {
			out = append(out, a)
		}
	}
	return found, out
}

// wantsHelp reports whether args contain a help flag (-h or --help).
// Help wins before auth/network/side effects for any subcommand.
func wantsHelp(args []string) bool {
	for _, a := range args {
		if a == "-h" || a == "--help" {
			return true
		}
	}
	return false
}

func profileAndBase(args []string) (profile, base string, rest []string) {
	profile, args = flagValue(args, "--profile")
	if profile == "" {
		profile = "default"
	}
	base, args = flagValue(args, "--base-url")
	return profile, base, args
}

func cmdAuth(env Env, args []string) int {
	if len(args) == 0 {
		fmt.Fprint(env.Stdout, CommandHelp("auth"))
		return api.ExitOK
	}
	st := store(env)
	sub := args[0]
	rest := args[1:]
	profile, base, rest := profileAndBase(rest)
	switch sub {
	case "login":
		if base == "" {
			base, rest = flagValue(rest, "--base-url")
		}
		if base == "" {
			fmt.Fprintln(env.Stderr, "auth login requires --base-url")
			return api.ExitValidation
		}
		token, rest := flagValue(rest, "--token")
		code, rest := flagValue(rest, "--code")
		_ = rest
		var err error
		if token != "" {
			_, err = auth.LoginWithToken(st, profile, base, token)
		} else if code != "" {
			c := api.NewClient(base, "")
			if env.NewClient != nil {
				c = env.NewClient(base, "")
			}
			_, err = auth.LoginBrowserOAuth(context.Background(), st, c, profile, base, code)
		} else {
			c := api.NewClient(base, "")
			if env.NewClient != nil {
				c = env.NewClient(base, "")
			}
			_, err = auth.LoginDeviceCode(context.Background(), st, c, profile, base)
		}
		if err != nil {
			fmt.Fprintln(env.Stderr, err.Error())
			return api.ExitInternal
		}
		// Prefetch schemas into cache (best-effort).
		if tok, e := st.GetToken(profile); e == nil {
			c := api.NewClient(base, tok)
			if env.NewClient != nil {
				c = env.NewClient(base, tok)
			}
			svc := &catalog.Service{Client: c, Cache: catalog.NewCache(st.SchemaCacheDir(profile))}
			_, _ = svc.Refresh(context.Background())
		}
		fmt.Fprintf(env.Stdout, "logged in profile=%s base_url=%s\n", profile, base)
		return api.ExitOK
	case "logout":
		_ = auth.Logout(st, profile)
		fmt.Fprintf(env.Stdout, "logged out profile=%s\n", profile)
		return api.ExitOK
	case "status":
		p := st.Status(profile)
		fmt.Fprintf(env.Stdout, "profile=%s base_url=%s logged_in=%v\n", p.Name, p.BaseURL, p.LoggedIn)
		// Never print token.
		return api.ExitOK
	case "help", "-h", "--help":
		fmt.Fprint(env.Stdout, CommandHelp("auth"))
		return api.ExitOK
	default:
		fmt.Fprintf(env.Stderr, "unknown auth subcommand %q\n", sub)
		return api.ExitValidation
	}
}

func cmdCatalog(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("catalog"))
		return api.ExitOK
	}
	st := store(env)
	profile, base, args := profileAndBase(args)
	jsonOut, args := flagBool(args, "--json")
	noCache, args := flagBool(args, "--no-cache")
	refresh, args := flagBool(args, "refresh", "--refresh")
	_ = args
	if err := auth.GuardAuth(st, profile, "catalog"); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	c, err := clientFor(env, st, profile, base)
	if err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	svc := &catalog.Service{Client: c, Cache: catalog.NewCache(st.SchemaCacheDir(profile)), NoCache: noCache}
	var list []catalog.CapabilitySummary
	if refresh {
		list, err = svc.Refresh(context.Background())
	} else {
		list, _, err = svc.List(context.Background())
	}
	if err != nil {
		if se, ok := err.(*api.StructuredError); ok {
			fmt.Fprintln(env.Stderr, se.Error())
			return se.ExitCode
		}
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitInternal
	}
	// Client-side mapping enrichment for agents (cli, mapped_command, mapping_error).
	list = catalog.EnrichSummaries(list)
	if jsonOut {
		fmt.Fprintln(env.Stdout, string(catalog.EnvelopeJSON(list)))
	} else {
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
			fmt.Fprintln(env.Stdout, line)
		}
	}
	return api.ExitOK
}

// cmdDomainOrUnknown handles synthesized domain/verb commands or unknown argv[0].
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

	// Capability invoke (stub: route to existing run by canonical name; full flagschema is ORI-175).
	canonical, ok := idx.Lookup(domain, verb)
	if !ok {
		return writeNotFound(env, fmt.Sprintf("unknown verb %q for domain %q", verb, domain),
			fmt.Sprintf("try: capabilities %s --help", domain))
	}
	// Rebuild args for run: <name> + remaining flags/positionals.
	runArgs := append([]string{canonical}, rest...)
	if profile != "default" {
		runArgs = append(runArgs, "--profile="+profile)
	}
	if base != "" {
		runArgs = append(runArgs, "--base-url="+base)
	}
	if noCache {
		runArgs = append(runArgs, "--no-cache")
	}
	if jsonOut {
		runArgs = append(runArgs, "--json")
	}
	return cmdRun(env, runArgs)
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

func cmdDescribe(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("describe"))
		return api.ExitOK
	}
	st := store(env)
	profile, base, args := profileAndBase(args)
	jsonOut, args := flagBool(args, "--json")
	noCache, args := flagBool(args, "--no-cache")
	if len(args) == 0 {
		fmt.Fprintln(env.Stderr, "describe requires a capability name")
		return api.ExitValidation
	}
	name := args[0]
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
	entry, _, err := svc.Describe(context.Background(), name)
	if err != nil {
		if se, ok := err.(*api.StructuredError); ok {
			fmt.Fprintln(env.Stderr, se.Error())
			return se.ExitCode
		}
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitInternal
	}
	if w := catalog.DeprecationWarning(entry, time.Now()); w != "" {
		fmt.Fprintln(env.Stderr, w)
	}
	if jsonOut {
		b, _ := json.MarshalIndent(entry, "", "  ")
		fmt.Fprintln(env.Stdout, string(b))
	} else {
		fmt.Fprintf(env.Stdout, "%s schema_version=%s\n", entry.Name, entry.SchemaVersion)
		fmt.Fprintln(env.Stdout, string(entry.InputSchema))
	}
	return api.ExitOK
}

func cmdRun(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("run"))
		return api.ExitOK
	}
	st := store(env)
	profile, base, args := profileAndBase(args)
	input, args := flagValue(args, "--input")
	inputFile, args := flagValue(args, "--input-file")
	idem, args := flagValue(args, "--idempotency-key")
	tenant, args := flagValue(args, "--tenant")
	jsonOut, args := flagBool(args, "--json")
	noCache, args := flagBool(args, "--no-cache")
	retryLast, args := flagBool(args, "--retry-last")
	if len(args) == 0 {
		fmt.Fprintln(env.Stderr, "run requires a capability name")
		return api.ExitValidation
	}
	name := args[0]
	if err := auth.GuardAuth(st, profile, "run"); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	c, err := clientFor(env, st, profile, base)
	if err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	svc := &catalog.Service{Client: c, Cache: catalog.NewCache(st.SchemaCacheDir(profile)), NoCache: noCache}
	opts := run.Options{
		Profile:        profile,
		BaseURL:        base,
		Capability:     name,
		InputJSON:      []byte(input),
		InputFile:      inputFile,
		IdempotencyKey: idem,
		RetryLast:      retryLast,
		NoCache:        noCache,
		JSON:           jsonOut,
		TenantHint:     tenant,
		Store:          st,
		Client:         c,
		Catalog:        svc,
	}
	result := run.Run(context.Background(), opts)
	if result.Stderr != "" {
		fmt.Fprint(env.Stderr, result.Stderr)
		if !strings.HasSuffix(result.Stderr, "\n") {
			fmt.Fprintln(env.Stderr)
		}
	}
	if result.Stdout != "" {
		fmt.Fprint(env.Stdout, result.Stdout)
		if !strings.HasSuffix(result.Stdout, "\n") {
			fmt.Fprintln(env.Stdout)
		}
	} else if jsonOut && len(result.Envelope) > 0 {
		fmt.Fprintln(env.Stdout, string(result.Envelope))
	}
	return result.ExitCode
}

func cmdMcp(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("mcp"))
		return api.ExitOK
	}
	st := store(env)
	profile, base, _ := profileAndBase(args)
	if err := auth.GuardAuth(st, profile, "mcp"); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	c, err := clientFor(env, st, profile, base)
	if err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	tok, _ := st.GetToken(profile)
	srv := mcpstdio.New(c, tok, env.Stdin, env.Stdout)
	if err := srv.Run(context.Background()); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitInternal
	}
	return api.ExitOK
}

func cmdApprovals(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("approvals"))
		return api.ExitOK
	}
	st := store(env)
	profile, base, args := profileAndBase(args)
	if err := auth.GuardAuth(st, profile, "approvals"); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	if len(args) < 2 {
		fmt.Fprint(env.Stdout, CommandHelp("approvals"))
		return api.ExitValidation
	}
	action, id := args[0], args[1]
	c, err := clientFor(env, st, profile, base)
	if err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	var res *api.Response
	switch action {
	case "accept":
		res, err = c.AcceptApproval(context.Background(), id)
	case "reject":
		res, err = c.RejectApproval(context.Background(), id)
	default:
		fmt.Fprintf(env.Stderr, "unknown approvals action %q\n", action)
		return api.ExitValidation
	}
	if err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitInternal
	}
	if res.Err != nil {
		fmt.Fprintln(env.Stderr, res.Err.Error())
		return res.Err.ExitCode
	}
	fmt.Fprintln(env.Stdout, string(res.Body))
	return api.ExitOK
}

// CaptureExecute runs Execute capturing stdout/stderr (tests).
func CaptureExecute(args []string, cfgRoot string, newClient func(string, string) *api.Client) (exit int, stdout, stderr string) {
	var out, errb bytes.Buffer
	exit = Execute(Env{
		Args:       args,
		Stdout:     &out,
		Stderr:     &errb,
		ConfigRoot: cfgRoot,
		NewClient:  newClient,
	})
	return exit, out.String(), errb.String()
}
