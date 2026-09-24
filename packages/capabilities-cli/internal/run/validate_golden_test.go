package run

import (
	"encoding/json"
	"errors"
	"os"
	"testing"
)

// goldenFormats is the portable string-format subset ValidateLocal checks by regex.
// Adding or dropping a format must update testdata/validate_local_golden.json too.
var goldenFormats = []string{"date", "date-time", "time", "email", "uuid"}

type formatGoldenCase struct {
	Format string `json:"format"`
	Value  string `json:"value"`
	Accept bool   `json:"accept"`
	Note   string `json:"note,omitempty"`
}

func loadFormatGolden(t *testing.T) []formatGoldenCase {
	t.Helper()
	raw, err := os.ReadFile("testdata/validate_local_golden.json")
	if err != nil {
		t.Fatalf("read golden: %v", err)
	}
	var cases []formatGoldenCase
	if err := json.Unmarshal(raw, &cases); err != nil {
		t.Fatalf("parse golden: %v", err)
	}
	return cases
}

// TestValidateLocalFormatGolden pins accept/reject for each portable format so a
// regex change shows up as a snapshot diff, not silent drift from the server (D-004).
func TestValidateLocalFormatGolden(t *testing.T) {
	for _, c := range loadFormatGolden(t) {
		schema, _ := json.Marshal(map[string]any{"type": "string", "format": c.Format})
		input, _ := json.Marshal(c.Value)
		err := ValidateLocal(schema, input)
		var ve *ValidationError
		if err != nil && !errors.As(err, &ve) {
			t.Fatalf("format=%s value=%q: unexpected error type %T: %v", c.Format, c.Value, err, err)
		}
		if got := err == nil; got != c.Accept {
			t.Errorf("format=%s value=%q: golden accept=%v, got accept=%v (err=%v) %s",
				c.Format, c.Value, c.Accept, got, err, c.Note)
		}
	}
}

func TestValidateLocalFormatGoldenCoversEveryFormat(t *testing.T) {
	seen := map[string][2]int{} // [accepts, rejects]
	for _, c := range loadFormatGolden(t) {
		n := seen[c.Format]
		if c.Accept {
			n[0]++
		} else {
			n[1]++
		}
		seen[c.Format] = n
	}
	for _, f := range goldenFormats {
		if n := seen[f]; n[0] == 0 || n[1] == 0 {
			t.Errorf("golden for format %q needs at least one accept and one reject case, has %d/%d", f, n[0], n[1])
		}
		delete(seen, f)
	}
	for f := range seen {
		t.Errorf("golden has cases for format %q, which is not in goldenFormats", f)
	}
}
