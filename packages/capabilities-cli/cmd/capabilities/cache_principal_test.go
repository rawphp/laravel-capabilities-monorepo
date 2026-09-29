package main

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/auth"
)

// A credential swap under the same profile that skipped the login purge
// (concurrent process, failed invalidate) must not serve the previous
// principal's cached schemas.
func TestDescribeDoesNotServeAnotherPrincipalsCachedSchema(t *testing.T) {
	hits := map[string]int{}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		hits[strings.TrimPrefix(r.Header.Get("Authorization"), "Bearer ")]++
		w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object"}}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	if _, err := auth.LoginWithToken(st, "default", srv.URL, "tok-alice"); err != nil {
		t.Fatal(err)
	}
	describe := func() {
		t.Helper()
		if code, out, errb := CaptureExecute([]string{"describe", "create-invoice"}, root, newClientFactory(srv)); code != 0 {
			t.Fatal(code, out, errb)
		}
	}
	describe()
	describe()
	if hits["tok-alice"] != 1 {
		t.Fatalf("alice should fetch once then hit her cache, got %d", hits["tok-alice"])
	}
	if err := st.SetToken("default", "tok-bob"); err != nil {
		t.Fatal(err)
	}
	describe()
	if hits["tok-bob"] != 1 {
		t.Fatalf("bob must fetch his own schema, not read alice's cache (bob hits=%d)", hits["tok-bob"])
	}
}
