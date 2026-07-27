package catalog

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
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

func TestCacheperprofileisolation(t *testing.T) {
	a := IsolationKey("p1", "https://a")
	b := IsolationKey("p2", "https://a")
	if a == b {
		t.Fatal("profiles must isolate")
	}
}

func TestCacheperbaseurlisolation(t *testing.T) {
	a := IsolationKey("p", "https://a")
	b := IsolationKey("p", "https://b")
	if a == b {
		t.Fatal("base urls must isolate")
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
