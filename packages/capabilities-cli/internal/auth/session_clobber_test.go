package auth

import (
	"context"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestLoginDeviceFailureDoesNotClobberExistingBaseURL(t *testing.T) {
	st := tempStore(t)
	if err := st.SetBaseURL("default", "https://good.example"); err != nil {
		t.Fatal(err)
	}
	if err := st.SetToken("default", "good-tok"); err != nil {
		t.Fatal(err)
	}
	c := api.NewClient("http://127.0.0.1:1", "")
	_, err := LoginDeviceCode(context.Background(), st, c, "default", "https://evil.example")
	if err == nil {
		t.Fatal("expected network failure")
	}
	base, err := st.GetBaseURL("default")
	if err != nil {
		t.Fatal(err)
	}
	if base != "https://good.example" {
		t.Fatalf("base clobbered to %q", base)
	}
	tok, err := st.GetToken("default")
	if err != nil || tok != "good-tok" {
		t.Fatalf("token changed: %v %q", err, tok)
	}
}

func TestLoginDeviceFailureDoesNotWriteBaseURLOnFreshProfile(t *testing.T) {
	st := tempStore(t)
	c := api.NewClient("http://127.0.0.1:1", "")
	_, err := LoginDeviceCode(context.Background(), st, c, "default", "https://nowhere.example")
	if err == nil {
		t.Fatal("expected failure")
	}
	if _, err := st.GetBaseURL("default"); err == nil {
		t.Fatal("base URL should not be written on failed login")
	}
}
