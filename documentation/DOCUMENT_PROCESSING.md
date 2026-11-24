# Document Processing Pipeline

The ingestion pipeline handles 12 file formats asynchronously using Redis queues.

```mermaid
flowchart LR
    Upload["Upload Binary"] --> V["ValidateUploadedFileJob"]
    V --> E["ExtractDocumentTextJob"]
    E --> S["DetectScannedPagesJob"]
    S --> OCR["PerformOCRJob (Tesseract)"]
    OCR --> N["NormalizeDocumentJob"]
    N --> Sec["DetectSectionsJob"]
    Sec --> C["ChunkDocumentJob"]
    C --> B["BuildComparisonIndexJob"]
    B --> Clean["CleanupTemporaryDataJob"]
```

## Supported Parsers

- **PDF:** `PdfParser` (poppler pdftotext)
- **DOCX / DOC:** `DocxParser` (ZipArchive / XML)
- **XLSX / XLS / CSV:** `SpreadsheetParser` & `CsvParser`
- **PPTX / PPT:** `PresentationParser`
- **Images (PNG, JPEG, TIFF):** `ImageOCRService` (Tesseract OCR)
- **Plain Text:** `TextParser`

## Scalability Guarantees

- Large files (> 100 pages) process in chunks without loading entire files into PHP memory.
- Ephemeral chunk indexes expire automatically after 24 hours.
