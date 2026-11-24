"use client";

import * as React from "react";
import {
  ChevronDown,
  ChevronUp,
  FileText,
  ArrowRight,
  Copy,
  Check,
  Eye,
  AlertCircle,
} from "lucide-react";
import { ClauseComparisonItem } from "@/types/comparison";
import { RiskBadge } from "@/components/reports/risk-badge";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

interface FindingCardProps {
  finding: ClauseComparisonItem;
  docAName?: string;
  docBName?: string;
  onNavigateToPage?: (slot: "A" | "B", page: number, quote?: string) => void;
  defaultExpanded?: boolean;
}

export function FindingCard({
  finding,
  docAName = "Document A",
  docBName = "Document B",
  onNavigateToPage,
  defaultExpanded = false,
}: FindingCardProps) {
  const [expanded, setExpanded] = React.useState(defaultExpanded);
  const [copiedSide, setCopiedSide] = React.useState<"A" | "B" | null>(null);

  const isMissing = finding.type === "missing";
  const isAdded = finding.type === "added";
  const isMatching = finding.type === "matching";

  const hasSufficientEvidence =
    Boolean(finding.docAContent && finding.docAContent.trim().length > 8) ||
    Boolean(finding.docBContent && finding.docBContent.trim().length > 8);

  const handleCopy = (text: string, side: "A" | "B", e: React.MouseEvent) => {
    e.stopPropagation();
    navigator.clipboard.writeText(text);
    setCopiedSide(side);
    setTimeout(() => setCopiedSide(null), 1800);
  };

  const getTypeBadge = () => {
    if (!hasSufficientEvidence && !isMatching) {
      return (
        <span className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-mono font-medium text-slate-600">
          Unconfirmed — Insufficient Source Evidence
        </span>
      );
    }

    switch (finding.type) {
      case "missing":
        return (
          <span className="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-mono font-semibold text-red-700 uppercase">
            Removed / Missing
          </span>
        );
      case "added":
        return (
          <span className="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-mono font-semibold text-emerald-700 uppercase">
            Added
          </span>
        );
      case "matching":
        return (
          <span className="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-mono font-medium text-slate-600 uppercase">
            Matching
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-mono font-semibold text-amber-800 uppercase">
            Modified
          </span>
        );
    }
  };

  return (
    <div className="rounded-xl border border-slate-200/90 bg-white shadow-subtle transition-all overflow-hidden">
      {/* Summary Header Row (Clickable to Expand/Collapse) */}
      <button
        type="button"
        onClick={() => setExpanded(!expanded)}
        aria-expanded={expanded}
        className="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 px-4 py-3 text-left hover:bg-slate-50/70 transition-colors"
      >
        <div className="flex flex-wrap items-center gap-2 min-w-0">
          {getTypeBadge()}
          <RiskBadge level={finding.riskLevel} className="text-[10px] py-0 px-2" />
          <Badge variant="outline" className="text-[10px] font-normal text-slate-500">
            {finding.category}
          </Badge>
          <span className="text-xs font-semibold text-slate-900 truncate">
            {finding.title}
          </span>
        </div>

        <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
          <span className="inline-flex items-center gap-1 font-mono text-[11px] text-slate-500 bg-slate-100/80 px-2 py-0.5 rounded">
            <FileText className="h-3 w-3 text-slate-500" />
            <span>A p.{finding.docAPage}</span>
            <ArrowRight className="h-2.5 w-2.5 text-slate-400" />
            <span>B p.{finding.docBPage}</span>
          </span>
          {expanded ? (
            <ChevronUp className="h-4 w-4 text-slate-400" />
          ) : (
            <ChevronDown className="h-4 w-4 text-slate-400" />
          )}
        </div>
      </button>

      {/* Brief Explanation always visible */}
      <div className="px-4 pb-3 text-xs text-slate-600 leading-relaxed border-b border-slate-100">
        {finding.explanation}
      </div>

      {/* Expandable Side-by-Side Evidence & Clauses */}
      {expanded && (
        <div className="p-4 bg-slate-50/40 space-y-3">
          {!hasSufficientEvidence && !isMatching && (
            <div className="rounded-lg border border-slate-200 bg-white p-2.5 text-[11px] text-slate-600 flex items-center gap-2">
              <AlertCircle className="h-3.5 w-3.5 text-slate-500 shrink-0" />
              <span>
                Note: Direct verbatim clause match was not confirmed in both documents. Inspect surrounding context before relying on this variance.
              </span>
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
            {/* Document A Box */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 space-y-2 flex flex-col justify-between">
              <div className="space-y-1.5">
                <div className="flex items-center justify-between border-b border-slate-100 pb-1.5">
                  <span className="text-[11px] font-semibold text-slate-700 flex items-center gap-1.5 truncate">
                    <span className="h-2 w-2 rounded-full bg-blue-600 shrink-0" />
                    <span className="truncate">{docAName}</span>
                    <span className="font-mono text-[10px] text-slate-400 shrink-0">
                      (Page {finding.docAPage})
                    </span>
                  </span>
                  <div className="flex items-center gap-1.5 shrink-0">
                    {onNavigateToPage && (
                      <button
                        type="button"
                        onClick={() =>
                          onNavigateToPage("A", finding.docAPage, finding.docAContent.slice(0, 80))
                        }
                        className="text-[10px] text-blue-600 hover:underline inline-flex items-center gap-0.5"
                      >
                        <Eye className="h-2.5 w-2.5" />
                        <span>View</span>
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={(e) => handleCopy(finding.docAContent, "A", e)}
                      className="text-[10px] text-slate-500 hover:text-slate-800 inline-flex items-center gap-0.5"
                    >
                      {copiedSide === "A" ? (
                        <Check className="h-2.5 w-2.5 text-emerald-600" />
                      ) : (
                        <Copy className="h-2.5 w-2.5" />
                      )}
                    </button>
                  </div>
                </div>
                <p
                  className={cn(
                    "text-xs leading-relaxed whitespace-pre-line select-text",
                    isMissing
                      ? "bg-red-50/60 text-red-950 px-2 py-1 rounded border-l-2 border-red-400"
                      : "text-slate-800"
                  )}
                >
                  {finding.docAContent}
                </p>
              </div>
            </div>

            {/* Document B Box */}
            <div className="rounded-lg border border-slate-200 bg-white p-3 space-y-2 flex flex-col justify-between">
              <div className="space-y-1.5">
                <div className="flex items-center justify-between border-b border-slate-100 pb-1.5">
                  <span className="text-[11px] font-semibold text-slate-700 flex items-center gap-1.5 truncate">
                    <span className="h-2 w-2 rounded-full bg-indigo-600 shrink-0" />
                    <span className="truncate">{docBName}</span>
                    <span className="font-mono text-[10px] text-slate-400 shrink-0">
                      (Page {finding.docBPage})
                    </span>
                  </span>
                  <div className="flex items-center gap-1.5 shrink-0">
                    {onNavigateToPage && (
                      <button
                        type="button"
                        onClick={() =>
                          onNavigateToPage("B", finding.docBPage, finding.docBContent.slice(0, 80))
                        }
                        className="text-[10px] text-indigo-600 hover:underline inline-flex items-center gap-0.5"
                      >
                        <Eye className="h-2.5 w-2.5" />
                        <span>View</span>
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={(e) => handleCopy(finding.docBContent, "B", e)}
                      className="text-[10px] text-slate-500 hover:text-slate-800 inline-flex items-center gap-0.5"
                    >
                      {copiedSide === "B" ? (
                        <Check className="h-2.5 w-2.5 text-emerald-600" />
                      ) : (
                        <Copy className="h-2.5 w-2.5" />
                      )}
                    </button>
                  </div>
                </div>
                <p
                  className={cn(
                    "text-xs leading-relaxed whitespace-pre-line select-text",
                    isMissing
                      ? "italic text-red-700"
                      : isAdded
                      ? "bg-emerald-50/70 text-emerald-950 px-2 py-1 rounded border-l-2 border-emerald-500"
                      : !isMatching
                      ? "bg-amber-50/50 text-slate-900 px-2 py-1 rounded border-l-2 border-amber-400"
                      : "text-slate-800"
                  )}
                >
                  {finding.docBContent}
                </p>
              </div>
            </div>
          </div>

          {finding.significance && (
            <div className="text-[11px] text-slate-600 pt-1 flex items-start gap-1.5">
              <strong className="text-slate-800 shrink-0">Significance:</strong>
              <span>{finding.significance}</span>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
