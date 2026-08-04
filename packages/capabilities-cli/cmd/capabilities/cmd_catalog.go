package main

import (
	"context"
	"fmt"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
)

func cmdCatalog(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("catalog"))
		return api.ExitOK
	}
	st := store(env)
	profile, base, args := profileAndBase(args)
	jsonOut, args := flagBool(args, "--json")
	flat, args := flagBool(args, "--flat")
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
		// Agent contract: full machine map (unchanged shape).
		fmt.Fprintln(env.Stdout, string(catalog.EnvelopeJSON(list)))
	} else if flat {
		// Previous flat name → domain verb listing.
		fmt.Fprint(env.Stdout, catalog.FormatHumanFlat(list))
	} else {
		// Human default: domain index (map of the territory).
		fmt.Fprint(env.Stdout, catalog.FormatHumanDomainIndex(list))
	}
	return api.ExitOK
}

// cmdDomainOrUnknown handles synthesized domain/verb commands or unknown argv[0].
