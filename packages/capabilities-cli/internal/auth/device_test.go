package auth

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
)

// deviceStart mirrors the core fake AuthTokenIssuer::issueDeviceCode shape
// (RFC 8628 start response wrapped in the D-018 ok envelope).
const deviceStart = `{"ok":true,"data":{"device_code":"host-device-code","user_code":"HOST-USER","verification_uri":"https://example.test/device","expires_in":600,"interval":5},"meta":{"flow":"device_code"}}`

type deviceServer struct {
	start      string
	polls      []string // token endpoint responses, served in order (last repeats)
	pollBodies []map[string]any
}

func (d *deviceServer) serve(t *testing.T) *api.Client {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case api.PathAuthDevice:
			w.Write([]byte(d.start))
		case api.PathAuthToken:
			var body map[string]any
			_ = json.NewDecoder(r.Body).Decode(&body)
			d.pollBodies = append(d.pollBodies, body)
			i := len(d.pollBodies) - 1
			if i >= len(d.polls) {
				i = len(d.polls) - 1
			}
			w.Write([]byte(d.polls[i]))
		default:
			t.Fatalf("unexpected path %s", r.URL.Path)
		}
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "")
	c.HTTP = srv.Client()
	return c
}

func recordSleeps(waits *[]time.Duration) func(context.Context, time.Duration) error {
	return func(_ context.Context, d time.Duration) error {
		*waits = append(*waits, d)
		return nil
	}
}

func TestDeviceLoginPollsTokenEndpointUntilAuthorized(t *testing.T) {
	st := tempStore(t)
	d := &deviceServer{start: deviceStart, polls: []string{
		`{"ok":true,"data":{"status":"authorization_pending"}}`,
		`{"ok":true,"data":{"token_type":"Bearer","access_token":"host-issued-token","expires_in":3600}}`,
	}}
	c := d.serve(t)
	var prompt bytes.Buffer
	var waits []time.Duration

	res, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Prompt: &prompt, Sleep: recordSleeps(&waits)})
	if err != nil {
		t.Fatal(err)
	}
	if res.Flow != "device" || !res.TokenPresent {
		t.Fatalf("%#v", res)
	}
	if tok, _ := st.GetToken("default"); tok != "host-issued-token" {
		t.Fatalf("token %q", tok)
	}
	if base, _ := st.GetBaseURL("default"); base != c.BaseURL {
		t.Fatalf("base %q", base)
	}
	if !strings.Contains(prompt.String(), "HOST-USER") || !strings.Contains(prompt.String(), "https://example.test/device") {
		t.Fatalf("prompt %q", prompt.String())
	}
	if strings.Contains(prompt.String(), "host-issued-token") || strings.Contains(prompt.String(), "host-device-code") {
		t.Fatal("secret leaked into prompt")
	}
	// Server interval 5s is floored to 10s: auth routes are throttled (6/min by default).
	if len(waits) != 2 || waits[0] != 10*time.Second || waits[1] != 10*time.Second {
		t.Fatalf("waits %v", waits)
	}
	if len(d.pollBodies) != 2 {
		t.Fatalf("polls %d", len(d.pollBodies))
	}
	body := d.pollBodies[0]
	if body["grant_type"] != DeviceCodeGrantType || body["device_code"] != "host-device-code" || body["client_id"] != "capabilities-cli" {
		t.Fatalf("poll body %#v", body)
	}
}

func TestDeviceLoginSlowDownBacksOff(t *testing.T) {
	st := tempStore(t)
	d := &deviceServer{start: deviceStart, polls: []string{
		`{"ok":true,"data":{"status":"slow_down"}}`,
		`{"ok":true,"data":{"access_token":"t"}}`,
	}}
	c := d.serve(t)
	var waits []time.Duration
	if _, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)}); err != nil {
		t.Fatal(err)
	}
	if len(waits) != 2 || waits[1] != 15*time.Second {
		t.Fatalf("waits %v", waits)
	}
}

func TestDeviceLoginHonoursServerIntervalAboveFloor(t *testing.T) {
	st := tempStore(t)
	start := `{"ok":true,"data":{"device_code":"dc","user_code":"U","verification_uri":"https://v","expires_in":600,"interval":20}}`
	d := &deviceServer{start: start, polls: []string{`{"ok":true,"data":{"access_token":"t"}}`}}
	c := d.serve(t)
	var waits []time.Duration
	if _, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)}); err != nil {
		t.Fatal(err)
	}
	if len(waits) != 1 || waits[0] != 20*time.Second {
		t.Fatalf("waits %v", waits)
	}
}

func TestDeviceLoginBacksOffOnRateLimit(t *testing.T) {
	st := tempStore(t)
	polls := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == api.PathAuthDevice {
			w.Write([]byte(deviceStart))
			return
		}
		polls++
		switch polls {
		case 1:
			w.Header().Set("Retry-After", "30")
			w.WriteHeader(http.StatusTooManyRequests)
			w.Write([]byte(`{"ok":false,"error":{"code":"rate_limited","message":"slow"}}`))
		case 2:
			// throttle page without Retry-After → interval grows by 5s
			w.WriteHeader(http.StatusTooManyRequests)
			w.Write([]byte(`Too Many Attempts.`))
		default:
			w.Write([]byte(`{"ok":true,"data":{"access_token":"t"}}`))
		}
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "")
	c.HTTP = srv.Client()
	var waits []time.Duration
	if _, err := LoginDeviceCode(context.Background(), st, c, "default", srv.URL, DeviceFlow{Sleep: recordSleeps(&waits)}); err != nil {
		t.Fatal(err)
	}
	want := []time.Duration{10 * time.Second, 30 * time.Second, 35 * time.Second}
	if len(waits) != len(want) {
		t.Fatalf("waits %v", waits)
	}
	for i := range want {
		if waits[i] != want[i] {
			t.Fatalf("waits %v want %v", waits, want)
		}
	}
}

func TestDeviceLoginAcceptsRFCErrorField(t *testing.T) {
	st := tempStore(t)
	d := &deviceServer{start: deviceStart, polls: []string{
		`{"ok":true,"data":{"error":"authorization_pending"}}`,
		`{"ok":true,"data":{"access_token":"t"}}`,
	}}
	c := d.serve(t)
	var waits []time.Duration
	if _, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)}); err != nil {
		t.Fatal(err)
	}
}

func TestDeviceLoginDeniedAndExpiredExitAuthWithoutWriting(t *testing.T) {
	for _, status := range []string{"access_denied", "expired_token"} {
		t.Run(status, func(t *testing.T) {
			st := tempStore(t)
			_ = st.SetBaseURL("default", "https://good.example")
			_ = st.SetToken("default", "good-tok")
			d := &deviceServer{start: deviceStart, polls: []string{`{"ok":true,"data":{"status":"` + status + `"}}`}}
			c := d.serve(t)
			var waits []time.Duration
			_, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)})
			var se *api.StructuredError
			if !errors.As(err, &se) || se.ExitCode != api.ExitAuth {
				t.Fatalf("err %v", err)
			}
			if base, _ := st.GetBaseURL("default"); base != "https://good.example" {
				t.Fatalf("base clobbered %q", base)
			}
			if tok, _ := st.GetToken("default"); tok != "good-tok" {
				t.Fatalf("token clobbered %q", tok)
			}
		})
	}
}

func TestDeviceLoginStopsAtExpiresIn(t *testing.T) {
	st := tempStore(t)
	start := `{"ok":true,"data":{"device_code":"dc","user_code":"U","verification_uri":"https://v","expires_in":25,"interval":10}}`
	d := &deviceServer{start: start, polls: []string{`{"ok":true,"data":{"status":"authorization_pending"}}`}}
	c := d.serve(t)
	var waits []time.Duration
	_, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)})
	var se *api.StructuredError
	if !errors.As(err, &se) || se.ExitCode != api.ExitAuth {
		t.Fatalf("err %v", err)
	}
	if len(d.pollBodies) != 2 {
		t.Fatalf("expected 2 polls inside a 25s window, got %d", len(d.pollBodies))
	}
}

func TestDeviceLoginDefaultsIntervalAndExpiry(t *testing.T) {
	st := tempStore(t)
	start := `{"ok":true,"data":{"device_code":"dc","user_code":"U","verification_uri":"https://v"}}`
	d := &deviceServer{start: start, polls: []string{`{"ok":true,"data":{"status":"authorization_pending"}}`}}
	c := d.serve(t)
	var waits []time.Duration
	_, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)})
	if err == nil {
		t.Fatal("expected expiry")
	}
	if waits[0] != minDeviceInterval {
		t.Fatalf("default interval %v", waits[0])
	}
	if want := int(defaultDeviceExpiry / minDeviceInterval); len(d.pollBodies) != want {
		t.Fatalf("polls %d want %d", len(d.pollBodies), want)
	}
}

func TestDeviceLoginFailsClosedOnBadResponses(t *testing.T) {
	cases := map[string]deviceServer{
		"missing device_code": {start: `{"ok":true,"data":{"user_code":"U"}}`, polls: []string{`{"ok":true,"data":{"access_token":"t"}}`}},
		"unknown poll status": {start: deviceStart, polls: []string{`{"ok":true,"data":{"status":"weird"}}`}},
		"empty poll data":     {start: deviceStart, polls: []string{`{"ok":true,"data":{}}`}},
		"poll error envelope": {start: deviceStart, polls: []string{`{"ok":false,"error":{"code":"unauthenticated","message":"no"}}`}},
	}
	for name, d := range cases {
		t.Run(name, func(t *testing.T) {
			st := tempStore(t)
			c := d.serve(t)
			var waits []time.Duration
			if _, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: recordSleeps(&waits)}); err == nil {
				t.Fatal("expected failure")
			}
			if st.HasToken("default") {
				t.Fatal("token written on failed login")
			}
			if _, err := st.GetBaseURL("default"); err == nil {
				t.Fatal("base URL written on failed login")
			}
		})
	}
}

func TestDeviceLoginSleepErrorAborts(t *testing.T) {
	st := tempStore(t)
	d := &deviceServer{start: deviceStart, polls: []string{`{"ok":true,"data":{"access_token":"t"}}`}}
	c := d.serve(t)
	boom := errors.New("interrupted")
	_, err := LoginDeviceCode(context.Background(), st, c, "default", c.BaseURL, DeviceFlow{Sleep: func(context.Context, time.Duration) error { return boom }})
	if !errors.Is(err, boom) {
		t.Fatalf("err %v", err)
	}
	if len(d.pollBodies) != 0 {
		t.Fatal("polled after interrupted wait")
	}
}

func TestSleepContextHonoursCancel(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	if err := sleepContext(ctx, time.Hour); !errors.Is(err, context.Canceled) {
		t.Fatalf("err %v", err)
	}
	if err := sleepContext(context.Background(), time.Millisecond); err != nil {
		t.Fatal(err)
	}
}
