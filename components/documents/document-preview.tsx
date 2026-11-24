"use client";

import * as React from "react";
import {
  X,
  ZoomIn,
  ZoomOut,
  ChevronLeft,
  ChevronRight,
  Search,
  FileText,
  Sparkles,
  Copy,
  Check,
  Wand2,
} from "lucide-react";
import { DocumentUploadMeta } from "@/types/comparison";
import { formatBytes, cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { DocumentStatus } from "./document-status";

interface DocumentPreviewProps {
  document: DocumentUploadMeta | null;
  allDocuments?: DocumentUploadMeta[];
  onSelectDocument?: (docId: string) => void;
  currentPage: number;
  onPageChange: (page: number) => void;
  searchQuery: string;
  onSearchQueryChange: (query: string) => void;
  highlightText?: string | null;
  onClose: () => void;
  onRequestEdit?: (instruction: string, docId: string) => void;
}

export function DocumentPreview({
  document,
  allDocuments = [],
  onSelectDocument,
  currentPage,
  onPageChange,
  searchQuery,
  onSearchQueryChange,
  highlightText,
  onClose,
  onRequestEdit,
}: DocumentPreviewProps) {
  const [zoom, setZoom] = React.useState(100);
  const [copied, setCopied] = React.useState(false);
  const [quickEditPrompt, setQuickEditPrompt] = React.useState("");
  const [showEditBar, setShowEditBar] = React.useState(false);

  if (!document) return null;

  const pages =
    document.pages && document.pages.length > 0
      ? document.pages
      : [
          {
            page: 1,
            content:
              document.extractedText ||
              `Document "${document.name}" (${document.extension.toUpperCase()}, ${formatBytes(document.size)}) is loaded and verified in the workspace.`,
          },
        ];

  const totalPages = Math.max(pages.length, document.pageCount || 1);
  const safePage = Math.min(Math.max(1, currentPage), pages.length);
  const activePageContent = pages[safePage - 1]?.content || pages[0]?.content || "";

  // Highlight matching search query or citation snippet
  const renderHighlightedText = (text: string) => {
    const query = (searchQuery || highlightText || "").trim();
    if (!query || query.length < 2) {
      return text;
    }

    try {
      const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      const parts = text.split(new RegExp(`(${escaped})`, "gi"));
      return parts.map((part, idx) =>
        part.toLowerCase() === query.toLowerCase() ? (
          <mark
            key={idx}
            className="bg-amber-200/80 text-slate-900 px-0.5 rounded font-medium"
          >
            {part}
          </mark>
        ) : (
          part
        )
      );
    } catch {
      return text;
    }
  };

  const handleCopyText = () => {
    navigator.clipboard.writeText(activePageContent);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleQuickEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!quickEditPrompt.trim() || !onRequestEdit) return;
    onRequestEdit(quickEditPrompt.trim(), document.id);
    setQuickEditPrompt("");
    setShowEditBar(false);
  };

  // Count matches in current page
  const matchCount = React.useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    if (!q) return 0;
    return activePageContent.toLowerCase().split(q).length - 1;
  }, [activePageContent, searchQuery]);

  return (
    <div
      className="flex flex-col h-full bg-white border-l border-slate-200 overflow-hidden"
      aria-label="Document preview panel"
    >
      {/* Top Header: Document Switcher & Metadata */}
      <div className="flex items-center justify-between gap-2 px-4 py-3 border-b border-slate-200 bg-slate-50/70">
        <div className="flex items-center gap-2 min-w-0">
          <div className="h-7 w-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center shrink-0">
            <FileText className="h-4 w-4 text-blue-600" />
          </div>
          <div className="min-w-0">
            {allDocuments.length > 1 && onSelectDocument ? (
              <select
                value={document.id}
                onChange={(e) => onSelectDocument(e.target.value)}
                aria-label="Select document to preview"
                className="text-xs font-semibold text-slate-900 bg-transparent border-none focus:outline-none cursor-pointer truncate max-w-[200px]"
              >
                {allDocuments.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.slot ? `[Doc ${d.slot}] ` : ""}
                    {d.name}
                  </option>
                ))}
              </select>
            ) : (
              <h3 className="text-xs font-semibold text-slate-900 truncate" title={document.name}>
                {document.name}
              </h3>
            )}
            <div className="flex items-center gap-1.5 text-[10px] text-slate-500">
              <span className="uppercase font-mono">{document.extension}</span>
              <span>•</span>
              <span>{formatBytes(document.size)}</span>
              <span>•</span>
              <span>{totalPages} {totalPages === 1 ? "page" : "pages"}</span>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-1.5 shrink-0">
          <DocumentStatus status={document.status} compact />
          <button
            type="button"
            onClick={onClose}
            className="h-7 w-7 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-100 flex items-center justify-center transition-colors"
            aria-label="Close document preview"
            title="Collapse preview and return to full chat"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      {/* Controls Toolbar: Page Navigation, Zoom, Search, Edit */}
      <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border-b border-slate-200 bg-white text-xs">
        {/* Page Navigation */}
        <div className="flex items-center gap-1">
          <button
            type="button"
            disabled={safePage <= 1}
            onClick={() => onPageChange(Math.max(1, safePage - 1))}
            className="h-7 w-7 rounded-md border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            aria-label="Previous page"
          >
            <ChevronLeft className="h-3.5 w-3.5" />
          </button>
          <span className="px-2 text-[11px] font-mono text-slate-700">
            Page <strong>{safePage}</strong> of {pages.length}
          </span>
          <button
            type="button"
            disabled={safePage >= pages.length}
            onClick={() => onPageChange(Math.min(pages.length, safePage + 1))}
            className="h-7 w-7 rounded-md border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            aria-label="Next page"
          >
            <ChevronRight className="h-3.5 w-3.5" />
          </button>
        </div>

        {/* Zoom Controls */}
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => setZoom((z) => Math.max(75, z - 10))}
            className="h-7 w-7 rounded-md border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50"
            aria-label="Zoom out"
            title="Zoom out"
          >
            <ZoomOut className="h-3.5 w-3.5" />
          </button>
          <span className="w-11 text-center font-mono text-[11px] text-slate-600">
            {zoom}%
          </span>
          <button
            type="button"
            onClick={() => setZoom((z) => Math.min(150, z + 10))}
            className="h-7 w-7 rounded-md border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50"
            aria-label="Zoom in"
            title="Zoom in"
          >
            <ZoomIn className="h-3.5 w-3.5" />
          </button>
        </div>

        {/* Copy & Propose Edit Buttons */}
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={handleCopyText}
            className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
            title="Copy extracted page text"
          >
            {copied ? (
              <>
                <Check className="h-3 w-3 text-emerald-600" />
                <span className="text-emerald-700">Copied</span>
              </>
            ) : (
              <>
                <Copy className="h-3 w-3" />
                <span>Copy</span>
              </>
            )}
          </button>

          {onRequestEdit && (
            <button
              type="button"
              onClick={() => setShowEditBar(!showEditBar)}
              className={cn(
                "inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-medium transition-colors",
                showEditBar
                  ? "border-indigo-300 bg-indigo-50 text-indigo-700"
                  : "border-slate-200 text-slate-700 hover:bg-slate-50"
              )}
            >
              <Wand2 className="h-3 w-3 text-indigo-600" />
              <span>AI Edit</span>
            </button>
          )}
        </div>
      </div>

      {/* Search within Document Bar */}
      <div className="px-3 py-2 border-b border-slate-200 bg-slate-50/40 flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-2.5 top-2 h-3.5 w-3.5 text-slate-400" />
          <input
            type="search"
            value={searchQuery}
            onChange={(e) => onSearchQueryChange(e.target.value)}
            placeholder="Search within extracted text..."
            aria-label="Search within document"
            className="w-full rounded-md border border-slate-200 bg-white pl-8 pr-3 py-1 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none"
          />
        </div>
        {searchQuery.trim().length > 0 && (
          <span className="text-[11px] font-mono text-slate-500 shrink-0">
            {matchCount} {matchCount === 1 ? "match" : "matches"}
          </span>
        )}
      </div>

      {/* Inline AI Edit Request Bar */}
      {showEditBar && onRequestEdit && (
        <form
          onSubmit={handleQuickEditSubmit}
          className="p-2.5 border-b border-indigo-200 bg-indigo-50/60 flex items-center gap-2"
        >
          <Sparkles className="h-3.5 w-3.5 text-indigo-600 shrink-0" />
          <input
            type="text"
            value={quickEditPrompt}
            onChange={(e) => setQuickEditPrompt(e.target.value)}
            placeholder={`Describe changes to propose for ${document.name}...`}
            className="flex-1 rounded-md border border-indigo-200 bg-white px-2.5 py-1 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500"
          />
          <Button type="submit" size="sm" className="h-7 text-xs px-2.5">
            Propose Diff
          </Button>
        </form>
      )}

      {/* Citation Navigation Banner if navigated from a citation */}
      {highlightText && (
        <div className="px-3 py-1.5 bg-amber-50 border-b border-amber-200/80 flex items-center justify-between text-[11px] text-amber-900">
          <span className="truncate">
            <strong>Source Reference (Page {safePage}):</strong> &ldquo;{highlightText}&rdquo;
          </span>
        </div>
      )}

      {/* Extracted Text Reading Area */}
      <div className="flex-1 overflow-y-auto p-4 sm:p-5 bg-slate-100/60">
        <div
          className="mx-auto max-w-2xl rounded-xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-subtle transition-all"
          style={{
            fontSize: `${Math.round((13 * zoom) / 100)}px`,
            lineHeight: 1.65,
          }}
        >
          <div className="flex items-center justify-between pb-3 mb-4 border-b border-slate-100 text-[11px] text-slate-400 font-mono">
            <span>EXTRACTED TEXT VIEW • {document.name}</span>
            <span>PAGE {safePage}</span>
          </div>

          <div className="font-sans text-slate-800 whitespace-pre-wrap break-words select-text">
            {renderHighlightedText(activePageContent)}
          </div>
        </div>
      </div>
    </div>
  );
}
