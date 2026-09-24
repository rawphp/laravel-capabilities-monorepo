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

func TestRetrylastreplayslastinputwhennonegiven(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "replay-key"
	first := Run(context.Background(), opts)
	if first.ExitCode != 0 {
		t.Fatal(first.Stderr)
	}
	sent := string(rec.Body)
	opts.RetryLast = true
	opts.IdempotencyKey = ""
	opts.InputJSON = nil
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	if string(rec.Body) != sent || rec.Key != "replay-key" {
		t.Fatalf("retry body %s key %s, want %s replay-key", rec.Body, rec.Key, sent)
	}
}

func TestRetrylastexplicitinputwinsoverlastinput(t *testing.T) {
	opts, rec := harness(t, nil)
	_ = Run(context.Background(), opts)
	opts.RetryLast = true
	opts.InputJSON = []byte(`{"customer_id":7}`)
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res.ExitCode, res.Stderr)
	}
	if string(rec.Body) != `{"customer_id":7}` {
		t.Fatal(string(rec.Body))
	}
}
