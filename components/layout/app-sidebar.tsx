"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  ShieldCheck,
  Plus,
  Search,
  MessageSquare,
  FileText,
  Settings,
  BarChart3,
  LayoutDashboard,
  History,
  HelpCircle,
  PanelLeftClose,
  PanelLeftOpen,
  FolderOpen,
  Sparkles,
} from "lucide-react";
import { useComparisonStore } from "@/stores/comparison-store";
import { useAuth } from "@/components/auth-provider";
import { comparisonService } from "@/services/api/comparison-service";
import { ComparisonJob } from "@/types/comparison";
import { getDocumentFileIcon } from "@/components/documents/document-card";
import { cn } from "@/lib/utils";

interface AppSidebarProps {
  collapsed?: boolean;
  onToggleCollapse?: () => void;
  onCloseMobile?: () => void;
}

export function AppSidebar({
  collapsed = false,
  onToggleCollapse,
  onCloseMobile,
}: AppSidebarProps) {
  const pathname = usePathname();
  const router = useRouter();
  const { user } = useAuth();

  const {
    conversations,
    activeConversationId,
    conversationSearchQuery,
    setConversationSearchQuery,
    startNewConversation,
    selectConversation,
    loadComparison,
    documents,
    openDocumentPreview,
    activeComparison,
  } = useComparisonStore();

  const [recentJobs, setRecentJobs] = React.useState<ComparisonJob[]>([]);

  React.useEffect(() => {
    let mounted = true;
    comparisonService
      .listComparisons()
      .then((list) => {
        if (mounted) setRecentJobs(list.slice(0, 8));
      })
      .catch(() => {
        // ignore offline fallback
      });
    return () => {
      mounted = false;
    };
  }, [activeComparison?.id]);

  const handleNewChat = () => {
    startNewConversation();
    if (pathname !== "/dashboard/compare" && pathname !== "/compare") {
      router.push("/dashboard/compare");
    }
    onCloseMobile?.();
  };

  const handleSelectJob = (jobId: string) => {
    loadComparison(jobId);
    if (pathname !== "/dashboard/compare" && pathname !== "/compare") {
      router.push(`/dashboard/compare?id=${jobId}`);
    }
    onCloseMobile?.();
  };

  const handleSelectThread = (threadId: string) => {
    selectConversation(threadId);
    if (pathname !== "/dashboard/compare" && pathname !== "/compare") {
      router.push("/dashboard/compare");
    }
    onCloseMobile?.();
  };

  const query = conversationSearchQuery.trim().toLowerCase();

  // Deduplicate active session conversations & exclude dummy placeholders
  const filteredThreads = React.useMemo(() => {
    const seenTitles = new Set<string>();
    return conversations.filter((c) => {
      const titleLower = c.title.trim().toLowerCase();
      if (
        !titleLower ||
        titleLower === "test comparison" ||
        titleLower.includes("dummy") ||
        titleLower.includes("sample") ||
        titleLower.startsWith("untitled")
      ) {
        return false;
      }
      if (seenTitles.has(titleLower)) {
        return false;
      }
      seenTitles.add(titleLower);
      return !query ? true : titleLower.includes(query);
    });
  }, [conversations, query]);

  // Persisted backend comparisons, deduplicated against threads and against themselves
  const filteredJobs = React.useMemo(() => {
    const threadTitles = new Set(filteredThreads.map((t) => t.title.trim().toLowerCase()));
    const seenJobTitles = new Set<string>();

    return recentJobs.filter((j) => {
      const jobTitle = j.title.trim().toLowerCase();
      if (
        !jobTitle ||
        jobTitle === "test comparison" ||
        jobTitle.includes("dummy") ||
        jobTitle.includes("sample") ||
        threadTitles.has(jobTitle) ||
        seenJobTitles.has(jobTitle)
      ) {
        return false;
      }
      seenJobTitles.add(jobTitle);
      if (!query) return true;
      return (
        jobTitle.includes(query) ||
        j.docA.name.toLowerCase().includes(query) ||
        j.docB.name.toLowerCase().includes(query)
      );
    });
  }, [recentJobs, filteredThreads, query]);

  // Collect genuine recent documents, explicitly filtering out dummy placeholders
  const recentDocsList = React.useMemo(() => {
    const seen = new Set<string>();
    const list: { id: string; name: string; ext: string; isSession?: boolean }[] = [];

    const isDummyDoc = (name: string): boolean => {
      const lower = name.trim().toLowerCase();
      return (
        !lower ||
        lower.includes("sample") ||
        lower.includes("dummy") ||
        lower.includes("test") ||
        lower.includes("readme") ||
        lower === "document b" ||
        lower === "document a" ||
        lower.startsWith("untitled") ||
        !lower.includes(".")
      );
    };

    for (const d of documents) {
      if (!isDummyDoc(d.name) && !seen.has(d.name.toLowerCase())) {
        seen.add(d.name.toLowerCase());
        list.push({
          id: d.id,
          name: d.name,
          ext: d.extension,
          isSession: true,
        });
      }
    }

    for (const j of recentJobs) {
      if (j.docA?.name && !isDummyDoc(j.docA.name) && !seen.has(j.docA.name.toLowerCase())) {
        seen.add(j.docA.name.toLowerCase());
        list.push({
          id: `job_${j.id}_a`,
          name: j.docA.name,
          ext: j.docA.name.split(".").pop() || "pdf",
        });
      }
      if (j.docB?.name && !isDummyDoc(j.docB.name) && !seen.has(j.docB.name.toLowerCase())) {
        seen.add(j.docB.name.toLowerCase());
        list.push({
          id: `job_${j.id}_b`,
          name: j.docB.name,
          ext: j.docB.name.split(".").pop() || "pdf",
        });
      }
    }

    return list.slice(0, 6);
  }, [documents, recentJobs]);

  const initials = user?.name ? user.name.slice(0, 2).toUpperCase() : "DV";
  const displayName = user?.name || "DocVer Auditor";
  const displayEmail = user?.email || "workspace@docver.ai";

  // Collapsed Icon Rail Mode
  if (collapsed) {
    return (
      <aside
        aria-label="Primary sidebar navigation"
        className="flex h-full w-17 flex-col items-center justify-between border-r border-slate-200/90 bg-white py-4 select-none"
      >
        <div className="flex flex-col items-center gap-3.5">
          <Link
            href="/dashboard/compare"
            className="flex h-10.5 w-10.5 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/25 ring-1 ring-black/5 hover:scale-105 transition-all"
            title="DocVer AI"
          >
            <ShieldCheck className="h-6 w-6 stroke-[2.2]" />
          </Link>

          <button
            type="button"
            onClick={handleNewChat}
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 transition-all cursor-pointer"
            title="New Chat"
          >
            <Plus className="h-4 w-4" />
          </button>

          <Link
            href="/dashboard/compare"
            className={cn(
              "h-9 w-9 rounded-xl flex items-center justify-center transition-colors",
              pathname.includes("compare")
                ? "bg-indigo-50 text-indigo-700"
                : "text-slate-500 hover:bg-slate-100 hover:text-slate-900"
            )}
            title="AI Document Workspace"
          >
            <MessageSquare className="h-4 w-4" />
          </Link>

          <Link
            href="/dashboard"
            className={cn(
              "h-9 w-9 rounded-xl flex items-center justify-center transition-colors",
              pathname === "/dashboard"
                ? "bg-indigo-50 text-indigo-700"
                : "text-slate-500 hover:bg-slate-100 hover:text-slate-900"
            )}
            title="Overview"
          >
            <LayoutDashboard className="h-4 w-4" />
          </Link>

          <Link
            href="/dashboard/comparisons"
            className={cn(
              "h-9 w-9 rounded-xl flex items-center justify-center transition-colors",
              pathname.startsWith("/dashboard/comparisons")
                ? "bg-indigo-50 text-indigo-700"
                : "text-slate-500 hover:bg-slate-100 hover:text-slate-900"
            )}
            title="Comparison Library"
          >
            <FolderOpen className="h-4 w-4" />
          </Link>

          <Link
            href="/dashboard/usage"
            className={cn(
              "h-9 w-9 rounded-xl flex items-center justify-center transition-colors",
              pathname.startsWith("/dashboard/usage")
                ? "bg-indigo-50 text-indigo-700"
                : "text-slate-500 hover:bg-slate-100 hover:text-slate-900"
            )}
            title="Usage & Quotas"
          >
            <BarChart3 className="h-4 w-4" />
          </Link>
        </div>

        <div className="flex flex-col items-center gap-2.5">
          {onToggleCollapse && (
            <button
              type="button"
              onClick={onToggleCollapse}
              className="h-8 w-8 rounded-lg text-slate-400 hover:text-slate-800 hover:bg-slate-100 flex items-center justify-center cursor-pointer"
              aria-label="Expand sidebar"
              title="Expand sidebar"
            >
              <PanelLeftOpen className="h-4 w-4" />
            </button>
          )}
          <Link
            href="/dashboard/settings"
            className="h-8 w-8 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 flex items-center justify-center"
            title="Settings"
          >
            <Settings className="h-4 w-4" />
          </Link>
        </div>
      </aside>
    );
  }

  return (
    <aside
      aria-label="Primary sidebar navigation"
      className="flex h-full w-64 flex-col border-r border-slate-200/90 bg-white select-none"
    >
      {/* Top: Brand Logo & Collapse Control */}
      <div className="flex min-h-[70px] items-center justify-between px-5 py-3.5 border-b border-slate-200/80 bg-gradient-to-r from-slate-50/90 via-white to-white">
        <Link
          href="/dashboard/compare"
          onClick={onCloseMobile}
          className="flex items-center gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-xl"
        >
          <div className="flex h-10.5 w-10.5 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-500/25 ring-1 ring-black/5 group-hover:scale-105 transition-transform">
            <ShieldCheck className="h-6 w-6 stroke-[2.2]" />
          </div>
          <div className="flex items-center gap-2">
            <span className="text-lg sm:text-xl font-bold tracking-tight text-slate-900 group-hover:text-indigo-600 transition-colors">
              DocVer
            </span>
            <span className="rounded-md bg-indigo-50 border border-indigo-200/80 px-2 py-0.5 font-mono text-[11px] font-bold text-indigo-700 shadow-2xs">
              AI
            </span>
          </div>
        </Link>

        {onToggleCollapse && (
          <button
            type="button"
            onClick={onToggleCollapse}
            className="hidden md:flex h-8.5 w-8.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 items-center justify-center transition-colors cursor-pointer border border-transparent hover:border-slate-200/60"
            aria-label="Collapse sidebar"
            title="Collapse sidebar"
          >
            <PanelLeftClose className="h-4.5 w-4.5" />
          </button>
        )}
      </div>

      {/* Primary Action: New Chat Button & Search Input */}
      <div className="p-4 space-y-2.5 border-b border-slate-100">
        <button
          type="button"
          onClick={handleNewChat}
          className="w-full flex items-center justify-between rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-3.5 py-2.5 text-xs font-semibold text-white hover:from-indigo-500 hover:to-violet-500 transition-all shadow-sm shadow-indigo-600/20 cursor-pointer"
        >
          <span className="flex items-center gap-2">
            <Plus className="h-4 w-4" />
            <span>New Chat</span>
          </span>
          <kbd className="rounded bg-indigo-900/40 border border-indigo-400/30 px-1.5 py-0.5 font-mono text-[10px] text-indigo-100">
            ⌘K
          </kbd>
        </button>

        {/* Search Conversations */}
        <div className="relative">
          <Search className="absolute left-2.5 top-2 h-3.5 w-3.5 text-slate-400" />
          <input
            type="search"
            value={conversationSearchQuery}
            onChange={(e) => setConversationSearchQuery(e.target.value)}
            placeholder="Search conversations..."
            aria-label="Search conversations"
            className="w-full rounded-lg border border-slate-200 bg-slate-50/70 pl-8 pr-2.5 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none transition-colors"
          />
        </div>
      </div>

      {/* Scrollable Sections: Conversation History, Document Library, Recent Documents */}
      <div className="flex-1 overflow-y-auto px-2.5 py-3 space-y-5">
        {/* Conversation History */}
        <div className="space-y-1">
          <div className="px-2 pb-1 flex items-center justify-between">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Conversations
            </span>
          </div>

          {filteredThreads.length === 0 && filteredJobs.length === 0 ? (
            <p className="px-2 py-2 text-[11px] text-slate-400">
              {query ? "No matching conversations." : "Start a new document chat above."}
            </p>
          ) : (
            <div className="space-y-0.5">
              {filteredThreads.map((thread) => {
                const isActive =
                  activeConversationId === thread.id &&
                  (pathname.includes("compare") || pathname === "/dashboard");

                return (
                  <button
                    key={thread.id}
                    type="button"
                    onClick={() => handleSelectThread(thread.id)}
                    className={cn(
                      "w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs transition-colors text-left cursor-pointer",
                      isActive
                        ? "bg-indigo-50/90 text-indigo-950 font-semibold border border-indigo-200/70"
                        : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                    )}
                  >
                    <MessageSquare
                      className={cn(
                        "h-3.5 w-3.5 shrink-0",
                        isActive ? "text-indigo-600" : "text-slate-400"
                      )}
                    />
                    <span className="truncate flex-1">{thread.title}</span>
                  </button>
                );
              })}

              {filteredJobs.map((job) => {
                const isActive =
                  String(activeComparison?.id) === String(job.id) &&
                  pathname.includes("compare");

                return (
                  <button
                    key={`job_${job.id}`}
                    type="button"
                    onClick={() => handleSelectJob(job.id)}
                    className={cn(
                      "w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs transition-colors text-left cursor-pointer",
                      isActive
                        ? "bg-indigo-50/90 text-indigo-950 font-semibold border border-indigo-200/70"
                        : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                    )}
                  >
                    <MessageSquare
                      className={cn(
                        "h-3.5 w-3.5 shrink-0",
                        isActive ? "text-indigo-600" : "text-slate-400"
                      )}
                    />
                    <span className="truncate flex-1">{job.title}</span>
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {/* Recent Documents */}
        <div className="space-y-1">
          <div className="px-2 pb-1 flex items-center justify-between">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Recent Documents
            </span>
          </div>

          {recentDocsList.length === 0 ? (
            <p className="px-2 py-1.5 text-[11px] text-slate-400">
              Uploaded files will appear here.
            </p>
          ) : (
            <div className="space-y-0.5">
              {recentDocsList.map((doc) => (
                <button
                  key={doc.id}
                  type="button"
                  onClick={() => {
                    if (doc.isSession) {
                      openDocumentPreview(doc.id);
                    }
                    onCloseMobile?.();
                  }}
                  className="w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors text-left cursor-pointer"
                >
                  {getDocumentFileIcon(doc.ext, "h-3.5 w-3.5 shrink-0")}
                  <span className="truncate flex-1">{doc.name}</span>
                  {doc.isSession && (
                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" title="Active in workspace" />
                  )}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Document Workspace & Library Navigation */}
        <div className="space-y-1">
          <div className="px-2 pb-1">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Workspace & Library
            </span>
          </div>

          <Link
            href="/dashboard/compare"
            onClick={onCloseMobile}
            className={cn(
              "flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors",
              pathname === "/dashboard/compare" || pathname === "/compare"
                ? "bg-indigo-50/80 text-indigo-900 font-semibold border border-indigo-200/60"
                : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
            )}
          >
            <FileText className="h-3.5 w-3.5 text-indigo-600" />
            <span>AI Document Workspace</span>
          </Link>

          <Link
            href="/dashboard/comparisons"
            onClick={onCloseMobile}
            className={cn(
              "flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors",
              pathname.startsWith("/dashboard/comparisons")
                ? "bg-indigo-50/80 text-indigo-900 font-semibold border border-indigo-200/60"
                : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
            )}
          >
            <History className="h-3.5 w-3.5 text-slate-500" />
            <span>Comparison Library</span>
          </Link>

          <Link
            href="/dashboard"
            onClick={onCloseMobile}
            className={cn(
              "flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors",
              pathname === "/dashboard"
                ? "bg-indigo-50/80 text-indigo-900 font-semibold border border-indigo-200/60"
                : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
            )}
          >
            <LayoutDashboard className="h-3.5 w-3.5 text-slate-500" />
            <span>Analytics Overview</span>
          </Link>

          <Link
            href="/dashboard/usage"
            onClick={onCloseMobile}
            className={cn(
              "flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors",
              pathname.startsWith("/dashboard/usage")
                ? "bg-indigo-50/80 text-indigo-900 font-semibold border border-indigo-200/60"
                : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
            )}
          >
            <BarChart3 className="h-3.5 w-3.5 text-slate-500" />
            <span>Usage & Quotas</span>
          </Link>
        </div>
      </div>

      {/* Bottom: Settings & Help */}
      <div className="border-t border-slate-200 p-2.5 bg-slate-50/40">
        <div className="flex items-center gap-1">
          <Link
            href="/dashboard/settings"
            onClick={onCloseMobile}
            className={cn(
              "flex-1 flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors",
              pathname.includes("settings")
                ? "bg-slate-200/70 text-slate-900 font-semibold"
                : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
            )}
          >
            <Settings className="h-3.5 w-3.5 text-slate-500" />
            <span>Settings</span>
          </Link>

          <Link
            href="/dashboard/help"
            onClick={onCloseMobile}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            title="Help & Documentation"
          >
            <HelpCircle className="h-3.5 w-3.5" />
          </Link>
        </div>
      </div>
    </aside>
  );
}
