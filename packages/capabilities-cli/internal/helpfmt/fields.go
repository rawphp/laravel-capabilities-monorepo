// Package helpfmt formats human and machine capability/domain help from schemas.
// Help is the primary contract surface (Schema help UX design).
// Flag names and pass modes come from flagschema, so help advertises exactly the
// flags invoke accepts.
package helpfmt

import (
	"encoding/json"
	"fmt"
	"sort"
	"strings"

	"github.com/rawphp/capabilities-cli/internal/flagschema"
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
	flags, _ := flagschema.FromSchemaMap(inputSchema) // never errors on a decoded map

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
		out = append(out, fieldFromProp(name, ps, required[name], flags))
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

func fieldFromProp(name string, prop map[string]any, required bool, flags *flagschema.Schema) Field {
	f := Field{
		Name:        name,
		Type:        schemaTypeLabel(prop),
		Required:    required,
		Pass:        PassJSONOnly,
		Constraints: extractConstraints(prop),
	}
	if ff, ok := flags.LookupName(name); ok && ff.Pass == flagschema.PassFlag {
		flag := "--" + ff.FlagName
		f.Flag = &flag
		f.Pass = PassFlag
	}
	return f
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
