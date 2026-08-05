package run

import (
	"encoding/json"
	"fmt"
	"strings"

	"github.com/rawphp/capabilities-cli/internal/api"
)

// ValidationError is a local schema failure (exit 2, no network).
type ValidationError struct {
	Violations []api.Violation
	Message    string
}

func (e *ValidationError) Error() string {
	base := e.Message
	if base == "" {
		base = "validation_failed"
	}
	if len(e.Violations) == 0 {
		return base
	}
	// Surface field-level failures on stderr so humans need not parse the JSON envelope.
	parts := make([]string, 0, len(e.Violations))
	for _, v := range e.Violations {
		if v.Field != "" {
			parts = append(parts, v.Field+": "+v.Message)
		} else if v.Message != "" {
			parts = append(parts, v.Message)
		}
	}
	if len(parts) == 0 {
		return base
	}
	return base + " (" + strings.Join(parts, "; ") + ")"
}

// ValidateLocal validates input against a portable JSON Schema subset.
// Supports: type object/array/string/number/integer/boolean/null, required, properties, items.
// This is UX-only — server re-validates (D-004).
func ValidateLocal(schemaJSON, inputJSON []byte) error {
	if len(schemaJSON) == 0 {
		// No schema → skip local structural check (server still validates).
		return nil
	}
	var schema map[string]any
	if err := json.Unmarshal(schemaJSON, &schema); err != nil {
		return &ValidationError{Message: "invalid schema document: " + err.Error()}
	}
	var input any
	if err := json.Unmarshal(inputJSON, &input); err != nil {
		return &ValidationError{
			Message: "invalid JSON input",
			Violations: []api.Violation{{Field: "", Message: "invalid JSON: " + err.Error()}},
		}
	}
	var viol []api.Violation
	validateNode("", schema, input, &viol)
	if len(viol) > 0 {
		return &ValidationError{
			Message:    "local schema validation failed",
			Violations: viol, // Error() appends "field: message" for human stderr
		}
	}
	return nil
}

func validateNode(path string, schema map[string]any, value any, viol *[]api.Violation) {
	if schema == nil {
		return
	}
	if t, ok := schema["type"].(string); ok {
		if !typeMatches(t, value) {
			*viol = append(*viol, api.Violation{Field: path, Message: "must be " + t})
			return
		}
	}
	switch schema["type"] {
	case "object", nil:
		obj, ok := value.(map[string]any)
		if !ok {
			if value == nil {
				return
			}
			if schema["type"] == "object" {
				return // already reported
			}
		} else {
			if req, ok := schema["required"].([]any); ok {
				for _, r := range req {
					field, _ := r.(string)
					if field == "" {
						continue
					}
					if _, exists := obj[field]; !exists {
						fp := joinPath(path, field)
						*viol = append(*viol, api.Violation{Field: fp, Message: "is required"})
					}
				}
			}
			if props, ok := schema["properties"].(map[string]any); ok {
				for k, ps := range props {
					psm, ok := ps.(map[string]any)
					if !ok {
						continue
					}
					if v, exists := obj[k]; exists {
						validateNode(joinPath(path, k), psm, v, viol)
					}
				}
			}
		}
	case "array":
		arr, ok := value.([]any)
		if !ok {
			return
		}
		if items, ok := schema["items"].(map[string]any); ok {
			for i, item := range arr {
				validateNode(fmt.Sprintf("%s[%d]", path, i), items, item, viol)
			}
		}
	}
}

func typeMatches(t string, v any) bool {
	if v == nil {
		return t == "null"
	}
	switch t {
	case "object":
		_, ok := v.(map[string]any)
		return ok
	case "array":
		_, ok := v.([]any)
		return ok
	case "string":
		_, ok := v.(string)
		return ok
	case "boolean":
		_, ok := v.(bool)
		return ok
	case "number":
		switch v.(type) {
		case float64, json.Number:
			return true
		default:
			return false
		}
	case "integer":
		switch n := v.(type) {
		case float64:
			return n == float64(int64(n))
		case json.Number:
			_, err := n.Int64()
			return err == nil
		default:
			return false
		}
	case "null":
		return v == nil
	default:
		return true
	}
}

func joinPath(base, field string) string {
	if base == "" {
		return field
	}
	if strings.HasPrefix(field, "[") {
		return base + field
	}
	return base + "." + field
}
