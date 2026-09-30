package main

import (
	"bytes"
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
	if code := refuseUnsafeSegment(env, "capability name", name); code != api.ExitOK {
		return code
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
	svc := &catalog.Service{Client: c, Cache: catalog.PrincipalCache(st.SchemaCacheDir(profile), c), NoCache: noCache}
	entry, _, err := svc.Describe(context.Background(), name)
	if err != nil {
		if se, ok := err.(*api.StructuredError); ok {
			return writeErrorEnvelope(env, se)
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

// refuseUnsafeSegment writes a validation_failed envelope and returns exit 2
// when s is not a single safe URL path segment (C-401), before auth or HTTP.
// The API client enforces the same rule; this is the friendly usage error.
func refuseUnsafeSegment(env Env, kind, s string) int {
	if se := api.CheckPathSegment(kind, s); se != nil {
		return writeErrorEnvelope(env, se)
	}
	return api.ExitOK
}

// writeErrorEnvelope puts the machine envelope on stdout (the server's own
// D-018 body when it sent one, else one built from se) and a short line on
// stderr, and returns the exit code. Stdout is machine; stderr is human.
func writeErrorEnvelope(env Env, se *api.StructuredError) int {
	var probe api.ErrorEnvelope
	if json.Unmarshal(se.Body, &probe) == nil && !probe.OK && probe.Error != nil {
		fmt.Fprintln(env.Stdout, string(se.Body))
	} else {
		writeStructuredErrorStdout(env, se)
	}
	fmt.Fprintln(env.Stderr, se.Error())
	return se.ExitCode
}

func writeStructuredErrorStdout(env Env, se *api.StructuredError) {
	exit := se.ExitCode
	envBody := api.ErrorEnvelope{
		OK: false,
		Error: &api.ErrorBody{
			Code:       se.Code,
			Message:    se.Message,
			Violations: se.Violations,
			ApprovalID: se.ApprovalID,
			Retryable:  se.Retryable,
			RequestID:  se.RequestID,
			RetryAfter: se.RetryAfter,
			CLIExit:    &exit,
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
	return invokeCapability(env, st, profile, base, name, input, inputFile, idem, jsonOut, human, noCache, retryLast, flagArgs)
}

// invokeCapability is the single validate→key→POST path for run and domain/verb (ORI-175).
func invokeCapability(
	env Env,
	st *auth.Store,
	profile, base, name string,
	input, inputFile, idem string,
	jsonOut, human, noCache, retryLast bool,
	flagArgs []string,
) int {
	if code := refuseUnsafeSegment(env, "capability name", name); code != api.ExitOK {
		return code
	}
	if err := auth.GuardAuth(st, profile, "run"); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	c, err := clientFor(env, st, profile, base)
	if err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	svc := &catalog.Service{Client: c, Cache: catalog.PrincipalCache(st.SchemaCacheDir(profile), c), NoCache: noCache}
	// JSON field kept true for compatibility; Run always writes envelope to stdout.
	_ = jsonOut

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

	attempt := func(entry *catalog.CacheEntry) *run.Result {
		var schemaJSON []byte
		if entry != nil {
			schemaJSON = entry.InputSchema
		}
		merged, flagCount, msg := mergeInput(name, schemaJSON, baseJSON, flagArgs)
		if msg != "" {
			return &run.Result{ExitCode: api.ExitValidation, Stderr: msg}
		}
		// No fresh input on --retry-last: let Run replay the stored body.
		if retryLast && len(baseJSON) == 0 && flagCount == 0 {
			merged = nil
		}
		return run.Run(context.Background(), run.Options{
			Profile:        profile,
			BaseURL:        base,
			Capability:     name,
			InputJSON:      merged,
			IdempotencyKey: idem,
			RetryLast:      retryLast,
			NoCache:        noCache,
			JSON:           true, // always machine envelope
			Human:          human,
			Store:          st,
			Client:         c,
			Catalog:        svc,
			Entry:          entry,
		})
	}

	entry, dres, derr := svc.Describe(context.Background(), name)
	if derr != nil {
		entry = nil
	}
	result := attempt(entry)
	// A cached schema rejected the input before the network. The server's
	// schema may have moved on, so check once against the live one.
	if result.ExitCode == api.ExitValidation && !result.HTTPCalled && entry != nil && dres == nil {
		if fresh, _, ferr := svc.ForceFetchDescribe(context.Background(), name); ferr == nil && !bytes.Equal(fresh.InputSchema, entry.InputSchema) {
			result = attempt(fresh)
		}
	}

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

// mergeInput merges schema flags into the base JSON. On failure it returns the
// stderr text (with a --help hint for usage errors) instead of merged input.
func mergeInput(name string, schemaJSON, baseJSON []byte, flagArgs []string) ([]byte, int, string) {
	fs, err := flagschema.FromJSONSchema(schemaJSON)
	if err != nil {
		return nil, 0, err.Error()
	}
	flagMap, rest, err := flagschema.CollectFlags(flagArgs)
	if err != nil {
		return nil, 0, err.Error()
	}
	if len(rest) > 0 {
		return nil, 0, fmt.Sprintf("unexpected arguments: %s (see --help)", strings.Join(rest, " "))
	}
	merged, err := fs.MergeJSON(baseJSON, flagMap)
	if err != nil {
		msg := err.Error()
		// Point agents at help for required / usage errors.
		if strings.Contains(msg, "required") || strings.Contains(msg, "unknown flag") {
			msg += "\nhint: capabilities run " + name + " --help"
		}
		return nil, 0, msg
	}
	return merged, len(flagMap), ""
}
