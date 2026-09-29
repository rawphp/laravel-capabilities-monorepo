package run

import "testing"

// Mirrors laravel-capabilities tests/Unit/Schema/PortableFormatParityTest.php:
// the CLI must reject exactly what the server's JsonSchemaValidator rejects
// for portable string formats (server is law, D-004).
func TestFormatViolationMatchesServerValidator(t *testing.T) {
	rejected := [][2]string{
		{"date", "2026-02-30"},
		{"date", "2026-01-15\n"},
		{"date-time", "2026-01-15"},
		{"date-time", "2026-01-15 10:00:00Z"},
		{"date-time", "2026-01-15T10:00:00"},
		{"date-time", "2026-01-15T10:00:00Z\n"},
		{"datetime", "yesterday"},
		{"time", "10:00"},
		{"time", "10:00:00Z"},
		{"email", "not-an-email"},
		{"email", "a b@example.com"},
		{"email", "a@localhost"},
		{"uri", "example.com"},
		{"url", "www.example.com/x"},
		{"uuid", "123e4567-e89b-12d3-a456-42661417400"},
		{"uuid", "not-a-uuid"},
		{"UUID", "g23e4567-e89b-12d3-a456-426614174000"},
		{" Date-Time ", "nope"},
	}
	for _, c := range rejected {
		if formatViolation(c[0], c[1]) == "" {
			t.Errorf("format %q should reject %q", c[0], c[1])
		}
	}

	accepted := [][2]string{
		{"date", "2024-02-29"},
		{"date-time", "2026-01-15T10:00:00Z"},
		{"date-time", "2026-01-15T10:00:00.123+10:00"},
		{"datetime", "2026-01-15T10:00:00-05:00"},
		{"time", "10:00:00"},
		{"time", "10:00:00.5"},
		{"email", "ops@example.com"},
		{"uri", "https://example.com/x"},
		{"uri", "/relative/path"},
		{"url", "mailto://ops@example.com"},
		{"uuid", "123e4567-e89b-12d3-a456-426614174000"},
		{"uuid", "ABCDEF01-E89B-12D3-A456-426614174000"},
		{"UUID", "ffffffff-ffff-ffff-ffff-ffffffffffff"},
		{"hostname", "anything goes"},
	}
	for _, c := range accepted {
		if msg := formatViolation(c[0], c[1]); msg != "" {
			t.Errorf("format %q should accept %q, got %q", c[0], c[1], msg)
		}
	}
}
