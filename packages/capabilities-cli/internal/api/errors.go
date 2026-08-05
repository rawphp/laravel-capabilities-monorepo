// Package api is the HTTP client for the single capability API (D-009).
// No domain run() logic lives here — wire only.
package api

// Error codes from D-018 shared error envelope.
const (
	CodeValidationFailed  = "validation_failed"
	CodeUnauthenticated   = "unauthenticated"
	CodeForbidden         = "forbidden"
	CodeApprovalRequired  = "approval_required"
	CodeDomainError       = "domain_error"
	CodeRateLimited       = "rate_limited"
	CodeConflict          = "conflict"
	CodeNotFound          = "not_found"
	CodeOutputInvalid     = "output_invalid"
	CodeInternal          = "internal"
)

// CLI exit codes (D-018).
const (
	ExitOK         = 0
	ExitInternal   = 1
	ExitValidation = 2
	ExitAuth       = 3
	ExitApproval   = 4
	ExitDomain     = 5
	ExitRateLimit  = 6
)

// ExitCode maps a D-018 error.code to a CLI process exit code.
func ExitCode(code string) int {
	switch code {
	case CodeValidationFailed:
		return ExitValidation
	case CodeUnauthenticated, CodeForbidden:
		return ExitAuth
	case CodeApprovalRequired:
		return ExitApproval
	case CodeDomainError, CodeConflict, CodeNotFound, CodeOutputInvalid:
		return ExitDomain
	case CodeRateLimited:
		return ExitRateLimit
	case CodeInternal:
		return ExitInternal
	default:
		// Unknown codes default to internal (exit 1).
		return ExitInternal
	}
}

// HTTPStatus documents the normative HTTP status for a D-018 error code.
func HTTPStatus(code string) int {
	switch code {
	case CodeValidationFailed:
		return 422
	case CodeUnauthenticated:
		return 401
	case CodeForbidden:
		return 403
	case CodeApprovalRequired:
		return 202
	case CodeDomainError:
		return 422
	case CodeRateLimited:
		return 429
	case CodeConflict:
		return 409
	case CodeNotFound:
		return 404
	case CodeOutputInvalid:
		return 500
	case CodeInternal:
		return 500
	default:
		return 500
	}
}

// ErrorEnvelope is the D-018 shared error shape.
type ErrorEnvelope struct {
	OK    bool       `json:"ok"`
	Error *ErrorBody `json:"error,omitempty"`
	Data  any        `json:"data,omitempty"`
	Meta  *Meta      `json:"meta,omitempty"`
}

// ErrorBody is the nested error object.
type ErrorBody struct {
	Code        string      `json:"code"`
	Message     string      `json:"message"`
	Violations  []Violation `json:"violations,omitempty"`
	ApprovalID  *string     `json:"approval_id"`
	RequestID   string      `json:"request_id,omitempty"`
	Retryable   bool        `json:"retryable"`
}

// Violation is a field-level validation error.
type Violation struct {
	Field   string `json:"field"`
	Message string `json:"message"`
}

// Meta is success/response metadata.
type Meta struct {
	RequestID        string `json:"request_id,omitempty"`
	Capability       string `json:"capability,omitempty"`
	IdempotentReplay bool   `json:"idempotent_replay,omitempty"`
}

// StructuredError is a client-side mapped error from an HTTP envelope.
type StructuredError struct {
	Code       string      `json:"code"`
	Message    string      `json:"message"`
	HTTPStatus int         `json:"http_status,omitempty"`
	ExitCode   int         `json:"cli_exit,omitempty"`
	Retryable  bool        `json:"retryable"`
	RequestID  string      `json:"request_id,omitempty"`
	Violations []Violation `json:"violations,omitempty"`
	ApprovalID *string     `json:"approval_id"`
	// Body is the raw HTTP payload for debugging; omitted from JSON (can be large/binary).
	Body []byte `json:"-"`
}

func (e *StructuredError) Error() string {
	if e == nil {
		return "nil structured error"
	}
	if e.Message != "" {
		return e.Message
	}
	return e.Code
}

// PublicData is a JSON-safe map for agent surfaces (MCP error.data).
// Uses the same field names as the D-018 wire shape — never Go-exported identifiers.
func (e *StructuredError) PublicData() map[string]any {
	if e == nil {
		return nil
	}
	m := map[string]any{
		"code":       e.Code,
		"message":    e.Message,
		"retryable":  e.Retryable,
		"http_status": e.HTTPStatus,
		"cli_exit":   e.ExitCode,
	}
	if e.RequestID != "" {
		m["request_id"] = e.RequestID
	}
	if e.ApprovalID != nil {
		m["approval_id"] = *e.ApprovalID
	} else {
		m["approval_id"] = nil
	}
	if len(e.Violations) > 0 {
		vs := make([]map[string]string, 0, len(e.Violations))
		for _, v := range e.Violations {
			vs = append(vs, map[string]string{"field": v.Field, "message": v.Message})
		}
		m["violations"] = vs
	}
	return m
}

// MapErrorCode builds a StructuredError from a D-018 code.
func MapErrorCode(code string) *StructuredError {
	return &StructuredError{
		Code:       code,
		HTTPStatus: HTTPStatus(code),
		ExitCode:   ExitCode(code),
	}
}

// ParseErrorEnvelope maps a decoded envelope to StructuredError.
func ParseErrorEnvelope(env ErrorEnvelope, httpStatus int, raw []byte) *StructuredError {
	if env.OK || env.Error == nil {
		return nil
	}
	code := env.Error.Code
	if code == "" {
		code = CodeInternal
	}
	return &StructuredError{
		Code:       code,
		Message:    env.Error.Message,
		HTTPStatus: httpStatus,
		ExitCode:   ExitCode(code),
		Retryable:  env.Error.Retryable,
		RequestID:  env.Error.RequestID,
		Violations: env.Error.Violations,
		ApprovalID: env.Error.ApprovalID,
		Body:       raw,
	}
}
