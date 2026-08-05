package run

import "testing"

func TestHumanSuccessSummaryShort(t *testing.T) {
	body := []byte(`{"ok":true,"data":{"payload":{"date":"2026-08-06","meals":[]}}}`)
	s := humanSuccessSummary("get_today_meals", body)
	if s != "ok get_today_meals date=2026-08-06" {
		t.Fatal(s)
	}
	// Must not embed the full meals array.
	if len(s) > 80 {
		t.Fatalf("too long: %q", s)
	}
}

func TestHumanSuccessSummaryFallback(t *testing.T) {
	s := humanSuccessSummary("x", []byte(`{"ok":true,"data":{"nested":{"deep":true}}}`))
	if s != "ok x" {
		t.Fatal(s)
	}
}
