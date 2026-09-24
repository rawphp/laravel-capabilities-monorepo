package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"strings"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/selfupdate"
)

// SelfUpdateEngine runs the pure-Go self-update package. Tests inject a fake
// to avoid live network and filesystem replace.
type SelfUpdateEngine func(ctx context.Context, opt selfupdate.Options) (*selfupdate.Result, error)

func cmdSelfUpdate(env Env, args []string) int {
	if wantsHelp(args) {
		fmt.Fprint(env.Stdout, CommandHelp("self-update"))
		return api.ExitOK
	}
	jsonOut, _ := flagBool(args, "--json")

	target := env.ExecutablePath
	if target == "" {
		exe, err := os.Executable()
		if err != nil {
			return selfUpdateFail(env, jsonOut, fmt.Sprintf("could not resolve this binary path: %v", err), false)
		}
		target = exe
	}

	eng := env.SelfUpdate
	if eng == nil {
		eng = selfupdate.Update
	}

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Minute)
	defer cancel()

	res, err := eng(ctx, selfupdate.Options{
		CurrentVersion: Version,
		TargetPath:     target,
	})
	if err != nil {
		return mapSelfUpdateError(env, jsonOut, err)
	}

	switch res.Outcome {
	case selfupdate.OutcomeAlreadyLatest:
		ver := res.LatestVersion
		if ver == "" {
			ver = res.CurrentVersion
		}
		if ver == "" {
			ver = Version
		}
		if jsonOut {
			return selfUpdateOK(env, "already_latest", res.CurrentVersion, ver)
		}
		fmt.Fprintf(env.Stdout, "capabilities is already up-to-date (%s)\n", ver)
		return api.ExitOK
	case selfupdate.OutcomeUpdated:
		if jsonOut {
			return selfUpdateOK(env, "updated", res.CurrentVersion, res.LatestVersion)
		}
		fmt.Fprintf(env.Stdout, "Updated capabilities to %s\n", res.LatestVersion)
		return api.ExitOK
	default:
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("unexpected outcome %d", res.Outcome), false)
	}
}

func mapSelfUpdateError(env Env, jsonOut bool, err error) int {
	low := strings.ToLower(err.Error())
	switch {
	case errors.Is(err, selfupdate.ErrUnwritable):
		code := selfUpdateFail(env, jsonOut, fmt.Sprintf("install path is not writable (%v)", err), false)
		fmt.Fprint(env.Stderr, "Reinstall to a writable directory, for example:\n")
		fmt.Fprint(env.Stderr, "  curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | bash\n")
		fmt.Fprint(env.Stderr, "  # or set CAPABILITIES_INSTALL_DIR to a directory you own, then re-run install.sh\n")
		return code
	case errors.Is(err, selfupdate.ErrUnsupportedOS):
		return selfUpdateFail(env, jsonOut, "unsupported operating system (darwin and linux only; Windows is not supported)", false)
	case errors.Is(err, selfupdate.ErrUnsupportedArch):
		return selfUpdateFail(env, jsonOut, "unsupported architecture (need amd64 or arm64)", false)
	case errors.Is(err, selfupdate.ErrSignatureMissing), errors.Is(err, selfupdate.ErrSignatureInvalid):
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("release signature verification failed — aborting (%v)", err), false)
	case errors.Is(err, selfupdate.ErrChecksumMissing), errors.Is(err, selfupdate.ErrChecksumMismatch):
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("checksum verification failed — aborting (%v)", err), false)
	case errors.Is(err, selfupdate.ErrNetwork), errors.Is(err, selfupdate.ErrHTTP):
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("network/download failed (%v)", err), true)
	case errors.Is(err, selfupdate.ErrResolve):
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("could not resolve latest release (%v)", err), true)
	case errors.Is(err, selfupdate.ErrExtract):
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("release archive invalid (%v)", err), false)
	// Unwrapped errors: fall back to sniffing the message.
	case strings.Contains(low, "checksum"):
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("checksum verification failed — aborting (%v)", err), false)
	case strings.Contains(low, "network"), strings.Contains(low, "http"):
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("network/download failed (%v)", err), true)
	default:
		return selfUpdateFail(env, jsonOut, fmt.Sprintf("failed (%v)", err), false)
	}
}

// selfUpdateOK writes the --json success envelope.
func selfUpdateOK(env Env, outcome, current, latest string) int {
	b, _ := json.MarshalIndent(map[string]any{
		"ok": true,
		"data": map[string]any{
			"outcome":         outcome,
			"current_version": current,
			"latest_version":  latest,
		},
	}, "", "  ")
	fmt.Fprintln(env.Stdout, string(b))
	return api.ExitOK
}

// selfUpdateFail prints the human diagnostic on stderr and, with --json, a
// D-018 error envelope on stdout. Self-update failures are local, so the code
// is always internal (exit 1); retryable marks transient network/resolve errors.
func selfUpdateFail(env Env, jsonOut bool, message string, retryable bool) int {
	fmt.Fprintf(env.Stderr, "self-update: %s\n", message)
	if jsonOut {
		writeStructuredErrorStdout(env, &api.StructuredError{
			Code:      api.CodeInternal,
			Message:   message,
			Retryable: retryable,
		})
	}
	return api.ExitCode(api.CodeInternal)
}
