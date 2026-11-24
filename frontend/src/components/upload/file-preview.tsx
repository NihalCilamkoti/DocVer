"use client";

import * as React from "react";
import {
  FileText,
  Trash2,
  RefreshCw,
  FileSpreadsheet,
  FileBarChart,
  FileImage,
  CheckCircle2,
} from "lucide-react";
import { DocumentUploadMeta } from "@/types/comparison";
import { formatBytes } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

interface FilePreviewProps {
  doc: DocumentUploadMeta;
  onRemove: () => void;
  onReplace: () => void;
}

export function FilePreview({ doc, onRemove, onReplace }: FilePreviewProps) {
  const getFileIcon = (ext: string) => {
    const lower = ext.toLowerCase();
    if (["xls", "xlsx", "csv"].includes(lower)) {
      return <FileSpreadsheet className="h-7 w-7 text-emerald-600" />;
    }
    if (["ppt", "pptx"].includes(lower)) {
      return <FileBarChart className="h-7 w-7 text-amber-600" />;
    }
    if (["png", "jpg", "jpeg", "webp"].includes(lower)) {
      return <FileImage className="h-7 w-7 text-blue-600" />;
    }
    return <FileText className="h-7 w-7 text-primary-600" />;
  };

  return (
    <div className="rounded-xl border border-border bg-white p-4 shadow-subtle flex flex-col justify-between h-full space-y-4">
      <div className="flex items-start justify-between">
        <div className="flex items-start space-x-3 overflow-hidden">
          <div className="h-12 w-12 rounded-xl bg-slate-50 border border-border flex items-center justify-center shrink-0">
            {getFileIcon(doc.extension)}
          </div>
          <div className="overflow-hidden">
            <h4 className="text-sm font-bold text-text-main truncate max-w-[220px]" title={doc.name}>
              {doc.name}
            </h4>
            <div className="flex items-center space-x-2 text-xs text-text-secondary mt-1">
              <span>{formatBytes(doc.size)}</span>
              <span>•</span>
              <span className="uppercase font-mono">{doc.extension}</span>
              {doc.pageCount && (
                <>
                  <span>•</span>
                  <span>{doc.pageCount} pages</span>
                </>
              )}
            </div>
          </div>
        </div>

        <Badge variant="success" className="gap-1 text-[11px] shrink-0 font-medium">
          <CheckCircle2 className="h-3 w-3" />
          Ready
        </Badge>
      </div>

      <div className="flex items-center justify-between border-t border-border pt-3">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onReplace}
          className="text-xs h-8 gap-1.5"
        >
          <RefreshCw className="h-3.5 w-3.5" />
          Replace
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={onRemove}
          className="text-xs h-8 text-error hover:bg-error-50 hover:text-error gap-1.5"
        >
          <Trash2 className="h-3.5 w-3.5" />
          Remove
        </Button>
      </div>
    </div>
  );
}
