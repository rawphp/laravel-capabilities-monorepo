package api

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"
)

func TestParseRetryAfterDeltaSeconds(t *testing.T) {
	now := time.Date(2026, 9, 24, 12, 0, 0, 0, time.UTC)
	cases := map[string]int{
		"":     0,
		"30":   30,
		" 7 ":  7,
		"0":    0,
		"-5":   0,
		"soon": 0,
		"1.5":  0,
	}
	for in, want := range cases {
		if got := ParseRetryAfter(in, now); got != want {
			t.Errorf("ParseRetryAfter(%q) = %d, want %d", in, got, want)
		}
	}
}

func TestParseRetryAfterHTTPDate(t *testing.T) {
	now := time.Date(2026, 9, 24, 12, 0, 0, 0, time.UTC)
	future := now.Add(90*time.Second + 400*time.Millisecond).Format(http.TimeFormat)
	// HTTP-date has second precision; 90s ahead stays 90.
	if got := ParseRetryAfter(future, now); got != 90 {
		t.Fatalf("future date: got %d", got)
	}
	past := now.Add(-time.Minute).Format(http.TimeFormat)
	if got := ParseRetryAfter(past, now); got != 0 {
		t.Fatalf("past date: got %d", got)
	}
	sub := now.Add(500 * time.Millisecond)
	// Sub-second remainder rounds up so callers never retry early.
	if got := ParseRetryAfter(now.Add(2*time.Second).Format(http.TimeFormat), sub); got != 2 {
		t.Fatalf("rounding: got %d", got)
	}
}

func rateLimitedServer(t *testing.T, header, body string) *Client {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if header != "" {
			w.Header().Set("Retry-After", header)
		}
		w.WriteHeader(http.StatusTooManyRequests)
		w.Write([]byte(body))
	}))
	t.Cleanup(srv.Close)
	c := NewClient(srv.URL, "tok")
	c.HTTP = srv.Client()
	return c
}

func TestRateLimitedEnvelopeCarriesRetryAfterHeader(t *testing.T) {
	c := rateLimitedServer(t, "42", `{"ok":false,"error":{"code":"rate_limited","message":"slow down","retryable":true}}`)
	res, err := c.InvokeCapability(context.Background(), "x", nil, "k")
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeRateLimited || res.Err.RetryAfter != 42 {
		t.Fatalf("got %#v", res.Err)
	}
	if res.Err.PublicData()["retry_after"] != 42 {
		t.Fatalf("public data %#v", res.Err.PublicData())
	}
}

func TestNonEnvelope429CarriesRetryAfterHeader(t *testing.T) {
	c := rateLimitedServer(t, "15", `{"message":"Too Many Attempts."}`)
	res, err := c.Health(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeRateLimited || res.Err.RetryAfter != 15 {
		t.Fatalf("got %#v", res.Err)
	}
}

func TestRateLimitedWithoutHeaderHasNoRetryAfter(t *testing.T) {
	c := rateLimitedServer(t, "", `{"ok":false,"error":{"code":"rate_limited","message":"slow down","retryable":true}}`)
	res, _ := c.Health(context.Background())
	if res.Err == nil || res.Err.RetryAfter != 0 {
		t.Fatalf("got %#v", res.Err)
	}
	if _, ok := res.Err.PublicData()["retry_after"]; ok {
		t.Fatal("retry_after must be omitted when unknown")
	}
	b, _ := json.Marshal(res.Err)
	var m map[string]any
	_ = json.Unmarshal(b, &m)
	if _, ok := m["retry_after"]; ok {
		t.Fatalf("json should omit retry_after: %s", b)
	}
}

func TestRetryAfterIgnoredOnNon429(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Retry-After", "120")
		w.WriteHeader(http.StatusServiceUnavailable)
		w.Write([]byte(`{"ok":false,"error":{"code":"internal","message":"maintenance"}}`))
	}))
	t.Cleanup(srv.Close)
	c := NewClient(srv.URL, "tok")
	c.HTTP = srv.Client()
	res, _ := c.Health(context.Background())
	if res.Err == nil || res.Err.RetryAfter != 0 {
		t.Fatalf("got %#v", res.Err)
	}
}

func TestEnvelopeRetryAfterKeptWhenHeaderAbsent(t *testing.T) {
	c := rateLimitedServer(t, "", `{"ok":false,"error":{"code":"rate_limited","message":"slow down","retryable":true,"retry_after":9}}`)
	res, _ := c.Health(context.Background())
	if res.Err == nil || res.Err.RetryAfter != 9 {
		t.Fatalf("got %#v", res.Err)
	}
}

func TestRetryAfterHeaderWinsOverEnvelope(t *testing.T) {
	c := rateLimitedServer(t, "20", `{"ok":false,"error":{"code":"rate_limited","message":"slow down","retryable":true,"retry_after":9}}`)
	res, _ := c.Health(context.Background())
	if res.Err == nil || res.Err.RetryAfter != 20 {
		t.Fatalf("got %#v", res.Err)
	}
}
