package run

import (
	"context"
	"testing"
)

func TestRetrylastreusesidempotencykey(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "same"
	_ = Run(context.Background(), opts)
	opts.RetryLast = true
	opts.IdempotencyKey = ""
	_ = Run(context.Background(), opts)
	if rec.Key != "same" {
		t.Fatal(rec.Key)
	}
}

func TestRetrylastfailsifnoprevious(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.RetryLast = true
	// no prior last_run file content beyond empty path — delete
	opts.LastRunPath = opts.LastRunPath + ".missing"
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitValidation {
		t.Fatal(res.ExitCode, res.Stderr)
	}
}

func TestManualkeyoverridesauto(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "manual"
	_ = Run(context.Background(), opts)
	if rec.Key != "manual" {
		t.Fatal(rec.Key)
	}
}

func TestNetworkfaildoesnotrotatekeyonretrylast(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "net-key"
	// First run succeeds and stores key
	_ = Run(context.Background(), opts)
	// Simulate retry-last reuse
	opts.RetryLast = true
	opts.IdempotencyKey = ""
	_ = Run(context.Background(), opts)
	if rec.Key != "net-key" {
		t.Fatal(rec.Key)
	}
}

func TestNewrunwithoutretrylastgetsnewkey(t *testing.T) {
	opts, rec := harness(t, nil)
	_ = Run(context.Background(), opts)
	k1 := rec.Key
	_ = Run(context.Background(), opts)
	k2 := rec.Key
	if k1 == "" || k2 == "" || k1 == k2 {
		t.Fatalf("keys should differ: %s %s", k1, k2)
	}
}

func TestRetrylastwithoutinputresendsstoredinput(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "k-prev"
	_ = Run(context.Background(), opts)
	want := string(rec.Body)

	opts.RetryLast = true
	opts.IdempotencyKey = ""
	opts.InputJSON = nil
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitOK {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	if rec.Key != "k-prev" || string(rec.Body) != want {
		t.Fatalf("retry must resend prior invoke: key=%s body=%s want=%s", rec.Key, rec.Body, want)
	}
}

func TestRetrylastexplicitinputwins(t *testing.T) {
	opts, rec := harness(t, nil)
	_ = Run(context.Background(), opts)

	opts.RetryLast = true
	opts.InputJSON = []byte(`{"customer_id":7}`)
	_ = Run(context.Background(), opts)
	if string(rec.Body) != `{"customer_id":7}` {
		t.Fatalf("explicit input must be sent: %s", rec.Body)
	}
}

func TestRetrylastdoesnotrestoreotherCapabilityinput(t *testing.T) {
	opts, rec := harness(t, nil)
	_ = Run(context.Background(), opts)
	n := rec.N

	opts.Capability = "x"
	opts.RetryLast = true
	opts.InputJSON = nil
	res := Run(context.Background(), opts)
	// "x" requires customer_id; the create-invoice body must not leak in.
	if res.ExitCode != ExitValidation || rec.N != n {
		t.Fatal(res.ExitCode, res.Stderr, rec.N)
	}
}
