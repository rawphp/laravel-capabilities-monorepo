package flagschema

import (
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
