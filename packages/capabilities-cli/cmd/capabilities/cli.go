package main

import (
	"bytes"
	"fmt"
	"io"
	"os"
	"strings"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
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
