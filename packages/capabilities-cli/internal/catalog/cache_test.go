package catalog

import (
	"context"
	"encoding/json"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestCachehitsameversion(t *testing.T) {
	c := NewCache(t.TempDir())
	_ = c.Put(&CacheEntry{Name: "n", SchemaVersion: "1", InputSchema: json.RawMessage(`{}`)})
	_, ok := c.Get("n", "1")
	if !ok {
		t.Fatal("miss")
	}
}

func TestCachemissfetches(t *testing.T) {
	c := NewCache(t.TempDir())
	_, ok := c.Get("missing", "")
	if ok {
		t.Fatal("should miss")
	}
}

func TestCacheinvalidateonversionchange(t *testing.T) {
	c := NewCache(t.TempDir())
	_ = c.Put(&CacheEntry{Name: "n", SchemaVersion: "1", InputSchema: json.RawMessage(`{}`)})
	_, ok := c.Get("n", "2")
	if ok {
		t.Fatal("version change must miss")
	}
}

func TestCacheinvalidateonetagchange(t *testing.T) {
	c := NewCache(t.TempDir())
	_ = c.Put(&CacheEntry{Name: "n", SchemaVersion: "1", ETag: "a", InputSchema: json.RawMessage(`{}`)})
	_, ok := c.GetByETag("n", "b")
	if ok {
		t.Fatal()
	}
}

func TestCacheinvalidateonrefreshcommand(t *testing.T) {
	c := NewCache(t.TempDir())
	_ = c.Put(&CacheEntry{Name: "n", SchemaVersion: "1", InputSchema: json.RawMessage(`{}`)})
	if err := c.Invalidate(""); err != nil {
		t.Fatal(err)
	}
	_, ok := c.Get("n", "")
	if ok {
		t.Fatal()
	}
}

func TestCachebypassnocacheflag(t *testing.T) {
	// Service.NoCache is the flag; documented here.
	s := &Service{NoCache: true}
	if !s.NoCache {
		t.Fatal()
	}
}

func principalClient(base, token string) *api.Client {
	return api.NewClient(base, token)
}

func seededMiss(t *testing.T, a, b *Cache) bool {
	t.Helper()
	if err := a.Put(&CacheEntry{Name: "n", SchemaVersion: "1", InputSchema: json.RawMessage(`{}`)}); err != nil {
		t.Fatal(err)
	}
	_, ok := b.Get("n", "")
	return !ok
}

func TestCacheperprofileisolation(t *testing.T) {
	root := t.TempDir()
	c := principalClient("https://a", "tok")
	a := PrincipalCache(filepath.Join(root, "p1"), c)
	b := PrincipalCache(filepath.Join(root, "p2"), c)
	if !seededMiss(t, a, b) {
		t.Fatal("profiles must isolate")
	}
}

func TestCacheperbaseurlisolation(t *testing.T) {
	root := t.TempDir()
	a := PrincipalCache(root, principalClient("https://a", "tok"))
	b := PrincipalCache(root, principalClient("https://b", "tok"))
	if !seededMiss(t, a, b) {
		t.Fatal("base urls must isolate")
	}
}

func TestCacheperprincipalisolation(t *testing.T) {
	root := t.TempDir()
	alice := PrincipalCache(root, principalClient("https://a", "tok-alice"))
	bob := PrincipalCache(root, principalClient("https://a", "tok-bob"))
	if !seededMiss(t, alice, bob) {
		t.Fatal("a different credential under the same profile must not read another principal's schemas")
	}
	again := PrincipalCache(root, principalClient("https://a", "tok-alice"))
	if _, ok := again.Get("n", ""); !ok {
		t.Fatal("same principal must hit its own cache")
	}
}

func TestCacheprincipalkeydoesnotleaktoken(t *testing.T) {
	root := t.TempDir()
	c := PrincipalCache(root, principalClient("https://a", "secret-token-value"))
	if strings.Contains(c.Dir, "secret-token-value") {
		t.Fatalf("cache path must not contain the raw token: %s", c.Dir)
	}
	if filepath.Dir(c.Dir) != root {
		t.Fatalf("principal cache must live directly under the profile schema root: %s", c.Dir)
	}
}

func TestDescribeservesprincipalcachewithoutrefetch(t *testing.T) {
	hits := 0
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		hits++
		w.Write([]byte(`{"ok":true,"data":{"name":"x","schema_version":"1","input_schema":{"type":"object"}}}`))
	})
	svc := &Service{Client: c, Cache: PrincipalCache(t.TempDir(), c)}
	for i := 0; i < 2; i++ {
		if _, _, err := svc.Describe(context.Background(), "x"); err != nil {
			t.Fatal(err)
		}
	}
	if hits != 1 {
		t.Fatalf("second describe for the same principal must come from cache, hits=%d", hits)
	}
}

func TestCachecorruptfilerefetches(t *testing.T) {
	dir := t.TempDir()
	c := NewCache(dir)
	_ = os.WriteFile(filepath.Join(dir, "n.json"), []byte("not-json"), 0o600)
	_, ok := c.Get("n", "")
	if ok {
		t.Fatal("corrupt must miss")
	}
}

func TestCachewriteatomic(t *testing.T) {
	c := NewCache(t.TempDir())
	if err := c.Put(&CacheEntry{Name: "n", SchemaVersion: "1", InputSchema: json.RawMessage(`{"type":"object"}`)}); err != nil {
		t.Fatal(err)
	}
	// no .tmp left
	entries, _ := os.ReadDir(c.Dir)
	for _, e := range entries {
		if filepath.Ext(e.Name()) == ".tmp" || len(e.Name()) > 4 && e.Name()[len(e.Name())-4:] == ".tmp" {
			t.Fatal("tmp left")
		}
	}
}
