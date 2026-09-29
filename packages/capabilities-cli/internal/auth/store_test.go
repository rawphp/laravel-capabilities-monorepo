package auth

import (
	"errors"
	"os"
	"path/filepath"
	"reflect"
	"testing"
)

func TestListProfilesReturnsSortedNonSecretStatus(t *testing.T) {
	st := tempStore(t)
	if got := st.ListProfiles(); got != nil {
		t.Fatalf("no store yet: %v", got)
	}
	_ = st.SetBaseURL("zeta", "https://z.example")
	_ = st.SetToken("zeta", "secret")
	_ = st.SetBaseURL("alpha", "https://a.example")
	// Stray files in the profiles dir are not profiles.
	_ = os.WriteFile(filepath.Join(st.Root, "profiles", "README"), []byte("x"), 0o600)

	want := []Profile{
		{Name: "alpha", BaseURL: "https://a.example"},
		{Name: "zeta", BaseURL: "https://z.example", LoggedIn: true},
	}
	if got := st.ListProfiles(); !reflect.DeepEqual(got, want) {
		t.Fatalf("got %+v want %+v", got, want)
	}
}

func TestStoreWritesFailWhenConfigRootIsNotADirectory(t *testing.T) {
	st := unwritableStore(t)
	if err := st.SetToken("default", "tok"); err == nil {
		t.Fatal("SetToken must fail")
	}
	if err := st.SetBaseURL("default", "https://x.example"); err == nil {
		t.Fatal("SetBaseURL must fail")
	}
}

func TestSetBaseURLRejectsUnsafeProfileAndBadURL(t *testing.T) {
	st := tempStore(t)
	if err := st.SetBaseURL("prod.eu", "https://x.example"); !errors.Is(err, ErrInvalidProfile) {
		t.Fatalf("want ErrInvalidProfile, got %v", err)
	}
	if err := st.SetBaseURL("default", "x.example"); !errors.Is(err, ErrInvalidBaseURL) {
		t.Fatalf("want ErrInvalidBaseURL, got %v", err)
	}
}

func TestUnreadableTokenIsNotAuthenticated(t *testing.T) {
	st := tempStore(t)
	blockTokenFile(t, st, "default")
	if _, err := st.GetToken("default"); !errors.Is(err, ErrNoToken) {
		t.Fatalf("want ErrNoToken, got %v", err)
	}
}

func TestDeleteTokenReportsRemoveFailure(t *testing.T) {
	st := tempStore(t)
	blockTokenFile(t, st, "default")
	if err := st.DeleteToken("default"); err == nil {
		t.Fatal("logout must report a token it could not remove")
	}
}

func TestCorruptProfileConfigIsAnError(t *testing.T) {
	st := tempStore(t)
	dir, _ := st.profileDir("bad")
	_ = os.MkdirAll(dir, 0o700)
	_ = os.WriteFile(filepath.Join(dir, "config.json"), []byte("{"), 0o600)
	if _, err := st.GetBaseURL("bad"); err == nil {
		t.Fatal("expected corrupt config error")
	}
	if st.Status("bad").BaseURL != "" {
		t.Fatal("status must not surface a corrupt base URL")
	}
}
