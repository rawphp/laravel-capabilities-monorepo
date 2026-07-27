package run

import (
	"context"
	"testing"
)

func TestFlaginputjson(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.InputJSON = []byte(`{"customer_id":1,"amount_cents":1,"currency":"USD"}`)
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}

func TestFlaginputfile(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.InputJSON = nil
	opts.InputFile = writeInputFile(t, `{"customer_id":1,"amount_cents":1,"currency":"USD"}`)
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res.Stderr)
	}
}

func TestFlagidempotencykey(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "k-manual"
	_ = Run(context.Background(), opts)
	if rec.Key != "k-manual" {
		t.Fatal(rec.Key)
	}
}

func TestFlagjson(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.JSON = true
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 || res.Stdout == "" && len(res.Envelope) == 0 {
		t.Fatal(res)
	}
}

func TestFlagnocache(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.NoCache = true
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}

func TestFlagtenanthintnotauthoritative(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.TenantHint = "t1"
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}

func TestFlagretrylast(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.IdempotencyKey = "rl-1"
	_ = Run(context.Background(), opts)
	opts.RetryLast = true
	opts.IdempotencyKey = ""
	_ = Run(context.Background(), opts)
	if rec.Key != "rl-1" {
		t.Fatal(rec.Key)
	}
}

func TestFlagbaseurloverride(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.BaseURL = opts.Client.BaseURL
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}

func TestFlagprofileselection(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.Profile = "default"
	res := Run(context.Background(), opts)
	if res.ExitCode != 0 {
		t.Fatal(res)
	}
}

func TestMissinginputfailslocalvalidation(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.InputJSON = nil
	opts.InputFile = ""
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitValidation || rec.N != 0 {
		t.Fatal(res.ExitCode, rec.N)
	}
}

func TestInvalidjsoninputfailslocalvalidation(t *testing.T) {
	opts, rec := harness(t, nil)
	opts.InputJSON = []byte(`{not json`)
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitValidation || rec.N != 0 {
		t.Fatal(res)
	}
}

func TestEmptycapabilitynamefails(t *testing.T) {
	opts, _ := harness(t, nil)
	opts.Capability = ""
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitValidation {
		t.Fatal(res.ExitCode)
	}
}
