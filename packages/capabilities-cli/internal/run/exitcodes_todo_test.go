package run

import (
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestExitcodeforValidationFailedIs2(t *testing.T) {
	if ExitCodeFor(api.CodeValidationFailed) != 2 {
		t.Fatal()
	}
}
func TestHttpstatusforValidationFailedIs422Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeValidationFailed) != 422 {
		t.Fatal()
	}
}
func TestExitcodeforUnauthenticatedIs3(t *testing.T) {
	if ExitCodeFor(api.CodeUnauthenticated) != 3 {
		t.Fatal()
	}
}
func TestHttpstatusforUnauthenticatedIs401Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeUnauthenticated) != 401 {
		t.Fatal()
	}
}
func TestExitcodeforForbiddenIs3(t *testing.T) {
	if ExitCodeFor(api.CodeForbidden) != 3 {
		t.Fatal()
	}
}
func TestHttpstatusforForbiddenIs403Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeForbidden) != 403 {
		t.Fatal()
	}
}
func TestExitcodeforApprovalRequiredIs4(t *testing.T) {
	if ExitCodeFor(api.CodeApprovalRequired) != 4 {
		t.Fatal()
	}
}
func TestHttpstatusforApprovalRequiredIs202Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeApprovalRequired) != 202 {
		t.Fatal()
	}
}
func TestExitcodeforDomainErrorIs5(t *testing.T) {
	if ExitCodeFor(api.CodeDomainError) != 5 {
		t.Fatal()
	}
}
func TestHttpstatusforDomainErrorIs422Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeDomainError) != 422 {
		t.Fatal()
	}
}
func TestExitcodeforRateLimitedIs6(t *testing.T) {
	if ExitCodeFor(api.CodeRateLimited) != 6 {
		t.Fatal()
	}
}
func TestHttpstatusforRateLimitedIs429Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeRateLimited) != 429 {
		t.Fatal()
	}
}
func TestExitcodeforConflictIs5(t *testing.T) {
	if ExitCodeFor(api.CodeConflict) != 5 {
		t.Fatal()
	}
}
func TestHttpstatusforConflictIs409Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeConflict) != 409 {
		t.Fatal()
	}
}
func TestExitcodeforNotFoundIs5(t *testing.T) {
	if ExitCodeFor(api.CodeNotFound) != 5 {
		t.Fatal()
	}
}
func TestHttpstatusforNotFoundIs404Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeNotFound) != 404 {
		t.Fatal()
	}
}
func TestExitcodeforOutputInvalidIs5(t *testing.T) {
	if ExitCodeFor(api.CodeOutputInvalid) != 5 {
		t.Fatal()
	}
}
func TestHttpstatusforOutputInvalidIs500Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeOutputInvalid) != 500 {
		t.Fatal()
	}
}
func TestExitcodeforInternalIs1(t *testing.T) {
	if ExitCodeFor(api.CodeInternal) != 1 {
		t.Fatal()
	}
}
func TestHttpstatusforInternalIs500Documented(t *testing.T) {
	if DocumentedHTTPStatus(api.CodeInternal) != 500 {
		t.Fatal()
	}
}
func TestExitcode0onsuccess(t *testing.T) {
	if ExitOK != 0 {
		t.Fatal()
	}
}
