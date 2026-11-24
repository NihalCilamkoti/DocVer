# Security Architecture & OWASP Compliance

DocVer applies defense-in-depth security across all layers:

1. **Air-Gapped AI Key Isolation:** AI provider keys are never exposed to clients, scripts, or public routes.
2. **Strict Authorization Policies:** Workspace isolation policies protect all comparison jobs and documents from IDOR attacks.
3. **Magic Byte File Signature Inspection:** Uploads are inspected for executable masquerading (ELF, PE, Mach-O).
4. **Decompression Bomb Protection:** ZIP, DOCX, and XLSX archives enforce strict compression ratios ($\le 100:1$).
5. **Prompt Injection Defense:** `PromptSanitizer` neutralizes delimiter hijacking and wraps extracted document context in safe XML blocks.
6. **SQL Injection & XSS Guard:** Prepared statements via Eloquent ORM and strict output escaping in Next.js.
7. **Rate Limiting & Abuse Prevention:** Redis sliding window rate limits protect public authentication and comparison endpoints.
