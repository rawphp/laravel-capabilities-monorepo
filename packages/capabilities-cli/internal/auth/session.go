package auth

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
)

// cliClientID identifies this binary to the host's auth issuer.
const cliClientID = "capabilities-cli"

// LoginResult is the outcome of an auth login attempt (token never meant for stdout).
type LoginResult struct {
	Profile string
	BaseURL string
	// TokenPresent indicates success without exposing the secret.
	TokenPresent bool
	Flow         string // "device" | "token" | "pat"
}

// LoginWithToken verifies a pre-issued token (PAT / API token) with one
// authenticated GET /capabilities, then stores it. Nothing is written unless
// the server accepts the token with a capability envelope, so a mistyped
// token or wrong --base-url cannot clobber an already-working profile.
func LoginWithToken(ctx context.Context, store *Store, client *api.Client, profile, baseURL, token string) (*LoginResult, error) {
	if token == "" {
		return nil, fmt.Errorf("empty token")
	}
	if _, err := profileName(profile); err != nil {
		return nil, err
	}
	normalized, err := NormalizeBaseURL(baseURL)
	if err != nil {
		return nil, err
	}
	client.BaseURL = normalized
	client.Token = token
	res, err := client.ListCapabilities(ctx)
	if err != nil {
		return nil, err
	}
	if res.Err != nil {
		return nil, res.Err
	}
	if !res.Envelope.OK {
		se := api.MapErrorCode(api.CodeInternal)
		se.Message = fmt.Sprintf("HTTP %d from %s is not a capability API response; check --base-url", res.StatusCode, normalized)
		return nil, se
	}
	if err := store.SetBaseURL(profile, normalized); err != nil {
		return nil, err
	}
	if err := store.SetToken(profile, token); err != nil {
		return nil, err
	}
	return &LoginResult{Profile: profile, BaseURL: normalized, TokenPresent: true, Flow: "token"}, nil
}

// DeviceCodeGrantType is the RFC 8628 grant polled on the token endpoint.
const DeviceCodeGrantType = "urn:ietf:params:oauth:grant-type:device_code"

const (
	// minDeviceInterval floors the poll interval: the core throttles auth
	// routes (6 requests/minute by default), so faster polling would 429.
	minDeviceInterval = 10 * time.Second
	// defaultDeviceExpiry bounds the poll loop when the server omits expires_in.
	defaultDeviceExpiry = 10 * time.Minute
	slowDownStep        = 5 * time.Second
)

// DeviceFlow wires the human prompt and the wait between polls.
type DeviceFlow struct {
	// Prompt receives user_code + verification_uri (stderr; never the token).
	Prompt io.Writer
	// Sleep waits between polls; nil waits in real time and honours ctx.
	Sleep func(ctx context.Context, d time.Duration) error
}

// LoginDeviceCode runs the RFC 8628 device-code flow: POST the device route
// for a device_code, show user_code + verification_uri, then poll the token
// route with the device-code grant every interval until the host issues a
// token, denies, or expires_in passes. The interval is at least 10 seconds;
// slow_down or an HTTP 429 lengthens it (a 429 Retry-After wins when longer).
//
// The host issuer signals a pending poll inside the ok envelope as
// data.status (or RFC data.error): authorization_pending | slow_down |
// access_denied | expired_token. Any other response without access_token
// fails closed.
//
// Profile base URL is written only after a token is obtained so a failed
// attempt cannot clobber a working profile's --base-url.
func LoginDeviceCode(ctx context.Context, store *Store, client *api.Client, profile, baseURL string, flow DeviceFlow) (*LoginResult, error) {
	normalized, err := NormalizeBaseURL(baseURL)
	if err != nil {
		return nil, err
	}
	client.BaseURL = normalized
	res, err := client.LoginDevice(ctx, map[string]any{"client_id": cliClientID})
	if err != nil {
		return nil, err
	}
	if res.Err != nil {
		return nil, res.Err
	}
	var start struct {
		Data struct {
			DeviceCode      string `json:"device_code"`
			UserCode        string `json:"user_code"`
			VerificationURI string `json:"verification_uri"`
			ExpiresIn       int    `json:"expires_in"`
			Interval        int    `json:"interval"`
		} `json:"data"`
	}
	_ = json.Unmarshal(res.Body, &start)
	if start.Data.DeviceCode == "" {
		return nil, fmt.Errorf("device login response missing device_code")
	}
	if flow.Prompt != nil {
		fmt.Fprintf(flow.Prompt, "To log in, open %s and enter code %s\nWaiting for approval…\n", start.Data.VerificationURI, start.Data.UserCode)
	}
	sleep := flow.Sleep
	if sleep == nil {
		sleep = sleepContext
	}
	interval := max(time.Duration(start.Data.Interval)*time.Second, minDeviceInterval)
	expiry := time.Duration(start.Data.ExpiresIn) * time.Second
	if expiry <= 0 {
		expiry = defaultDeviceExpiry
	}
	for waited := time.Duration(0); waited+interval <= expiry; {
		if err := sleep(ctx, interval); err != nil {
			return nil, err
		}
		waited += interval
		res, err := client.LoginToken(ctx, map[string]any{
			"grant_type":  DeviceCodeGrantType,
			"device_code": start.Data.DeviceCode,
			"client_id":   cliClientID,
		})
		if err != nil {
			return nil, err
		}
		if res.Err != nil && res.Err.Code == api.CodeRateLimited {
			interval = max(interval+slowDownStep, time.Duration(res.Err.RetryAfter)*time.Second)
			continue
		}
		if res.Err != nil {
			return nil, res.Err
		}
		var payload map[string]any
		_ = json.Unmarshal(res.Body, &payload)
		if token := extractToken(payload); token != "" {
			if err := store.SetBaseURL(profile, normalized); err != nil {
				return nil, err
			}
			if err := store.SetToken(profile, token); err != nil {
				return nil, err
			}
			return &LoginResult{Profile: profile, BaseURL: normalized, TokenPresent: true, Flow: "device"}, nil
		}
		switch status := devicePollStatus(payload); status {
		case "authorization_pending":
		case "slow_down":
			interval += slowDownStep
		case "access_denied":
			return nil, deviceAuthError("device login was denied")
		case "expired_token":
			return nil, deviceAuthError("device code expired; run auth login again")
		default:
			return nil, fmt.Errorf("device token response missing access_token (status %q)", status)
		}
	}
	return nil, deviceAuthError("device code expired; run auth login again")
}

func devicePollStatus(payload map[string]any) string {
	data, _ := payload["data"].(map[string]any)
	if s, ok := data["status"].(string); ok {
		return s
	}
	s, _ := data["error"].(string)
	return s
}

func deviceAuthError(msg string) *api.StructuredError {
	se := api.MapErrorCode(api.CodeUnauthenticated)
	se.Message = msg
	return se
}

func sleepContext(ctx context.Context, d time.Duration) error {
	t := time.NewTimer(d)
	defer t.Stop()
	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-t.C:
		return nil
	}
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
		"client_id":  cliClientID,
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

// CommandsWithoutAuth lists subcommands that never need a token. Every other
// command requires one (fail closed), so a new server-touching subcommand is
// guarded unless it is deliberately exempted here.
var CommandsWithoutAuth = []string{"help", "version", "self-update", "auth"}

// RequiresAuth reports whether command needs a stored token.
func RequiresAuth(command string) bool {
	for _, c := range CommandsWithoutAuth {
		if c == command {
			return false
		}
	}
	return true
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
