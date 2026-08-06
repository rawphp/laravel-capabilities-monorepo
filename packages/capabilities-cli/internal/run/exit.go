// Package run implements local validate → ensure Idempotency-Key → POST invoke.
// Domain logic never lives here; server re-validates (D-004, D-016).
package run

import "github.com/rawphp/capabilities-cli/internal/api"

// Exit code aliases for CLI run command.
const (
	ExitOK         = api.ExitOK
	ExitInternal   = api.ExitInternal
	ExitValidation = api.ExitValidation
	ExitAuth       = api.ExitAuth
	ExitApproval   = api.ExitApproval
	ExitDomain     = api.ExitDomain
	ExitRateLimit  = api.ExitRateLimit
)

// ExitCodeFor maps error.code → process exit (D-018).
func ExitCodeFor(code string) int {
	return api.ExitCode(code)
}

// DocumentedHTTPStatus returns normative HTTP status for an error code (D-018 table).
func DocumentedHTTPStatus(code string) int {
	return api.HTTPStatus(code)
}

// ExitCodeTable is the full D-018 mapping for docs + tests.
var ExitCodeTable = map[string]struct {
	HTTP int
	Exit int
}{
	api.CodeValidationFailed: {422, 2},
	api.CodeUnauthenticated:  {401, 3},
	api.CodeForbidden:        {403, 3},
	api.CodeApprovalRequired: {202, 4},
	api.CodeDomainError:      {422, 5},
	api.CodeRateLimited:      {429, 6},
	api.CodeConflict:         {409, 5},
	api.CodeNotFound:         {404, 5},
	api.CodeOutputInvalid:    {500, 5},
	api.CodeInternal:         {500, 1},
}

// DocsExitCodes is human-readable exit code documentation (help text source).
const DocsExitCodes = `Exit codes (stable):
  0  success (also help/usage: bare binary, --help, bare approvals)
  1  internal error
  2  validation_failed
  3  unauthenticated / forbidden
  4  approval_required
  5  domain_error / conflict / not_found / output_invalid
  6  rate_limited
`

// DocsPrinciples documents CLI product principles for help/docs tests.
const DocsPrinciples = `capabilities is a downloadable HTTP client (not Artisan).
Server derives caller from credentials — never trust client-claimed caller.
Local JSON Schema validation is UX only; server always re-validates.
Idempotency-Key is always sent on run (auto UUID unless --idempotency-key or --retry-last).
No domain business logic, SQL, or authorize/approval state machine on the client.
Binary name: capabilities. Single static binary; no Node/PHP required on the user machine.
No multi-language CLI matrix in v0.2 (Go only — D-016).
Cross-compile targets: darwin/linux/windows amd64/arm64.
`
