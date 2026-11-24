"use client";

import * as React from "react";
import { History, RotateCcw, CheckCircle2, Shield } from "lucide-react";
import { DocumentVersionRecord } from "@/types/comparison";
import { cn } from "@/lib/utils";

interface VersionHistoryProps {
  documentId: string;
  documentName?: string;
  versions: DocumentVersionRecord[];
  onUndo?: (documentId: string) => void;
  onRestoreVersion?: (documentId: string, versionId: string) => void;
}

export function VersionHistory({
  documentId,
  documentName,
  versions,
  onUndo,
  onRestoreVersion,
}: VersionHistoryProps) {
  if (!versions || versions.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-slate-200 p-3 text-center text-[11px] text-slate-500">
        No version history recorded yet.
      </div>
    );
  }

  const latestVersion = versions[versions.length - 1];

  return (
    <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
      <div className="flex items-center justify-between px-3.5 py-2.5 border-b border-slate-200 bg-slate-50/70">
        <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-800">
          <History className="h-3.5 w-3.5 text-slate-500" />
          <span className="truncate">
            Version History {documentName ? `— ${documentName}` : ""}
          </span>
        </div>

        {versions.length > 1 && onUndo && (
          <button
            type="button"
            onClick={() => onUndo(documentId)}
            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-700 hover:bg-slate-100 transition-colors"
            title="Undo latest version change"
          >
            <RotateCcw className="h-3 w-3" />
            <span>Undo</span>
          </button>
        )}
      </div>

      <div className="divide-y divide-slate-100 max-h-56 overflow-y-auto">
        {[...versions].reverse().map((ver) => {
          const isCurrent = ver.id === latestVersion.id;
          const isOriginal = ver.versionNumber === 1;

          return (
            <div
              key={ver.id}
              className={cn(
                "px-3.5 py-2.5 flex items-start justify-between gap-2 text-xs transition-colors",
                isCurrent ? "bg-blue-50/30" : "hover:bg-slate-50/60"
              )}
            >
              <div className="space-y-0.5 min-w-0">
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[11px] font-semibold text-slate-900">
                    v{ver.versionNumber}.0
                  </span>
                  {isOriginal && (
                    <span className="inline-flex items-center gap-0.5 rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-medium text-slate-600">
                      <Shield className="h-2.5 w-2.5 text-slate-500" />
                      Original
                    </span>
                  )}
                  {isCurrent && (
                    <span className="inline-flex items-center gap-0.5 rounded bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 text-[10px] font-medium text-emerald-700">
                      <CheckCircle2 className="h-2.5 w-2.5" />
                      Active
                    </span>
                  )}
                </div>
                <p className="text-[11px] text-slate-600 truncate" title={ver.summary}>
                  {ver.summary}
                </p>
                <span className="text-[10px] text-slate-400 block">
                  {ver.author} •{" "}
                  {new Date(ver.createdAt).toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </span>
              </div>

              {!isCurrent && onRestoreVersion && (
                <button
                  type="button"
                  onClick={() => onRestoreVersion(documentId, ver.id)}
                  className="text-[11px] font-medium text-blue-600 hover:underline shrink-0 mt-0.5"
                >
                  Restore
                </button>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
