"use client";

import * as React from "react";
import {
  Send,
  Paperclip,
  Square,
  X,
  CornerDownLeft,
  Wand2,
  GitCompare,
} from "lucide-react";
import { DocumentUploadMeta } from "@/types/comparison";
import { DocumentStatus } from "@/components/documents/document-status";
import { getDocumentFileIcon } from "@/components/documents/document-card";
import { formatBytes, cn } from "@/lib/utils";

interface ChatComposerProps {
  onSend: (message: string) => void;
  onUploadFiles: (files: File[]) => Promise<void>;
  onRemoveDocument: (docId: string) => void;
  onPreviewDocument?: (docId: string) => void;
  onStopGeneration?: () => void;
  onRunComparison?: () => void;
  onRequestEdit?: (instruction: string) => void;
  documents: DocumentUploadMeta[];
  disabled?: boolean;
  isGenerating?: boolean;
  placeholder?: string;
}

export function ChatComposer({
  onSend,
  onUploadFiles,
  onRemoveDocument,
  onPreviewDocument,
  onStopGeneration,
  onRunComparison,
  documents,
  disabled = false,
  isGenerating = false,
  placeholder,
}: ChatComposerProps) {
  const [content, setContent] = React.useState("");
  const [isDragging, setIsDragging] = React.useState(false);
  const textareaRef = React.useRef<HTMLTextAreaElement | null>(null);
  const fileInputRef = React.useRef<HTMLInputElement | null>(null);

  const readyDocs = documents.filter(
    (d) => d.status === "ready" && d.inActiveContext !== false
  );
  const hasUploadingDocs = documents.some(
    (d) =>
      d.status === "uploading" ||
      d.status === "uploaded" ||
      d.status === "extracting" ||
      d.status === "processing"
  );

  // Auto-resize multi-line textarea
  React.useEffect(() => {
    const el = textareaRef.current;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 180)}px`;
  }, [content]);

  const defaultPlaceholder =
    readyDocs.length >= 2
      ? `Ask about differences between ${readyDocs[0].name} and ${readyDocs[1].name}, or ask any general question...`
      : readyDocs.length === 1
      ? `Ask about ${readyDocs[0].name}, summarize it, or ask any general question...`
      : "Message DocVer AI or attach a document...";

  const handleSubmit = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (disabled || isGenerating || hasUploadingDocs) return;

    const trimmed = content.trim();
    if (!trimmed) {
      if (readyDocs.length >= 2 && onRunComparison) {
        onRunComparison();
      }
      return;
    }

    onSend(trimmed);
    setContent("");
    if (textareaRef.current) {
      textareaRef.current.style.height = "auto";
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      handleSubmit();
    }
  };

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
      await onUploadFiles(Array.from(e.dataTransfer.files));
    }
  };

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      await onUploadFiles(Array.from(e.target.files));
      e.target.value = "";
    }
  };

  return (
    <div
      onDragOver={handleDragOver}
      onDragLeave={handleDragLeave}
      onDrop={handleDrop}
      className="w-full max-w-4xl mx-auto"
    >
      <input
        ref={fileInputRef}
        type="file"
        multiple
        className="hidden"
        onChange={handleFileChange}
        accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.csv,.ppt,.pptx,.png,.jpg,.jpeg,.webp"
        aria-label="Attach documents to conversation"
      />

      <form
        onSubmit={handleSubmit}
        className={cn(
          "relative rounded-2xl border bg-white shadow-card transition-all",
          isDragging
            ? "border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/20"
            : "border-slate-200/90 focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-500/15"
        )}
      >
        {/* Drag overlay hint */}
        {isDragging && (
          <div className="absolute inset-0 z-10 rounded-2xl bg-indigo-50/90 border-2 border-dashed border-indigo-500 flex items-center justify-center text-xs font-semibold text-indigo-700">
            Drop documents here to attach to conversation
          </div>
        )}

        {/* Attachment Previews Above the Input (as shown in Screenshot 1) */}
        {documents.length > 0 && (
          <div className="flex flex-wrap items-center gap-1.5 px-3 pt-2.5 pb-1.5 border-b border-slate-100">
            {documents.map((doc) => (
              <div
                key={doc.id}
                className={cn(
                  "inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs transition-colors max-w-[260px]",
                  doc.status === "failed" || doc.status === "unsupported"
                    ? "border-red-200 bg-red-50/50 text-red-800"
                    : "border-slate-200/90 bg-slate-50/90 text-slate-800"
                )}
              >
                {doc.slot && (
                  <span
                    className={cn(
                      "h-4 px-1 rounded text-[9px] font-bold uppercase flex items-center justify-center shrink-0",
                      doc.slot === "A"
                        ? "bg-indigo-100 text-indigo-800"
                        : "bg-violet-100 text-violet-800"
                    )}
                  >
                    {doc.slot}
                  </span>
                )}
                {getDocumentFileIcon(doc.extension, "h-3.5 w-3.5 shrink-0")}
                <button
                  type="button"
                  onClick={() =>
                    doc.status === "ready" &&
                    onPreviewDocument &&
                    onPreviewDocument(doc.id)
                  }
                  className="truncate font-medium text-[11px] hover:underline hover:text-indigo-600 text-left cursor-pointer"
                  title={`${doc.name} (${formatBytes(doc.size)})`}
                >
                  {doc.name}
                </button>
                <DocumentStatus status={doc.status} compact />
                <button
                  type="button"
                  onClick={() => onRemoveDocument(doc.id)}
                  className="text-slate-400 hover:text-red-600 p-0.5 rounded shrink-0 cursor-pointer transition-colors"
                  aria-label={`Remove ${doc.name}`}
                >
                  <X className="h-3 w-3" />
                </button>
              </div>
            ))}
          </div>
        )}

        {/* Multi-line Expandable Textarea */}
        <div className="px-4 pt-3 pb-1">
          <textarea
            ref={textareaRef}
            rows={1}
            value={content}
            onChange={(e) => setContent(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder={placeholder || defaultPlaceholder}
            disabled={disabled}
            aria-label="Message input"
            className="w-full resize-none bg-transparent text-xs sm:text-[13px] text-slate-900 placeholder:text-slate-400 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 leading-relaxed max-h-[180px]"
          />
        </div>

        {/* Bottom Controls Bar */}
        <div className="flex items-center justify-between gap-2 px-3 pb-2.5 pt-1">
          <div className="flex items-center gap-2">
            {/* Attach Document Button */}
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              disabled={disabled || isGenerating}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50/80 hover:bg-indigo-50/60 hover:border-indigo-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:text-indigo-600 disabled:opacity-40 transition-colors cursor-pointer"
              aria-label="Attach document"
              title="Attach PDF, Word, Excel, CSV, PowerPoint, or Image"
            >
              <Paperclip className="h-3.5 w-3.5 text-indigo-600" />
              <span>Attach</span>
            </button>
            {readyDocs.length > 0 && (
              <span className="text-[11px] text-slate-400 font-medium">
                {readyDocs.length} {readyDocs.length === 1 ? "document" : "documents"} attached
              </span>
            )}
          </div>

          <div className="flex items-center gap-2">
            <span className="hidden md:inline-flex items-center gap-1 text-[10px] text-slate-400">
              <CornerDownLeft className="h-2.5 w-2.5" />
              <span>Enter to send</span>
            </span>

            {isGenerating ? (
              <button
                type="button"
                onClick={onStopGeneration}
                className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 transition-colors shadow-2xs cursor-pointer"
                aria-label="Stop generation"
                title="Stop AI generation"
              >
                <Square className="h-3 w-3 fill-current" />
                <span>Stop</span>
              </button>
            ) : (
              <button
                type="submit"
                disabled={
                  disabled ||
                  hasUploadingDocs ||
                  (!content.trim() && readyDocs.length < 2)
                }
                className="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-white hover:from-indigo-500 hover:to-violet-500 disabled:from-slate-200 disabled:to-slate-300 disabled:text-slate-400 transition-all shadow-md shadow-indigo-600/20 cursor-pointer"
                aria-label="Send message"
              >
                <Send className="h-3.5 w-3.5" />
              </button>
            )}
          </div>
        </div>
      </form>
    </div>
  );
}
