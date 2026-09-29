package api

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"testing"
)

// unsafeSegments would, placed in a URL path, reach another route (C-401):
// `approvals/<id>/accept` as a capability name hits the approval accept route.
var unsafeSegments = map[string]string{
	"empty":         "",
	"dot":           ".",
	"dotdot":        "..",
	"slash":         "approvals/x/accept",
	"leading slash": "/health",
	"backslash":     `approvals\x`,
	"percent":       "approvals%2Fx%2Faccept",
	"query":         "a?b=1",
	"fragment":      "a#b",
	"space":         "create invoice",
	"tab":           "a\tb",
	"newline":       "a\nb",
	"nul":           "a\x00b",
	"del":           "a\x7fb",
}

func TestCheckPathSegmentRejectsUnsafeShapes(t *testing.T) {
	for label, s := range unsafeSegments {
		err := CheckPathSegment("capability name", s)
		if err == nil {
			t.Fatalf("%s: %q accepted", label, s)
		}
		if err.Code != CodeValidationFailed || err.ExitCode != ExitValidation {
			t.Fatalf("%s: %+v", label, err)
		}
	}
}

func TestCheckPathSegmentAcceptsCapabilityNames(t *testing.T) {
	for _, s := range []string{"create-invoice", "ai.cap", "billing.invoice.create", "a..b", "Create_Invoice", "0f3c9a1b2d4e5f60718293a4b5c6d7e8"} {
		if err := CheckPathSegment("capability name", s); err != nil {
			t.Fatalf("%q rejected: %v", s, err)
		}
	}
}

func TestClientRefusesUnsafeSegmentsWithoutHTTP(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		t.Fatalf("unexpected request %s %s", r.Method, r.URL.Path)
	})
	ctx := context.Background()
	calls := map[string]func(string) (*Response, error){
		"describe": func(s string) (*Response, error) { return c.DescribeCapability(ctx, s) },
		"invoke": func(s string) (*Response, error) {
			return c.InvokeCapability(ctx, s, json.RawMessage(`{}`), "k")
		},
		"accept": func(s string) (*Response, error) { return c.AcceptApproval(ctx, s) },
		"reject": func(s string) (*Response, error) { return c.RejectApproval(ctx, s) },
	}
	for call, fn := range calls {
		for label, s := range unsafeSegments {
			res, err := fn(s)
			var se *StructuredError
			if res != nil || !errors.As(err, &se) || se.ExitCode != ExitValidation {
				t.Fatalf("%s %s: res=%v err=%v", call, label, res, err)
			}
		}
	}
}

func TestClientAcceptsDottedNameAndHexApprovalID(t *testing.T) {
	var paths []string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		paths = append(paths, r.URL.Path)
		w.Write([]byte(`{"ok":true,"data":{}}`))
	})
	ctx := context.Background()
	if _, err := c.DescribeCapability(ctx, "billing.create-invoice"); err != nil {
		t.Fatal(err)
	}
	if _, err := c.AcceptApproval(ctx, "0f3c9a1b"); err != nil {
		t.Fatal(err)
	}
	want := []string{"/capabilities/billing.create-invoice", "/capabilities/approvals/0f3c9a1b/accept"}
	if len(paths) != 2 || paths[0] != want[0] || paths[1] != want[1] {
		t.Fatal(paths)
	}
}
