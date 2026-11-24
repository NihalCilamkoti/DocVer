"use client";

import * as React from "react";
import { DocumentUploadMeta } from "@/types/comparison";
import { DocumentCard } from "./document-card";
import { FileText } from "lucide-react";

interface DocumentListProps {
  documents: DocumentUploadMeta[];
  onRemove?: (id: string) => void;
  onRetry?: (id: string) => void;
  onPreview?: (id: string) => void;
  onToggleContext?: (id: string) => void;
  previewDocumentId?: string | null;
  emptyMessage?: string;
}

export function DocumentList({
  documents,
  onRemove,
  onRetry,
  onPreview,
  onToggleContext,
  previewDocumentId,
  emptyMessage = "No documents uploaded yet.",
}: DocumentListProps) {
  if (documents.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-slate-200 p-5 text-center space-y-1.5 bg-slate-50/40">
        <FileText className="h-5 w-5 text-slate-400 mx-auto" />
        <p className="text-xs text-slate-500">{emptyMessage}</p>
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {documents.map((doc) => (
        <DocumentCard
          key={doc.id}
          doc={doc}
          onRemove={onRemove}
          onRetry={onRetry}
          onPreview={onPreview}
          onToggleContext={onToggleContext}
          isSelectedForPreview={previewDocumentId === doc.id}
          compact
        />
      ))}
    </div>
  );
}
