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
	"github.com/rawphp/capabilities-cli/internal/mcpstdio"
	"github.com/rawphp/capabilities-cli/internal/run"
)

// Version is the CLI version string.
var Version = "0.2.0"

// BinaryName is the product binary name (D-016).
const BinaryName = "capabilities"

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
}

// Execute parses args and runs a subcommand. Returns process exit code.
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
		fmt.Fprintf(env.Stderr, "unknown command %q\n\n%s", cmd, RootHelp())
		return api.ExitValidation
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
	if jsonOut {
		fmt.Fprintln(env.Stdout, string(catalog.EnvelopeJSON(list)))
	} else {
		for _, cap := range list {
			line := cap.Name
			if cap.Deprecated {
				line += " (deprecated)"
			}
			fmt.Fprintln(env.Stdout, line)
		}
	}
	return api.ExitOK
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
