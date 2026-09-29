package selfupdate

import (
	"context"
	"crypto/ed25519"
	"crypto/rand"
	"encoding/base64"
	"errors"
	"net/http"
	"os"
	"strings"
	"testing"
)

// signedFixture builds a release whose checksums.txt is signed by priv.
func signedFixture(t *testing.T, priv ed25519.PrivateKey) (*releaseFixture, string, string) {
	t.Helper()
	goos, goarch := defaultOSArch(t)
	archive := makeTarGz(t, BinaryName, []byte(testBinaryPayload))
	checksums := sha256Hex(archive) + "  " + assetName(testVersion, goos, goarch) + "\n"
	return &releaseFixture{
		Version:   testVersion,
		OS:        goos,
		Arch:      goarch,
		Archive:   archive,
		Checksums: checksums,
		Signature: ed25519.Sign(priv, []byte(checksums)),
	}, goos, goarch
}

func newKey(t *testing.T) (string, ed25519.PrivateKey) {
	t.Helper()
	pub, priv, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		t.Fatal(err)
	}
	return base64.StdEncoding.EncodeToString(pub), priv
}

func assertTargetUnchanged(t *testing.T, target string) {
	t.Helper()
	got, err := os.ReadFile(target)
	if err != nil {
		t.Fatal(err)
	}
	if string(got) != "old-binary" {
		t.Fatalf("target must be untouched on verification failure, got %q", got)
	}
}

func TestUpdateSignedReleaseVerifiesAndInstalls(t *testing.T) {
	pub, priv := newKey(t)
	f, goos, goarch := signedFixture(t, priv)
	srv := startFixture(t, f)
	target := writableTarget(t)

	opt := optsFor(srv, "1.0.0", target, goos, goarch)
	opt.PublicKey = pub
	res, err := Update(context.Background(), opt)
	if err != nil {
		t.Fatalf("Update: %v", err)
	}
	if res.Outcome != OutcomeUpdated {
		t.Fatalf("outcome %v want Updated", res.Outcome)
	}
	got, _ := os.ReadFile(target)
	if string(got) != testBinaryPayload {
		t.Fatalf("target content %q", got)
	}
}

func TestUpdateSignatureMissingFailsClosedWhenKeyPinned(t *testing.T) {
	pub, priv := newKey(t)
	f, goos, goarch := signedFixture(t, priv)
	f.Signature = nil
	srv := startFixture(t, f)
	target := writableTarget(t)

	opt := optsFor(srv, "1.0.0", target, goos, goarch)
	opt.PublicKey = pub
	_, err := Update(context.Background(), opt)
	if !errors.Is(err, ErrSignatureMissing) {
		t.Fatalf("err=%v want ErrSignatureMissing", err)
	}
	assertTargetUnchanged(t, target)
}

// A compromised release swaps binary + checksums.txt consistently and signs
// with its own key: the sha256 check passes, the pinned key must not.
func TestUpdateConsistentSwapSignedByForeignKeyIsRejected(t *testing.T) {
	pub, _ := newKey(t)
	_, attacker := newKey(t)
	f, goos, goarch := signedFixture(t, attacker)
	srv := startFixture(t, f)
	target := writableTarget(t)

	opt := optsFor(srv, "1.0.0", target, goos, goarch)
	opt.PublicKey = pub
	_, err := Update(context.Background(), opt)
	if !errors.Is(err, ErrSignatureInvalid) {
		t.Fatalf("err=%v want ErrSignatureInvalid", err)
	}
	assertTargetUnchanged(t, target)
}

func TestUpdateChecksumsEditedAfterSigningIsRejected(t *testing.T) {
	pub, priv := newKey(t)
	f, goos, goarch := signedFixture(t, priv)
	f.Checksums += strings.Repeat("0", 64) + "  extra.tar.gz\n"
	srv := startFixture(t, f)
	target := writableTarget(t)

	opt := optsFor(srv, "1.0.0", target, goos, goarch)
	opt.PublicKey = pub
	_, err := Update(context.Background(), opt)
	if !errors.Is(err, ErrSignatureInvalid) {
		t.Fatalf("err=%v want ErrSignatureInvalid", err)
	}
	assertTargetUnchanged(t, target)
}

func TestUpdateMalformedPublicKeyFailsBeforeNetwork(t *testing.T) {
	for name, key := range map[string]string{
		"not base64":   "%%%not-base64%%%",
		"wrong length": base64.StdEncoding.EncodeToString([]byte("short")),
	} {
		t.Run(name, func(t *testing.T) {
			_, priv := newKey(t)
			f, goos, goarch := signedFixture(t, priv)
			srv := startFixture(t, f)
			target := writableTarget(t)

			opt := optsFor(srv, "1.0.0", target, goos, goarch)
			opt.PublicKey = key
			_, err := Update(context.Background(), opt)
			if !errors.Is(err, ErrSignatureInvalid) {
				t.Fatalf("err=%v want ErrSignatureInvalid", err)
			}
			if f.DownloadHits != 0 {
				t.Fatalf("malformed key must fail before download, hits=%d", f.DownloadHits)
			}
			assertTargetUnchanged(t, target)
		})
	}
}

// Release builds pin the key via -ldflags -X ...selfupdate.ReleasePublicKey.
func TestUpdateUsesLinkTimeReleasePublicKeyByDefault(t *testing.T) {
	pub, _ := newKey(t)
	_, attacker := newKey(t)
	prev := ReleasePublicKey
	ReleasePublicKey = pub
	t.Cleanup(func() { ReleasePublicKey = prev })

	f, goos, goarch := signedFixture(t, attacker)
	srv := startFixture(t, f)
	target := writableTarget(t)

	_, err := Update(context.Background(), optsFor(srv, "1.0.0", target, goos, goarch))
	if !errors.Is(err, ErrSignatureInvalid) {
		t.Fatalf("err=%v want ErrSignatureInvalid from link-time key", err)
	}
	assertTargetUnchanged(t, target)
}

type failSignatureTransport struct{ next http.RoundTripper }

func (f failSignatureTransport) RoundTrip(r *http.Request) (*http.Response, error) {
	if strings.HasSuffix(r.URL.Path, "/"+SignatureName) {
		return nil, errors.New("connection reset")
	}
	return f.next.RoundTrip(r)
}

func TestUpdateSignatureNetworkErrorIsNotReportedAsMissing(t *testing.T) {
	pub, priv := newKey(t)
	f, goos, goarch := signedFixture(t, priv)
	srv := startFixture(t, f)
	target := writableTarget(t)

	opt := optsFor(srv, "1.0.0", target, goos, goarch)
	opt.PublicKey = pub
	opt.HTTPClient = &http.Client{Transport: failSignatureTransport{next: srv.Client().Transport}}
	_, err := Update(context.Background(), opt)
	if !errors.Is(err, ErrNetwork) || errors.Is(err, ErrSignatureMissing) {
		t.Fatalf("err=%v want ErrNetwork only", err)
	}
	assertTargetUnchanged(t, target)
}
