"use client";

import * as React from "react";
import { FileText, ExternalLink } from "lucide-react";
import { ChatCitation } from "@/types/chat";
import { cn } from "@/lib/utils";

interface SourceCitationProps {
  citation: ChatCitation;
  onSelect?: (citation: ChatCitation) => void;
  className?: string;
}

export function SourceCitation({
  citation,
  onSelect,
  className,
}: SourceCitationProps) {
  return (
    <button
      type="button"
      onClick={() => onSelect && onSelect(citation)}
      className={cn(
        "group inline-flex items-center gap-1.5 rounded-lg border border-slate-200/90 bg-white px-2.5 py-1 text-[11px] text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 transition-colors text-left shadow-2xs cursor-pointer",
        className
      )}
      title={citation.quote ? `Source excerpt: "${citation.quote}"` : `Navigate to ${citation.docLabel} page ${citation.page}`}
    >
      <FileText className="h-3 w-3 text-indigo-600 shrink-0" />
      <span className="font-semibold truncate max-w-[150px]">
        {citation.docName || citation.docLabel}
      </span>
      <span className="font-mono text-[10px] text-indigo-600 font-medium group-hover:text-indigo-800">
        p.{citation.page}
      </span>
      <ExternalLink className="h-2.5 w-2.5 text-slate-400 group-hover:text-indigo-600 shrink-0" />
    </button>
  );
}
