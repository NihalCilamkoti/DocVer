"use client";

import * as React from "react";
import { AppSidebar } from "./app-sidebar";
import { ContextPanel } from "./context-panel";
import { DocumentPreview } from "@/components/documents/document-preview";
import { DocumentUploadMeta } from "@/types/comparison";

interface MobileNavigationProps {
  sidebarOpen: boolean;
  onCloseSidebar: () => void;
  contextOpen: boolean;
  onCloseContext: () => void;
  previewOpen?: boolean;
  previewDoc?: DocumentUploadMeta | null;
  allDocuments?: DocumentUploadMeta[];
  previewPage?: number;
  onPreviewPageChange?: (p: number) => void;
  previewSearchQuery?: string;
  onPreviewSearchChange?: (q: string) => void;
  previewHighlightText?: string | null;
  onClosePreview?: () => void;
  onSelectPreviewDoc?: (id: string) => void;
  onRequestEdit?: (instruction: string, docId: string) => void;
}

export function MobileNavigation({
  sidebarOpen,
  onCloseSidebar,
  contextOpen,
  onCloseContext,
  previewOpen = false,
  previewDoc = null,
  allDocuments = [],
  previewPage = 1,
  onPreviewPageChange,
  previewSearchQuery = "",
  onPreviewSearchChange,
  previewHighlightText,
  onClosePreview,
  onSelectPreviewDoc,
  onRequestEdit,
}: MobileNavigationProps) {
  return (
    <>
      {/* Left Navigation Drawer on Mobile */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-50 flex md:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Navigation menu"
        >
          <div
            className="fixed inset-0 bg-slate-900/40 backdrop-blur-2xs"
            onClick={onCloseSidebar}
          />
          <div className="relative z-50 flex w-64 max-w-[85vw] flex-col bg-white shadow-elevated">
            <AppSidebar onCloseMobile={onCloseSidebar} />
          </div>
        </div>
      )}

      {/* Right Context Panel Drawer on Mobile/Tablet */}
      {contextOpen && (
        <div
          className="fixed inset-0 z-50 flex xl:hidden justify-end"
          role="dialog"
          aria-modal="true"
          aria-label="Document context drawer"
        >
          <div
            className="fixed inset-0 bg-slate-900/40 backdrop-blur-2xs"
            onClick={onCloseContext}
          />
          <div className="relative z-50 flex w-80 max-w-[90vw] flex-col bg-white shadow-elevated">
            <ContextPanel onClose={onCloseContext} />
          </div>
        </div>
      )}

      {/* Full-Screen Document Preview Overlay on Smaller Screens (< lg) */}
      {previewOpen && previewDoc && onClosePreview && (
        <div
          className="fixed inset-0 z-50 flex lg:hidden flex-col bg-white"
          role="dialog"
          aria-modal="true"
          aria-label="Document reader"
        >
          <DocumentPreview
            document={previewDoc}
            allDocuments={allDocuments}
            onSelectDocument={onSelectPreviewDoc}
            currentPage={previewPage}
            onPageChange={onPreviewPageChange || (() => {})}
            searchQuery={previewSearchQuery}
            onSearchQueryChange={onPreviewSearchChange || (() => {})}
            highlightText={previewHighlightText}
            onClose={onClosePreview}
            onRequestEdit={onRequestEdit}
          />
        </div>
      )}
    </>
  );
}
