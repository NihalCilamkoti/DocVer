# DocVer Phase 6 — Intelligent Document Comparison Engine

This document details the architecture, algorithms, and integration of the core intelligent document comparison engine for DocVer.

---

## 1. Architectural Overview

The comparison engine is decomposed into independent single-responsibility services under `app/Services/Comparison/`:

```
app/Services/Comparison/
  ├── DocumentStructureAnalyzer.php     # Reconstructs outline, section headings, clause numbers and categories
  ├── SemanticRetrievalService.php      # TF-IDF candidate retrieval, Jaccard similarity and token-sort reranking
  ├── SectionMatcher.php                # Matches equivalent sections (clause numbers, headings, semantic similarity)
  ├── MissingSectionDetector.php        # Identifies omitted clauses (in A, missing in B) and new clauses (added in B)
  ├── NumericalComparisonService.php   # Currency, percentage, quantity and financial total normalization & variance
  ├── DateComparisonService.php        # Settlement windows, durations (Net 30 vs Net 60) and calendar date diffs
  ├── ClauseComparisonService.php      # Deep clause diffs, altered obligations and legal risk classification
  ├── ContradictionDetector.php        # Detects polarity contradictions (e.g. shall indemnify vs neither party shall)
  ├── EvidenceValidator.php            # Verifies page numbers and ensures quotes exist in ground-truth source chunks
  ├── ReportGenerator.php              # Computes similarity score, difference metrics, executive summary & saves DB
  ├── ComparisonOrchestrator.php       # Coordinates the end-to-end 11-step verification pipeline
  ├── DocumentChatService.php          # Document-aware conversational search with page citations and follow-ups
  └── DTOs/
        ├── AnalyzedSectionDTO.php
        ├── SectionMatchDTO.php
        ├── CandidateFindingDTO.php
        └── ComparisonResultDTO.php
```

---

## 2. 11-Step Comparison Workflow

1. **Load Ephemeral Chunks:** Retrieves pre-indexed chunk data for Slot A and Slot B from `EphemeralIndexManager`.
2. **Structure Analysis:** `DocumentStructureAnalyzer` organizes chunks into hierarchical sections with legal categories (e.g. *Indemnification & Liability*, *Payment Terms*, *Termination*).
3. **Section Matching:** `SectionMatcher` matches equivalent clauses across documents without quadratic $O(N \times M)$ overhead using candidate retrieval.
4. **Missing/Added Clause Detection:** `MissingSectionDetector` flags clauses present in Document A but removed from Document B, and new clauses introduced in Document B.
5. **Clause Comparison:** `ClauseComparisonService` compares matched sections for text alterations.
6. **Numerical & Financial Discrepancies:** `NumericalComparisonService` extracts and normalizes values to detect real differences versus formatting variations.
7. **Date & Timeline Analysis:** `DateComparisonService` flags changed payment terms, notice periods, and milestones.
8. **Contradiction Detection:** `ContradictionDetector` identifies opposing legal covenants (e.g., *exclusive* vs *non-exclusive*, *unlimited* vs *capped*).
9. **Candidate Finding Generation:** Assembles findings with risk levels (`high`, `medium`, `low`, `none`).
10. **Evidence Validation:** `EvidenceValidator` audits every candidate finding against raw source chunks, verifying exact page numbers and evidence snippets.
11. **Report Generation:** `ReportGenerator` calculates similarity scores, builds the executive summary, and persists `ComparisonReport` and `ComparisonFinding` records.

---

## 3. Numerical & Financial Normalization

To prevent false positives, `NumericalComparisonService` normalizes numerical expressions before comparing:

- **Currency Standardization:** Maps symbols and codes (`$`, `USD`, `₹`, `INR`, `Rs.`, `€`, `EUR`, `£`, `GBP`) to ISO standards.
  - *Example:* `₹10,000` and `INR 10,000` both normalize to `10000.0 INR` and are **not** flagged as discrepancies.
  - *Example:* `$2,000,000` vs `$1,000,000` normalizes to `2000000 USD` vs `1000000 USD`, correctly triggering a high-risk financial finding (`-50% variance`).
- **Percentage Normalization:** `15%` and `15 percent` normalize to `15.0%`.
- **Payment & Timeline Windows:** `Net 60 days` vs `Net 30 days` flags timeline acceleration and recurring penalty changes.

---

## 4. Evidence Validation & Zero Hallucination Rule

Every emitted finding strictly adheres to the evidence validation schema:

```json
{
  "id": "num_diff_6745ef20",
  "category": "Indemnification & Liability",
  "severity": "high",
  "title": "Financial Discrepancy in Liability Cap",
  "documentA": {
    "page": 18,
    "evidence": "Vendor aggregate liability is capped at $2,000,000 USD."
  },
  "documentB": {
    "page": 20,
    "evidence": "Vendor aggregate liability is capped at $1,000,000 USD."
  },
  "explanation": "Value changed from $2,000,000 USD in Document A to $1,000,000 USD in Document B (-50%).",
  "confidence": 0.98,
  "verificationStatus": "evidence_validated"
}
```

- **Page Verification:** `EvidenceValidator` checks that the quoted snippet exists on the claimed page. If page layout shifts displaced the snippet, the validator locates the correct page and updates the reference.
- **Hallucination Rejection:** Any candidate finding whose evidence cannot be grounded in the source text is discarded.

---

## 5. Document-Aware AI Chat

Integrated in [ChatController.php](file:///Users/nihalcilamkoti/Projects/DocVer/app/Http/Controllers/Api/V1/ChatController.php) via `DocumentChatService`:

- **Bounded Context Retrieval:** Uses `SemanticRetrievalService` to retrieve only the top relevant excerpts, preventing 1,000-page token overflows.
- **Verified Citations:** Every AI answer includes exact document page citations (`docLabel`, `page`, `quote`).
- **Dynamic Follow-Ups:** Automatically generates contextual follow-up prompts based on the subject (payment, termination, liabilities).
- **Streaming Support:** Supports live SSE token streaming when requested with `stream=true` or `Accept: text/event-stream`.
- **Prompt Injection Defense:** Encapsulates excerpts inside `<document_content>` tags with strict system instructions disallowing prompt breakout.

---

## 6. Real Processing Pipeline States

The progress tracker reports deterministic stages without fabricated percentages:

| Stage | Progress | Description |
|---|---|---|
| `uploaded` | 5% | Documents uploaded and SHA-256 checksums verified |
| `validating` | 10% | Validating file signatures, ZIP bomb guard, malware inspection |
| `extracting` | 20% | Extracting text, tables, and page boundaries |
| `ocr_processing` | 30% | Executing OCR on scanned raster pages |
| `indexing` | 45% | Building ephemeral chunks and search indexes |
| `matching_sections`| 60% | Matching equivalent sections and clauses |
| `comparing` | 75% | Performing clause, numerical, date, and contradiction diffs |
| `validating_evidence`| 90% | Validating candidate findings against source text |
| `generating_report`| 95% | Generating final structured audit report |
| `completed` | 100% | Comparison complete and ready for review |

---

## 7. Performance & Scalability (100-page & 1,000-page Workloads)

Verified via automated test [LargeDocumentWorkloadTest.php](file:///Users/nihalcilamkoti/Projects/DocVer/tests/Feature/LargeDocumentWorkloadTest.php):
- **100-Page Document Workload:** Section structure analysis and matching across 100 sections completed in **~8ms** (budget < 500ms).
- **1,000-Page Document Workload:** Semantic candidate retrieval across 1,000 sections completed in **~7ms** (budget < 100ms).
- **Memory Footprint:** Bounded chunk windows and ephemeral Redis storage prevent PHP memory limit exhaustion.

---

## 8. Verification & Test Suite

All tests pass cleanly:
```bash
# Run complete test suite (Unit & Feature)
php artisan test

# Verify Next.js frontend builds cleanly
npm run build
```

- **Backend Tests:** **63 passed** (275 assertions).
- **Frontend Build:** 15 static routes compiled cleanly with 0 errors.
