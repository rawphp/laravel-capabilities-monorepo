package main

import (
	"context"
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

	target := env.ExecutablePath
	if target == "" {
		exe, err := os.Executable()
		if err != nil {
			fmt.Fprintf(env.Stderr, "self-update: could not resolve this binary path: %v\n", err)
			return api.ExitInternal
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
		return mapSelfUpdateError(env, err)
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
		fmt.Fprintf(env.Stdout, "capabilities is already up-to-date (%s)\n", ver)
		return api.ExitOK
	case selfupdate.OutcomeUpdated:
		fmt.Fprintf(env.Stdout, "Updated capabilities to %s\n", res.LatestVersion)
		return api.ExitOK
	default:
		fmt.Fprintf(env.Stderr, "self-update: unexpected outcome %d\n", res.Outcome)
		return api.ExitInternal
	}
}

func mapSelfUpdateError(env Env, err error) int {
	switch {
	case errors.Is(err, selfupdate.ErrUnwritable):
		fmt.Fprintf(env.Stderr, "self-update: install path is not writable (%v)\n", err)
		fmt.Fprint(env.Stderr, "Reinstall to a writable directory, for example:\n")
		fmt.Fprint(env.Stderr, "  curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | bash\n")
		fmt.Fprint(env.Stderr, "  # or set CAPABILITIES_INSTALL_DIR to a directory you own, then re-run install.sh\n")
		return api.ExitInternal
	case errors.Is(err, selfupdate.ErrUnsupportedOS):
		fmt.Fprintf(env.Stderr, "self-update: unsupported operating system (darwin and linux only; Windows is not supported)\n")
		return api.ExitInternal
	case errors.Is(err, selfupdate.ErrUnsupportedArch):
		fmt.Fprintf(env.Stderr, "self-update: unsupported architecture (need amd64 or arm64)\n")
		return api.ExitInternal
	case errors.Is(err, selfupdate.ErrChecksumMissing), errors.Is(err, selfupdate.ErrChecksumMismatch):
		fmt.Fprintf(env.Stderr, "self-update: checksum verification failed — aborting (%v)\n", err)
		return api.ExitInternal
	case errors.Is(err, selfupdate.ErrNetwork), errors.Is(err, selfupdate.ErrHTTP):
		fmt.Fprintf(env.Stderr, "self-update: network/download failed (%v)\n", err)
		return api.ExitInternal
	case errors.Is(err, selfupdate.ErrResolve):
		fmt.Fprintf(env.Stderr, "self-update: could not resolve latest release (%v)\n", err)
		return api.ExitInternal
	case errors.Is(err, selfupdate.ErrExtract):
		fmt.Fprintf(env.Stderr, "self-update: release archive invalid (%v)\n", err)
		return api.ExitInternal
	default:
		// Wrap chains may still match sentinels via errors.Is above; generic fallback.
		msg := err.Error()
		low := strings.ToLower(msg)
		if strings.Contains(low, "checksum") {
			fmt.Fprintf(env.Stderr, "self-update: checksum verification failed — aborting (%v)\n", err)
			return api.ExitInternal
		}
		if strings.Contains(low, "network") || strings.Contains(low, "http") {
			fmt.Fprintf(env.Stderr, "self-update: network/download failed (%v)\n", err)
			return api.ExitInternal
		}
		fmt.Fprintf(env.Stderr, "self-update: failed (%v)\n", err)
		return api.ExitInternal
	}
}
