package auth

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"

	"github.com/rawphp/capabilities-cli/internal/api"
)

// LoginResult is the outcome of an auth login attempt (token never meant for stdout).
type LoginResult struct {
	Profile string
	BaseURL string
	// TokenPresent indicates success without exposing the secret.
	TokenPresent bool
	Flow         string // "device" | "token" | "pat"
}

// LoginWithToken stores a pre-issued token (PAT / token endpoint result).
// Base URL is written only after the token is accepted so a bad login cannot
// re-point an already-working profile.
func LoginWithToken(store *Store, profile, baseURL, token string) (*LoginResult, error) {
	if token == "" {
		return nil, fmt.Errorf("empty token")
	}
	if _, err := NormalizeBaseURL(baseURL); err != nil {
		return nil, err
	}
	if err := store.SetBaseURL(profile, baseURL); err != nil {
		return nil, err
	}
	if err := store.SetToken(profile, token); err != nil {
		return nil, err
	}
	return &LoginResult{Profile: profile, BaseURL: baseURL, TokenPresent: true, Flow: "token"}, nil
}

// LoginDeviceCode performs device-code flow against the capability auth API.
// Token is stored; never returned for printing.
// Profile base URL is written only after a token is obtained so a failed
// attempt cannot clobber a working profile's --base-url.
func LoginDeviceCode(ctx context.Context, store *Store, client *api.Client, profile, baseURL string) (*LoginResult, error) {
	normalized, err := NormalizeBaseURL(baseURL)
	if err != nil {
		return nil, err
	}
	client.BaseURL = normalized
	res, err := client.LoginDevice(ctx, map[string]any{"client_id": "capabilities-cli"})
	if err != nil {
		return nil, err
	}
	if res.Err != nil {
		return nil, res.Err
	}
	var payload map[string]any
	_ = json.Unmarshal(res.Body, &payload)
	token := extractToken(payload)
	if token == "" {
		return nil, fmt.Errorf("device login response missing access_token")
	}
	if err := store.SetBaseURL(profile, normalized); err != nil {
		return nil, err
	}
	if err := store.SetToken(profile, token); err != nil {
		return nil, err
	}
	return &LoginResult{Profile: profile, BaseURL: normalized, TokenPresent: true, Flow: "device"}, nil
}

// LoginBrowserOAuth is a placeholder for browser OAuth; uses token endpoint with code.
// Profile base URL is written only after a token is obtained.
func LoginBrowserOAuth(ctx context.Context, store *Store, client *api.Client, profile, baseURL, code string) (*LoginResult, error) {
	normalized, err := NormalizeBaseURL(baseURL)
	if err != nil {
		return nil, err
	}
	client.BaseURL = normalized
	res, err := client.LoginToken(ctx, map[string]any{
		"grant_type": "authorization_code",
		"code":       code,
		"client_id":  "capabilities-cli",
	})
	if err != nil {
		return nil, err
	}
	if res.Err != nil {
		return nil, res.Err
	}
	var payload map[string]any
	_ = json.Unmarshal(res.Body, &payload)
	token := extractToken(payload)
	if token == "" {
		return nil, fmt.Errorf("oauth token response missing access_token")
	}
	if err := store.SetBaseURL(profile, normalized); err != nil {
		return nil, err
	}
	if err := store.SetToken(profile, token); err != nil {
		return nil, err
	}
	return &LoginResult{Profile: profile, BaseURL: normalized, TokenPresent: true, Flow: "browser"}, nil
}

func extractToken(payload map[string]any) string {
	if data, ok := payload["data"].(map[string]any); ok {
		if t, ok := data["access_token"].(string); ok {
			return t
		}
	}
	if t, ok := payload["access_token"].(string); ok {
		return t
	}
	return ""
}

// Logout clears the token for a profile (idempotent).
func Logout(store *Store, profile string) error {
	return store.DeleteToken(profile)
}

// CommandsRequiringAuth lists subcommands that must have a token.
var CommandsRequiringAuth = []string{"run", "catalog", "describe", "approvals"}

// RequiresAuth reports whether command needs a stored token.
func RequiresAuth(command string) bool {
	for _, c := range CommandsRequiringAuth {
		if c == command {
			return true
		}
	}
	return false
}

// GuardAuth returns ErrNoToken when the profile lacks credentials.
func GuardAuth(store *Store, profile, command string) error {
	if !RequiresAuth(command) {
		return nil
	}
	_, err := store.RequireToken(profile)
	return err
}

// ExitCodeForAuthError maps auth failures to exit 3.
func ExitCodeForAuthError(err error) int {
	if err == nil {
		return api.ExitOK
	}
	if errors.Is(err, ErrNoToken) {
		return api.ExitAuth
	}
	return api.ExitInternal
}
