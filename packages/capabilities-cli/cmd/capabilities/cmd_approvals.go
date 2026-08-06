package main

import (
	"context"
	"fmt"
	"strings"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func cmdApprovals(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("approvals"))
		return api.ExitOK
	}
	st := store(env)
	profile, base, args := profileAndBase(args)
	// Usage before auth: bare `approvals` is help, not a failed invoke.
	if len(args) == 0 {
		fmt.Fprint(env.Stdout, CommandHelp("approvals"))
		return api.ExitOK
	}
	if err := auth.GuardAuth(st, profile, "approvals"); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitAuth
	}
	action := args[0]
	if action != "accept" && action != "reject" {
		fmt.Fprintf(env.Stderr, "unknown approvals action %q\n", action)
		fmt.Fprint(env.Stdout, CommandHelp("approvals"))
		return api.ExitValidation
	}
	if len(args) < 2 || strings.TrimSpace(args[1]) == "" {
		fmt.Fprintf(env.Stderr, "approvals %s requires <id>\n", action)
		fmt.Fprintf(env.Stderr, "USAGE: capabilities approvals %s <id>\n", action)
		return api.ExitValidation
	}
	id := args[1]
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
