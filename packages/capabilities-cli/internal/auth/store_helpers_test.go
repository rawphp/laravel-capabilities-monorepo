package auth

import "testing"

func tempStore(t *testing.T) *Store {
	t.Helper()
	return NewStore(t.TempDir())
}
