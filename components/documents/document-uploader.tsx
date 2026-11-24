"use client";

import * as React from "react";
import { UploadCloud, Plus, FolderOpen } from "lucide-react";
import { DocumentUploadMeta } from "@/types/comparison";
import { DEFAULT_CONFIG } from "@/lib/constants";
import { formatBytes, cn } from "@/lib/utils";
import { DocumentCard } from "./document-card";
import { Button } from "@/components/ui/button";

interface DocumentUploaderProps {
  documents: DocumentUploadMeta[];
  onUploadFiles: (files: File[], targetSlot?: "A" | "B") => Promise<void>;
  onRemoveDocument: (id: string) => void;
  onRetryDocument: (id: string) => void;
  onPreviewDocument?: (id: string) => void;
  onToggleContext?: (id: string) => void;
  previewDocumentId?: string | null;
  maxFileSize?: number;
  compact?: boolean;
  className?: string;
}

export function DocumentUploader({
  documents,
  onUploadFiles,
  onRemoveDocument,
  onRetryDocument,
  onPreviewDocument,
  onToggleContext,
  previewDocumentId,
  maxFileSize = DEFAULT_CONFIG.maxFileSizeBytes,
  compact = false,
  className,
}: DocumentUploaderProps) {
  const [isDragging, setIsDragging] = React.useState(false);
  const fileInputRef = React.useRef<HTMLInputElement | null>(null);

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  };

  const handleDrop = async (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      const fileList = Array.from(e.dataTransfer.files);
      await onUploadFiles(fileList);
    }
  };

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const fileList = Array.from(e.target.files);
      await onUploadFiles(fileList);
      e.target.value = "";
    }
  };

  return (
    <div className={cn("space-y-3", className)}>
      <input
        ref={fileInputRef}
        type="file"
        multiple
        className="hidden"
        onChange={handleFileChange}
        accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.csv,.ppt,.pptx,.png,.jpg,.jpeg,.webp"
        aria-label="Upload documents"
      />

      {/* Drag and drop zone */}
      <div
        role="button"
        tabIndex={0}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
        onKeyDown={(e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            fileInputRef.current?.click();
          }
        }}
        className={cn(
          "cursor-pointer rounded-xl border border-dashed transition-all text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500",
          compact ? "p-3.5" : "p-5",
          isDragging
            ? "border-blue-500 bg-blue-50/50"
            : "border-slate-300 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-400"
        )}
      >
        <div className="flex flex-col items-center justify-center space-y-2">
          <div
            className={cn(
              "rounded-lg bg-white border border-slate-200/90 shadow-subtle flex items-center justify-center text-slate-700",
              compact ? "h-8 w-8" : "h-10 w-10"
            )}
          >
            <UploadCloud className={cn(compact ? "h-4 w-4" : "h-5 w-5", "text-blue-600")} />
          </div>

          <div className="space-y-0.5">
            <p className="text-xs font-semibold text-slate-900">
              Drag & drop documents here, or{" "}
              <span className="text-blue-600 underline-offset-2 hover:underline">
                browse files
              </span>
            </p>
            <p className="text-[11px] text-slate-500">
              PDF, DOCX, DOC, XLSX, CSV, PPTX, TXT, PNG, JPG • Up to {formatBytes(maxFileSize)}
            </p>
          </div>

          {!compact && (
            <div className="pt-1">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={(e) => {
                  e.stopPropagation();
                  fileInputRef.current?.click();
                }}
                className="h-7 text-xs gap-1.5 bg-white"
              >
                <FolderOpen className="h-3.5 w-3.5 text-slate-600" />
                Browse Files
              </Button>
            </div>
          )}
        </div>
      </div>

      {/* Uploaded Compact File Cards */}
      {documents.length > 0 && (
        <div className="space-y-2">
          <div className="flex items-center justify-between px-0.5">
            <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
              Workspace Documents ({documents.length})
            </span>
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              className="inline-flex items-center gap-1 text-[11px] font-medium text-blue-600 hover:text-blue-700"
            >
              <Plus className="h-3 w-3" />
              <span>Add file</span>
            </button>
          </div>

          <div className="grid grid-cols-1 gap-2">
            {documents.map((doc) => (
              <DocumentCard
                key={doc.id}
                doc={doc}
                onRemove={onRemoveDocument}
                onRetry={onRetryDocument}
                onPreview={onPreviewDocument}
                onToggleContext={onToggleContext}
                isSelectedForPreview={previewDocumentId === doc.id}
                compact={compact}
              />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
