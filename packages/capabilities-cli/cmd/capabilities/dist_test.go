package main

import (
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"testing"
)

func moduleRoot(t *testing.T) string {
	t.Helper()
	_, file, _, ok := runtime.Caller(0)
	if !ok {
		t.Fatal("caller")
	}
	// cmd/capabilities -> package root
	return filepath.Clean(filepath.Join(filepath.Dir(file), "..", ".."))
}

func TestNoembeddedphpruntime(t *testing.T) {
	root := moduleRoot(t)
	// no php binary or runtime vendored
	for _, p := range []string{"php", "php-src", "embed/php"} {
		if _, err := os.Stat(filepath.Join(root, p)); err == nil {
			t.Fatalf("found embedded php path %s", p)
		}
	}
}

func TestNoembeddedlaravelapp(t *testing.T) {
	root := moduleRoot(t)
	if _, err := os.Stat(filepath.Join(root, "artisan")); err == nil {
		t.Fatal("artisan must not be in CLI package")
	}
	if _, err := os.Stat(filepath.Join(root, "app")); err == nil {
		t.Fatal("laravel app dir must not be in CLI package")
	}
}

func TestNolocaldatabasedriver(t *testing.T) {
	root := moduleRoot(t)
	// scan go files for sql drivers
	err := filepath.Walk(root, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return nil
		}
		if !strings.HasSuffix(path, ".go") || strings.HasSuffix(path, "_test.go") {
			return nil
		}
		b, _ := os.ReadFile(path)
		s := string(b)
		for _, ban := range []string{"database/sql", "github.com/lib/pq", "go-sql-driver"} {
			if strings.Contains(s, ban) {
				t.Errorf("banned %s in %s", ban, path)
			}
		}
		return nil
	})
	if err != nil {
		t.Fatal(err)
	}
}

func TestCrosscompiletargetsdocumented(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, "dist", "README.md"))
	if err != nil {
		t.Fatal(err)
	}
	s := string(b)
	for _, needle := range []string{"darwin", "linux", "windows", "amd64", "arm64"} {
		if !strings.Contains(s, needle) {
			t.Fatalf("dist README missing %s", needle)
		}
	}
}

// TestVersionldflagsdocumented ensures release notes document the -X main.Version
// ldflags key so GoReleaser (and manual release builds) inject the same symbol.
func TestVersionldflagsdocumented(t *testing.T) {
	root := moduleRoot(t)
	b, err := os.ReadFile(filepath.Join(root, "dist", "README.md"))
	if err != nil {
		t.Fatal(err)
	}
	s := string(b)
	for _, needle := range []string{"-ldflags", "main.Version", "-X"} {
		if !strings.Contains(s, needle) {
			t.Fatalf("dist README must document version ldflags (missing %q)", needle)
		}
	}
}
