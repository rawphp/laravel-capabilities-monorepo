package main

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"strings"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
	"github.com/rawphp/capabilities-cli/internal/flagschema"
	"github.com/rawphp/capabilities-cli/internal/run"
)

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
			// Match domain not_found: machine envelope on stdout + short stderr line.
			if len(se.Body) > 0 {
				fmt.Fprintln(env.Stdout, string(se.Body))
			} else {
				writeStructuredErrorStdout(env, se)
			}
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

func writeStructuredErrorStdout(env Env, se *api.StructuredError) {
	envBody := api.ErrorEnvelope{
		OK: false,
		Error: &api.ErrorBody{
			Code:       se.Code,
			Message:    se.Message,
			Violations: se.Violations,
			ApprovalID: se.ApprovalID,
			Retryable:  se.Retryable,
			RequestID:  se.RequestID,
		},
	}
	b, _ := json.MarshalIndent(envBody, "", "  ")
	fmt.Fprintln(env.Stdout, string(b))
}

func cmdRun(env Env, args []string) int {
	// Help before network: generic run help, or schema-first help when a name is given
	// (parity with `capabilities <domain> <verb> --help`).
	if wantsHelp(args) {
		profile, base, rest := profileAndBase(args)
		jsonOut, rest := flagBool(rest, "--json")
		noCache, rest := flagBool(rest, "--no-cache")
		rest = stripHelpFlags(rest)
		// Drop other run flags so the first leftover is the capability name.
		_, rest = flagValue(rest, "--input")
		_, rest = flagValue(rest, "--input-file")
		_, rest = flagValue(rest, "--idempotency-key")
		_, rest = flagValue(rest, "--tenant")
		_, rest = flagBool(rest, "--human")
		_, rest = flagBool(rest, "--retry-last")
		name, rest := takeFirstPositional(rest)
		_ = rest
		if name != "" {
			return writeCapabilityHelp(env, "", "", name, jsonOut, profile, base, noCache)
		}
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
	human, args := flagBool(args, "--human")
	noCache, args := flagBool(args, "--no-cache")
	retryLast, args := flagBool(args, "--retry-last")
	if len(args) == 0 {
		fmt.Fprintln(env.Stderr, "run requires a capability name")
		return api.ExitValidation
	}
	name := args[0]
	flagArgs := args[1:]
	return invokeCapability(env, st, profile, base, name, input, inputFile, idem, tenant, jsonOut, human, noCache, retryLast, flagArgs)
}

// invokeCapability is the single validate→key→POST path for run and domain/verb (ORI-175).
func invokeCapability(
	env Env,
	st *auth.Store,
	profile, base, name string,
	input, inputFile, idem, tenant string,
	jsonOut, human, noCache, retryLast bool,
	flagArgs []string,
) int {
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

	// Load schema for flag merge (cache / describe).
	var schemaJSON []byte
	entry, _, derr := svc.Describe(context.Background(), name)
	if derr == nil && entry != nil {
		schemaJSON = entry.InputSchema
	}

	fs, ferr := flagschema.FromJSONSchema(schemaJSON)
	if ferr != nil {
		fmt.Fprintln(env.Stderr, ferr.Error())
		return api.ExitValidation
	}

	// Base JSON from --input / --input-file.
	var baseJSON []byte
	if inputFile != "" {
		b, rerr := os.ReadFile(inputFile)
		if rerr != nil {
			fmt.Fprintln(env.Stderr, "read input file:", rerr.Error())
			return api.ExitValidation
		}
		baseJSON = b
	} else if input != "" {
		baseJSON = []byte(input)
	}

	flagMap, rest, cerr := flagschema.CollectFlags(flagArgs)
	if cerr != nil {
		fmt.Fprintln(env.Stderr, cerr.Error())
		return api.ExitValidation
	}
	if len(rest) > 0 {
		fmt.Fprintf(env.Stderr, "unexpected arguments: %s (see --help)\n", strings.Join(rest, " "))
		return api.ExitValidation
	}

	merged, merr := fs.MergeJSON(baseJSON, flagMap)
	if merr != nil {
		fmt.Fprintln(env.Stderr, merr.Error())
		// Point agents at help for required / usage errors.
		if strings.Contains(merr.Error(), "required") || strings.Contains(merr.Error(), "unknown flag") {
			fmt.Fprintln(env.Stderr, "hint: capabilities run", name, "--help")
		}
		return api.ExitValidation
	}

	opts := run.Options{
		Profile:        profile,
		BaseURL:        base,
		Capability:     name,
		InputJSON:      merged,
		IdempotencyKey: idem,
		RetryLast:      retryLast,
		NoCache:        noCache,
		JSON:           true, // always machine envelope
		Human:          human,
		TenantHint:     tenant,
		Store:          st,
		Client:         c,
		Catalog:        svc,
	}
	// JSON field kept true for compatibility; Run always writes envelope to stdout.
	_ = jsonOut
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
	} else if len(result.Envelope) > 0 {
		fmt.Fprintln(env.Stdout, string(result.Envelope))
	}
	return result.ExitCode
}
