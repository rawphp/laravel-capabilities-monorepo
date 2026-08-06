package main

import (
	"context"
	"fmt"
	"io"
	"os"
	"strings"

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
	// Humans who type `capabilities mcp` in a terminal get silence + a hang.
	// MCP hosts use pipes; only hint when stdin is a real TTY (never refuse —
	// some wrappers allocate a PTY).
	if stdinIsInteractive(env.Stdin) {
		fmt.Fprintln(env.Stderr, "capabilities mcp speaks JSON-RPC over stdio for MCP hosts — not an interactive shell.")
		fmt.Fprintf(env.Stderr, "Waiting for host input. Wire Cursor/Claude/etc. to: capabilities mcp --profile=%s\n", profile)
		fmt.Fprintln(env.Stderr, "Help: capabilities help mcp")
	}
	tok, _ := st.GetToken(profile)
	srv := mcpstdio.New(c, tok, env.Stdin, env.Stdout)
	srv.Version = Version
	if err := srv.Run(context.Background()); err != nil {
		fmt.Fprintln(env.Stderr, err.Error())
		return api.ExitInternal
	}
	return api.ExitOK
}

// stdinIsInteractive is true when the reader is a character device (TTY).
// Pipes and buffers used by MCP hosts and tests return false.
func stdinIsInteractive(r io.Reader) bool {
	f, ok := r.(*os.File)
	if !ok {
		return false
	}
	fi, err := f.Stat()
	if err != nil {
		return false
	}
	return fi.Mode()&os.ModeCharDevice != 0
}

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
