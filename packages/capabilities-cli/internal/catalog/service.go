package catalog

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
)

// Service loads catalog entries from HTTP and the local schema cache.
type Service struct {
	Client  *api.Client
	Cache   *Cache
	NoCache bool
}

// CLIMeta is optional CLI routing metadata on a catalog list/describe row (wire: cli).
// Both Domain and Verb are required when CLI is present; incomplete objects are fail-closed for synthesis.
type CLIMeta struct {
	Domain string `json:"domain,omitempty"`
	Verb   string `json:"verb,omitempty"`
}

// CapabilitySummary is a list-row (schemas may be omitted until describe).
type CapabilitySummary struct {
	Name          string   `json:"name"`
	Description   string   `json:"description,omitempty"`
	Aliases       []string `json:"aliases,omitempty"`
	Deprecated    bool     `json:"deprecated,omitempty"`
	Successor     string   `json:"successor,omitempty"`
	SunsetAt      string   `json:"sunset_at,omitempty"`
	SchemaVersion string   `json:"schema_version,omitempty"`
	Surfaces      []string `json:"surfaces,omitempty"`
	// CLI is routing metadata for domain/verb synthesis (omit when unmapped).
	CLI *CLIMeta `json:"cli,omitempty"`
	// MappedCommand is optional client-derived "domain verb" after synth index build.
	MappedCommand string `json:"mapped_command,omitempty"`
	// MappingError is set client-side when synthesis was suppressed (e.g. collision).
	MappingError string `json:"mapping_error,omitempty"`
	// InputSchema/OutputSchema present when list was fetched with include_schemas=1.
	InputSchema  json.RawMessage `json:"input_schema,omitempty"`
	OutputSchema json.RawMessage `json:"output_schema,omitempty"`
}

// List fetches GET /capabilities and returns summaries.
func (s *Service) List(ctx context.Context) ([]CapabilitySummary, *api.Response, error) {
	res, err := s.Client.ListCapabilities(ctx)
	if err != nil {
		return nil, nil, err
	}
	return parseCapabilityList(res)
}

// ListWithSchemas fetches GET /capabilities?include_schemas=1 for agent one-shot discovery.
func (s *Service) ListWithSchemas(ctx context.Context) ([]CapabilitySummary, *api.Response, error) {
	res, err := s.Client.ListCapabilitiesWithSchemas(ctx)
	if err != nil {
		return nil, nil, err
	}
	return parseCapabilityList(res)
}

func parseCapabilityList(res *api.Response) ([]CapabilitySummary, *api.Response, error) {
	if res.Err != nil {
		return nil, res, res.Err
	}
	var payload struct {
		OK   bool `json:"ok"`
		Data struct {
			Capabilities []CapabilitySummary `json:"capabilities"`
		} `json:"data"`
	}
	// Also accept bare array or data as array. Empty list is valid.
	if err := json.Unmarshal(res.Body, &payload); err == nil && payload.Data.Capabilities != nil {
		return payload.Data.Capabilities, res, nil
	}
	// Detect object shape with capabilities key even when empty via raw map.
	var raw map[string]any
	if err := json.Unmarshal(res.Body, &raw); err == nil {
		if data, ok := raw["data"].(map[string]any); ok {
			if caps, ok := data["capabilities"].([]any); ok {
				out := make([]CapabilitySummary, 0, len(caps))
				for _, c := range caps {
					if m, ok := c.(map[string]any); ok {
						name, _ := m["name"].(string)
						out = append(out, CapabilitySummary{Name: name})
					}
				}
				return out, res, nil
			}
		}
	}
	var alt struct {
		OK   bool                `json:"ok"`
		Data []CapabilitySummary `json:"data"`
	}
	if err := json.Unmarshal(res.Body, &alt); err == nil && alt.Data != nil {
		return alt.Data, res, nil
	}
	var bare []CapabilitySummary
	if err := json.Unmarshal(res.Body, &bare); err == nil {
		return bare, res, nil
	}
	return nil, res, fmt.Errorf("unexpected catalog list shape")
}

// Describe returns schema for name (cache-aware).
func (s *Service) Describe(ctx context.Context, name string) (*CacheEntry, *api.Response, error) {
	if !s.NoCache && s.Cache != nil {
		if e, ok := s.Cache.Get(name, ""); ok {
			return e, nil, nil
		}
	}
	res, err := s.Client.DescribeCapability(ctx, name)
	if err != nil {
		return nil, nil, err
	}
	if res.Err != nil {
		return nil, res, res.Err
	}
	entry, err := parseDescribe(res)
	if err != nil {
		return nil, res, err
	}
	if s.Cache != nil && !s.NoCache {
		_ = s.Cache.Put(entry)
	}
	return entry, res, nil
}

// Refresh invalidates cache and re-lists.
func (s *Service) Refresh(ctx context.Context) ([]CapabilitySummary, error) {
	if s.Cache != nil {
		_ = s.Cache.Invalidate("")
	}
	list, _, err := s.List(ctx)
	return list, err
}

// ForceFetchDescribe bypasses cache.
func (s *Service) ForceFetchDescribe(ctx context.Context, name string) (*CacheEntry, *api.Response, error) {
	prev := s.NoCache
	s.NoCache = true
	defer func() { s.NoCache = prev }()
	return s.Describe(ctx, name)
}

func parseDescribe(res *api.Response) (*CacheEntry, error) {
	var wrap struct {
		OK   bool            `json:"ok"`
		Data json.RawMessage `json:"data"`
	}
	raw := res.Body
	if err := json.Unmarshal(res.Body, &wrap); err == nil && len(wrap.Data) > 0 {
		raw = wrap.Data
	}
	var e CacheEntry
	if err := json.Unmarshal(raw, &e); err != nil {
		return nil, err
	}
	// Map alternate field layouts.
	var m map[string]any
	_ = json.Unmarshal(raw, &m)
	if e.Name == "" {
		if n, ok := m["name"].(string); ok {
			e.Name = n
		}
	}
	if e.SchemaVersion == "" {
		if v, ok := m["schema_version"].(string); ok {
			e.SchemaVersion = v
		}
	}
	if len(e.InputSchema) == 0 {
		if is, ok := m["input_schema"]; ok {
			b, _ := json.Marshal(is)
			e.InputSchema = b
		}
	}
	if et := res.Header.Get("ETag"); et != "" {
		e.ETag = strings.Trim(et, `"`)
	}
	if e.Canonical == "" {
		e.Canonical = e.Name
	}
	return &e, nil
}

// DeprecationWarning returns a human warning when entry is deprecated or past sunset.
func DeprecationWarning(e *CacheEntry, now time.Time) string {
	if e == nil {
		return ""
	}
	if e.SunsetAt != "" {
		if t, err := time.Parse("2006-01-02", e.SunsetAt); err == nil && !now.Before(t) {
			msg := fmt.Sprintf("capability %q is past sunset (%s)", e.Name, e.SunsetAt)
			if e.Successor != "" {
				msg += "; use " + e.Successor
			}
			return msg
		}
	}
	if e.Deprecated {
		msg := fmt.Sprintf("capability %q is deprecated", e.Name)
		if e.Successor != "" {
			msg += "; successor: " + e.Successor
		}
		return msg
	}
	return ""
}

// ResolveAlias returns canonical name when name matches an alias in entry.
func ResolveAlias(entry *CacheEntry, name string) string {
	if entry == nil {
		return name
	}
	if entry.Name == name || entry.Canonical == name {
		if entry.Canonical != "" {
			return entry.Canonical
		}
		return entry.Name
	}
	for _, a := range entry.Aliases {
		if a == name {
			if entry.Canonical != "" {
				return entry.Canonical
			}
			return entry.Name
		}
	}
	return name
}

// EnvelopeJSON builds a D-018-ish catalog list envelope for --json.
func EnvelopeJSON(list []CapabilitySummary) []byte {
	payload := map[string]any{
		"ok":   true,
		"data": map[string]any{"capabilities": list},
	}
	b, _ := json.MarshalIndent(payload, "", "  ")
	return b
}
