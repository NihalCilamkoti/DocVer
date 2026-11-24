# DocVer Phase 4 — Document Processing Engine

This document details the implementation of the enterprise document processing and ingestion architecture for DocVer.

---

## 1. Supported Formats & Extraction Adapters

DocVer implements dedicated parser adapters for 12 document formats without trusting client extensions or MIME headers:

| Format | Adapter Class | Extraction Strategy & Page Semantics |
|---|---|---|
| **PDF** | `PdfDocumentParser` | Page-by-page extraction using `smalot/pdfparser`. Preserves page boundaries and detects scanned pages. |
| **DOCX** | `DocxDocumentParser` | OOXML package reader (`word/document.xml`). Extracts headings, paragraphs, tables, and page breaks (`w:lastRenderedPageBreak` & `w:br[type=page]`). |
| **DOC** | `DocDocumentParser` | Legacy OLE Compound Document extractor. Extracts text streams and header records. |
| **TXT** | `TextDocumentParser` | Streaming SplFileObject parser with explicit page breaks (`\f`) and bounded line threshold partitioning (45 lines/page). |
| **XLSX** | `XlsxDocumentParser` | OpenXML workbook & shared strings reader (`xl/worksheets/sheet*.xml`). Maps each worksheet to 1 page and parses tabular rows. |
| **XLS** | `XlsDocumentParser` | Legacy BIFF binary workbook stream extractor. Extracts labels, shared formulas, and sheets. |
| **CSV** | `CsvDocumentParser` | Streaming `fgetcsv` parser with RFC 4180 compliance, table header detection, and 50 rows/page partitioning. |
| **PPTX** | `PptxDocumentParser` | OpenXML presentation reader (`ppt/slides/slide*.xml`). Maps each slide to exactly 1 page (`Slide 1 = Page 1`). |
| **PPT** | `PptDocumentParser` | Legacy PowerPoint binary stream parser extracting slide text containers. |
| **JPEG** | `ImageDocumentParser` | Image dimensions inspection and OCR pipeline handover. |
| **PNG** | `ImageDocumentParser` | Image metadata extraction, transparency check, and OCR pipeline handover. |
| **TIFF** | `ImageDocumentParser` | Multi-page / high-resolution scanned document adapter and OCR pipeline handover. |

---

## 2. Security & Zero Permanent Storage Architecture

### Magic Byte File Signature Verification (`FileSignatureValidator`)
- Never trusts client-supplied filename extensions or `Content-Type` headers.
- Inspects real binary magic signatures for PDF (`%PDF-`), PNG (`\x89PNG\r\n\x1a\n`), JPEG (`\xFF\xD8\xFF`), TIFF (`II*\x00` / `MM\x00*`), ZIP container (`PK\x03\x04`), and OLE compound (`\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1`).
- Strictly rejects binary executable payloads, including Windows PE (`MZ`), Linux ELF (`\x7FELF`), and Mach-O (`\xCA\xFE\xBA\xBE`, `\xFE\xED\xFA\xCE`, `\xFE\xED\xFA\xCF`).

### ZIP Bomb & Decompression Protection (`ZipBombGuard`)
- Enforces strict decompression guards for OOXML archives (DOCX, XLSX, PPTX):
  - Maximum uncompressed size: **150 MB**.
  - Maximum compression ratio: **100:1**.
  - Maximum internal file entry count: **1,500 entries**.

### Malware Scanner Interface (`MalwareScannerInterface` / `NullMalwareScanner`)
- Heuristic inspection for dangerous script triggers, including `/JavaScript`, `/Launch`, `<script`, and embedded execution macros.

### Zero Permanent Storage Rule
- Raw document files are staged in private temporary storage (`storage/app/documents/{workspace}/{job}/`).
- Extracted raw text, paragraphs, and vector chunk embeddings are **NEVER** stored in permanent MySQL columns.
- Parsed chunks and indexes are stored in ephemeral Redis/encrypted cache structures via `EphemeralIndexManager` with a 24-hour TTL.
- Raw staged files are automatically deleted upon job completion, cancellation, or failure by `CleanupTemporaryDataJob`.

---

## 3. Asynchronous 9-Stage Processing Pipeline

The pipeline is coordinated by `DocumentPipelineCoordinator` using queued Laravel jobs:

1. **`ValidateUploadedFileJob`** (10%)
   - Verifies magic byte file signatures, ZIP bomb constraints, and malware scans for both documents.
2. **`ExtractDocumentTextJob`** (25%)
   - Dispatches document parsers for Slot A and Slot B; extracts text, tables, and page counts while preserving 1-based page numbers.
3. **`DetectScannedPagesJob`** (40%)
   - Flags pages lacking digital text streams or below word density thresholds (< 15 words).
4. **`PerformOCRJob`** (50%)
   - Invokes OCR engine for scanned pages and image formats; provides safe fallback if Tesseract is not installed on the host.
5. **`NormalizeDocumentJob`** (65%)
   - Cleans non-printable control characters, normalizes line breaks, collapses whitespace, and standardizes Unicode quotes/dashes.
6. **`DetectSectionsJob`** (75%)
   - Parses document headings, numbered hierarchical clauses (e.g. `1.1 Definitions`), and legal articles (`Article 2`, `Section 4.1`).
7. **`ChunkDocumentJob`** (85%)
   - Splits document text into bounded chunks (configurable 600 chars with 100 char overlap) and tabular chunks, preserving `document_id`, `slot`, `pageNumber`, `sectionTitle`, and approximate token counts.
8. **`BuildComparisonIndexJob`** (95% -> 100%)
   - Builds the ephemeral index in Redis via `EphemeralIndexManager::storeChunks()`, computes total chunks and token statistics, and marks pipeline completed.
9. **`CleanupTemporaryDataJob`**
   - Purges raw temporary file binaries from storage, and if cancelled or failed, wipes all ephemeral caches.

---

## 4. REST & Streaming API Endpoints

### 1. Direct Document Upload
- **Endpoint:** `POST /api/v1/upload` (or `POST /api/v1/workspaces/{workspace}/documents/upload`)
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: multipart/form-data`
- **Payload:**
  - `file`: Document binary
  - `slot`: `'A'` or `'B'`
  - `workspace_id`: (optional) Target workspace ID
  - `comparison_job_id`: (optional) Link directly to a comparison job
- **Features:** Validates magic bytes, checks max size, guards against duplicate file hashes within the same comparison job.

### 2. Status Tracking & SSE Streaming
- **Endpoint:** `GET /api/v1/comparisons/{id}/status`
- **Headers:** `Authorization: Bearer <token>`
- **Polling Response (`application/json`):**
  ```json
  {
    "success": true,
    "message": "Comparison processing status",
    "data": {
      "job_id": 1,
      "stage": "ocr_processing",
      "label": "Executing OCR on scanned documents",
      "progress": 50,
      "is_cancelled": false,
      "status": "processing"
    }
  }
  ```
- **SSE Stream (`Accept: text/event-stream`):**
  Streams live progress events directly to the browser client with `Content-Type: text/event-stream`.

### 3. Comparison Cancellation
- **Endpoint:** `POST /api/v1/comparisons/{id}/cancel`
- **Headers:** `Authorization: Bearer <token>`
- **Action:** Sets `cancelled_at = now()`, marks job `cancelled`, halts all subsequent pipeline jobs, and dispatches `CleanupTemporaryDataJob` with `onFailureOrCancel = true`.

---

## 5. Verification & Testing

### Running Tests
```bash
# Run all unit and feature tests
php artisan test

# Run Next.js frontend typecheck and production build
npm run build
```

### Verified Test Results
- **Backend Test Suite:** 31 tests passing, 138 assertions (`php artisan test`).
- **Frontend Test Suite:** 15 static routes compiled cleanly, 0 TypeScript errors, 0 ESLint errors (`npm run build`).
