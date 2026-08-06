package helpfmt

import (
	"encoding/json"
	"fmt"
	"sort"
	"strings"
)

// CapabilityInfo is the input for building capability help (human or machine).
// Domain and Verb may be empty for unmapped `run <name>` help (serialized as null).
type CapabilityInfo struct {
	Domain        string
	Verb          string
	Name          string
	Description   string
	SchemaVersion string
	InputSchema   map[string]any
	OutputSchema  map[string]any
}

// Examples holds illustrative invoke strings for help.
type Examples struct {
	Flags string `json:"flags,omitempty"`
	JSON  string `json:"json"`
}

// CapabilityHelp is the machine `capability_help` data payload.
type CapabilityHelp struct {
	Kind          string         `json:"kind"`
	Domain        *string        `json:"domain"`
	Verb          *string        `json:"verb"`
	Name          string         `json:"name"`
	Description   string         `json:"description,omitempty"`
	SchemaVersion string         `json:"schema_version,omitempty"`
	InputSchema   map[string]any `json:"input_schema"`
	OutputSchema  map[string]any `json:"output_schema"`
	Fields        []Field        `json:"fields"`
	Examples      Examples       `json:"examples"`
}

// DomainVerb is one synthesizable verb under a domain.
type DomainVerb struct {
	Verb        string `json:"verb"`
	Name        string `json:"name"`
	Description string `json:"description,omitempty"`
}

// DomainHelp is the machine domain list payload.
type DomainHelp struct {
	Kind   string       `json:"kind"`
	Domain string       `json:"domain"`
	Verbs  []DomainVerb `json:"verbs"`
}

// BuildCapabilityHelp derives fields and examples from schema + routing metadata.
func BuildCapabilityHelp(info CapabilityInfo) CapabilityHelp {
	in := info.InputSchema
	if in == nil {
		in = map[string]any{}
	}
	out := info.OutputSchema
	if out == nil {
		out = map[string]any{}
	}
	fields := DeriveFields(in)
	h := CapabilityHelp{
		Kind:          KindCapabilityHelp,
		Name:          info.Name,
		Description:   info.Description,
		SchemaVersion: info.SchemaVersion,
		InputSchema:   in,
		OutputSchema:  out,
		Fields:        fields,
		Examples:      buildExamples(info, fields),
	}
	if info.Domain != "" {
		d := info.Domain
		h.Domain = &d
	}
	if info.Verb != "" {
		v := info.Verb
		h.Verb = &v
	}
	return h
}

// FormatMachineCapability returns a D-018-style success envelope with capability_help data.
// Callers print to stdout and exit 0 (no invoke).
func FormatMachineCapability(h CapabilityHelp) []byte {
	if h.Kind == "" {
		h.Kind = KindCapabilityHelp
	}
	if h.InputSchema == nil {
		h.InputSchema = map[string]any{}
	}
	if h.OutputSchema == nil {
		h.OutputSchema = map[string]any{}
	}
	if h.Fields == nil {
		h.Fields = []Field{}
	}
	payload := map[string]any{
		"ok":   true,
		"data": h,
	}
	b, _ := json.MarshalIndent(payload, "", "  ")
	return append(b, '\n')
}

// FormatMachineDomain returns a D-018-style envelope listing domain verbs.
func FormatMachineDomain(domain string, verbs []DomainVerb) []byte {
	if verbs == nil {
		verbs = []DomainVerb{}
	}
	h := DomainHelp{
		Kind:   KindDomainHelp,
		Domain: domain,
		Verbs:  verbs,
	}
	payload := map[string]any{
		"ok":   true,
		"data": h,
	}
	b, _ := json.MarshalIndent(payload, "", "  ")
	return append(b, '\n')
}

// FormatHumanCapability renders human capability help with INPUT table, OUTPUT, examples.
func FormatHumanCapability(h CapabilityHelp) string {
	var b strings.Builder

	title := h.Name
	if h.Domain != nil && h.Verb != nil {
		title = fmt.Sprintf("%s %s", *h.Domain, *h.Verb)
	}
	if h.Description != "" {
		fmt.Fprintf(&b, "%s — %s\n\n", title, h.Description)
	} else {
		fmt.Fprintf(&b, "%s\n\n", title)
	}

	fmt.Fprintf(&b, "USAGE:\n")
	if h.Domain != nil && h.Verb != nil {
		fmt.Fprintf(&b, "  capabilities %s %s [flags]\n", *h.Domain, *h.Verb)
		fmt.Fprintf(&b, "  capabilities %s %s --input=JSON\n", *h.Domain, *h.Verb)
	} else {
		fmt.Fprintf(&b, "  capabilities run %s [flags]\n", h.Name)
		fmt.Fprintf(&b, "  capabilities run %s --input=JSON\n", h.Name)
	}
	fmt.Fprintf(&b, "\n")
	fmt.Fprintf(&b, "Canonical name: %s\n", h.Name)
	if h.SchemaVersion != "" {
		fmt.Fprintf(&b, "schema_version: %s\n", h.SchemaVersion)
	}
	fmt.Fprintf(&b, "\n")

	fmt.Fprintf(&b, "INPUT:\n")
	if len(h.Fields) == 0 {
		fmt.Fprintf(&b, "  (no properties)\n")
	} else {
		// Columns: NAME TYPE REQUIRED PASS CONSTRAINTS FLAG
		fmt.Fprintf(&b, "  %-16s %-12s %-8s %-10s %-24s %s\n",
			"NAME", "TYPE", "REQUIRED", "PASS", "CONSTRAINTS", "FLAG")
		for _, f := range h.Fields {
			req := "no"
			if f.Required {
				req = "yes"
			}
			flag := "json-only"
			if f.Flag != nil {
				flag = *f.Flag
			}
			fmt.Fprintf(&b, "  %-16s %-12s %-8s %-10s %-24s %s\n",
				f.Name, f.Type, req, f.Pass, formatConstraints(f.Constraints), flag)
		}
	}
	fmt.Fprintf(&b, "\n")

	fmt.Fprintf(&b, "OUTPUT:\n")
	fmt.Fprintf(&b, "%s", formatOutputSummary(h.OutputSchema))
	fmt.Fprintf(&b, "\n")

	fmt.Fprintf(&b, "EXAMPLES:\n")
	if h.Examples.Flags != "" {
		fmt.Fprintf(&b, "  %s\n", h.Examples.Flags)
	}
	if h.Examples.JSON != "" {
		fmt.Fprintf(&b, "  %s\n", h.Examples.JSON)
	}
	fmt.Fprintf(&b, "\n")

	fmt.Fprintf(&b, "SEE ALSO:\n")
	fmt.Fprintf(&b, "  capabilities describe %s\n", h.Name)
	fmt.Fprintf(&b, "  capabilities run %s --input=JSON\n", h.Name)
	if h.Domain != nil && h.Verb != nil {
		fmt.Fprintf(&b, "  capabilities %s %s --human     # one-line success on stderr\n", *h.Domain, *h.Verb)
	} else {
		fmt.Fprintf(&b, "  capabilities run %s --human     # one-line success on stderr\n", h.Name)
	}
	fmt.Fprintf(&b, "  capabilities help\n")
	return b.String()
}

// FormatHumanDomain lists verbs with one-line descriptions and canonical names.
func FormatHumanDomain(domain string, verbs []DomainVerb) string {
	var b strings.Builder
	fmt.Fprintf(&b, "%s — domain capabilities\n\n", domain)
	fmt.Fprintf(&b, "VERBS:\n")
	if len(verbs) == 0 {
		fmt.Fprintf(&b, "  (none)\n")
	} else {
		// Stable sort by verb for display.
		sorted := append([]DomainVerb(nil), verbs...)
		sort.Slice(sorted, func(i, j int) bool {
			return sorted[i].Verb < sorted[j].Verb
		})
		for _, v := range sorted {
			desc := v.Description
			if desc == "" {
				desc = "-"
			}
			fmt.Fprintf(&b, "  %-16s %-24s %s\n", v.Verb, v.Name, desc)
		}
	}
	fmt.Fprintf(&b, "\nUSAGE:\n")
	fmt.Fprintf(&b, "  capabilities %s <verb> --help\n", domain)
	fmt.Fprintf(&b, "  capabilities %s <verb> [flags]\n", domain)
	fmt.Fprintf(&b, "  capabilities %s --help --json\n", domain)
	return b.String()
}

func buildExamples(info CapabilityInfo, fields []Field) Examples {
	base := invokeBase(info)
	ex := Examples{}

	// JSON example with a minimal object of required scalar-looking fields.
	payload := map[string]any{}
	for _, f := range fields {
		if !f.Required {
			continue
		}
		payload[f.Name] = exampleValue(f)
	}
	// If nothing required, show one optional scalar when available.
	if len(payload) == 0 {
		for _, f := range fields {
			if f.Pass == PassFlag {
				payload[f.Name] = exampleValue(f)
				break
			}
		}
	}
	raw, _ := json.Marshal(payload)
	if len(payload) == 0 {
		raw = []byte("{}")
	}
	ex.JSON = fmt.Sprintf("%s --input='%s'", base, string(raw))

	// Flags example: only scalar/enum flags (skip json-only).
	var flagParts []string
	for _, f := range fields {
		if f.Pass != PassFlag || f.Flag == nil {
			continue
		}
		// Prefer required flags in the example; include a few optionals if none required.
		if f.Required {
			flagParts = append(flagParts, fmt.Sprintf("%s=%v", *f.Flag, exampleValue(f)))
		}
	}
	if len(flagParts) == 0 {
		for _, f := range fields {
			if f.Pass == PassFlag && f.Flag != nil {
				flagParts = append(flagParts, fmt.Sprintf("%s=%v", *f.Flag, exampleValue(f)))
				if len(flagParts) >= 3 {
					break
				}
			}
		}
	}
	if len(flagParts) > 0 {
		ex.Flags = base + " " + strings.Join(flagParts, " ")
	}
	return ex
}

func invokeBase(info CapabilityInfo) string {
	if info.Domain != "" && info.Verb != "" {
		return fmt.Sprintf("capabilities %s %s", info.Domain, info.Verb)
	}
	return fmt.Sprintf("capabilities run %s", info.Name)
}

func exampleValue(f Field) any {
	if enum, ok := f.Constraints["enum"].([]any); ok && len(enum) > 0 {
		return enum[0]
	}
	if v, ok := f.Constraints["const"]; ok {
		return v
	}
	if v, ok := f.Constraints["default"]; ok {
		return v
	}
	// Structured types first — never stringify as "example" (invalid JSON Schema shape).
	if strings.Contains(f.Type, "array") {
		if min, ok := asFloat(f.Constraints["minItems"]); ok && min >= 1 {
			// minItems≥1: one empty object placeholder so paste isn't an empty-array fail.
			return []any{map[string]any{}}
		}
		return []any{}
	}
	if strings.Contains(f.Type, "object") {
		return map[string]any{}
	}
	switch {
	case strings.Contains(f.Type, "integer"):
		if min, ok := asFloat(f.Constraints["minimum"]); ok {
			return int64(min)
		}
		return 42
	case strings.Contains(f.Type, "number"):
		return 1.0
	case strings.Contains(f.Type, "boolean"):
		return true
	case strings.Contains(f.Type, "string"):
		return exampleString(f)
	default:
		return "example"
	}
}

// exampleString picks a paste-safe placeholder that respects format / common date names.
// Help used to emit date=example, which always failed format="date" validation.
func exampleString(f Field) string {
	if fmtName, ok := f.Constraints["format"].(string); ok {
		switch strings.ToLower(strings.TrimSpace(fmtName)) {
		case "date":
			return "2026-01-15"
		case "date-time", "datetime":
			return "2026-01-15T12:00:00Z"
		case "time":
			return "12:00:00"
		case "email":
			return "user@example.com"
		case "uri", "url":
			return "https://example.com"
		case "uuid":
			return "00000000-0000-4000-8000-000000000001"
		}
	}
	// Common property names when the server omitted format but means a calendar date.
	switch strings.ToLower(strings.TrimSpace(f.Name)) {
	case "date", "new_date", "from", "to", "start_date", "end_date", "on":
		return "2026-01-15"
	}
	if strings.HasSuffix(strings.ToLower(f.Name), "_date") {
		return "2026-01-15"
	}
	return "example"
}

func asFloat(v any) (float64, bool) {
	switch n := v.(type) {
	case float64:
		return n, true
	case json.Number:
		f, err := n.Float64()
		return f, err == nil
	case int:
		return float64(n), true
	case int64:
		return float64(n), true
	default:
		return 0, false
	}
}

func formatConstraints(c map[string]any) string {
	if len(c) == 0 {
		return "-"
	}
	// Stable key order from constraintKeys.
	var parts []string
	for _, k := range constraintKeys {
		v, ok := c[k]
		if !ok {
			continue
		}
		parts = append(parts, fmt.Sprintf("%s=%s", k, compactJSON(v)))
	}
	if len(parts) == 0 {
		return "-"
	}
	s := strings.Join(parts, ",")
	if len(s) > 24 {
		return s[:21] + "..."
	}
	return s
}

func compactJSON(v any) string {
	b, err := json.Marshal(v)
	if err != nil {
		return fmt.Sprint(v)
	}
	return string(b)
}

func formatOutputSummary(schema map[string]any) string {
	if schema == nil || len(schema) == 0 {
		return "  (no output_schema)\n"
	}
	props, _ := schema["properties"].(map[string]any)
	if len(props) == 0 {
		if t, ok := schema["type"].(string); ok {
			return fmt.Sprintf("  type: %s\n", t)
		}
		return "  (see output_schema via describe)\n"
	}
	names := make([]string, 0, len(props))
	for name := range props {
		names = append(names, name)
	}
	sort.Strings(names)
	var b strings.Builder
	fmt.Fprintf(&b, "  %-16s %s\n", "NAME", "TYPE")
	for _, name := range names {
		ps, _ := props[name].(map[string]any)
		fmt.Fprintf(&b, "  %-16s %s\n", name, schemaTypeLabel(ps))
	}
	return b.String()
}
