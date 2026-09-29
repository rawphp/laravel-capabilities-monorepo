package main

import (
	"go/ast"
	"go/parser"
	"go/token"
	"regexp"
	"strconv"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/synth"
)

// Every meta-command Execute dispatches before domain synthesis must be a
// reserved domain; otherwise a capability with that cli.domain is shadowed
// silently instead of being reported as reserved_domain.
func TestExecuteMetaCommandsAreReservedDomains(t *testing.T) {
	fset := token.NewFileSet()
	file, err := parser.ParseFile(fset, "cli.go", nil, 0)
	if err != nil {
		t.Fatal(err)
	}
	tokenRE := regexp.MustCompile(`^[a-z][a-z0-9-]*$`)
	var seen int
	ast.Inspect(file, func(n ast.Node) bool {
		fn, ok := n.(*ast.FuncDecl)
		if !ok || fn.Name.Name != "Execute" {
			return true
		}
		ast.Inspect(fn.Body, func(n ast.Node) bool {
			cc, ok := n.(*ast.CaseClause)
			if !ok {
				return true
			}
			for _, e := range cc.List {
				lit, ok := e.(*ast.BasicLit)
				if !ok || lit.Kind != token.STRING {
					continue
				}
				name, _ := strconv.Unquote(lit.Value)
				if !tokenRE.MatchString(name) {
					continue // flags like --help can never be domains
				}
				seen++
				if !synth.IsReservedDomain(name) {
					t.Errorf("meta-command %q is dispatched by Execute but not reserved in synth", name)
				}
			}
			return true
		})
		return false
	})
	if seen == 0 {
		t.Fatal("found no meta-command cases in Execute")
	}
}
