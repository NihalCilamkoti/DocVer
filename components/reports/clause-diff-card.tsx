"use client";

import * as React from "react";
import { ClauseComparisonItem } from "@/types/comparison";
import { RiskBadge } from "./risk-badge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EvidenceModal } from "./evidence-modal";
import {
  FileText,
  ArrowRight,
  Eye,
  Copy,
  Check,
  ChevronDown,
  ChevronUp,
  AlertCircle,
  FileCheck2,
} from "lucide-react";

interface ClauseDiffCardProps {
  clause: ClauseComparisonItem;
}

export function ClauseDiffCard({ clause }: ClauseDiffCardProps) {
  const [evidenceOpen, setEvidenceOpen] = React.useState(false);
  const [expandedA, setExpandedA] = React.useState(false);
  const [expandedB, setExpandedB] = React.useState(false);
  const [copiedA, setCopiedA] = React.useState(false);
  const [copiedB, setCopiedB] = React.useState(false);

  const isMissing = clause.type === "missing";
  const isMatching = clause.type === "matching";
  const isAdded = clause.type === "added";

  const handleCopy = (text: string, isDocA: boolean) => {
    navigator.clipboard.writeText(text);
    if (isDocA) {
      setCopiedA(true);
      setTimeout(() => setCopiedA(false), 2000);
    } else {
      setCopiedB(true);
      setTimeout(() => setCopiedB(false), 2000);
    }
  };

  const textLimit = 220;
  const isLongA = clause.docAContent.length > textLimit;
  const isLongB = clause.docBContent.length > textLimit;

  const displayA = expandedA || !isLongA
    ? clause.docAContent
    : `${clause.docAContent.slice(0, textLimit)}...`;

  const displayB = expandedB || !isLongB
    ? clause.docBContent
    : `${clause.docBContent.slice(0, textLimit)}...`;

  return (
    <>
      <div className="rounded-2xl border border-border bg-white shadow-subtle p-5 sm:p-6 space-y-4 hover:shadow-card hover:border-slate-300 transition-all">
        {/* Top Meta Bar */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 border-b border-border/80 pb-3.5">
          <div className="flex flex-wrap items-center gap-2">
            <RiskBadge level={clause.riskLevel} />
            <Badge variant="outline" className="text-xs font-medium text-text-secondary">
              {clause.category}
            </Badge>
            <Badge
              variant={
                isMissing
                  ? "destructive"
                  : isMatching
                  ? "success"
                  : isAdded
                  ? "success"
                  : "secondary"
              }
              className="text-[10px] uppercase font-mono tracking-wider font-semibold"
            >
              {clause.type}
            </Badge>
          </div>

          <div className="flex items-center space-x-2 text-xs text-text-secondary font-mono bg-slate-50 border border-slate-200/80 px-2.5 py-1 rounded-md self-start sm:self-center">
            <FileText className="h-3 w-3 text-primary-600" />
            <span>Doc A (p.{clause.docAPage})</span>
            <ArrowRight className="h-3 w-3 text-slate-400" />
            <span>Doc B (p.{clause.docBPage})</span>
          </div>
        </div>

        {/* Title & Core Distinction */}
        <div className="space-y-1">
          <h4 className="text-base font-bold text-text-main flex items-center gap-2">
            <span>{clause.title}</span>
          </h4>
          <p className="text-xs text-text-secondary leading-relaxed">
            {clause.explanation}
          </p>
        </div>

        {/* Side-by-side comparison */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-sans pt-1">
          {/* Doc A Column */}
          <div className="rounded-xl bg-blue-50/50 border border-blue-200/80 p-4 space-y-2.5 flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between text-[11px] font-semibold text-primary-800 border-b border-blue-100 pb-2 mb-2">
                <span className="flex items-center gap-1.5">
                  <span className="h-2 w-2 rounded-full bg-primary-600" />
                  Original (Doc A - Page {clause.docAPage})
                </span>
                <button
                  type="button"
                  onClick={() => handleCopy(clause.docAContent, true)}
                  title="Copy Original Text"
                  className="inline-flex items-center gap-1 text-[10px] text-primary-600 hover:text-primary-800 transition-colors cursor-pointer"
                >
                  {copiedA ? (
                    <>
                      <Check className="h-3 w-3 text-emerald-600" />
                      <span className="text-emerald-600 font-bold">Copied</span>
                    </>
                  ) : (
                    <>
                      <Copy className="h-3 w-3" />
                      <span>Copy</span>
                    </>
                  )}
                </button>
              </div>

              <p className="text-text-main leading-relaxed whitespace-pre-line select-text">
                {displayA}
              </p>
            </div>

            {isLongA && (
              <button
                type="button"
                onClick={() => setExpandedA(!expandedA)}
                className="text-[11px] font-medium text-primary-700 hover:text-primary-900 flex items-center gap-1 self-start pt-1 cursor-pointer"
              >
                {expandedA ? (
                  <>
                    <ChevronUp className="h-3 w-3" />
                    <span>Show less</span>
                  </>
                ) : (
                  <>
                    <ChevronDown className="h-3 w-3" />
                    <span>Show full excerpt</span>
                  </>
                )}
              </button>
            )}
          </div>

          {/* Doc B Column */}
          <div
            className={`rounded-xl p-4 space-y-2.5 border flex flex-col justify-between ${
              isMissing
                ? "bg-red-50/50 border-red-200/90 text-error"
                : isMatching
                ? "bg-emerald-50/50 border-emerald-200/90"
                : "bg-purple-50/50 border-purple-200/90"
            }`}
          >
            <div>
              <div className="flex items-center justify-between text-[11px] font-semibold text-text-main border-b border-slate-200/60 pb-2 mb-2">
                <span className="flex items-center gap-1.5">
                  <span
                    className={`h-2 w-2 rounded-full ${
                      isMissing
                        ? "bg-red-600"
                        : isMatching
                        ? "bg-emerald-600"
                        : "bg-purple-600"
                    }`}
                  />
                  Revised Draft (Doc B - Page {clause.docBPage})
                </span>
                <button
                  type="button"
                  onClick={() => handleCopy(clause.docBContent, false)}
                  title="Copy Revised Text"
                  className="inline-flex items-center gap-1 text-[10px] text-text-secondary hover:text-text-main transition-colors cursor-pointer"
                >
                  {copiedB ? (
                    <>
                      <Check className="h-3 w-3 text-emerald-600" />
                      <span className="text-emerald-600 font-bold">Copied</span>
                    </>
                  ) : (
                    <>
                      <Copy className="h-3 w-3" />
                      <span>Copy</span>
                    </>
                  )}
                </button>
              </div>

              <p
                className={`leading-relaxed whitespace-pre-line select-text ${
                  isMissing ? "italic font-medium text-error" : "text-text-main"
                }`}
              >
                {displayB}
              </p>
            </div>

            {isLongB && (
              <button
                type="button"
                onClick={() => setExpandedB(!expandedB)}
                className="text-[11px] font-medium text-purple-700 hover:text-purple-900 flex items-center gap-1 self-start pt-1 cursor-pointer"
              >
                {expandedB ? (
                  <>
                    <ChevronUp className="h-3 w-3" />
                    <span>Show less</span>
                  </>
                ) : (
                  <>
                    <ChevronDown className="h-3 w-3" />
                    <span>Show full excerpt</span>
                  </>
                )}
              </button>
            )}
          </div>
        </div>

        {/* Significance & Action Footer */}
        <div className="pt-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-t border-border/80 text-xs">
          <div className="flex items-start sm:items-center gap-2 text-text-secondary">
            <AlertCircle className="h-3.5 w-3.5 text-amber-600 shrink-0 mt-0.5 sm:mt-0" />
            <div>
              <strong className="text-text-main font-semibold">Impact: </strong>
              <span>{clause.significance}</span>
            </div>
          </div>

          {clause.evidenceSnippet && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setEvidenceOpen(true)}
              className="text-xs h-7 gap-1.5 shrink-0 hover:bg-slate-50"
            >
              <Eye className="h-3 w-3 text-primary-600" />
              <span>Inspect Evidence Context</span>
            </Button>
          )}
        </div>
      </div>

      {clause.evidenceSnippet && (
        <EvidenceModal
          isOpen={evidenceOpen}
          onClose={() => setEvidenceOpen(false)}
          title={clause.title}
          docLabel="Both Documents"
          page={clause.docBPage}
          snippet={clause.evidenceSnippet}
          explanation={clause.explanation}
        />
      )}
    </>
  );
}
