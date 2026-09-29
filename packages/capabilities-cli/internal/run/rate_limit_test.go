package run

import (
	"context"
	"encoding/json"
	"net/http"
	"strings"
	"testing"
)

func rateLimitedHandler(retryAfter, body string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if retryAfter != "" {
			w.Header().Set("Retry-After", retryAfter)
		}
		w.WriteHeader(http.StatusTooManyRequests)
		w.Write([]byte(body))
	}
}

func stdoutError(t *testing.T, stdout string) map[string]any {
	t.Helper()
	var env map[string]any
	if err := json.Unmarshal([]byte(stdout), &env); err != nil {
		t.Fatalf("stdout is not JSON: %v: %s", err, stdout)
	}
	if env["ok"] != false {
		t.Fatalf("ok should be false: %s", stdout)
	}
	e, ok := env["error"].(map[string]any)
	if !ok {
		t.Fatalf("no error object: %s", stdout)
	}
	return e
}

func TestRateLimitedEnvelopeGainsRetryAfterOnStdout(t *testing.T) {
	opts, _ := harness(t, rateLimitedHandler("42",
		`{"ok":false,"error":{"code":"rate_limited","message":"Rate limit exceeded (per_minute).","retryable":true,"request_id":"r9","approval_id":null}}`))
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitRateLimit {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	e := stdoutError(t, res.Stdout)
	if e["retry_after"] != float64(42) {
		t.Fatalf("retry_after: %s", res.Stdout)
	}
	// Server fields survive untouched.
	if e["code"] != "rate_limited" || e["message"] != "Rate limit exceeded (per_minute)." || e["request_id"] != "r9" || e["retryable"] != true {
		t.Fatalf("server fields changed: %s", res.Stdout)
	}
	if !strings.Contains(res.Stderr, "retry after 42s") {
		t.Fatalf("stderr hint missing: %q", res.Stderr)
	}
}

func TestNonEnvelope429BecomesRateLimitedEnvelopeWithRetryAfter(t *testing.T) {
	opts, _ := harness(t, rateLimitedHandler("15", `{"message":"Too Many Attempts."}`))
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitRateLimit {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	e := stdoutError(t, res.Stdout)
	if e["code"] != "rate_limited" || e["retry_after"] != float64(15) || e["message"] != "Too Many Attempts." {
		t.Fatalf("stdout: %s", res.Stdout)
	}
}

func TestRateLimitedWithoutRetryAfterKeepsServerBody(t *testing.T) {
	body := `{"ok":false,"error":{"code":"rate_limited","message":"slow down","retryable":true}}`
	opts, _ := harness(t, rateLimitedHandler("", body))
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitRateLimit {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	if res.Stdout != body {
		t.Fatalf("stdout rewritten: %s", res.Stdout)
	}
	if strings.Contains(res.Stderr, "retry after") {
		t.Fatalf("unexpected hint: %q", res.Stderr)
	}
}
