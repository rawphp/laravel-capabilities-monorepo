package main

import (
	"context"
	"fmt"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/mcpstdio"
)

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
