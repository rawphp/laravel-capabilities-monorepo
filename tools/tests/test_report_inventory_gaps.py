"""Unit tests for tools/report_inventory_gaps.py.

Hermetic: temporary directories and fixtures only. Does not mutate monorepo inventory.
"""

from __future__ import annotations

import importlib.util
import io
import sys
import tempfile
import unittest
from contextlib import redirect_stdout
from pathlib import Path


TOOLS_DIR = Path(__file__).resolve().parents[1]
REPORT_PATH = TOOLS_DIR / "report_inventory_gaps.py"


def load_report():
    """Load report_inventory_gaps as a module without executing main()."""
    # Ensure sibling sync module is importable by file path when report imports it.
    sync_path = TOOLS_DIR / "sync_requirements_inventory.py"
    sync_spec = importlib.util.spec_from_file_location(
        "sync_requirements_inventory", sync_path
    )
    assert sync_spec is not None and sync_spec.loader is not None
    sync_mod = importlib.util.module_from_spec(sync_spec)
    sys.modules["sync_requirements_inventory"] = sync_mod
    sync_spec.loader.exec_module(sync_mod)

    spec = importlib.util.spec_from_file_location(
        "report_inventory_gaps", REPORT_PATH
    )
    assert spec is not None and spec.loader is not None
    mod = importlib.util.module_from_spec(spec)
    sys.modules["report_inventory_gaps"] = mod
    spec.loader.exec_module(mod)
    return mod


class ReportInventoryGapsTests(unittest.TestCase):
    def setUp(self) -> None:
        if not REPORT_PATH.is_file():
            self.fail(f"Missing implementation: {REPORT_PATH}")
        self.report = load_report()

    def _seed_tree(self, root: Path) -> None:
        inv = root / "docs/requirements-inventory.md"
        inv.parent.mkdir(parents=True)
        inv.write_text(
            """# Inventory

## Core (`packages/laravel-capabilities`)

### `Registry/InvokePipelineTest.php` (2)

- [ ] happy: successful invoke runs full pipeline [PIPE-001]
- [ ] fail: missing stage case [PIPE-002]

### `Http/RoutesMatrixTest.php` (2)

- [ ] happy: registers http_enabled=True GET list [D-009]
- [ ] happy: matrix case for agent [D-013]

## Messaging (`packages/laravel-capabilities-messaging`)

### `Telegram/WebhookTest.php` (1)

- [ ] happy: accepts valid webhook [MSG-001]

## CLI (`packages/capabilities-cli`)

### `internal/catalog/cache_test.go` (2)

- [ ] TestCachehitsameversion [CLI-CAT]
- [ ] TestMissingGapCase [CLI-CAT]
""",
            encoding="utf-8",
        )

        php = (
            root
            / "packages/laravel-capabilities/tests/Unit/Registry/InvokePipelineTest.php"
        )
        php.parent.mkdir(parents=True)
        php.write_text(
            '<?php\n'
            'it("happy: successful invoke runs full pipeline [PIPE-001]", function () {});\n'
            "foreach (['agent'] as $caller) {\n"
            '    it("happy: matrix case for {$caller} [D-013]", function () use ($caller) {});\n'
            "}\n",
            encoding="utf-8",
        )

        go = root / "packages/capabilities-cli/internal/catalog/cache_test.go"
        go.parent.mkdir(parents=True)
        go.write_text(
            "package catalog\n\nfunc TestCachehitsameversion(t *testing.T) {}\n",
            encoding="utf-8",
        )

    def test_totals_and_by_package_counts(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            self._seed_tree(root)
            result = self.report.report_gaps(root, use_pest_list=False)

            self.assertEqual(result["total"], 7)
            self.assertEqual(result["matched"], 3)
            self.assertEqual(result["unmatched"], 4)

            by_pkg = result["by_package"]
            self.assertEqual(by_pkg["packages/laravel-capabilities"]["total"], 4)
            self.assertEqual(by_pkg["packages/laravel-capabilities"]["matched"], 2)
            self.assertEqual(by_pkg["packages/laravel-capabilities"]["unmatched"], 2)
            self.assertEqual(
                by_pkg["packages/laravel-capabilities-messaging"]["total"], 1
            )
            self.assertEqual(
                by_pkg["packages/laravel-capabilities-messaging"]["unmatched"], 1
            )
            self.assertEqual(by_pkg["packages/capabilities-cli"]["total"], 2)
            self.assertEqual(by_pkg["packages/capabilities-cli"]["matched"], 1)
            self.assertEqual(by_pkg["packages/capabilities-cli"]["unmatched"], 1)

    def test_dynamic_matrix_titles_are_matched(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            self._seed_tree(root)
            result = self.report.report_gaps(root, use_pest_list=False)
            labels = {g["label"] for g in result["gaps"]}
            self.assertNotIn("happy: matrix case for agent [D-013]", labels)
            self.assertIn("fail: missing stage case [PIPE-002]", labels)

    def test_cli_test_star_names_matched(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            self._seed_tree(root)
            result = self.report.report_gaps(root, use_pest_list=False)
            labels = {g["label"] for g in result["gaps"]}
            self.assertNotIn("TestCachehitsameversion [CLI-CAT]", labels)
            self.assertIn("TestMissingGapCase [CLI-CAT]", labels)

    def test_human_readable_stdout_and_exit_zero(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            self._seed_tree(root)
            buf = io.StringIO()
            with redirect_stdout(buf):
                code = self.report.main(["--root", str(root), "--no-pest-list"])
            out = buf.getvalue()
            self.assertEqual(code, 0)
            self.assertRegex(out, r"(?i)total")
            self.assertRegex(out, r"(?i)matched")
            self.assertRegex(out, r"(?i)unmatched|gap")
            self.assertIn("packages/laravel-capabilities", out)
            self.assertIn("packages/capabilities-cli", out)
            # Remaining gap labels appear in the report body
            self.assertIn("TestMissingGapCase", out)

    def test_gaps_grouped_with_file_context(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            self._seed_tree(root)
            result = self.report.report_gaps(root, use_pest_list=False)
            gap = next(
                g
                for g in result["gaps"]
                if g["label"] == "fail: missing stage case [PIPE-002]"
            )
            self.assertEqual(gap["package"], "packages/laravel-capabilities")
            self.assertIn("InvokePipelineTest.php", gap["file"])


if __name__ == "__main__":
    unittest.main()
