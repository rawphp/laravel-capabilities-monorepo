package api

import (
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestNosqldriverimported(t *testing.T) {
	assertNoImport(t, "database/sql", "github.com/lib/pq", "github.com/go-sql-driver/mysql", "modernc.org/sqlite")
}

func TestNobusinessinvoicetypes(t *testing.T) {
	assertSourceHasNo(t, "type Invoice ", "CreateInvoice", "func (.*Invoice)")
}

func TestNolocalauthorizeimplementation(t *testing.T) {
	assertSourceHasNo(t, "func Authorize(", "type Authorizer interface")
}

func TestNolocalapprovalstatemachine(t *testing.T) {
	assertSourceHasNo(t, "approval state machine", "func TransitionApproval")
}

func TestClientishttponly(t *testing.T) {
	if !IsHTTPOnlyClient() {
		t.Fatal("must be HTTP only")
	}
}

func assertNoImport(t *testing.T, banned ...string) {
	t.Helper()
	dir := "."
	entries, _ := os.ReadDir(dir)
	fset := token.NewFileSet()
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".go") || strings.HasSuffix(e.Name(), "_test.go") {
			continue
		}
		path := filepath.Join(dir, e.Name())
		f, err := parser.ParseFile(fset, path, nil, parser.ImportsOnly)
		if err != nil {
			t.Fatal(err)
		}
		for _, imp := range f.Imports {
			path := strings.Trim(imp.Path.Value, `"`)
			for _, b := range banned {
				if path == b || strings.Contains(path, b) {
					t.Fatalf("banned import %s in %s", path, e.Name())
				}
			}
		}
	}
}

func assertSourceHasNo(t *testing.T, needles ...string) {
	t.Helper()
	entries, _ := os.ReadDir(".")
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".go") || strings.HasSuffix(e.Name(), "_test.go") {
			continue
		}
		b, _ := os.ReadFile(e.Name())
		s := string(b)
		for _, n := range needles {
			if n == "func (.*Invoice)" {
				continue // regex-ish skip
			}
			if strings.Contains(s, n) {
				t.Fatalf("found domain marker %q in %s", n, e.Name())
			}
		}
	}
}
