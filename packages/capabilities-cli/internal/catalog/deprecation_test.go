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

func TestNoWarningForMissingEntryOrFutureSunset(t *testing.T) {
	if w := DeprecationWarning(nil, time.Now()); w != "" {
		t.Fatal(w)
	}
	if w := DeprecationWarning(&CacheEntry{Name: "x", SunsetAt: "2099-01-01"}, time.Now()); w != "" {
		t.Fatal(w)
	}
}

func TestSunsetWarningNamesSuccessor(t *testing.T) {
	w := DeprecationWarning(&CacheEntry{Name: "x", SunsetAt: "2000-01-01", Successor: "y"}, time.Now())
	if !strings.Contains(w, "past sunset") || !strings.Contains(w, "use y") {
		t.Fatal(w)
	}
}

func TestAliasResolutionWithoutCanonicalFallsBackToName(t *testing.T) {
	e := &CacheEntry{Name: "n", Aliases: []string{"a"}}
	if got := ResolveAlias(e, "a"); got != "n" {
		t.Fatal(got)
	}
	if got := ResolveAlias(e, "n"); got != "n" {
		t.Fatal(got)
	}
	if got := ResolveAlias(e, "other"); got != "other" {
		t.Fatal(got)
	}
	if got := ResolveAlias(nil, "x"); got != "x" {
		t.Fatal(got)
	}
}
