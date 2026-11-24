"use client";

import * as React from "react";
import { Columns2, AlignLeft } from "lucide-react";
import { cn } from "@/lib/utils";

interface DocumentDiffProps {
  originalText: string;
  proposedText: string;
  defaultMode?: "inline" | "split";
}

interface WordToken {
  type: "same" | "added" | "removed";
  value: string;
}

function computeWordDiff(original: string, proposed: string): WordToken[] {
  const origWords = original.split(/(\s+)/);
  const propWords = proposed.split(/(\s+)/);

  let start = 0;
  while (
    start < origWords.length &&
    start < propWords.length &&
    origWords[start] === propWords[start]
  ) {
    start++;
  }

  let endOrig = origWords.length - 1;
  let endProp = propWords.length - 1;
  while (
    endOrig >= start &&
    endProp >= start &&
    origWords[endOrig] === propWords[endProp]
  ) {
    endOrig--;
    endProp--;
  }

  const tokens: WordToken[] = [];

  if (start > 0) {
    tokens.push({
      type: "same",
      value: origWords.slice(0, start).join(""),
    });
  }

  const removedSegment = origWords.slice(start, endOrig + 1).join("");
  if (removedSegment.trim().length > 0) {
    tokens.push({
      type: "removed",
      value: removedSegment,
    });
  }

  const addedSegment = propWords.slice(start, endProp + 1).join("");
  if (addedSegment.trim().length > 0) {
    tokens.push({
      type: "added",
      value: addedSegment,
    });
  }

  if (endOrig + 1 < origWords.length) {
    tokens.push({
      type: "same",
      value: origWords.slice(endOrig + 1).join(""),
    });
  }

  return tokens;
}

export function DocumentDiff({
  originalText,
  proposedText,
  defaultMode = "split",
}: DocumentDiffProps) {
  const [viewMode, setViewMode] = React.useState<"inline" | "split">(defaultMode);
  const diffTokens = React.useMemo(
    () => computeWordDiff(originalText, proposedText),
    [originalText, proposedText]
  );

  return (
    <div className="space-y-2.5">
      {/* Mode Switcher Bar */}
      <div className="flex items-center justify-between text-[11px] text-slate-500">
        <div className="flex items-center gap-3">
          <span className="inline-flex items-center gap-1">
            <span className="h-2 w-2 rounded-xs bg-red-500/80" />
            <span>Removed</span>
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="h-2 w-2 rounded-xs bg-emerald-500/80" />
            <span>Added</span>
          </span>
        </div>

        <div className="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 p-0.5">
          <button
            type="button"
            onClick={() => setViewMode("split")}
            className={cn(
              "inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-medium transition-colors",
              viewMode === "split"
                ? "bg-white text-slate-900 shadow-2xs"
                : "text-slate-600 hover:text-slate-900"
            )}
          >
            <Columns2 className="h-3 w-3" />
            <span>Side-by-Side</span>
          </button>
          <button
            type="button"
            onClick={() => setViewMode("inline")}
            className={cn(
              "inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-medium transition-colors",
              viewMode === "inline"
                ? "bg-white text-slate-900 shadow-2xs"
                : "text-slate-600 hover:text-slate-900"
            )}
          >
            <AlignLeft className="h-3 w-3" />
            <span>Unified Diff</span>
          </button>
        </div>
      </div>

      {viewMode === "split" ? (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
          {/* Original Content */}
          <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3 space-y-1.5">
            <div className="flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-200/80 pb-1">
              <span>Original Content (Preserved)</span>
              <span className="font-mono text-red-600">− Baseline</span>
            </div>
            <div className="text-slate-700 leading-relaxed whitespace-pre-wrap select-text font-sans">
              {diffTokens.map((tok, idx) => {
                if (tok.type === "added") return null;
                if (tok.type === "removed") {
                  return (
                    <del
                      key={idx}
                      className="bg-red-100/90 text-red-900 px-1 py-0.5 rounded-xs line-through decoration-red-400"
                    >
                      {tok.value}
                    </del>
                  );
                }
                return <span key={idx}>{tok.value}</span>;
              })}
            </div>
          </div>

          {/* Proposed Modified Content */}
          <div className="rounded-lg border border-emerald-200/90 bg-emerald-50/25 p-3 space-y-1.5">
            <div className="flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-emerald-800 border-b border-emerald-200/60 pb-1">
              <span>Proposed Modified Content</span>
              <span className="font-mono text-emerald-700">+ Revision</span>
            </div>
            <div className="text-slate-900 leading-relaxed whitespace-pre-wrap select-text font-sans">
              {diffTokens.map((tok, idx) => {
                if (tok.type === "removed") return null;
                if (tok.type === "added") {
                  return (
                    <ins
                      key={idx}
                      className="bg-emerald-100/90 text-emerald-950 px-1 py-0.5 rounded-xs no-underline font-medium border-b border-emerald-400"
                    >
                      {tok.value}
                    </ins>
                  );
                }
                return <span key={idx}>{tok.value}</span>;
              })}
            </div>
          </div>
        </div>
      ) : (
        <div className="rounded-lg border border-slate-200 bg-white p-3.5 text-xs leading-relaxed whitespace-pre-wrap select-text font-sans">
          {diffTokens.map((tok, idx) => {
            if (tok.type === "removed") {
              return (
                <del
                  key={idx}
                  className="bg-red-100 text-red-900 px-1 py-0.5 rounded-xs line-through mr-1"
                >
                  {tok.value}
                </del>
              );
            }
            if (tok.type === "added") {
              return (
                <ins
                  key={idx}
                  className="bg-emerald-100 text-emerald-950 px-1 py-0.5 rounded-xs no-underline font-medium"
                >
                  {tok.value}
                </ins>
              );
            }
            return <span key={idx}>{tok.value}</span>;
          })}
        </div>
      )}
    </div>
  );
}
