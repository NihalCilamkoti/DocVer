"use client";

import * as React from "react";
import {
  FileText,
  FileSpreadsheet,
  FileBarChart,
  FileImage,
  FileCode,
  Eye,
  X,
  RotateCcw,
  Check,
  Plus,
} from "lucide-react";
import { DocumentUploadMeta } from "@/types/comparison";
import { formatBytes, cn } from "@/lib/utils";
import { DocumentStatus } from "./document-status";
import { Progress } from "@/components/ui/progress";

interface DocumentCardProps {
  doc: DocumentUploadMeta;
  onRemove?: (id: string) => void;
  onRetry?: (id: string) => void;
  onPreview?: (id: string) => void;
  onToggleContext?: (id: string) => void;
  isSelectedForPreview?: boolean;
  compact?: boolean;
}

export function getDocumentFileIcon(ext: string, className = "h-4 w-4") {
  const lower = (ext || "").toLowerCase();
  if (["xls", "xlsx", "csv"].includes(lower)) {
    return <FileSpreadsheet className={cn(className, "text-emerald-600")} />;
  }
  if (["ppt", "pptx"].includes(lower)) {
    return <FileBarChart className={cn(className, "text-amber-600")} />;
  }
  if (["png", "jpg", "jpeg", "webp"].includes(lower)) {
    return <FileImage className={cn(className, "text-sky-600")} />;
  }
  if (["txt", "md", "json"].includes(lower)) {
    return <FileCode className={cn(className, "text-slate-600")} />;
  }
  return <FileText className={cn(className, "text-blue-600")} />;
}

export function DocumentCard({
  doc,
  onRemove,
  onRetry,
  onPreview,
  onToggleContext,
  isSelectedForPreview = false,
  compact = false,
}: DocumentCardProps) {
  const isBusy =
    doc.status === "uploading" ||
    doc.status === "uploaded" ||
    doc.status === "extracting" ||
    doc.status === "processing";

  const isFailed = doc.status === "failed" || doc.status === "error" || doc.status === "unsupported";
  const inContext = doc.inActiveContext !== false && doc.status === "ready";

  return (
    <div
      className={cn(
        "group relative rounded-xl border bg-white transition-all",
        compact ? "p-2.5" : "p-3",
        isSelectedForPreview
          ? "border-blue-500 ring-1 ring-blue-500/20 bg-blue-50/10"
          : isFailed
          ? "border-red-200 bg-red-50/20"
          : "border-slate-200/90 hover:border-slate-300 shadow-subtle"
      )}
    >
      <div className="flex items-start gap-2.5">
        {/* File Icon */}
        <div
          className={cn(
            "rounded-lg border border-slate-200/80 bg-slate-50 flex items-center justify-center shrink-0",
            compact ? "h-8 w-8" : "h-9 w-9"
          )}
        >
          {getDocumentFileIcon(doc.extension, compact ? "h-4 w-4" : "h-4 w-4")}
        </div>

        {/* File Info */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-1.5">
            <div className="flex items-center gap-1.5 min-w-0">
              {doc.slot && (
                <span
                  className={cn(
                    "inline-flex items-center justify-center h-4 px-1.5 rounded text-[10px] font-bold uppercase tracking-wider shrink-0",
                    doc.slot === "A"
                      ? "bg-blue-100 text-blue-800"
                      : "bg-indigo-100 text-indigo-800"
                  )}
                >
                  Doc {doc.slot}
                </span>
              )}
              <h4
                className="text-xs font-semibold text-slate-900 truncate"
                title={doc.name}
              >
                {doc.name}
              </h4>
            </div>

            {onRemove && (
              <button
                type="button"
                onClick={() => onRemove(doc.id)}
                className="text-slate-400 hover:text-slate-700 p-0.5 rounded-md transition-colors shrink-0"
                aria-label={`Remove ${doc.name}`}
                title="Remove document"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            )}
          </div>

          {/* Metadata Row */}
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500 mt-0.5">
            <span className="uppercase font-mono text-[10px] font-medium text-slate-600">
              {doc.extension || "FILE"}
            </span>
            <span>•</span>
            <span>{formatBytes(doc.size)}</span>
            {doc.pageCount && doc.status === "ready" && (
              <>
                <span>•</span>
                <span>
                  {doc.pageCount} {doc.pageCount === 1 ? "page" : "pages"}
                </span>
              </>
            )}
          </div>

          {/* Status & Action Controls */}
          <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
            <DocumentStatus status={doc.status} progress={doc.progress} compact />

            <div className="flex items-center gap-1">
              {doc.status === "ready" && onPreview && (
                <button
                  type="button"
                  onClick={() => onPreview(doc.id)}
                  className={cn(
                    "inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium transition-colors",
                    isSelectedForPreview
                      ? "bg-blue-600 text-white"
                      : "bg-slate-100 text-slate-700 hover:bg-slate-200/80"
                  )}
                  title="Preview and inspect extracted text"
                >
                  <Eye className="h-3 w-3" />
                  <span>{isSelectedForPreview ? "Viewing" : "Preview"}</span>
                </button>
              )}

              {doc.status === "ready" && onToggleContext && (
                <button
                  type="button"
                  onClick={() => onToggleContext(doc.id)}
                  className={cn(
                    "inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium border transition-colors",
                    inContext
                      ? "border-emerald-200 bg-emerald-50/70 text-emerald-800 hover:bg-emerald-100/70"
                      : "border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
                  )}
                  title={
                    inContext
                      ? "Active in conversation context (Click to remove from context)"
                      : "Click to include in active conversation context"
                  }
                >
                  {inContext ? (
                    <>
                      <Check className="h-3 w-3 text-emerald-600" />
                      <span>In Context</span>
                    </>
                  ) : (
                    <>
                      <Plus className="h-3 w-3" />
                      <span>Add to Context</span>
                    </>
                  )}
                </button>
              )}

              {isFailed && doc.status !== "unsupported" && onRetry && doc.file && (
                <button
                  type="button"
                  onClick={() => onRetry(doc.id)}
                  className="inline-flex items-center gap-1 rounded-md bg-white border border-red-200 px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-50 transition-colors"
                >
                  <RotateCcw className="h-3 w-3" />
                  <span>Retry</span>
                </button>
              )}
            </div>
          </div>

          {/* Progress Bar while uploading / extracting / processing */}
          {isBusy && (
            <div className="mt-2 space-y-1">
              <Progress value={doc.progress} className="h-1" />
            </div>
          )}

          {/* Error explanation */}
          {isFailed && doc.errorMessage && (
            <p className="mt-1.5 text-[11px] text-red-600 leading-snug">
              {doc.errorMessage}
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
