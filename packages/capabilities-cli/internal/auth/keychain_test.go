package auth

import (
	"os"
	"path/filepath"
	"testing"
)

func TestStoretokenencryptedoroskeychain(t *testing.T) {
	st := tempStore(t)
	if err := st.SetToken("default", "tok"); err != nil {
		t.Fatal(err)
	}
	// File mode 0600
	info, err := os.Stat(filepath.Join(st.profileDir("default"), "token"))
	if err != nil {
		t.Fatal(err)
	}
	if info.Mode().Perm() != 0o600 {
		t.Fatalf("perm %o", info.Mode().Perm())
	}
}

func TestReadtokenforapiclient(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("default", "abc")
	got, err := st.GetToken("default")
	if err != nil || got != "abc" {
		t.Fatal(got, err)
	}
}

func TestDeletetokenonlogout(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("default", "abc")
	_ = st.DeleteToken("default")
	if _, err := st.GetToken("default"); err != ErrNoToken {
		t.Fatal(err)
	}
}

func TestNeverechotokeninstatus(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("default", "secret")
	p := st.Status("default")
	// no token field
	if p.LoggedIn != true {
		t.Fatal(p)
	}
}

func TestNeverpasstokentoagentprompt(t *testing.T) {
	// Design invariant: Status never returns token string.
	st := tempStore(t)
	_ = st.SetToken("default", "secret")
	p := st.Status("default")
	type hasToken interface{ Token() string }
	if _, ok := any(p).(hasToken); ok {
		t.Fatal("status must not expose Token()")
	}
}

func TestMultipleprofilesisolated(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("a", "1")
	_ = st.SetToken("b", "2")
	ta, _ := st.GetToken("a")
	tb, _ := st.GetToken("b")
	if ta != "1" || tb != "2" {
		t.Fatal(ta, tb)
	}
}

func TestCorruptkeychainhandled(t *testing.T) {
	st := tempStore(t)
	dir := st.profileDir("default")
	_ = os.MkdirAll(dir, 0o700)
	// Write non-token garbage as empty after trim → ErrNoToken
	_ = os.WriteFile(filepath.Join(dir, "token"), []byte("   \n"), 0o600)
	_, err := st.GetToken("default")
	if err != ErrNoToken {
		t.Fatal(err)
	}
}

func TestMissingkeychainfallsbacksecurely(t *testing.T) {
	st := tempStore(t)
	_, err := st.GetToken("nope")
	if err != ErrNoToken {
		t.Fatal(err)
	}
}
