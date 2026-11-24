"use client";

import * as React from "react";
import {
  X,
  FileText,
  Layers,
  ShieldCheck,
  Wand2,
  GitCompare,
} from "lucide-react";
import { useComparisonStore } from "@/store/comparison-store";
import { DocumentUploader } from "@/components/documents/document-uploader";
import { VersionHistory } from "@/components/editing/version-history";
import { formatBytes } from "@/lib/utils";
import { Button } from "@/components/ui/button";

interface ContextPanelProps {
  onClose?: () => void;
}

export function ContextPanel({ onClose }: ContextPanelProps) {
  const {
    documents,
    activeContextDocIds,
    uploadFiles,
    removeDocument,
    retryDocumentUpload,
    openDocumentPreview,
    toggleDocumentInContext,
    previewDocumentId,
    maxFileSize,
    versionHistory,
    undoDocumentVersion,
    restoreDocumentVersion,
    requestDocumentEdit,
    runComparison,
    isComparing,
    isAiGenerating,
  } = useComparisonStore();

  const activeReadyDocs = documents.filter(
    (d) => activeContextDocIds.includes(d.id) && d.status === "ready"
  );

  const selectedDoc =
    documents.find((d) => d.id === previewDocumentId) ||
    activeReadyDocs[0] ||
    documents[0] ||
    null;

  const selectedDocVersions = selectedDoc
    ? versionHistory[selectedDoc.id] || []
    : [];

  return (
    <aside
      aria-label="Conversation document context panel"
      className="flex h-full w-80 flex-col border-l border-slate-200 bg-white select-none"
    >
      {/* Header */}
      <div className="flex h-13 items-center justify-between px-4 border-b border-slate-200">
        <div className="flex items-center gap-2">
          <Layers className="h-4 w-4 text-slate-600" />
          <h2 className="text-xs font-semibold text-slate-900">
            Active Context
          </h2>
          <span className="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-semibold text-slate-700">
            {activeReadyDocs.length} active
          </span>
        </div>

        {onClose && (
          <button
            type="button"
            onClick={onClose}
            className="h-7 w-7 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition-colors"
            aria-label="Close context panel"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {/* Scrollable Body */}
      <div className="flex-1 overflow-y-auto p-3.5 space-y-5">
        {/* Upload & Document Cards */}
        <DocumentUploader
          documents={documents}
          onUploadFiles={uploadFiles}
          onRemoveDocument={removeDocument}
          onRetryDocument={retryDocumentUpload}
          onPreviewDocument={(id) => openDocumentPreview(id)}
          onToggleContext={toggleDocumentInContext}
          previewDocumentId={previewDocumentId}
          maxFileSize={maxFileSize}
          compact
        />

        {/* Quick Actions when 1 or 2 Ready Documents are in Context */}
        {activeReadyDocs.length >= 2 && (
          <div className="rounded-xl border border-blue-200/80 bg-blue-50/40 p-3 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-semibold text-blue-950">
                Dual-Document Comparison Ready
              </span>
            </div>
            <p className="text-[11px] text-slate-600 leading-snug">
              <strong>{activeReadyDocs[0].name}</strong> (Doc A) and{" "}
              <strong>{activeReadyDocs[1].name}</strong> (Doc B) are active in context.
            </p>
            <Button
              type="button"
              size="sm"
              disabled={isComparing || isAiGenerating}
              onClick={() => runComparison()}
              className="w-full h-8 text-xs gap-1.5 bg-slate-900 hover:bg-slate-800 text-white"
            >
              <GitCompare className="h-3.5 w-3.5" />
              <span>Run Full Comparison</span>
            </Button>
          </div>
        )}

        {/* Currently Selected Document Metadata Details */}
        {selectedDoc && (
          <div className="space-y-2.5 pt-2 border-t border-slate-100">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 block">
              Selected Document Inspector
            </span>

            <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-3 space-y-2.5 text-xs">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <h4
                    className="font-semibold text-slate-900 truncate"
                    title={selectedDoc.name}
                  >
                    {selectedDoc.name}
                  </h4>
                  <p className="text-[11px] text-slate-500 mt-0.5">
                    {selectedDoc.slot ? `Slot: Document ${selectedDoc.slot} • ` : ""}
                    {selectedDoc.extension.toUpperCase()}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 pt-1 border-t border-slate-200/70 text-[11px]">
                <div>
                  <span className="text-slate-400 block text-[10px]">File Size</span>
                  <span className="font-mono font-medium text-slate-700">
                    {formatBytes(selectedDoc.size)}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 block text-[10px]">Pages</span>
                  <span className="font-mono font-medium text-slate-700">
                    {selectedDoc.pageCount || 1}
                  </span>
                </div>
              </div>

              {selectedDoc.status === "ready" && (
                <div className="flex items-center gap-1.5 pt-1">
                  <button
                    type="button"
                    onClick={() => openDocumentPreview(selectedDoc.id)}
                    className="flex-1 inline-flex items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                  >
                    <FileText className="h-3 w-3 text-blue-600" />
                    <span>Open Reader</span>
                  </button>
                  <button
                    type="button"
                    onClick={() =>
                      requestDocumentEdit(
                        `Propose an edit to ${selectedDoc.name} to strengthen clarity and compliance obligations`,
                        selectedDoc.id
                      )
                    }
                    disabled={isAiGenerating}
                    className="flex-1 inline-flex items-center justify-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50/70 px-2.5 py-1.5 text-[11px] font-medium text-indigo-700 hover:bg-indigo-100/70 transition-colors disabled:opacity-40"
                  >
                    <Wand2 className="h-3 w-3" />
                    <span>Propose Edit</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Version History for Selected Document */}
        {selectedDoc && selectedDocVersions.length > 0 && (
          <div className="space-y-2">
            <VersionHistory
              documentId={selectedDoc.id}
              documentName={selectedDoc.name}
              versions={selectedDocVersions}
              onUndo={undoDocumentVersion}
              onRestoreVersion={restoreDocumentVersion}
            />
          </div>
        )}
      </div>

      {/* Footer Security Notice */}
      <div className="border-t border-slate-200 p-3 bg-slate-50/50 flex items-center gap-2 text-[11px] text-slate-500">
        <ShieldCheck className="h-4 w-4 text-emerald-600 shrink-0" />
        <span className="leading-tight">
          Grounded citations only. Original files are never overwritten.
        </span>
      </div>
    </aside>
  );
}
