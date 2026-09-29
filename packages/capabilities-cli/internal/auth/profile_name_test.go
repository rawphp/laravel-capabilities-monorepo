package auth

import (
	"errors"
	"strings"
	"testing"
)

// Profile names map 1:1 to directories. A name that would need rewriting to
// be filesystem-safe is rejected, so two distinct names can never share
// (read, overwrite, or delete) one token file.

func TestInvalidProfileCannotOverwriteCollidingProfileToken(t *testing.T) {
	st := tempStore(t)
	if err := st.SetToken("prod_eu", "token-a"); err != nil {
		t.Fatal(err)
	}
	if err := st.SetToken("prod.eu", "token-b"); !errors.Is(err, ErrInvalidProfile) {
		t.Fatalf("want ErrInvalidProfile, got %v", err)
	}
	got, err := st.GetToken("prod_eu")
	if err != nil || got != "token-a" {
		t.Fatalf("prod_eu token clobbered: %q %v", got, err)
	}
}

func TestInvalidProfileCannotReadCollidingProfileToken(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("prod_eu", "token-a")
	for _, name := range []string{"prod.eu", "prod/eu", "prod eu", "../prod_eu"} {
		tok, err := st.GetToken(name)
		if !errors.Is(err, ErrInvalidProfile) || tok != "" {
			t.Fatalf("%q: want ErrInvalidProfile and no token, got %q %v", name, tok, err)
		}
		if st.HasToken(name) {
			t.Fatalf("%q reported as logged in", name)
		}
		if st.Status(name).LoggedIn {
			t.Fatalf("%q status reported logged in", name)
		}
	}
}

func TestInvalidProfileCannotDeleteCollidingProfileToken(t *testing.T) {
	st := tempStore(t)
	_ = st.SetToken("prod_eu", "token-a")
	if err := st.DeleteToken("prod.eu"); !errors.Is(err, ErrInvalidProfile) {
		t.Fatalf("want ErrInvalidProfile, got %v", err)
	}
	if !st.HasToken("prod_eu") {
		t.Fatal("prod_eu token deleted via colliding name")
	}
}

func TestInvalidProfileCannotTouchCollidingBaseURL(t *testing.T) {
	st := tempStore(t)
	_ = st.SetBaseURL("prod_eu", "https://eu.example")
	if err := st.SetBaseURL("prod.eu", "https://evil.example"); !errors.Is(err, ErrInvalidProfile) {
		t.Fatalf("want ErrInvalidProfile, got %v", err)
	}
	if _, err := st.GetBaseURL("prod.eu"); !errors.Is(err, ErrInvalidProfile) {
		t.Fatalf("want ErrInvalidProfile, got %v", err)
	}
	if u, _ := st.GetBaseURL("prod_eu"); u != "https://eu.example" {
		t.Fatalf("prod_eu base URL clobbered: %q", u)
	}
}

func TestInvalidProfileLoginWritesNothing(t *testing.T) {
	st := tempStore(t)
	if _, err := LoginWithToken(st, "prod.eu", "https://x", "tok"); !errors.Is(err, ErrInvalidProfile) {
		t.Fatalf("want ErrInvalidProfile, got %v", err)
	}
	if got := st.ListProfiles(); len(got) != 0 {
		t.Fatalf("invalid login created profiles: %+v", got)
	}
}

func TestInvalidProfileErrorSuggestsSafeName(t *testing.T) {
	st := tempStore(t)
	_, err := st.GetToken("prod.eu")
	if err == nil || !strings.Contains(err.Error(), `"prod.eu"`) || !strings.Contains(err.Error(), `"prod_eu"`) {
		t.Fatalf("error should name the input and a safe alternative: %v", err)
	}
}

func TestInvalidProfileHasNoPaths(t *testing.T) {
	st := tempStore(t)
	if p := st.SchemaCacheDir("prod.eu"); p != "" {
		t.Fatalf("schema cache dir for invalid profile: %q", p)
	}
	if p := st.LastRunPath("prod.eu"); p != "" {
		t.Fatalf("last run path for invalid profile: %q", p)
	}
}

func TestProfileNameNormalization(t *testing.T) {
	cases := map[string]string{
		"":          "default",
		"   ":       "default",
		" prod ":    "prod",
		"Prod-EU_2": "Prod-EU_2",
	}
	for in, want := range cases {
		got, err := profileName(in)
		if err != nil || got != want {
			t.Fatalf("profileName(%q) = %q, %v; want %q", in, got, err, want)
		}
	}
}
