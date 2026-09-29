package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/selfupdate"
)

type selfUpdateJSON struct {
	OK    bool           `json:"ok"`
	Data  map[string]any `json:"data"`
	Error *api.ErrorBody `json:"error"`
}

func decodeSelfUpdateJSON(t *testing.T, stdout string) selfUpdateJSON {
	t.Helper()
	var got selfUpdateJSON
	if err := json.Unmarshal([]byte(stdout), &got); err != nil {
		t.Fatalf("stdout is not one JSON document: %v\n%s", err, stdout)
	}
	return got
}

func TestSelfUpdateJSONSuccessOutcomes(t *testing.T) {
	cases := []struct {
		name    string
		res     selfupdate.Result
		outcome string
	}{
		{"already_latest", selfupdate.Result{Outcome: selfupdate.OutcomeAlreadyLatest, CurrentVersion: "0.4.0", LatestVersion: "0.4.0"}, "already_latest"},
		{"updated", selfupdate.Result{Outcome: selfupdate.OutcomeUpdated, CurrentVersion: "0.3.0", LatestVersion: "0.4.0"}, "updated"},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
				r := tc.res
				return &r, nil
			}
			code, out, errb := captureSelfUpdate(t, []string{"self-update", "--json"}, eng, "/tmp/fake-capabilities")
			if code != api.ExitOK {
				t.Fatalf("exit=%d stderr=%q", code, errb)
			}
			got := decodeSelfUpdateJSON(t, out)
			if !got.OK || got.Error != nil {
				t.Fatalf("want ok envelope, got %s", out)
			}
			if got.Data["outcome"] != tc.outcome {
				t.Fatalf("outcome=%v want %s", got.Data["outcome"], tc.outcome)
			}
			if got.Data["current_version"] != tc.res.CurrentVersion || got.Data["latest_version"] != tc.res.LatestVersion {
				t.Fatalf("versions not carried: %s", out)
			}
		})
	}
}

func TestSelfUpdateJSONLeadingGlobalFlag(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return &selfupdate.Result{Outcome: selfupdate.OutcomeUpdated, CurrentVersion: "0.3.0", LatestVersion: "0.4.0"}, nil
	}
	code, out, _ := captureSelfUpdate(t, []string{"--json", "self-update"}, eng, "/tmp/fake-capabilities")
	if code != api.ExitOK {
		t.Fatalf("exit=%d", code)
	}
	if got := decodeSelfUpdateJSON(t, out); got.Data["outcome"] != "updated" {
		t.Fatalf("leading --json not honoured: %s", out)
	}
}

func TestSelfUpdateJSONErrorEnvelopes(t *testing.T) {
	cases := []struct {
		name      string
		err       error
		msg       string
		retryable bool
	}{
		{"unwritable", selfupdate.ErrUnwritable, "install path is not writable", false},
		{"unsupported_os", selfupdate.ErrUnsupportedOS, "unsupported operating system", false},
		{"unsupported_arch", selfupdate.ErrUnsupportedArch, "unsupported architecture", false},
		{"checksum_missing", selfupdate.ErrChecksumMissing, "checksum verification failed", false},
		{"checksum_mismatch", selfupdate.ErrChecksumMismatch, "checksum verification failed", false},
		{"network", selfupdate.ErrNetwork, "network/download failed", true},
		{"http", selfupdate.ErrHTTP, "network/download failed", true},
		{"resolve", selfupdate.ErrResolve, "could not resolve latest release", true},
		{"extract", selfupdate.ErrExtract, "release archive invalid", false},
		{"sniffed_checksum", errors.New("bad checksum line"), "checksum verification failed", false},
		{"sniffed_network", errors.New("http 502"), "network/download failed", true},
		{"generic", errors.New("boom"), "failed", false},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
				return nil, fmt.Errorf("wrap: %w", tc.err)
			}
			code, out, errb := captureSelfUpdate(t, []string{"self-update", "--json"}, eng, "/tmp/fake-capabilities")
			if code != api.ExitInternal {
				t.Fatalf("exit=%d want %d", code, api.ExitInternal)
			}
			got := decodeSelfUpdateJSON(t, out)
			if got.OK || got.Error == nil {
				t.Fatalf("want error envelope, got %s", out)
			}
			if got.Error.Code != api.CodeInternal {
				t.Fatalf("code=%q want %q", got.Error.Code, api.CodeInternal)
			}
			if !strings.Contains(got.Error.Message, tc.msg) {
				t.Fatalf("message=%q want contains %q", got.Error.Message, tc.msg)
			}
			if got.Error.Retryable != tc.retryable {
				t.Fatalf("retryable=%v want %v", got.Error.Retryable, tc.retryable)
			}
			if !strings.Contains(errb, "self-update:") {
				t.Fatalf("human stderr diagnostic must stay: %q", errb)
			}
		})
	}
}

func TestSelfUpdateJSONUnexpectedOutcome(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return &selfupdate.Result{Outcome: selfupdate.Outcome(99)}, nil
	}
	code, out, _ := captureSelfUpdate(t, []string{"self-update", "--json"}, eng, "/tmp/fake-capabilities")
	if code != api.ExitInternal {
		t.Fatalf("exit=%d", code)
	}
	got := decodeSelfUpdateJSON(t, out)
	if got.OK || got.Error == nil || got.Error.Code != api.CodeInternal || !strings.Contains(got.Error.Message, "unexpected outcome") {
		t.Fatalf("want internal envelope for unexpected outcome, got %s", out)
	}
}

func TestSelfUpdateHumanModeKeepsStdoutClean(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return nil, selfupdate.ErrNetwork
	}
	_, out, _ := captureSelfUpdate(t, []string{"self-update"}, eng, "/tmp/fake-capabilities")
	if out != "" {
		t.Fatalf("human mode must not print an envelope on stdout: %q", out)
	}
}

func TestSelfUpdateHelpDocumentsJSON(t *testing.T) {
	if !strings.Contains(CommandHelp("self-update"), "--json") {
		t.Fatal("self-update help must document --json")
	}
}
