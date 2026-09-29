package auth

import (
	"os"
	"path/filepath"
	"testing"
)

func tempStore(t *testing.T) *Store {
	t.Helper()
	return NewStore(t.TempDir())
}

// unwritableStore is rooted at a regular file, so every profile write fails.
func unwritableStore(t *testing.T) *Store {
	t.Helper()
	root := filepath.Join(t.TempDir(), "not-a-dir")
	if err := os.WriteFile(root, []byte("x"), 0o600); err != nil {
		t.Fatal(err)
	}
	return NewStore(root)
}

// blockTokenFile turns the profile's token path into a non-empty directory, so
// reading, writing, or removing the token fails while config writes succeed.
func blockTokenFile(t *testing.T, st *Store, profile string) {
	t.Helper()
	dir, err := st.profileDir(profile)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(dir, "token", "blocker"), 0o700); err != nil {
		t.Fatal(err)
	}
}
