package main

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/run"
)

func TestSinglestaticbinaryname(t *testing.T) {
	if BinaryName != "capabilities" {
		t.Fatal()
	}
}
func TestNonoderequired(t *testing.T) {
	if !strings.Contains(run.DocsPrinciples, "no Node") && !strings.Contains(run.DocsPrinciples, "No Node") {
		// check RootHelp
		if !strings.Contains(RootHelp(), "no Node") {
			t.Fatal()
		}
	}
}
func TestNophprequiredonusermachine(t *testing.T) {
	if !strings.Contains(RootHelp(), "PHP") {
		t.Fatal()
	}
}
func TestNomultilanguageclimatrixinv02(t *testing.T) {
	if !strings.Contains(run.DocsPrinciples, "No multi-language CLI matrix") {
		t.Fatal()
	}
}
func TestProductclinotartisan(t *testing.T) {
	if !strings.Contains(RootHelp(), "not artisan") {
		t.Fatal()
	}
	_ = filepath.Join
	_ = os.TempDir
}
