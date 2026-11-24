"use client";

import * as React from "react";
import { useSearchParams } from "next/navigation";
import {
  FileDiff,
  RotateCcw,
  Sparkles,
  MessageSquare,
  LayoutGrid,
  Columns,
  Paperclip,
  X,
  FileText,
  AlertCircle,
  Loader2,
  CheckCircle2,
  Trash2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { ComparisonResults } from "@/components/reports/comparison-results";
import { ChatInterface } from "@/components/chat/chat-interface";
import { useComparisonStore } from "@/stores/comparison-store";
import { getDocumentFileIcon } from "@/components/documents/document-card";
import { formatBytes } from "@/lib/utils";

function CompareWorkspaceContent() {
  const searchParams = useSearchParams();
  const initialJobId = searchParams.get("id");
  const fileInputRef = React.useRef<HTMLInputElement | null>(null);

  const {
    docA,
    docB,
    setDocA,
    setDocB,
    documents,
    uploadFiles,
    removeDocument,
    openDocumentPreview,
    isComparing,
    stageName,
    stageProgress,
    activeComparison,
    comparisonError,
    loadComparison,
    resetWorkspace,
    chatMessages,
    clearChat,
  } = useComparisonStore();

  const [workspaceTab, setWorkspaceTab] = React.useState<"chat" | "results" | "split">("chat");

  // Load comparison if passed via URL parameter
  React.useEffect(() => {
    if (initialJobId && (!activeComparison || String(activeComparison.id) !== String(initialJobId))) {
      loadComparison(initialJobId);
    }
  }, [initialJobId, activeComparison, loadComparison]);

  // When a comparison job finishes or is loaded from URL, keep chat accessible but allow switching
  const readyDocs = documents.filter(
    (d) => d.status === "ready" && d.inActiveContext !== false
  );

  const handleHeaderFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      await uploadFiles(Array.from(e.target.files));
      e.target.value = "";
    }
  };

  return (
    <div className="space-y-4 max-w-7xl mx-auto pb-4">
      <input
        ref={fileInputRef}
        type="file"
        multiple
        className="hidden"
        onChange={handleHeaderFileUpload}
        accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.csv,.ppt,.pptx,.png,.jpg,.jpeg,.webp"
        aria-label="Upload documents"
      />

      {/* Top Workspace Header Bar - Spacious Unified Header */}
      <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-card">
        <div className="flex items-center gap-3.5">
          <div className="h-10 w-10 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/25 shrink-0">
            <Sparkles className="h-5 w-5" />
          </div>
          <div className="space-y-0.5 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <h1 className="text-lg sm:text-xl font-bold tracking-tight text-slate-900 leading-snug">
                DocVer AI Assistant
              </h1>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200/80 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">
                <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                Online
              </span>
            </div>
            <p className="text-xs text-slate-500 max-w-xl">
              Ask questions, explore ideas, and work with your documents using AI.
            </p>
          </div>
        </div>

        {/* View Switcher & Actions */}
        <div className="flex flex-wrap items-center gap-2 shrink-0">
          {/* Segmented Mode Switcher */}
          <div className="flex items-center bg-slate-100/90 p-1 rounded-xl border border-slate-200/60 shadow-2xs">
            <button
              type="button"
              onClick={() => setWorkspaceTab("chat")}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                workspaceTab === "chat"
                  ? "bg-white text-indigo-700 shadow-xs border border-indigo-100"
                  : "text-slate-600 hover:text-slate-900"
              }`}
            >
              <MessageSquare className="h-3.5 w-3.5" />
              <span>AI Chatbot</span>
            </button>

            <button
              type="button"
              onClick={() => setWorkspaceTab("results")}
              disabled={!activeComparison}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                workspaceTab === "results"
                  ? "bg-white text-indigo-700 shadow-xs border border-indigo-100"
                  : activeComparison
                  ? "text-slate-600 hover:text-slate-900"
                  : "text-slate-400 opacity-60 cursor-not-allowed"
              }`}
            >
              <LayoutGrid className="h-3.5 w-3.5" />
              <span>Verification Report</span>
              {activeComparison && (
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
              )}
            </button>

            <button
              type="button"
              onClick={() => setWorkspaceTab("split")}
              disabled={!activeComparison}
              className={`hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                workspaceTab === "split"
                  ? "bg-white text-indigo-700 shadow-xs border border-indigo-100"
                  : activeComparison
                  ? "text-slate-600 hover:text-slate-900"
                  : "text-slate-400 opacity-60 cursor-not-allowed"
              }`}
            >
              <Columns className="h-3.5 w-3.5" />
              <span>Split View</span>
            </button>
          </div>

          {/* Attach Document Quick Button */}
          <Button
            variant="outline"
            size="sm"
            onClick={() => fileInputRef.current?.click()}
            className="text-xs gap-1.5 h-8 border-slate-200/90 text-slate-700 hover:text-indigo-600 hover:border-indigo-300"
          >
            <Paperclip className="h-3.5 w-3.5 text-indigo-600" />
            <span>Attach File</span>
          </Button>

          {/* Clear Chat Button when in chat tab and messages exist */}
          {workspaceTab === "chat" && chatMessages.length > 0 && (
            <Button
              variant="ghost"
              size="sm"
              onClick={clearChat}
              className="text-xs gap-1.5 h-8 text-slate-500 hover:text-red-600 hover:bg-red-50/50"
              title="Clear conversation messages"
            >
              <Trash2 className="h-3.5 w-3.5" />
              <span>Clear</span>
            </Button>
          )}

          {/* Reset Workspace */}
          {(docA || docB || documents.length > 0 || activeComparison) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={resetWorkspace}
              className="text-xs gap-1.5 h-8 text-slate-500 hover:text-red-600 hover:bg-red-50/50"
              title="Reset all attached documents and active comparison"
            >
              <RotateCcw className="h-3.5 w-3.5" />
              <span>Reset</span>
            </Button>
          )}
        </div>
      </div>

      {/* Compact Attached Document Badges Bar */}
      {documents.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 px-1">
          <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 flex items-center gap-1">
            <FileText className="h-3 w-3 text-indigo-600" />
            Attached Documents ({readyDocs.length} ready):
          </span>
          {documents.map((doc) => (
            <div
              key={doc.id}
              className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200/90 bg-white px-2.5 py-1 text-xs text-slate-800 shadow-2xs hover:border-indigo-200 transition-colors"
            >
              {doc.slot && (
                <span
                  className={`h-4 px-1 rounded text-[9px] font-bold uppercase flex items-center justify-center shrink-0 ${
                    doc.slot === "A"
                      ? "bg-indigo-100 text-indigo-800"
                      : "bg-violet-100 text-violet-800"
                  }`}
                >
                  Slot {doc.slot}
                </span>
              )}
              {getDocumentFileIcon(doc.extension, "h-3.5 w-3.5 shrink-0 text-slate-500")}
              <button
                type="button"
                onClick={() => doc.status === "ready" && openDocumentPreview(doc.id)}
                className="font-medium text-[11px] hover:text-indigo-600 hover:underline max-w-[200px] truncate"
                title={`${doc.name} (${formatBytes(doc.size)})`}
              >
                {doc.name}
              </button>
              {doc.status === "ready" ? (
                <span className="inline-flex items-center gap-0.5 text-[10px] text-emerald-600 font-medium">
                  <CheckCircle2 className="h-3 w-3" />
                  Ready
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 text-[10px] text-amber-600 font-medium">
                  <Loader2 className="h-3 w-3 animate-spin" />
                  {doc.status}
                </span>
              )}
              <button
                type="button"
                onClick={() => removeDocument(doc.id)}
                className="text-slate-400 hover:text-red-600 p-0.5 rounded cursor-pointer transition-colors"
                title={`Remove ${doc.name}`}
              >
                <X className="h-3 w-3" />
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Progress or Error Alerts */}
      {isComparing && (
        <div className="rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50/90 via-violet-50/80 to-white p-3.5 space-y-2 shadow-2xs">
          <div className="flex items-center justify-between text-xs font-semibold text-indigo-950">
            <span className="flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin text-indigo-600" />
              {stageName || "Analyzing documents with AI..."}
            </span>
            <span className="font-mono text-indigo-700">{stageProgress}%</span>
          </div>
          <div className="h-1.5 w-full bg-indigo-200/60 rounded-full overflow-hidden">
            <div
              className="h-full bg-gradient-to-r from-indigo-600 to-violet-600 rounded-full transition-all duration-300"
              style={{ width: `${stageProgress}%` }}
            />
          </div>
        </div>
      )}

      {comparisonError && (
        <div className="rounded-xl border border-red-200 bg-red-50/90 p-3.5 text-xs text-red-900 flex items-center gap-2 shadow-2xs">
          <AlertCircle className="h-4 w-4 text-red-600 shrink-0" />
          <span className="font-medium flex-1">{comparisonError}</span>
        </div>
      )}

      {/* Main Workspace Display Views */}
      {workspaceTab === "chat" && (
        <div className="w-full">
          <ChatInterface />
        </div>
      )}

      {workspaceTab === "results" && activeComparison && (
        <div className="space-y-4">
          <ComparisonResults job={activeComparison} />
        </div>
      )}

      {workspaceTab === "split" && activeComparison && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
          <div className="lg:col-span-7 space-y-4">
            <ComparisonResults job={activeComparison} />
          </div>
          <div className="lg:col-span-5 sticky top-16">
            <ChatInterface />
          </div>
        </div>
      )}
    </div>
  );
}

export default function CompareWorkspacePage() {
  return (
    <React.Suspense
      fallback={
        <div className="p-12 text-center text-xs text-slate-500 flex items-center justify-center gap-2">
          <Loader2 className="h-4 w-4 animate-spin text-indigo-600" />
          <span>Loading DocVer AI Workspace...</span>
        </div>
      }
    >
      <CompareWorkspaceContent />
    </React.Suspense>
  );
}
