#!/usr/bin/env python3
"""
Sync docs/requirements-inventory.md checkbox status to suite reality.

Marks inventory cases done (- [x]) when a matching live test exists:
  - Pest it("…") / it('…') titles (static)
  - Dynamic matrix titles: foreach + it("…{$var}…") / $title = "…" + it($title)
  - Static match-arm titles used with it($title)
  - Go func Test* names (inventory lines may append [REQ] tags)

Optionally merges titles from `pest --list-tests` (execute-scan) when Pest is
available under packages/*/vendor or monorepo vendor — this captures matrix
cases that use complex title construction.

Header is rewritten to report implemented vs remaining counts (not a permanent
"Total TODO cases" line). Re-running is idempotent.

Usage:
  python3 tools/sync_requirements_inventory.py
  python3 tools/sync_requirements_inventory.py --root /path/to/monorepo
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from itertools import product
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

CHECKBOX_RE = re.compile(r"^(\s*)- \[([ xX])\] (.+)$")
GO_LABEL_RE = re.compile(r"^(Test\w+)(?:\s+\[.+\])?$")
IT_DOUBLE_RE = re.compile(r'\bit\(\s*"((?:\\.|[^"\\])*)"\s*,')
IT_SINGLE_RE = re.compile(r"\bit\(\s*'((?:\\.|[^'\\])*)'\s*,")
IT_VAR_RE = re.compile(r"\bit\(\s*\$title\s*,")
TITLE_ASSIGN_RE = re.compile(r'\$title\s*=\s*"((?:\\.|[^"\\])*)"\s*;')
MATCH_ARM_RE = re.compile(
    r"""(?:'[^']*'|"[^"]*")\s*=>\s*"((?:\\.|[^"\\])*)"\s*,?"""
)
FOREACH_RE = re.compile(r"foreach\s*\((.+?)\s+as\s+(?:\$\w+\s*=>\s*)?\$(\w+)\s*\)\s*\{")
ARRAY_ASSIGN_RE = re.compile(r"\$(\w+)\s*=\s*(\[[\s\S]*?\]);")
CONST_ASSIGN_RE = re.compile(r"\$(\w+)\s*=\s*(\w+::\w+)\s*;")
GO_FUNC_RE = re.compile(r"^func (Test\w+)\b", re.M)
PHP_CONST_RE = re.compile(
    r"(?:public\s+)?const\s+([A-Z_][A-Z0-9_]*)\s*=\s*\[(.*?)\];", re.S
)
CLASS_RE = re.compile(r"class\s+(\w+)")
PEST_METHOD_RE = re.compile(r"(__pest_evaluable_\S+)")


def pest_evaluable(description: str) -> str:
    """Mirror Pest\\Support\\Str::evaluable (same as generate_requirement_stubs)."""
    code = description.replace("_", "__")
    code = "__pest_evaluable_" + code.replace(" ", "_")
    return re.sub(r"[^a-zA-Z0-9_\x80-\xff]", "_", code)


def _unescape_double(s: str) -> str:
    return s.replace("\\$", "$").replace('\\"', '"').replace("\\\\", "\\")


def _unescape_single(s: str) -> str:
    return s.replace("\\'", "'").replace("\\\\", "\\")


def _parse_string_array_body(body: str) -> list[str]:
    return re.findall(r"""['"]([^'"]+)['"]""", body)


def _scan_php_consts(root: Path) -> dict[str, list[str]]:
    consts: dict[str, list[str]] = {}
    for base in (
        root / "packages/laravel-capabilities",
        root / "packages/laravel-capabilities-messaging",
    ):
        if not base.exists():
            continue
        for path in base.rglob("*.php"):
            try:
                text = path.read_text(encoding="utf-8", errors="ignore")
            except OSError:
                continue
            cm = CLASS_RE.search(text)
            class_name = cm.group(1) if cm else None
            for m in PHP_CONST_RE.finditer(text):
                name, body = m.group(1), m.group(2)
                vals = _parse_string_array_body(body)
                if not vals:
                    continue
                consts[name] = vals
                if class_name:
                    consts[f"{class_name}::{name}"] = vals
    return consts


def _resolve_iterable(
    expr: str, local_vars: dict[str, list[str]], consts: dict[str, list[str]]
) -> list[str] | None:
    expr = expr.strip()
    if expr.startswith("["):
        vals = _parse_string_array_body(expr)
        return vals or None
    if expr.startswith("$"):
        name = expr[1:].split(" ", 1)[0]
        if name in local_vars:
            return local_vars[name]
        return None
    m = re.match(r"(\w+)::(\w+)$", expr)
    if m:
        key = f"{m.group(1)}::{m.group(2)}"
        if key in consts:
            return consts[key]
        if m.group(2) in consts:
            return consts[m.group(2)]
    return None


def _extract_local_arrays(
    text: str, consts: dict[str, list[str]]
) -> dict[str, list[str]]:
    local: dict[str, list[str]] = {}
    for m in ARRAY_ASSIGN_RE.finditer(text):
        vals = _parse_string_array_body(m.group(2))
        if vals:
            local[m.group(1)] = vals
    for m in CONST_ASSIGN_RE.finditer(text):
        vals = _resolve_iterable(m.group(2), {}, consts)
        if vals:
            local[m.group(1)] = vals
    # Associative arrays used as foreach ($map as $k => $v): keys are labels
    for m in re.finditer(r"\$(\w+)\s*=\s*\[(.*?)\];", text, re.S):
        name, body = m.group(1), m.group(2)
        # keys in 'k' => ...
        keys = re.findall(r"""^\s*['"]([^'"]+)['"]\s*=>""", body, re.M)
        if keys and name not in local:
            local[name] = keys
        # also value-side strings when used as foreach ($map as $label => $key)
        # store under name__keys already; name__values for values
        vals = re.findall(r"""=>\s*['"]([^'"]+)['"]""", body)
        if keys:
            local[name] = keys
        elif vals:
            local[name] = vals
    return local


def _interpolate(template: str, env: dict[str, str]) -> str | None:
    out = template
    for k, v in env.items():
        out = out.replace("{$" + k + "}", v)
    # longest keys first to avoid partial $caller vs $c
    for k in sorted(env.keys(), key=len, reverse=True):
        out = re.sub(rf"\${k}(?![A-Za-z0-9_])", env[k], out)
    if re.search(r"\{\$|\$\w+", out):
        return None
    return out


def _matching_brace_block(text: str, open_idx: int) -> str:
    """Return text inside braces starting at open_idx which points at '{'."""
    depth = 0
    i = open_idx
    while i < len(text):
        ch = text[i]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return text[open_idx + 1 : i]
        i += 1
    return text[open_idx + 1 :]


def _collect_foreach_chain(
    text: str, consts: dict[str, list[str]], local: dict[str, list[str]]
) -> list[tuple[list[str], list[str], str]]:
    """
    Return list of (var_names, value_lists_flattened_as_product_ready, block_text)
    for each outermost foreach that may nest more foreachs.
    """
    results: list[tuple[list[str], list[list[str]], str]] = []
    for m in FOREACH_RE.finditer(text):
        # Only treat as outer if not nested deeper handling — we expand all
        open_idx = m.end() - 1
        if text[open_idx] != "{":
            continue
        block = _matching_brace_block(text, open_idx)
        outer_vals = _resolve_iterable(m.group(1), local, consts)
        if not outer_vals:
            continue
        var_names = [m.group(2)]
        var_lists: list[list[str]] = [outer_vals]

        # Nested foreach inside block (any depth, sequential product)
        nested = list(FOREACH_RE.finditer(block))
        for nm in nested:
            nvals = _resolve_iterable(nm.group(1), local, consts)
            if not nvals:
                continue
            var_names.append(nm.group(2))
            var_lists.append(nvals)

        results.append((var_names, var_lists, block))
    return results


def extract_pest_titles(
    text: str, consts: dict[str, list[str]] | None = None
) -> set[str]:
    """Extract Pest it() titles: static quotes, match arms, and expanded matrices."""
    consts = consts or {}
    titles: set[str] = set()

    for m in IT_DOUBLE_RE.finditer(text):
        t = _unescape_double(m.group(1))
        if "$" not in t and not re.search(r"\{[^{$]*\}", t):
            # Allow literal path placeholders like {name} without $
            if "{$" not in t and not re.search(r"\$\w+", t):
                titles.add(t)
        elif "$" not in t:
            titles.add(t)

    for m in IT_SINGLE_RE.finditer(text):
        titles.add(_unescape_single(m.group(1)))

    # match arms that look like inventory labels
    for m in MATCH_ARM_RE.finditer(text):
        t = _unescape_double(m.group(1))
        if "$" not in t and re.match(r"(happy|fail|edge):", t):
            titles.add(t)

    local = _extract_local_arrays(text, consts)

    for var_names, var_lists, block in _collect_foreach_chain(text, consts, local):
        templates: list[str] = []
        for m in IT_DOUBLE_RE.finditer(block):
            templates.append(_unescape_double(m.group(1)))
        for m in TITLE_ASSIGN_RE.finditer(block):
            templates.append(_unescape_double(m.group(1)))
        # Nested match arms with interpolation
        for m in MATCH_ARM_RE.finditer(block):
            t = _unescape_double(m.group(1))
            if "$" in t:
                templates.append(t)
            elif re.match(r"(happy|fail|edge):", t):
                titles.add(t)

        if not templates:
            continue

        # If any nested foreach failed to resolve, var_lists may be incomplete;
        # product still expands what we have.
        try:
            combos = list(product(*var_lists)) if var_lists else [()]
        except Exception:
            continue

        for combo in combos:
            env = dict(zip(var_names, combo))
            for tmpl in templates:
                if "$" in tmpl or "{$" in tmpl:
                    expanded = _interpolate(tmpl, env)
                    if expanded:
                        titles.add(expanded)
                else:
                    titles.add(tmpl)

    return titles


def extract_go_test_names(text: str) -> set[str]:
    return set(GO_FUNC_RE.findall(text))


def _pest_binary(package_dir: Path, root: Path) -> Path | None:
    candidates = [
        package_dir / "vendor/bin/pest",
        root / "vendor/bin/pest",
        package_dir / "../../vendor/bin/pest",
    ]
    for c in candidates:
        try:
            if c.resolve().is_file():
                return c.resolve()
        except OSError:
            continue
    return None


def _list_pest_evaluable_methods(package_dir: Path, root: Path) -> set[str]:
    """Execute-scan: pest --list-tests → set of __pest_evaluable_* method names."""
    pest = _pest_binary(package_dir, root)
    if pest is None:
        return set()
    try:
        proc = subprocess.run(
            [str(pest), "--list-tests"],
            cwd=str(package_dir),
            capture_output=True,
            text=True,
            timeout=300,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return set()
    methods: set[str] = set()
    for line in (proc.stdout or "").splitlines() + (proc.stderr or "").splitlines():
        m = PEST_METHOD_RE.search(line)
        if not m:
            continue
        name = m.group(1).rstrip(".").rstrip()
        methods.add(name)
        methods.add(name.rstrip("_"))
        if not name.endswith("_"):
            methods.add(name + "_")
    return methods


def collect_implemented_titles(
    root: Path, *, use_pest_list: bool = True
) -> set[str]:
    """Collect implemented Pest titles + Go Test* names from the monorepo tree."""
    titles: set[str] = set()
    consts = _scan_php_consts(root)

    php_roots = [
        root / "packages/laravel-capabilities/tests/Unit",
        root / "packages/laravel-capabilities-messaging/tests/Unit",
    ]
    for php_root in php_roots:
        if not php_root.exists():
            continue
        for path in php_root.rglob("*Test.php"):
            try:
                text = path.read_text(encoding="utf-8", errors="ignore")
            except OSError:
                continue
            titles |= extract_pest_titles(text, consts)

    cli_root = root / "packages/capabilities-cli"
    if cli_root.exists():
        for path in cli_root.rglob("*_test.go"):
            try:
                text = path.read_text(encoding="utf-8", errors="ignore")
            except OSError:
                continue
            titles |= extract_go_test_names(text)

    if use_pest_list:
        pest_methods: set[str] = set()
        for pkg in (
            root / "packages/laravel-capabilities",
            root / "packages/laravel-capabilities-messaging",
        ):
            if pkg.is_dir():
                pest_methods |= _list_pest_evaluable_methods(pkg, root)
        # Store special marker set for label matching later via side channel
        # by embedding evaluable forms of already-known titles and retaining methods.
        if pest_methods:
            # Attach as attribute for is_implemented; also add reverse via known titles
            collect_implemented_titles._pest_methods = pest_methods  # type: ignore[attr-defined]
        else:
            collect_implemented_titles._pest_methods = set()  # type: ignore[attr-defined]
    else:
        collect_implemented_titles._pest_methods = set()  # type: ignore[attr-defined]

    return titles


def _pest_methods_cached() -> set[str]:
    return getattr(collect_implemented_titles, "_pest_methods", set())


def label_is_implemented(label: str, implemented: set[str]) -> bool:
    """True when inventory label matches a live Pest title or Go Test* name."""
    gm = GO_LABEL_RE.match(label)
    if gm:
        return gm.group(1) in implemented

    if label in implemented:
        return True

    # Pest execute-scan: method name for "it " + label
    methods = _pest_methods_cached()
    if methods:
        meth = pest_evaluable("it " + label)
        if meth in methods or meth.rstrip("_") in methods or (meth + "_") in methods:
            return True

    return False


def _format_header_block(
    total: int, implemented: int, remaining: int, kind_counts: dict[str, int]
) -> list[str]:
    return [
        f"**Cases: {total} total — {implemented} implemented, {remaining} remaining**",
        "",
        f"- happy: {kind_counts.get('happy', 0)}",
        f"- fail: {kind_counts.get('fail', 0)}",
        f"- edge: {kind_counts.get('edge', 0)}",
        f"- go: {kind_counts.get('go', 0)}",
        "",
    ]


def _classify_label(label: str) -> str:
    if GO_LABEL_RE.match(label):
        return "go"
    if label.startswith("fail:"):
        return "fail"
    if label.startswith("edge:"):
        return "edge"
    if label.startswith("happy:"):
        return "happy"
    return "happy"


def sync_inventory_content(inventory: str, implemented: set[str]) -> str:
    """
    Rewrite checkbox lines and header from suite `implemented` titles.

    `implemented` should contain Pest it() titles and bare Go Test* names.
    Pest execute-scan methods (if any) are read from collect_implemented_titles
    cache via label_is_implemented.
    """
    lines = inventory.splitlines()
    out: list[str] = []
    kind_counts: dict[str, int] = {"happy": 0, "fail": 0, "edge": 0, "go": 0}
    case_lines: list[tuple[str, str, str, bool]] = []  # indent, mark, label, done

    # First pass: decide checkbox states
    for line in lines:
        m = CHECKBOX_RE.match(line)
        if not m:
            continue
        indent, _mark, label = m.group(1), m.group(2), m.group(3)
        done = label_is_implemented(label, implemented)
        kind_counts[_classify_label(label)] = (
            kind_counts.get(_classify_label(label), 0) + 1
        )
        case_lines.append((indent, label, "x" if done else " ", done))

    total = len(case_lines)
    implemented_n = sum(1 for *_, done in case_lines if done)
    remaining = total - implemented_n

    # Second pass: rewrite document
    i = 0
    header_replaced = False
    while i < len(lines):
        line = lines[i]
        # Drop old "Total TODO cases" / "Cases: N total" header + kind bullets
        if re.match(r"^\*\*Total TODO cases:\*\*", line) or re.match(
            r"^\*\*Cases:\s*\d+\s*total", line
        ):
            if not header_replaced:
                out.extend(
                    _format_header_block(total, implemented_n, remaining, kind_counts)
                )
                header_replaced = True
            i += 1
            # Consume trailing blank lines and kind-count bullets from the old block.
            while i < len(lines):
                nxt = lines[i]
                if nxt.strip() == "":
                    i += 1
                    continue
                if re.match(r"^- (happy|fail|edge|go):", nxt):
                    i += 1
                    continue
                break
            # Preserve a single blank line before the next section when needed.
            if i < len(lines) and lines[i].startswith("##") and (
                not out or out[-1].strip() != ""
            ):
                out.append("")
            continue
        # Orphan kind-count bullets left from a prior partial rewrite
        if re.match(r"^- (happy|fail|edge|go):\s*\d+\s*$", line) and header_replaced:
            i += 1
            continue

        m = CHECKBOX_RE.match(line)
        if m:
            indent, label = m.group(1), m.group(3)
            mark = "x" if label_is_implemented(label, implemented) else " "
            out.append(f"{indent}- [{mark}] {label}")
            i += 1
            continue

        out.append(line)
        i += 1

    if not header_replaced:
        # Insert after first paragraph block if no header found
        insert_at = 0
        for idx, line in enumerate(out):
            if line.startswith("# "):
                insert_at = idx + 1
                break
        block = [""] + _format_header_block(
            total, implemented_n, remaining, kind_counts
        ) + [""]
        out = out[:insert_at] + block + out[insert_at:]

    text = "\n".join(out)
    if inventory.endswith("\n"):
        text += "\n"
    return text


def sync_inventory_file(
    root: Path, *, use_pest_list: bool = True
) -> dict[str, int | str]:
    """Sync docs/requirements-inventory.md under root; return stats."""
    inv_path = root / "docs/requirements-inventory.md"
    if not inv_path.is_file():
        raise FileNotFoundError(f"Inventory not found: {inv_path}")

    implemented = collect_implemented_titles(root, use_pest_list=use_pest_list)
    original = inv_path.read_text(encoding="utf-8")
    updated = sync_inventory_content(original, implemented)
    inv_path.write_text(updated, encoding="utf-8")

    total = 0
    implemented_n = 0
    for line in updated.splitlines():
        m = CHECKBOX_RE.match(line)
        if not m:
            continue
        total += 1
        if m.group(2).lower() == "x":
            implemented_n += 1

    return {
        "total": total,
        "implemented": implemented_n,
        "remaining": total - implemented_n,
        "suite_titles": len(implemented),
        "inventory": str(inv_path),
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Sync requirements-inventory.md checkboxes to suite reality."
    )
    parser.add_argument(
        "--root",
        type=Path,
        default=ROOT,
        help="Monorepo root (default: parent of tools/)",
    )
    parser.add_argument(
        "--no-pest-list",
        action="store_true",
        help="Skip pest --list-tests execute-scan (static extraction only).",
    )
    args = parser.parse_args(argv)
    root = args.root.resolve()
    stats = sync_inventory_file(root, use_pest_list=not args.no_pest_list)
    print(
        f"Synced inventory: {stats['implemented']} implemented, "
        f"{stats['remaining']} remaining, {stats['total']} total "
        f"(suite titles scanned: {stats['suite_titles']})"
    )
    print(f"Inventory: {stats['inventory']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
