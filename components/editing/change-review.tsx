"use client";

import * as React from "react";
import {
  Check,
  X,
  RotateCcw,
  FileEdit,
  ShieldCheck,
  History,
} from "lucide-react";
import { DocumentEditProposal } from "@/types/comparison";
import { DocumentDiff } from "./document-diff";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

interface ChangeReviewProps {
  proposal: DocumentEditProposal;
  onAccept: (proposalId: string) => void;
  onReject: (proposalId: string) => void;
  onUndo?: (documentId: string) => void;
  canUndo?: boolean;
  versionCount?: number;
}

export function ChangeReview({
  proposal,
  onAccept,
  onReject,
  onUndo,
  canUndo = false,
  versionCount = 1,
}: ChangeReviewProps) {
  const isPending = proposal.status === "pending";
  const isAccepted = proposal.status === "accepted";
  const isRejected = proposal.status === "rejected";

  return (
    <div className="rounded-xl border border-slate-200 bg-white shadow-subtle overflow-hidden">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 py-3 border-b border-slate-200 bg-slate-50/70">
        <div className="flex items-center gap-2 min-w-0">
          <div className="h-7 w-7 rounded-lg bg-indigo-50 border border-indigo-200/80 text-indigo-600 flex items-center justify-center shrink-0">
            <FileEdit className="h-3.5 w-3.5" />
          </div>
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <h4 className="text-xs font-semibold text-slate-900 truncate">
                {proposal.title}
              </h4>
              {isPending && (
                <Badge variant="warning" className="text-[10px] py-0 px-2">
                  Review Pending
                </Badge>
              )}
              {isAccepted && (
                <Badge variant="success" className="text-[10px] py-0 px-2">
                  Accepted (v{versionCount}.0)
                </Badge>
              )}
              {isRejected && (
                <Badge variant="secondary" className="text-[10px] py-0 px-2">
                  Rejected
                </Badge>
              )}
            </div>
            <p className="text-[11px] text-slate-500 truncate">
              Target: <strong>{proposal.documentName}</strong> • Page {proposal.page}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-1.5 text-[11px] text-slate-500 shrink-0">
          <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
          <span>Original v1.0 protected</span>
        </div>
      </div>

      {/* Diff Body */}
      <div className="p-4 space-y-3">
        <DocumentDiff
          originalText={proposal.originalText}
          proposedText={proposal.proposedText}
        />

        {proposal.rationale && (
          <div className="rounded-lg bg-slate-50 border border-slate-200/80 px-3 py-2 text-[11px] text-slate-600">
            <strong className="text-slate-800">Change Summary: </strong>
            {proposal.rationale}
          </div>
        )}
      </div>

      {/* Action Controls Footer */}
      <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-t border-slate-200 bg-slate-50/50">
        <div className="flex items-center gap-1.5 text-[11px] text-slate-500">
          <History className="h-3.5 w-3.5 text-slate-400" />
          <span>
            {isAccepted
              ? `Saved as new version (v${versionCount}.0). Original baseline preserved.`
              : isRejected
              ? "Proposal discarded. Document unchanged."
              : "Review proposed diff before applying to document."}
          </span>
        </div>

        <div className="flex items-center gap-2">
          {isPending && (
            <>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onReject(proposal.id)}
                className="h-7 text-xs gap-1 text-slate-700 hover:text-red-700 hover:border-red-200 hover:bg-red-50/50"
              >
                <X className="h-3.5 w-3.5" />
                <span>Reject</span>
              </Button>
              <Button
                type="button"
                size="sm"
                onClick={() => onAccept(proposal.id)}
                className="h-7 text-xs gap-1 bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                <Check className="h-3.5 w-3.5" />
                <span>Accept Changes</span>
              </Button>
            </>
          )}

          {isAccepted && canUndo && onUndo && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => onUndo(proposal.documentId)}
              className="h-7 text-xs gap-1"
            >
              <RotateCcw className="h-3 w-3" />
              <span>Undo Edit</span>
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
