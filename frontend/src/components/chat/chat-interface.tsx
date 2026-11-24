"use client";

import * as React from "react";
import {
  Sparkles,
  Trash2,
  Bot,
  BookOpen,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ChatMessageItem } from "./chat-message";
import { ChatComposer } from "./chat-composer";
import { SuggestedQuestions } from "./suggested-questions";
import { StreamingIndicator } from "./streaming-indicator";
import { useComparisonStore } from "@/stores/comparison-store";
import { ChatCitation } from "@/types/chat";

export function ChatInterface() {
  const {
    chatMessages,
    isAiGenerating,
    aiProcessingState,
    stageName,
    clearChat,
    askAssistant,
    documents,
    uploadFiles,
    removeDocument,
    openDocumentPreview,
    stopGeneration,
    runComparison,
    setMessageFeedback,
    regenerateResponse,
    acceptEditProposal,
    rejectEditProposal,
    undoDocumentVersion,
    versionHistory,
  } = useComparisonStore();

  const messagesEndRef = React.useRef<HTMLDivElement | null>(null);

  React.useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [chatMessages.length, isAiGenerating]);

  // Aggregate active source citations across recent AI messages for the top ticker (matches Screenshot 1)
  const activeCitations = React.useMemo(() => {
    const list: ChatCitation[] = [];
    const seen = new Set<string>();
    for (let i = chatMessages.length - 1; i >= 0; i--) {
      const msg = chatMessages[i];
      if (msg.sender === "ai" && msg.citations && msg.citations.length > 0) {
        for (const c of msg.citations) {
          const key = `${c.docLabel}_${c.page}`;
          if (!seen.has(key)) {
            seen.add(key);
            list.push(c);
          }
        }
      }
    }
    return list.slice(0, 10);
  }, [chatMessages]);

  const handleSendMessage = async (text: string) => {
    await askAssistant(text);
  };

  return (
    <div className="flex flex-col h-[calc(100vh-210px)] min-h-[640px] rounded-2xl border border-slate-200/90 bg-white shadow-card overflow-hidden">
      {/* Top Citations Ticker (as shown in Screenshot 1) */}
      {activeCitations.length > 0 && (
        <div className="flex items-center gap-2 border-b border-indigo-100/80 bg-gradient-to-r from-indigo-50/40 via-slate-50/50 to-white px-4 py-1.5 text-xs overflow-x-auto whitespace-nowrap scrollbar-thin">
          <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider shrink-0 flex items-center gap-1">
            <BookOpen className="h-3 w-3 text-indigo-600" />
            Citations:
          </span>
          <div className="flex items-center gap-1.5">
            {activeCitations.map((c, idx) => (
              <button
                key={idx}
                type="button"
                onClick={() =>
                  openDocumentPreview(
                    String(c.documentId || c.docLabel),
                    c.page,
                    c.quote
                  )
                }
                className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white/90 px-2 py-0.5 text-[11px] font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-600 hover:bg-indigo-50/40 transition-colors shadow-2xs cursor-pointer"
                title={c.quote || `Page ${c.page}`}
              >
                <span>{c.docLabel}</span>
                <span className="text-slate-400 font-mono">(p. {c.page})</span>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Message List with Smooth Vertical Scrolling */}
      <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-slate-50/30">
        {chatMessages.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-center p-6 space-y-4 text-slate-500">
            <div className="h-13 w-13 rounded-2xl bg-gradient-to-tr from-indigo-50 to-violet-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shadow-xs">
              <Bot className="h-7 w-7" />
            </div>
            <div className="max-w-md space-y-1.5">
              <h4 className="text-base font-bold text-slate-900">
                Hi! I&apos;m DocVer AI.
              </h4>
              <p className="text-xs text-slate-500 leading-relaxed">
                I can answer questions, help with everyday tasks, and work with your documents. What would you like to do?
              </p>
            </div>
            <div className="w-full max-w-lg pt-2">
              <SuggestedQuestions
                onSelect={handleSendMessage}
                disabled={isAiGenerating}
              />
            </div>
          </div>
        ) : (
          <>
            {chatMessages.map((msg) => (
              <ChatMessageItem
                key={msg.id}
                message={msg}
                onFollowUpClick={handleSendMessage}
                onCitationClick={(cite) =>
                  openDocumentPreview(
                    String(cite.documentId || cite.docLabel),
                    cite.page,
                    cite.quote
                  )
                }
                onFeedbackChange={setMessageFeedback}
                onRegenerate={regenerateResponse}
                onAcceptEdit={acceptEditProposal}
                onRejectEdit={rejectEditProposal}
                onUndoEdit={undoDocumentVersion}
                canUndoForDoc={(docId) =>
                  (versionHistory[docId]?.length || 0) > 1
                }
                versionCountForDoc={(docId) =>
                  versionHistory[docId]?.length || 1
                }
                isGenerating={isAiGenerating}
              />
            ))}

            {isAiGenerating && (
              <StreamingIndicator
                state={aiProcessingState}
                stageLabel={stageName || undefined}
              />
            )}
            <div ref={messagesEndRef} />
          </>
        )}
      </div>

      {/* Docked Composer Input Area */}
      <div className="p-3 sm:p-4 border-t border-slate-200/80 bg-white">
        <ChatComposer
          onSend={handleSendMessage}
          onUploadFiles={uploadFiles}
          onRemoveDocument={removeDocument}
          onPreviewDocument={(id) => openDocumentPreview(id)}
          onStopGeneration={stopGeneration}
          onRunComparison={() => runComparison()}
          documents={documents}
          isGenerating={isAiGenerating}
        />
      </div>
    </div>
  );
}
