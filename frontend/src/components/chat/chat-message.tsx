"use client";

import * as React from "react";
import { Sparkles, User, CornerDownRight, AlertCircle, Clock, RotateCcw } from "lucide-react";
import { ChatMessage as ChatMessageType, ChatCitation } from "@/types/chat";
import { AnalysisResult } from "@/components/analysis/analysis-result";
import { ComparisonTable } from "@/components/analysis/comparison-table";
import { ChangeReview } from "@/components/editing/change-review";
import { MessageActions } from "./message-actions";

interface ChatMessageProps {
  message: ChatMessageType;
  onFollowUpClick?: (followUp: string) => void;
  onCitationClick?: (citation: ChatCitation) => void;
  onNavigateToPage?: (slot: "A" | "B", page: number, quote?: string) => void;
  onFeedbackChange?: (id: string, feedback: "up" | "down" | null) => void;
  onRegenerate?: (id: string) => void;
  onAcceptEdit?: (proposalId: string) => void;
  onRejectEdit?: (proposalId: string) => void;
  onUndoEdit?: (documentId: string) => void;
  canUndoForDoc?: (documentId: string) => boolean;
  versionCountForDoc?: (documentId: string) => number;
  isGenerating?: boolean;
}

export function ChatMessageItem({
  message,
  onFollowUpClick,
  onCitationClick,
  onNavigateToPage,
  onFeedbackChange,
  onRegenerate,
  onAcceptEdit,
  onRejectEdit,
  onUndoEdit,
  canUndoForDoc,
  versionCountForDoc,
  isGenerating = false,
}: ChatMessageProps) {
  const isAi = message.sender === "ai";

  if (!isAi) {
    return (
      <div className="flex justify-end py-2">
        <div className="flex items-start gap-2.5 max-w-[85%] sm:max-w-[75%]">
          <div className="rounded-2xl rounded-tr-sm bg-gradient-to-r from-indigo-600 to-violet-600 text-white px-4 py-2.5 text-xs sm:text-[13px] leading-relaxed shadow-sm shadow-indigo-600/20 break-words">
            {message.content}
          </div>
          <div className="h-7 w-7 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center shrink-0 mt-0.5">
            <User className="h-3.5 w-3.5" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex items-start gap-3 py-3 group">
      {/* AI Avatar */}
      <div className="h-7 w-7 rounded-lg bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
        <Sparkles className="h-3.5 w-3.5" />
      </div>

      {/* Main AI Response Body */}
      <div className="flex-1 min-w-0 space-y-3">
        <div className="flex items-center gap-2">
          <span className="text-xs font-bold text-slate-900">DocVer AI Assistant</span>
          {message.provider && (
            <span className="rounded bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 font-mono text-[10px] text-indigo-700 uppercase font-semibold">
              {message.provider}
            </span>
          )}
        </div>

        {/* Friendly AI Quota Limit Card */}
        {message.isQuotaError ? (
          <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4 space-y-3 text-slate-800 shadow-2xs">
            <div className="flex items-center gap-2 text-amber-800 font-semibold text-xs">
              <AlertCircle className="h-4 w-4 text-amber-600" />
              <span>AI Usage Notice</span>
              {message.quotaDetails?.type && (
                <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-mono uppercase text-amber-700">
                  {message.quotaDetails.type.replace(/_/g, " ")}
                </span>
              )}
            </div>
            <p className="text-xs leading-relaxed text-slate-700 whitespace-pre-wrap">
              {message.content}
            </p>
            <div className="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-slate-600">
              {message.quotaDetails?.remaining_time_human && (
                <span className="inline-flex items-center gap-1 rounded bg-white px-2 py-1 border border-amber-200/80 font-medium text-amber-900">
                  <Clock className="h-3 w-3 text-amber-600" />
                  Estimated wait: {message.quotaDetails.remaining_time_human}
                </span>
              )}
              {message.quotaDetails?.resets_at_human && (
                <span className="inline-flex items-center gap-1 rounded bg-white px-2 py-1 border border-slate-200 text-slate-600">
                  Resets: {message.quotaDetails.resets_at_human}
                </span>
              )}
              {message.quotaDetails?.retry_allowed && onRegenerate && (
                <button
                  type="button"
                  onClick={() => onRegenerate(message.id)}
                  disabled={isGenerating}
                  className="inline-flex items-center gap-1 rounded-md bg-amber-600 px-2.5 py-1 text-white hover:bg-amber-700 font-medium transition-colors cursor-pointer"
                >
                  <RotateCcw className="h-3 w-3" />
                  <span>Retry Request</span>
                </button>
              )}
            </div>
          </div>
        ) : (
          /* Structured Markdown & Citation Presentation */
          <AnalysisResult
            content={message.content}
            citations={message.citations}
            onCitationClick={onCitationClick}
          />
        )}

        {/* Embedded Dedicated Comparison Presentation Table when message includes a ComparisonJob */}
        {message.comparisonJob && (
          <div className="pt-2">
            <ComparisonTable
              job={message.comparisonJob}
              onNavigateToPage={onNavigateToPage}
            />
          </div>
        )}

        {/* Embedded Document Edit Diff & Change Review when message includes an EditProposal */}
        {message.editProposal && onAcceptEdit && onRejectEdit && (
          <div className="pt-2">
            <ChangeReview
              proposal={message.editProposal}
              onAccept={onAcceptEdit}
              onReject={onRejectEdit}
              onUndo={onUndoEdit}
              canUndo={
                canUndoForDoc
                  ? canUndoForDoc(message.editProposal.documentId)
                  : false
              }
              versionCount={
                versionCountForDoc
                  ? versionCountForDoc(message.editProposal.documentId)
                  : 1
              }
            />
          </div>
        )}

        {/* Follow-up Suggestion Pills */}
        {message.suggestedFollowUps && message.suggestedFollowUps.length > 0 && (
          <div className="pt-2 flex flex-wrap items-center gap-1.5">
            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 mr-1">
              <CornerDownRight className="h-3 w-3 text-indigo-500" />
              <span>Follow-ups:</span>
            </span>
            {message.suggestedFollowUps.map((fu, idx) => (
              <button
                key={idx}
                type="button"
                disabled={isGenerating}
                onClick={() => onFollowUpClick && onFollowUpClick(fu)}
                className="rounded-lg border border-indigo-100 bg-white px-2.5 py-1 text-[11px] font-medium text-indigo-700 hover:border-indigo-300 hover:bg-indigo-50/50 transition-colors disabled:opacity-40 text-left shadow-2xs cursor-pointer"
              >
                {fu}
              </button>
            ))}
          </div>
        )}

        {/* Copy, Regenerate, and Feedback Controls */}
        <MessageActions
          content={message.content}
          messageId={message.id}
          feedback={message.feedback}
          onFeedbackChange={onFeedbackChange}
          onRegenerate={onRegenerate}
          isGenerating={isGenerating}
        />
      </div>
    </div>
  );
}
