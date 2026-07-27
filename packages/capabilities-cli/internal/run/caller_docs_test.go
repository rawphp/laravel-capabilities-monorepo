package run

import (
	"strings"
	"testing"
)

func TestDocssayserverderivescaller(t *testing.T) {
	if !strings.Contains(DocsPrinciples, "Server derives caller") {
		t.Fatal(DocsPrinciples)
	}
}
func TestDocssaynotartisan(t *testing.T) {
	if !strings.Contains(DocsPrinciples, "not Artisan") {
		t.Fatal()
	}
}
func TestDocssaysamehttpapi(t *testing.T) {
	if !strings.Contains(DocsPrinciples, "HTTP") {
		t.Fatal()
	}
}
func TestDocssaylocalvalidatethenserver(t *testing.T) {
	if !strings.Contains(DocsPrinciples, "server always re-validates") {
		t.Fatal()
	}
}
func TestDocssayidempotencyalwayssent(t *testing.T) {
	if !strings.Contains(DocsPrinciples, "Idempotency-Key is always sent") {
		t.Fatal()
	}
}
func TestDocssayexitcodesstable(t *testing.T) {
	if !strings.Contains(DocsExitCodes, "stable") {
		t.Fatal()
	}
}
