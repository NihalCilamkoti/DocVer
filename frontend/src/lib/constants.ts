export const SUPPORTED_EXTENSIONS = [
  "pdf",
  "doc",
  "docx",
  "txt",
  "xls",
  "xlsx",
  "csv",
  "ppt",
  "pptx",
  "png",
  "jpg",
  "jpeg",
  "webp",
] as const;

export type SupportedExtension = typeof SUPPORTED_EXTENSIONS[number];

export const SUPPORTED_MIME_TYPES: Record<string, string[]> = {
  "application/pdf": [".pdf"],
  "application/msword": [".doc"],
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document": [".docx"],
  "text/plain": [".txt"],
  "application/vnd.ms-excel": [".xls"],
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": [".xlsx"],
  "text/csv": [".csv"],
  "application/vnd.ms-powerpoint": [".ppt"],
  "application/vnd.openxmlformats-officedocument.presentationml.presentation": [".pptx"],
  "image/png": [".png"],
  "image/jpeg": [".jpg", ".jpeg"],
  "image/webp": [".webp"],
};

export const DEFAULT_CONFIG = {
  maxFileSizeBytes: 50 * 1024 * 1024, // 50MB
  maxPageLimit: 500,
  allowedFileTypesDescription: "PDF, Word, Excel, CSV, PPT, Images up to 50MB",
};

export const SUGGESTED_QUESTIONS = [
  "Where is India and what is its capital?",
  "What is artificial intelligence?",
  "Write an email to my professor requesting an extension",
  "Explain Python loops with a simple example",
  "Give me five creative project ideas",
  "Tell me what is different between two uploaded documents",
] as const;
