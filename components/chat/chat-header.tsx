"use client";

import * as React from "react";
import {
  PanelLeft,
  PanelRight,
  FileText,
  Eye,
  Plus,
  Sparkles,
} from "lucide-react";
import { DocumentUploadMeta } from "@/types/comparison";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface ChatHeaderProps {
  title: string;
  activeDocuments: DocumentUploadMeta[];
  sidebarCollapsed: boolean;
  onToggleSidebar: () => void;
  contextPanelOpen: boolean;
  onToggleContextPanel: () => void;
  previewOpen: boolean;
  onTogglePreview: () => void;
  onNewChat: () => void;
  onOpenMobileSidebar?: () => void;
}

export function ChatHeader({
  title,
  activeDocuments,
  sidebarCollapsed,
  onToggleSidebar,
  contextPanelOpen,
  onToggleContextPanel,
  previewOpen,
  onTogglePreview,
  onNewChat,
  onOpenMobileSidebar,
}: ChatHeaderProps) {
  return (
    <header className="sticky top-0 z-20 flex h-13 shrink-0 items-center justify-between border-b border-slate-200/90 bg-white/95 px-3 sm:px-4 backdrop-blur-xs">
      {/* Left: Sidebar toggle & Active Conversation Title */}
      <div className="flex items-center gap-2 min-w-0">
        {/* Mobile drawer trigger */}
        {onOpenMobileSidebar && (
          <button
            type="button"
            onClick={onOpenMobileSidebar}
            className="md:hidden h-8 w-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50"
            aria-label="Open navigation menu"
          >
            <PanelLeft className="h-4 w-4" />
          </button>
        )}

        {/* Desktop sidebar collapse toggle */}
        <button
          type="button"
          onClick={onToggleSidebar}
          className="hidden md:flex h-8 w-8 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 items-center justify-center transition-colors"
          aria-label={sidebarCollapsed ? "Expand sidebar" : "Collapse sidebar"}
          title={sidebarCollapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          <PanelLeft className="h-4 w-4" />
        </button>

        <div className="flex items-center gap-2 min-w-0">
          <h1 className="text-xs sm:text-sm font-semibold text-slate-900 truncate max-w-[200px] sm:max-w-[340px]">
            {title || "New Verification Session"}
          </h1>
          <span className="hidden sm:inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
            <Sparkles className="h-2.5 w-2.5 text-indigo-600" />
            Grounded AI
          </span>
        </div>
      </div>

      {/* Right: Active Document Context Pills, Preview Toggle & Context Drawer Toggle */}
      <div className="flex items-center gap-1.5 shrink-0">
        {/* Quick Document Preview Button when documents exist */}
        {activeDocuments.length > 0 && (
          <button
            type="button"
            onClick={onTogglePreview}
            className={cn(
              "inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors",
              previewOpen
                ? "border-blue-300 bg-blue-50 text-blue-700"
                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
            )}
            title={previewOpen ? "Hide document reader" : "Open split-screen document reader"}
          >
            <Eye className="h-3.5 w-3.5 text-blue-600" />
            <span className="hidden sm:inline">
              {previewOpen ? "Close Reader" : "Read Document"}
            </span>
          </button>
        )}

        {/* New Chat Button on compact screens */}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onNewChat}
          className="h-8 text-xs gap-1 px-2.5 hidden sm:inline-flex"
        >
          <Plus className="h-3.5 w-3.5" />
          <span>New Chat</span>
        </Button>

        {/* Context Panel Toggle */}
        <button
          type="button"
          onClick={onToggleContextPanel}
          className={cn(
            "inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors",
            contextPanelOpen
              ? "border-slate-300 bg-slate-100 text-slate-900"
              : "border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
          )}
          aria-label="Toggle document context panel"
          aria-expanded={contextPanelOpen}
          title="Toggle right context panel"
        >
          <FileText className="h-3.5 w-3.5 text-slate-600" />
          <span className="hidden lg:inline">Context</span>
          <span className="inline-flex items-center justify-center h-4 min-w-4 px-1 rounded-full bg-slate-200/80 text-[10px] font-mono font-semibold text-slate-800">
            {activeDocuments.length}
          </span>
          <PanelRight className="h-3.5 w-3.5 text-slate-500" />
        </button>
      </div>
    </header>
  );
}
