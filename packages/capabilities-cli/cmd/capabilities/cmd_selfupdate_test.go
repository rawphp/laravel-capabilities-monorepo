package main

import (
	"bytes"
	"context"
	"errors"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/selfupdate"
)

func captureSelfUpdate(t *testing.T, args []string, eng SelfUpdateEngine, exe string) (int, string, string) {
	t.Helper()
	var out, errb bytes.Buffer
	code := Execute(Env{
		Args:           args,
		Stdout:         &out,
		Stderr:         &errb,
		ConfigRoot:     t.TempDir(),
		SelfUpdate:     eng,
		ExecutablePath: exe,
	})
	return code, out.String(), errb.String()
}

func TestSelfUpdateDispatchAlreadyLatest(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		if opt.TargetPath == "" {
			t.Fatal("expected TargetPath set from executable")
		}
		if opt.CurrentVersion == "" {
			t.Fatal("expected CurrentVersion from Version")
		}
		return &selfupdate.Result{
			Outcome:        selfupdate.OutcomeAlreadyLatest,
			CurrentVersion: "0.4.0",
			LatestVersion:  "0.4.0",
			TargetPath:     opt.TargetPath,
		}, nil
	}
	code, out, errb := captureSelfUpdate(t, []string{"self-update"}, eng, "/tmp/fake-capabilities")
	if code != api.ExitOK {
		t.Fatalf("exit=%d want 0 stdout=%q stderr=%q", code, out, errb)
	}
	combined := out + errb
	if !strings.Contains(strings.ToLower(combined), "up-to-date") &&
		!strings.Contains(strings.ToLower(combined), "up to date") &&
		!strings.Contains(strings.ToLower(combined), "already") {
		t.Fatalf("expected already up-to-date message, got stdout=%q stderr=%q", out, errb)
	}
}

func TestSelfUpdateDispatchSuccess(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return &selfupdate.Result{
			Outcome:        selfupdate.OutcomeUpdated,
			CurrentVersion: "0.3.0",
			LatestVersion:  "0.4.0",
			TargetPath:     opt.TargetPath,
		}, nil
	}
	code, out, errb := captureSelfUpdate(t, []string{"self-update"}, eng, "/tmp/fake-capabilities")
	if code != api.ExitOK {
		t.Fatalf("exit=%d want 0 stdout=%q stderr=%q", code, out, errb)
	}
	combined := out + errb
	if !strings.Contains(combined, "0.4.0") {
		t.Fatalf("expected new version in message, got stdout=%q stderr=%q", out, errb)
	}
}

func TestSelfUpdateUnwritableGuidance(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return nil, selfupdate.ErrUnwritable
	}
	code, out, errb := captureSelfUpdate(t, []string{"self-update"}, eng, "/usr/local/bin/capabilities")
	if code == api.ExitOK {
		t.Fatalf("expected non-zero exit, got 0")
	}
	if !strings.Contains(errb, "CAPABILITIES_INSTALL_DIR") && !strings.Contains(errb, "install.sh") {
		t.Fatalf("expected install.sh / CAPABILITIES_INSTALL_DIR guidance, stderr=%q stdout=%q", errb, out)
	}
}

func TestSelfUpdateUnsupportedOS(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return nil, selfupdate.ErrUnsupportedOS
	}
	code, _, errb := captureSelfUpdate(t, []string{"self-update"}, eng, "/tmp/fake-capabilities")
	if code == api.ExitOK {
		t.Fatal("expected non-zero")
	}
	low := strings.ToLower(errb)
	if !strings.Contains(low, "darwin") && !strings.Contains(low, "linux") && !strings.Contains(low, "windows") && !strings.Contains(low, "unsupported") {
		t.Fatalf("expected unsupported OS guidance, stderr=%q", errb)
	}
}

func TestSelfUpdateChecksumFailure(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return nil, selfupdate.ErrChecksumMismatch
	}
	code, _, errb := captureSelfUpdate(t, []string{"self-update"}, eng, "/tmp/fake-capabilities")
	if code == api.ExitOK {
		t.Fatal("expected non-zero")
	}
	low := strings.ToLower(errb)
	if !strings.Contains(low, "checksum") {
		t.Fatalf("expected checksum message, stderr=%q", errb)
	}
}

func TestSelfUpdateNetworkFailure(t *testing.T) {
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		return nil, selfupdate.ErrNetwork
	}
	code, _, errb := captureSelfUpdate(t, []string{"self-update"}, eng, "/tmp/fake-capabilities")
	if code == api.ExitOK {
		t.Fatal("expected non-zero")
	}
	low := strings.ToLower(errb)
	if !strings.Contains(low, "network") && !strings.Contains(low, "http") && !strings.Contains(low, "download") {
		t.Fatalf("expected network/download message, stderr=%q", errb)
	}
}

func TestSelfUpdateNoAuthSideEffects(t *testing.T) {
	// Engine must be called; ConfigRoot is isolated; command must not require token.
	called := false
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		called = true
		return &selfupdate.Result{
			Outcome:        selfupdate.OutcomeAlreadyLatest,
			CurrentVersion: "1.0.0",
			LatestVersion:  "1.0.0",
		}, nil
	}
	code, _, _ := captureSelfUpdate(t, []string{"self-update"}, eng, "/tmp/fake-capabilities")
	if code != api.ExitOK {
		t.Fatalf("exit=%d", code)
	}
	if !called {
		t.Fatal("engine not called")
	}
}

func TestSelfUpdateHelpFlag(t *testing.T) {
	// --help must not call engine.
	eng := func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error) {
		t.Fatal("engine must not run on --help")
		return nil, errors.New("unreachable")
	}
	code, out, errb := captureSelfUpdate(t, []string{"self-update", "--help"}, eng, "/tmp/x")
	if code != api.ExitOK {
		t.Fatalf("exit=%d", code)
	}
	combined := out + errb
	if !strings.Contains(combined, "self-update") {
		t.Fatalf("expected self-update help, got %q", combined)
	}
}

func TestSelfUpdateCommandExistsAndHelp(t *testing.T) {
	if !CommandExists("self-update") {
		t.Fatal("self-update must be in KnownCommands")
	}
	h := CommandHelp("self-update")
	if !strings.Contains(h, "self-update") {
		t.Fatalf("help missing self-update: %s", h)
	}
}

func TestRootHelpListsSelfUpdate(t *testing.T) {
	h := RootHelp()
	if !strings.Contains(h, "self-update") {
		t.Fatalf("root help missing self-update:\n%s", h)
	}
}
