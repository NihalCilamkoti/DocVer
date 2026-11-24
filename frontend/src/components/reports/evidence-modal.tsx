"use client";

import * as React from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { FileText, Sparkles, CheckCircle2 } from "lucide-react";

interface EvidenceModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  docLabel: "Document A" | "Document B" | "Both Documents";
  page?: number;
  snippet: string;
  explanation?: string;
}

export function EvidenceModal({
  isOpen,
  onClose,
  title,
  docLabel,
  page,
  snippet,
  explanation,
}: EvidenceModalProps) {
  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <div className="flex items-center space-x-2 mb-1">
            <Badge variant="ai" className="gap-1 text-xs">
              <Sparkles className="h-3 w-3" />
              Verified Extraction
            </Badge>
            {page && (
              <Badge variant="outline" className="text-xs font-mono">
                Page {page}
              </Badge>
            )}
          </div>
          <DialogTitle className="text-base font-bold text-text-main">
            {title}
          </DialogTitle>
          <DialogDescription className="text-xs text-text-secondary">
            Verbatim evidence extracted from {docLabel}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="rounded-xl bg-slate-50 border border-border p-4 font-mono text-xs leading-relaxed text-text-main">
            <div className="flex items-center space-x-2 text-text-secondary text-[11px] mb-2 pb-1 border-b border-border">
              <FileText className="h-3.5 w-3.5" />
              <span>Verbatim Document Segment</span>
            </div>
            &ldquo;{snippet}&rdquo;
          </div>

          {explanation && (
            <div className="rounded-xl bg-blue-50/60 border border-blue-100 p-3.5 text-xs text-text-main space-y-1">
              <p className="font-semibold text-primary-700 flex items-center gap-1.5">
                <CheckCircle2 className="h-3.5 w-3.5" />
                Audit Significance
              </p>
              <p className="text-text-secondary leading-relaxed">{explanation}</p>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
