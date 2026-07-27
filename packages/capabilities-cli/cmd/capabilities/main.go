// Package main is the product CLI entrypoint for rawphp/capabilities-cli.
// Binary name: capabilities
// See docs/spec.md — D-016 (Go primary language), D-009 (HTTP client only).
package main

import (
	"fmt"
	"os"
)

func main() {
	// Scaffold only — implementation lands in v0.2 roadmap.
	fmt.Fprintln(os.Stderr, "capabilities: not implemented yet (scaffold)")
	os.Exit(1)
}
