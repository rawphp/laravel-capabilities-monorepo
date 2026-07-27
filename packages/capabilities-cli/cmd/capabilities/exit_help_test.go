package main

import (
	"strings"
	"testing"
)

func has(t *testing.T, needle string) {
	t.Helper()
	h := RootHelp() + CommandHelp("run")
	if !strings.Contains(h, needle) {
		t.Fatalf("help missing %q", needle)
	}
}

func TestHelpmentionsValidationFailedExit2(t *testing.T) { has(t, "validation_failed") }
func TestHelpmentionsUnauthenticatedExit3(t *testing.T)  { has(t, "unauthenticated") }
func TestHelpmentionsForbiddenExit3(t *testing.T)        { has(t, "forbidden") }
func TestHelpmentionsApprovalRequiredExit4(t *testing.T) { has(t, "approval_required") }
func TestHelpmentionsDomainErrorExit5(t *testing.T)      { has(t, "domain_error") }
func TestHelpmentionsRateLimitedExit6(t *testing.T)      { has(t, "rate_limited") }
func TestHelpmentionsConflictExit5(t *testing.T)         { has(t, "conflict") }
func TestHelpmentionsNotFoundExit5(t *testing.T)         { has(t, "not_found") }
func TestHelpmentionsOutputInvalidExit5(t *testing.T)    { has(t, "output_invalid") }
func TestHelpmentionsInternalExit1(t *testing.T)         { has(t, "internal error") }
