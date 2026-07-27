package catalog

import (
	"strings"
	"testing"
	"time"
)

func TestWarnondeprecatedtrue(t *testing.T) {
	w := DeprecationWarning(&CacheEntry{Name: "x", Deprecated: true}, time.Now())
	if !strings.Contains(w, "deprecated") {
		t.Fatal(w)
	}
}

func TestShowsuccessorwhenpresent(t *testing.T) {
	w := DeprecationWarning(&CacheEntry{Name: "x", Deprecated: true, Successor: "y"}, time.Now())
	if !strings.Contains(w, "y") {
		t.Fatal(w)
	}
}

func TestBlockorwarnaftersunset(t *testing.T) {
	w := DeprecationWarning(&CacheEntry{Name: "x", SunsetAt: "2000-01-01"}, time.Now())
	if !strings.Contains(w, "sunset") {
		t.Fatal(w)
	}
}

func TestAliasresolvesbeforerun(t *testing.T) {
	e := &CacheEntry{Name: "canon", Canonical: "canon", Aliases: []string{"alias"}}
	if ResolveAlias(e, "alias") != "canon" {
		t.Fatal()
	}
}

func TestCanonicalpreferredinlist(t *testing.T) {
	e := &CacheEntry{Name: "canon", Canonical: "canon"}
	if ResolveAlias(e, "canon") != "canon" {
		t.Fatal()
	}
}
