// Package auth stores CLI credentials in a profile-scoped secure store.
// Tokens never go into agent prompts; server derives caller from credentials (D-022).
package auth

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
)

// ErrNoToken is returned when a profile has no stored token.
var ErrNoToken = errors.New("not authenticated: run `capabilities auth login`")

// ErrInvalidBaseURL is returned for empty/malformed base URLs.
var ErrInvalidBaseURL = errors.New("invalid base URL")

// Profile holds non-secret profile metadata (base URL, labels).
// Token is stored separately and never echoed in status by default.
type Profile struct {
	Name    string `json:"name"`
	BaseURL string `json:"base_url"`
	// LoggedIn is derived; token value is never serialized into status output.
	LoggedIn bool `json:"logged_in"`
}

// Store is a profile-scoped credential + config store.
// Production uses file-backed 0600 storage under the config dir (OS keychain
// integration may wrap the same interface later). Tests inject temp dirs.
type Store struct {
	Root string
	mu   sync.Mutex
}

// NewStore creates a store rooted at dir (e.g. ~/.config/capabilities).
func NewStore(root string) *Store {
	return &Store{Root: root}
}

func (s *Store) profileDir(profile string) string {
	profile = sanitizeProfile(profile)
	return filepath.Join(s.Root, "profiles", profile)
}

func sanitizeProfile(p string) string {
	p = strings.TrimSpace(p)
	if p == "" {
		return "default"
	}
	// Keep filesystem-safe names.
	p = strings.Map(func(r rune) rune {
		if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '-' || r == '_' {
			return r
		}
		return '_'
	}, p)
	return p
}

// SetToken stores a token for profile with restrictive permissions.
func (s *Store) SetToken(profile, token string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	dir := s.profileDir(profile)
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}
	path := filepath.Join(dir, "token")
	return os.WriteFile(path, []byte(token), 0o600)
}

// GetToken reads the token or returns ErrNoToken.
func (s *Store) GetToken(profile string) (string, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	path := filepath.Join(s.profileDir(profile), "token")
	b, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return "", ErrNoToken
		}
		// Corrupt / unreadable → treat as missing for fail-closed auth.
		return "", ErrNoToken
	}
	tok := strings.TrimSpace(string(b))
	if tok == "" {
		return "", ErrNoToken
	}
	return tok, nil
}

// DeleteToken removes the stored token (logout). Idempotent.
func (s *Store) DeleteToken(profile string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	path := filepath.Join(s.profileDir(profile), "token")
	err := os.Remove(path)
	if err != nil && !os.IsNotExist(err) {
		return err
	}
	return nil
}

// NormalizeBaseURL validates and normalizes a deployment base URL without writing.
func NormalizeBaseURL(baseURL string) (string, error) {
	baseURL = strings.TrimSpace(baseURL)
	if baseURL == "" || !strings.HasPrefix(baseURL, "http") {
		return "", ErrInvalidBaseURL
	}
	return strings.TrimRight(baseURL, "/"), nil
}

// SetBaseURL stores the deployment base URL for a profile.
func (s *Store) SetBaseURL(profile, baseURL string) error {
	normalized, err := NormalizeBaseURL(baseURL)
	if err != nil {
		return err
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	dir := s.profileDir(profile)
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}
	path := filepath.Join(dir, "config.json")
	cfg := map[string]string{"base_url": normalized}
	b, _ := json.MarshalIndent(cfg, "", "  ")
	return os.WriteFile(path, b, 0o600)
}

// GetBaseURL returns the profile base URL.
func (s *Store) GetBaseURL(profile string) (string, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	path := filepath.Join(s.profileDir(profile), "config.json")
	b, err := os.ReadFile(path)
	if err != nil {
		return "", err
	}
	var cfg map[string]string
	if err := json.Unmarshal(b, &cfg); err != nil {
		return "", fmt.Errorf("corrupt profile config: %w", err)
	}
	return cfg["base_url"], nil
}

// Status returns non-secret profile status (never includes raw token).
func (s *Store) Status(profile string) Profile {
	p := Profile{Name: sanitizeProfile(profile)}
	if u, err := s.GetBaseURL(profile); err == nil {
		p.BaseURL = u
	}
	if _, err := s.GetToken(profile); err == nil {
		p.LoggedIn = true
	}
	return p
}

// ListProfiles returns non-secret status for every profile directory under the store.
// Never includes tokens. Empty slice when none exist (not an error).
func (s *Store) ListProfiles() []Profile {
	s.mu.Lock()
	root := filepath.Join(s.Root, "profiles")
	s.mu.Unlock()
	entries, err := os.ReadDir(root)
	if err != nil {
		return nil
	}
	out := make([]Profile, 0, len(entries))
	for _, e := range entries {
		if !e.IsDir() {
			continue
		}
		name := e.Name()
		if name == "" || name == "." || name == ".." {
			continue
		}
		out = append(out, s.Status(name))
	}
	// Stable order for humans and tests.
	sort.Slice(out, func(i, j int) bool { return out[i].Name < out[j].Name })
	return out
}

// RequireToken returns the token or ErrNoToken (exit 3 path for commands).
func (s *Store) RequireToken(profile string) (string, error) {
	return s.GetToken(profile)
}

// HasToken reports whether a non-empty token is stored.
func (s *Store) HasToken(profile string) bool {
	_, err := s.GetToken(profile)
	return err == nil
}

// SchemaCacheDir is where catalog schemas live for a profile.
func (s *Store) SchemaCacheDir(profile string) string {
	return filepath.Join(s.profileDir(profile), "schemas")
}

// LastRunPath is where last invoke metadata (idempotency key) is stored for --retry-last.
func (s *Store) LastRunPath(profile string) string {
	return filepath.Join(s.profileDir(profile), "last_run.json")
}
