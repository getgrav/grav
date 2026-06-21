# Markdown conformance harness

Measures Grav's vendored Parsedown engine against the **CommonMark** and **GitHub
Flavored Markdown (GFM)** specifications, so the markdown feature gap is visible and
tracked rather than guessed.

## Run

```bash
php tests/conformance/run.php                 # both suites, per-section matrix
php tests/conformance/run.php gfm             # GFM only
php tests/conformance/run.php commonmark --section="Fenced code" --show-fails=5
php tests/conformance/run.php both --save     # also (re)write fixtures/baseline.json
```

Regenerate the GFM JSON from the upstream spec text:

```bash
php tests/conformance/extract_gfm.php
```

## Fixtures
- `fixtures/commonmark.json` — official CommonMark 0.31.2 spec tests (652 examples).
- `fixtures/gfm-spec.txt` — GitHub `cmark-gfm` spec source.
- `fixtures/gfm.json` — examples extracted from the GFM spec (672 examples, incl. the 5 GFM extension sections).
- `fixtures/baseline.json` — recorded pass counts per section (regression reference).

## How to read the numbers

The harness reports two columns:
- **strict** — exact string match (after outer trim).
- **loose** — a light, `<pre>`/`<code>`-protected normalization (collapses insignificant
  inter-tag whitespace, normalizes void-tag spacing).

It tests the **raw vendored `\Parsedown`** (the engine), not Grav's wrapper subclass —
the wrapper additionally entity-escapes `<` `>` `"`, which changes many outputs and is
out of scope for an engine baseline.

**Absolute percentages are honest, not flattering.** Parsedown is a fast line-based
parser, not a CommonMark AST parser. Many core failures are genuine, well-known
divergences — e.g. it drops the trailing newline inside fenced `<code>`, keeps the
padding spaces in `` `code` `` spans that CommonMark strips, and does not implement
CommonMark emphasis flanking rules. So the light-normalized core rate (~46%) is real;
chasing a heavier normalizer would only inflate it. This is exactly why the plan targets
**GFM feature-completeness + a measured gap**, not literal 100% spec conformance.

## Baseline (PHP 8.5, pre-change)

GFM extension sections — the ones the feature work targets:

| GFM extension section | examples | loose pass |
|---|---|---|
| Strikethrough | 2 | **100%** ✅ already supported |
| Tables | 8 | 25% partial |
| Autolinks (www/email) | 11 | **0%** ❌ missing |
| Task list items | 2 | **0%** ❌ missing |
| Disallowed Raw HTML (tagfilter) | 1 | **0%** ❌ missing |

Overall: CommonMark 652 ex ≈ 46% loose; GFM 672 ex ≈ 46% loose. Use the per-section
matrix and the GFM-extension rows — not the headline number — to track progress. Every
engine change must keep core sections from regressing and push the GFM-extension rows
toward 100%.

## Regression gate for engine (perf) changes

Performance patches to the engine must be **output-identical**, not merely "spec pass-rate
unchanged." Verify by running the full corpus through the old and new engine and asserting
byte-identical output for every example (see the perf task / benchmark script).
