// Package catalog fetches and caches capability JSON Schema documents for local UX validation.
// Server remains the law — local cache is never authorization (D-004).
package catalog

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"os"
	"path/filepath"
	"sync"

	"github.com/rawphp/capabilities-cli/internal/api"
)

// CacheEntry is one cached schema document.
type CacheEntry struct {
	Name          string          `json:"name"`
	SchemaVersion string          `json:"schema_version"`
	ETag          string          `json:"etag,omitempty"`
	InputSchema   json.RawMessage `json:"input_schema"`
	OutputSchema  json.RawMessage `json:"output_schema,omitempty"`
	Deprecated    bool            `json:"deprecated,omitempty"`
	Successor     string          `json:"successor,omitempty"`
	SunsetAt      string          `json:"sunset_at,omitempty"`
	Aliases       []string        `json:"aliases,omitempty"`
	Canonical     string          `json:"canonical,omitempty"`
	// CLI is optional routing metadata preserved from describe when present.
	CLI *CLIMeta `json:"cli,omitempty"`
}

// Cache is an on-disk schema cache; production callers scope it with PrincipalCache.
type Cache struct {
	Dir string
	mu  sync.Mutex
}

// NewCache creates a cache under dir.
func NewCache(dir string) *Cache {
	return &Cache{Dir: dir}
}

func (c *Cache) path(name string) string {
	// Safe file name from capability name.
	safe := filepath.Base(name) + ".json"
	return filepath.Join(c.Dir, safe)
}

// Get returns a cache entry when present and version matches (if version != "").
func (c *Cache) Get(name, version string) (*CacheEntry, bool) {
	c.mu.Lock()
	defer c.mu.Unlock()
	b, err := os.ReadFile(c.path(name))
	if err != nil {
		return nil, false
	}
	var e CacheEntry
	if err := json.Unmarshal(b, &e); err != nil {
		// Corrupt → miss (caller refetches).
		return nil, false
	}
	if version != "" && e.SchemaVersion != version {
		return nil, false
	}
	return &e, true
}

// GetByETag returns entry only when etag matches.
func (c *Cache) GetByETag(name, etag string) (*CacheEntry, bool) {
	e, ok := c.Get(name, "")
	if !ok {
		return nil, false
	}
	if etag != "" && e.ETag != etag {
		return nil, false
	}
	return e, true
}

// Put writes an entry atomically (temp + rename).
func (c *Cache) Put(entry *CacheEntry) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	if err := os.MkdirAll(c.Dir, 0o700); err != nil {
		return err
	}
	b, err := json.MarshalIndent(entry, "", "  ")
	if err != nil {
		return err
	}
	final := c.path(entry.Name)
	tmp := final + ".tmp"
	if err := os.WriteFile(tmp, b, 0o600); err != nil {
		return err
	}
	return os.Rename(tmp, final)
}

// Invalidate removes a cached name (or all if name == "").
func (c *Cache) Invalidate(name string) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	if name == "" {
		entries, err := os.ReadDir(c.Dir)
		if err != nil {
			if os.IsNotExist(err) {
				return nil
			}
			return err
		}
		for _, e := range entries {
			_ = os.Remove(filepath.Join(c.Dir, e.Name()))
		}
		return nil
	}
	err := os.Remove(c.path(name))
	if err != nil && !os.IsNotExist(err) {
		return err
	}
	return nil
}

// PrincipalCache returns the cache for the principal c authenticates as, under a
// profile's schema root. The catalog is filtered by server authorization, so
// schemas cached for one credential or deployment are never read by another.
// The key is a hash so the raw token never lands in a path.
func PrincipalCache(root string, c *api.Client) *Cache {
	sum := sha256.Sum256([]byte(c.BaseURL + "\x00" + c.Token))
	return NewCache(filepath.Join(root, hex.EncodeToString(sum[:8])))
}
