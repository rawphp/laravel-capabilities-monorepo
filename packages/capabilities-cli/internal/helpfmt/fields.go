// Package helpfmt formats human and machine capability/domain help from schemas.
// Help is the primary contract surface (Schema help UX design).
// Field derivation mirrors flagschema rules: scalars/enums → flags; object/array → json-only.
package helpfmt

import (
	"encoding/json"
	"fmt"
	"sort"
	"strings"
	"unicode"
)

// Pass modes for input fields.
const (
	PassFlag     = "flag"
	PassJSONOnly = "json-only"
)

// Envelope kinds for machine help payloads.
const (
	KindCapabilityHelp = "capability_help"
	KindDomainHelp     = "domain_help"
)

// Field is one input property with pass mode (flag vs json-only).
type Field struct {
	Name        string         `json:"name"`
	Type        string         `json:"type"`
	Required    bool           `json:"required"`
	Flag        *string        `json:"flag"` // null when pass is json-only
	Pass        string         `json:"pass"` // "flag" | "json-only"
	Constraints map[string]any `json:"constraints"`
}

// constraintKeys are JSON Schema keywords surfaced in help when present.
var constraintKeys = []string{
	"enum", "minimum", "maximum", "exclusiveMinimum", "exclusiveMaximum",
	"minLength", "maxLength", "pattern", "format", "minItems", "maxItems",
	"const", "default",
}

// DeriveFields extracts field rows from a JSON Schema input object.
// Properties are ordered alphabetically for stable output.
func DeriveFields(inputSchema map[string]any) []Field {
	if inputSchema == nil {
		return nil
	}
	props, _ := inputSchema["properties"].(map[string]any)
	if len(props) == 0 {
		return nil
	}
	required := requiredSet(inputSchema)

	names := make([]string, 0, len(props))
	for name := range props {
		names = append(names, name)
	}
	sort.Strings(names)

	out := make([]Field, 0, len(names))
	for _, name := range names {
		ps, ok := props[name].(map[string]any)
		if !ok {
			ps = map[string]any{}
		}
		out = append(out, fieldFromProp(name, ps, required[name]))
	}
	return out
}

// DeriveFieldsFromJSON unmarshals schema JSON then derives fields.
func DeriveFieldsFromJSON(schemaJSON []byte) ([]Field, error) {
	if len(schemaJSON) == 0 {
		return nil, nil
	}
	var schema map[string]any
	if err := json.Unmarshal(schemaJSON, &schema); err != nil {
		return nil, fmt.Errorf("invalid input_schema JSON: %w", err)
	}
	return DeriveFields(schema), nil
}

func fieldFromProp(name string, prop map[string]any, required bool) Field {
	typ := schemaTypeLabel(prop)
	f := Field{
		Name:        name,
		Type:        typ,
		Required:    required,
		Constraints: extractConstraints(prop),
	}
	if IsFlaggable(prop) {
		flag := FlagName(name)
		f.Flag = &flag
		f.Pass = PassFlag
	} else {
		f.Flag = nil
		f.Pass = PassJSONOnly
	}
	return f
}

// IsFlaggable reports whether a property schema is a plain scalar (or scalar enum)
// that can be passed as a CLI flag. Object, array, and non-scalar unions are json-only.
func IsFlaggable(prop map[string]any) bool {
	if prop == nil {
		return false
	}
	// Explicit object/array shapes → json-only.
	if t, ok := prop["type"].(string); ok {
		if t == "object" || t == "array" {
			return false
		}
		if isPlainScalarType(t) {
			return true
		}
	}
	// Union types: flaggable only when every alternative is scalar or null.
	if arr, ok := prop["type"].([]any); ok {
		if len(arr) == 0 {
			return false
		}
		for _, item := range arr {
			s, _ := item.(string)
			if s == "null" {
				continue
			}
			if !isPlainScalarType(s) {
				return false
			}
		}
		return true
	}
	// Enum of primitives without type → flag.
	if enum, ok := prop["enum"].([]any); ok && len(enum) > 0 {
		for _, v := range enum {
			switch v.(type) {
			case string, float64, bool, json.Number, nil:
				// ok
			default:
				return false
			}
		}
		return true
	}
	// Structural object/array markers without a clean scalar type.
	if _, ok := prop["properties"]; ok {
		return false
	}
	if _, ok := prop["items"]; ok {
		return false
	}
	if _, ok := prop["additionalProperties"]; ok {
		return false
	}
	// oneOf / anyOf / allOf without a plain scalar type → json-only.
	if _, ok := prop["oneOf"]; ok {
		return false
	}
	if _, ok := prop["anyOf"]; ok {
		return false
	}
	if _, ok := prop["allOf"]; ok {
		return false
	}
	return false
}

func isPlainScalarType(t string) bool {
	switch t {
	case "string", "integer", "number", "boolean":
		return true
	default:
		return false
	}
}

// FlagName converts a schema property name to canonical kebab-case flag (`--customer-id`).
func FlagName(prop string) string {
	return "--" + toKebab(prop)
}

func toKebab(s string) string {
	if s == "" {
		return s
	}
	// Normalize existing separators and camelCase.
	var b strings.Builder
	b.Grow(len(s) + 4)
	prevDash := false
	for i, r := range s {
		switch {
		case r == '_' || r == '-' || r == ' ':
			if !prevDash && b.Len() > 0 {
				b.WriteByte('-')
				prevDash = true
			}
		case unicode.IsUpper(r):
			if i > 0 && !prevDash {
				b.WriteByte('-')
			}
			b.WriteRune(unicode.ToLower(r))
			prevDash = false
		default:
			b.WriteRune(unicode.ToLower(r))
			prevDash = false
		}
	}
	out := b.String()
	out = strings.Trim(out, "-")
	for strings.Contains(out, "--") {
		out = strings.ReplaceAll(out, "--", "-")
	}
	return out
}

func requiredSet(schema map[string]any) map[string]bool {
	out := map[string]bool{}
	req, ok := schema["required"].([]any)
	if !ok {
		return out
	}
	for _, r := range req {
		if s, ok := r.(string); ok && s != "" {
			out[s] = true
		}
	}
	return out
}

func schemaTypeLabel(prop map[string]any) string {
	if prop == nil {
		return "any"
	}
	switch t := prop["type"].(type) {
	case string:
		return t
	case []any:
		parts := make([]string, 0, len(t))
		for _, item := range t {
			if s, ok := item.(string); ok {
				parts = append(parts, s)
			}
		}
		if len(parts) == 0 {
			return "any"
		}
		return strings.Join(parts, "|")
	}
	if _, ok := prop["enum"]; ok {
		return "enum"
	}
	if _, ok := prop["properties"]; ok {
		return "object"
	}
	if _, ok := prop["items"]; ok {
		return "array"
	}
	return "any"
}

func extractConstraints(prop map[string]any) map[string]any {
	out := map[string]any{}
	if prop == nil {
		return out
	}
	for _, k := range constraintKeys {
		if v, ok := prop[k]; ok {
			out[k] = v
		}
	}
	return out
}
