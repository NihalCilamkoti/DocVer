"use client";

import * as React from "react";
import { Sparkles } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";

interface StreamingIndicatorProps {
  state?:
    | "uploading"
    | "extracting"
    | "thinking"
    | "generating"
    | "comparing"
    | "creating_doc"
    | "saving_changes"
    | null;
  stageLabel?: string;
}

const STATE_MESSAGES: Record<string, string> = {
  uploading: "Uploading document to encrypted workspace...",
  extracting: "Extracting text and indexing document sections...",
  thinking: "AI is reading active context and analyzing provisions...",
  generating: "Drafting grounded response with source references...",
  comparing: "Cross-comparing Document A and Document B clauses...",
  creating_doc: "Generating redline diff and proposed document changes...",
  saving_changes: "Saving new document version snapshot...",
};

export function StreamingIndicator({
  state = "thinking",
  stageLabel,
}: StreamingIndicatorProps) {
  const message =
    stageLabel || (state && STATE_MESSAGES[state]) || STATE_MESSAGES.thinking;

  return (
    <div
      role="status"
      aria-live="polite"
      className="flex items-start gap-3 py-3 px-1 max-w-2xl"
    >
      <div className="h-7 w-7 rounded-lg bg-indigo-50 border border-indigo-200/80 text-indigo-600 flex items-center justify-center shrink-0 mt-0.5">
        <Sparkles className="h-3.5 w-3.5 animate-pulse" />
      </div>

      <div className="flex-1 space-y-2 pt-0.5">
        <div className="flex items-center gap-2 text-xs font-medium text-slate-600">
          <span className="inline-flex items-center gap-1">
            <span className="h-1.5 w-1.5 rounded-full bg-indigo-600 animate-pulse" />
            <span>{message}</span>
          </span>
        </div>

        {/* Subtle skeleton lines */}
        <div className="space-y-1.5 max-w-md">
          <Skeleton className="h-2.5 w-4/5" />
          <Skeleton className="h-2.5 w-3/5" />
        </div>
      </div>
    </div>
  );
}
