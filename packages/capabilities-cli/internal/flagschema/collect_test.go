package flagschema

import (
	"errors"
	"reflect"
	"testing"
)

func TestCollectFlags(t *testing.T) {
	flags, rest, err := CollectFlags([]string{"--customer-id=42", "--currency", "USD", "--active", "extra"})
	if err != nil {
		t.Fatal(err)
	}
	// bare --active consumes next non-flag as value (shell style); use --active at EOL for boolean true
	want := map[string]string{"customer-id": "42", "currency": "USD", "active": "extra"}
	if !reflect.DeepEqual(flags, want) {
		t.Fatalf("flags=%v want %v", flags, want)
	}
	if len(rest) != 0 {
		t.Fatalf("rest=%v", rest)
	}

	flags, rest, err = CollectFlags([]string{"--active"})
	if err != nil {
		t.Fatal(err)
	}
	if flags["active"] != "" || len(rest) != 0 {
		t.Fatalf("bare bool: flags=%v rest=%v", flags, rest)
	}
}

func TestCollectFlags_positionalsAndDoubleDashGoToRest(t *testing.T) {
	flags, rest, err := CollectFlags([]string{"stray", "--note=hi", "--", "--not-a-flag", "x"})
	if err != nil {
		t.Fatal(err)
	}
	if !reflect.DeepEqual(flags, map[string]string{"note": "hi"}) {
		t.Fatalf("flags=%v", flags)
	}
	if !reflect.DeepEqual(rest, []string{"stray", "--not-a-flag", "x"}) {
		t.Fatalf("rest=%v", rest)
	}
}

func TestCollectFlags_emptyFlagNameIsRejected(t *testing.T) {
	if _, _, err := CollectFlags([]string{"--=5"}); !errors.Is(err, ErrUnknownFlag) {
		t.Fatalf("want ErrUnknownFlag, got %v", err)
	}
}
