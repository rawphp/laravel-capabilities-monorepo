package main

import (
	"context"
	"encoding/json"
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
	// Help wins before any side effects (login/logout) or flag requirements.
	if sub == "help" || sub == "-h" || sub == "--help" || wantsHelp(rest) {
		fmt.Fprint(env.Stdout, CommandHelp("auth"))
		return api.ExitOK
	}
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
		jsonOut, rest := flagBool(rest, "--json")
		_ = rest
		p := st.Status(profile)
		// Never print token.
		if jsonOut {
			payload := map[string]any{
				"ok": true,
				"data": map[string]any{
					"profile":   p.Name,
					"base_url":  p.BaseURL,
					"logged_in": p.LoggedIn,
				},
			}
			b, _ := json.MarshalIndent(payload, "", "  ")
			fmt.Fprintln(env.Stdout, string(b))
			return api.ExitOK
		}
		fmt.Fprintf(env.Stdout, "profile=%s base_url=%s logged_in=%v\n", p.Name, p.BaseURL, p.LoggedIn)
		return api.ExitOK
	case "list", "profiles":
		jsonOut, rest := flagBool(rest, "--json")
		_ = rest
		profiles := st.ListProfiles()
		// Never print tokens.
		if jsonOut {
			rows := make([]map[string]any, 0, len(profiles))
			for _, p := range profiles {
				rows = append(rows, map[string]any{
					"profile":   p.Name,
					"base_url":  p.BaseURL,
					"logged_in": p.LoggedIn,
				})
			}
			payload := map[string]any{"ok": true, "data": map[string]any{"profiles": rows}}
			b, _ := json.MarshalIndent(payload, "", "  ")
			fmt.Fprintln(env.Stdout, string(b))
			return api.ExitOK
		}
		if len(profiles) == 0 {
			fmt.Fprintln(env.Stdout, "No auth profiles yet. Run: capabilities auth login --base-url=URL")
			return api.ExitOK
		}
		fmt.Fprintln(env.Stdout, "PROFILES:")
		for _, p := range profiles {
			fmt.Fprintf(env.Stdout, "  %-16s  base_url=%-40s  logged_in=%v\n", p.Name, p.BaseURL, p.LoggedIn)
		}
		fmt.Fprintln(env.Stdout, "Next: capabilities auth status --profile=NAME")
		return api.ExitOK
	default:
		fmt.Fprintf(env.Stderr, "unknown auth subcommand %q\n", sub)
		return api.ExitValidation
	}
}
