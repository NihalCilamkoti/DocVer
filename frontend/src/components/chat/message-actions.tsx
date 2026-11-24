"use client";

import * as React from "react";
import {
  Copy,
  Check,
  RotateCcw,
  ThumbsUp,
  ThumbsDown,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface MessageActionsProps {
  content: string;
  messageId: string;
  feedback?: "up" | "down" | null;
  onFeedbackChange?: (id: string, feedback: "up" | "down" | null) => void;
  onRegenerate?: (id: string) => void;
  isGenerating?: boolean;
}

export function MessageActions({
  content,
  messageId,
  feedback,
  onFeedbackChange,
  onRegenerate,
  isGenerating = false,
}: MessageActionsProps) {
  const [copied, setCopied] = React.useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(content);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div
      className="flex items-center gap-1 pt-1.5 text-slate-400"
      aria-label="Response actions"
    >
      {/* Copy Button */}
      <button
        type="button"
        onClick={handleCopy}
        className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors"
        aria-label="Copy response"
        title="Copy response to clipboard"
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

      {/* Regenerate Button */}
      {onRegenerate && (
        <button
          type="button"
          disabled={isGenerating}
          onClick={() => onRegenerate(messageId)}
          className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-40 transition-colors"
          aria-label="Regenerate response"
          title="Regenerate AI response"
        >
          <RotateCcw className="h-3 w-3" />
          <span>Regenerate</span>
        </button>
      )}

      {/* Helpful / Not Helpful Feedback Controls */}
      {onFeedbackChange && (
        <div className="flex items-center gap-0.5 ml-1 pl-1 border-l border-slate-200">
          <button
            type="button"
            onClick={() =>
              onFeedbackChange(messageId, feedback === "up" ? null : "up")
            }
            className={cn(
              "p-1 rounded-md transition-colors",
              feedback === "up"
                ? "bg-emerald-50 text-emerald-600"
                : "text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            )}
            aria-label="Mark response as helpful"
            aria-pressed={feedback === "up"}
            title="Helpful response"
          >
            <ThumbsUp className="h-3 w-3" />
          </button>

          <button
            type="button"
            onClick={() =>
              onFeedbackChange(messageId, feedback === "down" ? null : "down")
            }
            className={cn(
              "p-1 rounded-md transition-colors",
              feedback === "down"
                ? "bg-red-50 text-red-600"
                : "text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            )}
            aria-label="Mark response as not helpful"
            aria-pressed={feedback === "down"}
            title="Needs improvement"
          >
            <ThumbsDown className="h-3 w-3" />
          </button>
        </div>
      )}
    </div>
  );
}
