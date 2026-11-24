# DocVer — Privacy Guarantees & Data Retention Policy

DocVer is architected around the core principle of **Zero Permanent Content Storage**. We guarantee that uploaded proprietary contracts and extracted text are held strictly on a temporary basis and never retained permanently.

---

## 1. Zero Permanent Storage Guarantees

1. **No Extracted Text in MySQL:**
   - Raw document paragraphs, tables, and vector chunk texts are **NEVER** stored in permanent MySQL database tables.
   - Database tables store strictly structured metadata (page counts, file sizes, SHA-256 hashes, difference metrics, and validated short evidence snippets).

2. **Ephemeral Redis In-Memory Index:**
   - Document chunks, outlines, and inverted search structures live in ephemeral Redis caches with a strict 24-hour TTL (`DEFAULT_TTL = 86400`).
   - Ephemeral indexes are immediately deleted upon job completion, cancellation, or failure by `CleanupTemporaryDataJob`.

3. **Temporary Binary Storage:**
   - Staged document files reside in private temporary disk locations during parsing.
   - `CleanupTemporaryDataJob` automatically deletes raw files after indexing is complete.

4. **Hourly Automated Privacy Purge:**
   - The scheduled command `docver:purge-temporary` executes every hour:
     - Deletes expired document records exceeding workspace retention settings.
     - Purges orphaned temporary files older than 2 hours.
     - Wipes expired Redis chunks from abandoned jobs.

---

## 2. AI Provider Data Isolation & Retention

1. **Zero Model Training:**
   - We utilize enterprise API tiers for Google Gemini and OpenRouter. Under these commercial API agreements, prompt contents and document excerpts are **NOT** used to train foundation models.
2. **Bounded Excerpt Transmission:**
   - DocVer never transmits an entire 1,000-page document to external AI endpoints.
   - Only the minimal relevant excerpts (extracted clauses or candidate pairs) are transmitted to the model.
3. **Queue Payload Safety:**
   - Laravel queue payloads contain only integer IDs (`comparison_job_id`) and never contain raw document text or file binaries.
4. **Sanitized Telemetry:**
   - Application logs and monitoring tools (Sentry) are stripped of document excerpts and customer credentials.
