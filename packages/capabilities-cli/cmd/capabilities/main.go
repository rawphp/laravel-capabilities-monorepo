// Package main is the product CLI entrypoint for rawphp/capabilities-cli.
// Binary name: capabilities
// See docs/spec.md — D-016 (Go primary language), D-009 (HTTP client only).
package main

import (
	"os"
)

func main() {
	code := Execute(Env{Args: os.Args[1:]})
	os.Exit(code)
}
