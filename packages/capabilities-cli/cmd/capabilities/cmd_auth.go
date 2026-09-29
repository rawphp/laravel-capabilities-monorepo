package main

import (
	"context"
	"encoding/json"
	"fmt"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
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
		jsonOut, rest := flagBool(rest, "--json")
		_ = rest
		var err error
		var result *auth.LoginResult
		c := api.NewClient(base, "")
		if env.NewClient != nil {
			c = env.NewClient(base, "")
		}
		if token != "" {
			result, err = auth.LoginWithToken(context.Background(), st, c, profile, base, token)
		} else if code != "" {
			_, err = auth.LoginBrowserOAuth(context.Background(), st, c, profile, base, code)
		} else {
			_, err = auth.LoginDeviceCode(context.Background(), st, c, profile, base, auth.DeviceFlow{Prompt: env.Stderr, Sleep: env.Sleep})
		}
		if err != nil {
			se, ok := err.(*api.StructuredError)
			if !ok {
				se = api.MapErrorCode(api.CodeInternal)
				se.Message = err.Error()
			}
			if jsonOut {
				writeStructuredErrorStdout(env, se)
			}
			fmt.Fprintln(env.Stderr, se.Error())
			return se.ExitCode
		}
		caller := ""
		if result != nil {
			caller = result.Caller
		}
		if caller != "" && caller != "cli" {
			fmt.Fprintf(env.Stderr, "warning: server treats this token as caller %q, not cli; capabilities exposed only to cli will be hidden (mint it with the capabilities:cli ability)\n", caller)
		}
		// Never print token.
		if jsonOut {
			data := map[string]any{"profile": profile, "base_url": base, "logged_in": true}
			if caller != "" {
				data["caller"] = caller
			}
			payload := map[string]any{"ok": true, "data": data}
			b, _ := json.MarshalIndent(payload, "", "  ")
			fmt.Fprintln(env.Stdout, string(b))
			return api.ExitOK
		}
		fmt.Fprintf(env.Stdout, "logged in profile=%s base_url=%s\n", profile, base)
		return api.ExitOK
	case "logout":
		if err := auth.Logout(st, profile); err != nil {
			fmt.Fprintln(env.Stderr, err.Error())
			return api.ExitValidation
		}
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
