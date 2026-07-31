// Package flagschema derives CLI scalar flags from JSON Schema properties
// and merges flags with JSON input (flag wins). Design: equal first-class
// inputs + merge rule (ORI-174).
package flagschema

import (
	"encoding/json"
	"fmt"
	"strconv"
	"strings"
)

// PassMode is how an input property may be supplied on the CLI.
type PassMode string

const (
	// PassFlag: property is a flat scalar (or scalar enum) exposed as a flag.
	PassFlag PassMode = "flag"
	// PassJSONOnly: object/array/non-plain-scalar — must come via --input/--input-file.
	PassJSONOnly PassMode = "json-only"
)

// Sentinel errors for merge/reject cases (exit 2 at the call site).
var (
	ErrUnknownFlag     = fmt.Errorf("unknown flag")
	ErrJSONOnlyFlag    = fmt.Errorf("flag targets json-only property")
	ErrInvalidScalar   = fmt.Errorf("invalid scalar flag value")
	ErrInvalidBaseJSON = fmt.Errorf("invalid base JSON")
	ErrInvalidSchema   = fmt.Errorf("invalid input schema")
)

// Field describes one schema property for flags + help.
type Field struct {
	Name     string   // schema property name (e.g. customer_id)
	FlagName string   // canonical kebab flag without leading -- (e.g. customer-id)
	Type     string   // primary type label: string|integer|number|boolean|object|array|enum|…
	Required bool
	Pass     PassMode
	Enum     []any // optional enum values (JSON-decoded)
}

// Schema is the flag model derived from a capability input_schema.
type Schema struct {
	Fields []Field

	byFlag map[string]*Field // kebab flag name → field
	byName map[string]*Field // property name → field
}

// FromJSONSchema parses a JSON Schema document (object with properties)
// into a Schema. Non-object roots yield zero fields.
func FromJSONSchema(schemaJSON []byte) (*Schema, error) {
	if len(schemaJSON) == 0 {
		return &Schema{byFlag: map[string]*Field{}, byName: map[string]*Field{}}, nil
	}
	var raw map[string]any
	if err := json.Unmarshal(schemaJSON, &raw); err != nil {
		return nil, fmt.Errorf("%w: %v", ErrInvalidSchema, err)
	}
	return FromSchemaMap(raw)
}

// FromSchemaMap builds a Schema from an already-decoded JSON Schema object.
func FromSchemaMap(schema map[string]any) (*Schema, error) {
	s := &Schema{
		byFlag: map[string]*Field{},
		byName: map[string]*Field{},
	}
	if schema == nil {
		return s, nil
	}

	required := map[string]bool{}
	if req, ok := schema["required"].([]any); ok {
		for _, r := range req {
			if name, ok := r.(string); ok && name != "" {
				required[name] = true
			}
		}
	}

	props, _ := schema["properties"].(map[string]any)
	// Stable-ish order: range is random; tests index by name so order is fine.
	// For deterministic help later, collect and sort when needed.
	for name, raw := range props {
		ps, ok := raw.(map[string]any)
		if !ok {
			continue
		}
		f := fieldFromProp(name, ps, required[name])
		s.Fields = append(s.Fields, f)
	}
	// Rebuild pointers into maps after append (slice growth safe).
	for i := range s.Fields {
		fp := &s.Fields[i]
		s.byName[fp.Name] = fp
		s.byFlag[fp.FlagName] = fp
	}
	return s, nil
}

func fieldFromProp(name string, prop map[string]any, required bool) Field {
	f := Field{
		Name:     name,
		FlagName: ToKebab(name),
		Required: required,
	}

	if enum, ok := prop["enum"].([]any); ok && len(enum) > 0 {
		f.Enum = enum
	}

	typeLabel, pass := classify(prop)
	f.Type = typeLabel
	f.Pass = pass
	return f
}

// classify returns (type label, pass mode) for a property schema.
func classify(prop map[string]any) (string, PassMode) {
	// oneOf / anyOf / allOf → non-plain composite
	for _, key := range []string{"oneOf", "anyOf", "allOf"} {
		if _, ok := prop[key]; ok {
			return key, PassJSONOnly
		}
	}

	// enum of plain scalars → flag (type may be absent)
	if enum, ok := prop["enum"].([]any); ok && len(enum) > 0 {
		if allPlainEnum(enum) {
			label := "enum"
			if t := singlePlainType(prop["type"]); t != "" {
				label = t
			} else if t := enumValueType(enum[0]); t != "" {
				label = t
			}
			return label, PassFlag
		}
		return "enum", PassJSONOnly
	}

	switch t := prop["type"].(type) {
	case string:
		if isPlainScalarType(t) {
			return t, PassFlag
		}
		if t == "object" || t == "array" {
			return t, PassJSONOnly
		}
		return t, PassJSONOnly
	case []any:
		// type union: string|null remains flag; multi non-null types → json-only
		plain, hasNull := plainTypesFromUnion(t)
		if len(plain) == 1 {
			return plain[0], PassFlag
		}
		if len(plain) == 0 && hasNull {
			return "null", PassJSONOnly
		}
		return "union", PassJSONOnly
	default:
		// no type, no enum → treat as json-only free-form
		return "unknown", PassJSONOnly
	}
}

func isPlainScalarType(t string) bool {
	switch t {
	case "string", "integer", "number", "boolean":
		return true
	default:
		return false
	}
}

func singlePlainType(v any) string {
	switch t := v.(type) {
	case string:
		if isPlainScalarType(t) {
			return t
		}
	case []any:
		plain, _ := plainTypesFromUnion(t)
		if len(plain) == 1 {
			return plain[0]
		}
	}
	return ""
}

func plainTypesFromUnion(types []any) (plain []string, hasNull bool) {
	seen := map[string]bool{}
	for _, raw := range types {
		ts, ok := raw.(string)
		if !ok {
			continue
		}
		if ts == "null" {
			hasNull = true
			continue
		}
		if isPlainScalarType(ts) && !seen[ts] {
			seen[ts] = true
			plain = append(plain, ts)
		} else if !isPlainScalarType(ts) {
			// object/array in union forces non-plain
			return []string{}, hasNull
		}
	}
	// if any non-plain non-null was present we returned early empty
	// re-scan for non-plain:
	for _, raw := range types {
		ts, _ := raw.(string)
		if ts == "" || ts == "null" {
			continue
		}
		if !isPlainScalarType(ts) {
			return nil, hasNull
		}
	}
	return plain, hasNull
}

func allPlainEnum(enum []any) bool {
	for _, v := range enum {
		switch v.(type) {
		case string, bool, float64, json.Number:
			// ok
		case nil:
			// allow null in enum
		default:
			// objects/arrays in enum → not plain
			return false
		}
	}
	return true
}

func enumValueType(v any) string {
	switch v.(type) {
	case string:
		return "string"
	case bool:
		return "boolean"
	case float64:
		// JSON numbers decode as float64; prefer integer when whole
		return "number"
	default:
		return "enum"
	}
}

// ToKebab converts a schema property name to the canonical kebab-case flag
// name (without leading --). v1: underscore → hyphen only; no camelCase split.
func ToKebab(propertyName string) string {
	return strings.ReplaceAll(propertyName, "_", "-")
}

// LookupFlag returns the field for a canonical kebab flag name.
func (s *Schema) LookupFlag(flagName string) (*Field, bool) {
	if s == nil {
		return nil, false
	}
	f, ok := s.byFlag[flagName]
	return f, ok
}

// LookupName returns the field for a schema property name.
func (s *Schema) LookupName(propertyName string) (*Field, bool) {
	if s == nil {
		return nil, false
	}
	f, ok := s.byName[propertyName]
	return f, ok
}

// Merge combines base JSON (from --input / --input-file) with provided
// scalar flags. Base is {} when baseJSON is nil or empty.
// Flags are keyed by canonical kebab flag name (no leading --).
// On key conflict, the flag value wins. Absent optional flags omit the key.
// Unknown flags, flags targeting json-only properties, and invalid scalar
// types return a sentinel error suitable for exit 2.
func (s *Schema) Merge(baseJSON []byte, flags map[string]string) (map[string]any, error) {
	out := map[string]any{}
	if len(baseJSON) > 0 {
		if !json.Valid(baseJSON) {
			return nil, fmt.Errorf("%w: not valid JSON", ErrInvalidBaseJSON)
		}
		var base any
		if err := json.Unmarshal(baseJSON, &base); err != nil {
			return nil, fmt.Errorf("%w: %v", ErrInvalidBaseJSON, err)
		}
		obj, ok := base.(map[string]any)
		if !ok {
			return nil, fmt.Errorf("%w: base must be a JSON object", ErrInvalidBaseJSON)
		}
		for k, v := range obj {
			out[k] = v
		}
	}

	if s == nil {
		s = &Schema{byFlag: map[string]*Field{}, byName: map[string]*Field{}}
	}

	for flagName, raw := range flags {
		f, ok := s.byFlag[flagName]
		if !ok {
			return nil, fmt.Errorf("%w: --%s", ErrUnknownFlag, flagName)
		}
		if f.Pass == PassJSONOnly {
			return nil, fmt.Errorf("%w: --%s (property %q is json-only; pass via --input/--input-file)", ErrJSONOnlyFlag, flagName, f.Name)
		}
		val, err := parseScalar(f, raw)
		if err != nil {
			return nil, err
		}
		out[f.Name] = val
	}
	return out, nil
}

// MergeJSON is Merge returning marshaled JSON bytes.
func (s *Schema) MergeJSON(baseJSON []byte, flags map[string]string) ([]byte, error) {
	m, err := s.Merge(baseJSON, flags)
	if err != nil {
		return nil, err
	}
	return json.Marshal(m)
}

func parseScalar(f *Field, raw string) (any, error) {
	// Prefer enum validation when present.
	if len(f.Enum) > 0 {
		return parseEnum(f, raw)
	}
	switch f.Type {
	case "string":
		return raw, nil
	case "integer":
		n, err := strconv.ParseInt(raw, 10, 64)
		if err != nil {
			return nil, fmt.Errorf("%w: --%s expects integer, got %q", ErrInvalidScalar, f.FlagName, raw)
		}
		return n, nil
	case "number":
		n, err := strconv.ParseFloat(raw, 64)
		if err != nil {
			return nil, fmt.Errorf("%w: --%s expects number, got %q", ErrInvalidScalar, f.FlagName, raw)
		}
		return n, nil
	case "boolean":
		switch strings.ToLower(raw) {
		case "true", "":
			// bare --flag treated as true
			return true, nil
		case "false":
			return false, nil
		default:
			return nil, fmt.Errorf("%w: --%s expects true|false, got %q", ErrInvalidScalar, f.FlagName, raw)
		}
	default:
		// unknown typed flag — treat as string
		return raw, nil
	}
}

func parseEnum(f *Field, raw string) (any, error) {
	for _, candidate := range f.Enum {
		switch c := candidate.(type) {
		case string:
			if c == raw {
				return c, nil
			}
		case bool:
			if (c && (raw == "true" || raw == "")) || (!c && raw == "false") {
				return c, nil
			}
		case float64:
			// JSON numbers
			if i, err := strconv.ParseInt(raw, 10, 64); err == nil {
				if float64(i) == c && c == float64(int64(c)) {
					return int64(i), nil
				}
			}
			if n, err := strconv.ParseFloat(raw, 64); err == nil && n == c {
				return c, nil
			}
		case nil:
			if raw == "null" {
				return nil, nil
			}
		}
	}
	return nil, fmt.Errorf("%w: --%s value %q not in enum", ErrInvalidScalar, f.FlagName, raw)
}
