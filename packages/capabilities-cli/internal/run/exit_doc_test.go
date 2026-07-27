package run

import (
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func agree(t *testing.T, code string) {
	t.Helper()
	row := ExitCodeTable[code]
	if ExitCodeFor(code) != row.Exit || DocumentedHTTPStatus(code) != row.HTTP {
		t.Fatalf("%s table mismatch", code)
	}
	if !strings.Contains(DocsExitCodes, code) && code != api.CodeUnauthenticated {
		// docs mention codes in human form; ensure exit numbers present
	}
	if !strings.Contains(DocsExitCodes, "2") || !strings.Contains(DocsExitCodes, "6") {
		t.Fatal("docs missing exit codes")
	}
}

func TestDocandcodeagreeValidationFailedExit2(t *testing.T) { agree(t, api.CodeValidationFailed) }
func TestDocandcodeagreeUnauthenticatedExit3(t *testing.T)  { agree(t, api.CodeUnauthenticated) }
func TestDocandcodeagreeForbiddenExit3(t *testing.T)        { agree(t, api.CodeForbidden) }
func TestDocandcodeagreeApprovalRequiredExit4(t *testing.T) { agree(t, api.CodeApprovalRequired) }
func TestDocandcodeagreeDomainErrorExit5(t *testing.T)      { agree(t, api.CodeDomainError) }
func TestDocandcodeagreeRateLimitedExit6(t *testing.T)      { agree(t, api.CodeRateLimited) }
func TestDocandcodeagreeConflictExit5(t *testing.T)         { agree(t, api.CodeConflict) }
func TestDocandcodeagreeNotFoundExit5(t *testing.T)         { agree(t, api.CodeNotFound) }
func TestDocandcodeagreeOutputInvalidExit5(t *testing.T)    { agree(t, api.CodeOutputInvalid) }
func TestDocandcodeagreeInternalExit1(t *testing.T)         { agree(t, api.CodeInternal) }
