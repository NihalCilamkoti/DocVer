# Security Policy & Vulnerability Disclosure

DocVer processes sensitive enterprise contracts, agreements, and financial filings. We adhere to rigorous security and privacy standards.

## Supported Versions

| Version | Supported |
| :--- | :--- |
| 1.x (Current) | :white_check_mark: |

## Core Security Guarantees

1. **Zero Permanent Document Retention:** Document uploads and extracted text exist only in memory or encrypted ephemeral storage with a strict automated purge window.
2. **Strict Authorization & IDOR Prevention:** Workspace boundaries, role checks, and ownership policies enforce that no tenant or user can ever inspect another workspace's reports or documents.
3. **Air-Gapped AI Key Protection:** AI provider API keys (`GEMINI_API_KEY`, `GROQ_API_KEY`, `OPENROUTER_API_KEY`) are restricted exclusively to server-side backend execution. They are never exposed to browser client bundles or public endpoints.
4. **File Signature Validation:** Never trust client-provided file extensions or MIME types. Real binary magic bytes are validated on upload.

## Reporting a Vulnerability

If you discover a security vulnerability in DocVer, please report it privately:

- **Email:** security@docver.io
- **PGP Key:** Available upon request
- Please do NOT create public GitHub issues for security vulnerabilities.
- We will acknowledge receipt within 24 hours and provide a remediation timeline.
