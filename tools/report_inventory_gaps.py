#!/usr/bin/env python3
"""
Report remaining inventory gaps using matrix-aware suite matching.

Reuses matching logic from tools/sync_requirements_inventory.py (static Pest
titles, dynamic foreach/it($title) matrices, Go Test* names, optional pest
--list-tests execute-scan). Does not write inventory — read-only report.

Usage:
  python3 tools/report_inventory_gaps.py
  python3 tools/report_inventory_gaps.py --root /path/to/monorepo
  python3 tools/report_inventory_gaps.py --no-pest-list
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path
from typing import Any

# Import matching helpers from the sync tool (same directory).
_TOOLS_DIR = Path(__file__).resolve().parent
if str(_TOOLS_DIR) not in sys.path:
    sys.path.insert(0, str(_TOOLS_DIR))

from sync_requirements_inventory import (  # noqa: E402
    CHECKBOX_RE,
    ROOT,
    collect_implemented_titles,
    label_is_implemented,
)

PACKAGE_HEADING_RE = re.compile(
    r"^##\s+.+\(`(packages/[^`]+)`\)\s*$"
)
# Fallback: ## Core / ## Messaging / ## CLI without package path
PACKAGE_ALIAS_RE = re.compile(
    r"^##\s+(Core|Messaging|CLI)\b", re.I
)
FILE_HEADING_RE = re.compile(r"^###\s+`([^`]+)`")

PACKAGE_ALIASES = {
    "core": "packages/laravel-capabilities",
    "messaging": "packages/laravel-capabilities-messaging",
    "cli": "packages/capabilities-cli",
}


def parse_inventory_cases(inventory: str) -> list[dict[str, str]]:
    """
    Parse checkbox cases with current package + file section context.

    Returns list of {label, package, file, tag}.
    """
    cases: list[dict[str, str]] = []
    package = "unknown"
    file_rel = ""

    for line in inventory.splitlines():
        pm = PACKAGE_HEADING_RE.match(line)
        if pm:
            package = pm.group(1)
            file_rel = ""
            continue
        am = PACKAGE_ALIAS_RE.match(line)
        if am:
            package = PACKAGE_ALIASES.get(am.group(1).lower(), am.group(1))
            file_rel = ""
            continue
        fm = FILE_HEADING_RE.match(line)
        if fm:
            file_rel = fm.group(1)
            continue
        cm = CHECKBOX_RE.match(line)
        if not cm:
            continue
        label = cm.group(3)
        tag = ""
        tm = re.search(r"\[([^\]]+)\]\s*$", label)
        if tm:
            tag = tm.group(1)
        cases.append(
            {
                "label": label,
                "package": package,
                "file": file_rel,
                "tag": tag,
            }
        )
    return cases


def report_gaps(
    root: Path, *, use_pest_list: bool = True
) -> dict[str, Any]:
    """
    Compare inventory labels to suite via matrix-aware matching.

    Returns:
      total, matched, unmatched, by_package, gaps (list of unmatched case dicts)
    """
    inv_path = root / "docs/requirements-inventory.md"
    if not inv_path.is_file():
        raise FileNotFoundError(f"Inventory not found: {inv_path}")

    inventory = inv_path.read_text(encoding="utf-8")
    cases = parse_inventory_cases(inventory)
    implemented = collect_implemented_titles(root, use_pest_list=use_pest_list)

    gaps: list[dict[str, str]] = []
    matched = 0
    by_package: dict[str, dict[str, int]] = {}

    for case in cases:
        pkg = case["package"]
        if pkg not in by_package:
            by_package[pkg] = {"total": 0, "matched": 0, "unmatched": 0}
        by_package[pkg]["total"] += 1

        if label_is_implemented(case["label"], implemented):
            matched += 1
            by_package[pkg]["matched"] += 1
        else:
            by_package[pkg]["unmatched"] += 1
            gaps.append(dict(case))

    total = len(cases)
    return {
        "total": total,
        "matched": matched,
        "unmatched": total - matched,
        "by_package": by_package,
        "gaps": gaps,
        "inventory": str(inv_path),
        "suite_titles": len(implemented),
    }


def format_report(result: dict[str, Any], *, show_gaps: bool = True) -> str:
    """Human-readable multi-line report for stdout."""
    lines: list[str] = []
    lines.append("Inventory gap report (matrix-aware matching)")
    lines.append("=" * 48)
    lines.append(f"Inventory cases: {result['total']}")
    lines.append(f"Matched:         {result['matched']}")
    lines.append(f"Unmatched:       {result['unmatched']}")
    lines.append(f"Suite titles:    {result.get('suite_titles', 0)}")
    lines.append("")
    lines.append("By package:")
    for pkg in sorted(result["by_package"].keys()):
        stats = result["by_package"][pkg]
        lines.append(
            f"  {pkg}: total={stats['total']} "
            f"matched={stats['matched']} unmatched={stats['unmatched']}"
        )
    lines.append("")

    if show_gaps and result["gaps"]:
        lines.append(f"Remaining gaps ({result['unmatched']}):")
        # Group by package then file
        grouped: dict[str, dict[str, list[dict[str, str]]]] = {}
        for g in result["gaps"]:
            grouped.setdefault(g["package"], {}).setdefault(g["file"] or "(no file)", []).append(
                g
            )
        for pkg in sorted(grouped.keys()):
            lines.append(f"  [{pkg}]")
            for file_rel in sorted(grouped[pkg].keys()):
                lines.append(f"    {file_rel}")
                for g in grouped[pkg][file_rel]:
                    lines.append(f"      - {g['label']}")
        lines.append("")
    elif show_gaps:
        lines.append("Remaining gaps: none")
        lines.append("")

    lines.append(f"Inventory: {result.get('inventory', '')}")
    return "\n".join(lines) + "\n"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description=(
            "Report remaining requirements-inventory gaps after matrix-aware "
            "matching against Pest/Go suites (read-only)."
        )
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
    parser.add_argument(
        "--summary-only",
        action="store_true",
        help="Print totals and by-package only (omit individual gap labels).",
    )
    args = parser.parse_args(argv)
    root = args.root.resolve()
    result = report_gaps(root, use_pest_list=not args.no_pest_list)
    sys.stdout.write(
        format_report(result, show_gaps=not args.summary_only)
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
