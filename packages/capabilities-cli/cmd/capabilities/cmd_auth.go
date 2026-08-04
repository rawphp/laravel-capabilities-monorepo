package main

import (
	"context"
	"fmt"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
)

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
