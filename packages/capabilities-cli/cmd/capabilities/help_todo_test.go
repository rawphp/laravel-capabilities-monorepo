package main

import (
	"strings"
	"testing"
)

func TestHelpauth(t *testing.T) {
	if !strings.Contains(CommandHelp("auth"), "login") {
		t.Fatal()
	}
}
func TestHelpcatalog(t *testing.T) {
	if !strings.Contains(CommandHelp("catalog"), "catalog") {
		t.Fatal()
	}
}
func TestHelpdescribe(t *testing.T) {
	if !strings.Contains(CommandHelp("describe"), "Schema") {
		t.Fatal()
	}
}
func TestHelprun(t *testing.T) {
	h := CommandHelp("run")
	if !strings.Contains(h, "Idempotency") || !strings.Contains(h, "Exit codes") {
		t.Fatal(h)
	}
}
func TestHelpmcp(t *testing.T) {
	if !strings.Contains(CommandHelp("mcp"), "stdio") {
		t.Fatal()
	}
}
func TestHelpapprovals(t *testing.T) {
	if !strings.Contains(CommandHelp("approvals"), "accept") {
		t.Fatal()
	}
}
func TestHelpexitcodestable(t *testing.T) {
	if !strings.Contains(RootHelp(), "Exit codes") {
		t.Fatal()
	}
}
func TestHelpexamplesdonotshowdomainlogic(t *testing.T) {
	h := RootHelp()
	if strings.Contains(h, "Eloquent") || strings.Contains(h, "DB::") {
		t.Fatal()
	}
	if !strings.Contains(h, "never embed domain") {
		t.Fatal(h)
	}
}
