"use client";

import * as React from "react";
import {
  UploadCloud,
  FileText,
  AlertCircle,
  FileCheck2,
  Sparkles,
} from "lucide-react";
import { DocumentUploadMeta } from "@/types/comparison";
import { SUPPORTED_EXTENSIONS, SupportedExtension } from "@/lib/constants";
import { formatBytes } from "@/lib/utils";
import { FilePreview } from "./file-preview";
import { Progress } from "@/components/ui/progress";

interface DocumentUploaderProps {
  slot: "A" | "B";
  title: string;
  subtitle: string;
  document: DocumentUploadMeta | null;
  onDocumentChange: (doc: DocumentUploadMeta | null) => void;
  maxFileSize: number;
  maxPageLimit: number;
}

export function DocumentUploader({
  slot,
  title,
  subtitle,
  document,
  onDocumentChange,
  maxFileSize,
  maxPageLimit,
}: DocumentUploaderProps) {
  const [isDragging, setIsDragging] = React.useState(false);
  const [validationError, setValidationError] = React.useState<string | null>(null);
  const fileInputRef = React.useRef<HTMLInputElement | null>(null);

  const validateAndProcessFile = (file: File) => {
    setValidationError(null);

    // 1. Extension check
    const extension = file.name.split(".").pop()?.toLowerCase() || "";
    if (!SUPPORTED_EXTENSIONS.includes(extension as SupportedExtension)) {
      setValidationError(
        `Unsupported file type .${extension}. Supported: PDF, DOC, DOCX, TXT, XLS, XLSX, CSV, PPT, PPTX, and standard image formats.`
      );
      return;
    }

    // 2. Size check
    if (file.size > maxFileSize) {
      setValidationError(
        `File exceeds maximum limit of ${formatBytes(maxFileSize)}.`
      );
      return;
    }

    // Create document upload representation
    const newDoc: DocumentUploadMeta = {
      id: "doc_" + slot.toLowerCase() + "_" + Date.now(),
      file,
      name: file.name,
      size: file.size,
      type: file.type || "application/octet-stream",
      extension,
      pageCount: Math.max(1, Math.round(file.size / (60 * 1024))), // Realistic estimated pages
      uploadedAt: new Date().toISOString(),
      progress: 0,
      status: "uploading",
    };

    onDocumentChange(newDoc);

    // Simulated quick progress increment for UI polish
    let currentProgress = 0;
    const interval = setInterval(() => {
      currentProgress += 25;
      if (currentProgress >= 100) {
        clearInterval(interval);
        onDocumentChange({
          ...newDoc,
          progress: 100,
          status: "ready",
        });
      } else {
        onDocumentChange({
          ...newDoc,
          progress: currentProgress,
          status: "uploading",
        });
      }
    }, 150);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      validateAndProcessFile(e.dataTransfer.files[0]);
    }
  };

  const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      validateAndProcessFile(e.target.files[0]);
    }
  };

  const handleRemove = () => {
    onDocumentChange(null);
    setValidationError(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = "";
    }
  };

  const handleTriggerPicker = () => {
    fileInputRef.current?.click();
  };

  return (
    <div className="flex flex-col space-y-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-2">
          <div
            className={`h-6 w-6 rounded-md flex items-center justify-center font-bold text-xs ${
              slot === "A"
                ? "bg-primary-100 text-primary-700"
                : "bg-purple-100 text-ai-700"
            }`}
          >
            {slot}
          </div>
          <span className="text-sm font-bold text-text-main">{title}</span>
        </div>
        <span className="text-[11px] text-text-secondary">{subtitle}</span>
      </div>

      <input
        ref={fileInputRef}
        type="file"
        className="hidden"
        onChange={handleFileInput}
        accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.csv,.ppt,.pptx,.png,.jpg,.jpeg,.webp"
      />

      {document && document.status === "ready" ? (
        <FilePreview
          doc={document}
          onRemove={handleRemove}
          onReplace={handleTriggerPicker}
        />
      ) : document && document.status === "uploading" ? (
        <div className="rounded-xl border border-primary-200 bg-primary-50/40 p-6 flex flex-col items-center justify-center space-y-3 min-h-[180px]">
          <FileText className="h-8 w-8 text-primary-600 animate-bounce" />
          <div className="text-center space-y-1 w-full max-w-xs">
            <p className="text-xs font-semibold text-text-main truncate">
              Uploading {document.name}
            </p>
            <Progress value={document.progress} className="h-1.5" />
            <p className="text-[10px] text-text-secondary">
              Validating format & client pre-processing...
            </p>
          </div>
        </div>
      ) : (
        <div
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={handleTriggerPicker}
          className={`cursor-pointer rounded-xl border-2 border-dashed p-6 flex flex-col items-center justify-center text-center transition-all min-h-[180px] bg-white ${
            isDragging
              ? "border-primary-500 bg-primary-50/50 scale-[0.99]"
              : "border-border hover:border-primary-300 hover:bg-slate-50/60"
          }`}
        >
          <div className="h-12 w-12 rounded-2xl bg-slate-50 border border-border flex items-center justify-center text-text-secondary mb-3 group-hover:scale-105 transition-transform">
            <UploadCloud className="h-6 w-6 text-primary-600" />
          </div>

          <div className="space-y-1">
            <p className="text-xs font-semibold text-text-main">
              <span className="text-primary-600 hover:underline">Click to upload</span> or drag and drop
            </p>
            <p className="text-[11px] text-text-secondary">
              PDF, DOC, DOCX, TXT, XLS, XLSX, CSV, PPT, PPTX or Images
            </p>
            <p className="text-[10px] text-text-muted">
              Up to {formatBytes(maxFileSize)} • Max {maxPageLimit} pages
            </p>
          </div>
        </div>
      )}

      {validationError && (
        <div className="flex items-center gap-2 rounded-lg bg-error-50 p-2.5 text-xs text-error border border-error-100">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{validationError}</span>
        </div>
      )}
    </div>
  );
}
