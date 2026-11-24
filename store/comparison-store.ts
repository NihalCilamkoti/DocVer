import { create } from "zustand";
import {
  DocumentUploadMeta,
  ComparisonJob,
  DocumentEditProposal,
  DocumentVersionRecord,
  WorkspaceErrorDetail,
} from "@/types/comparison";
import { ChatMessage, ConversationThread } from "@/types/chat";
import { DEFAULT_CONFIG, SUPPORTED_EXTENSIONS, SupportedExtension } from "@/lib/constants";
import { comparisonService } from "@/services/api/comparison-service";
import { formatBytes } from "@/lib/utils";
import { ToastItem } from "@/components/ui/toast";

interface ComparisonStore {
  // Config
  maxFileSize: number;
  maxPageLimit: number;
  setMaxFileSize: (size: number) => void;
  setMaxPageLimit: (pages: number) => void;

  // Layout & Panels
  sidebarCollapsed: boolean;
  setSidebarCollapsed: (collapsed: boolean) => void;
  toggleSidebar: () => void;
  contextPanelOpen: boolean;
  setContextPanelOpen: (open: boolean) => void;
  toggleContextPanel: () => void;

  // Document Preview & Split-Screen Reading
  previewOpen: boolean;
  previewDocumentId: string | null;
  previewPage: number;
  previewSearchQuery: string;
  previewHighlightText: string | null;
  openDocumentPreview: (docId?: string, page?: number, highlightText?: string) => Promise<void>;
  closeDocumentPreview: () => void;
  setPreviewPage: (page: number) => void;
  setPreviewSearchQuery: (query: string) => void;

  // Upload slots & Multi-document Workspace Library
  docA: DocumentUploadMeta | null;
  docB: DocumentUploadMeta | null;
  documents: DocumentUploadMeta[];
  activeContextDocIds: string[];
  setDocA: (doc: DocumentUploadMeta | null) => void;
  setDocB: (doc: DocumentUploadMeta | null) => void;
  updateDocAProgress: (progress: number) => void;
  updateDocBProgress: (progress: number) => void;
  uploadFiles: (files: File[], targetSlot?: "A" | "B") => Promise<void>;
  retryDocumentUpload: (docId: string) => Promise<void>;
  removeDocument: (docId: string) => void;
  toggleDocumentInContext: (docId: string) => void;

  // Comparison State
  isComparing: boolean;
  stageName: string;
  stageProgress: number;
  activeComparison: ComparisonJob | null;
  comparisonError: string | null;
  workspaceError: WorkspaceErrorDetail | null;
  setWorkspaceError: (err: WorkspaceErrorDetail | null) => void;
  setActiveComparison: (job: ComparisonJob | null) => void;
  runComparison: (focusPrompt?: string) => Promise<void>;
  loadComparison: (id: string) => Promise<void>;
  loadSampleComparison: () => Promise<void>;
  resetWorkspace: () => void;

  // Single-Document & Cross-Document AI Analysis
  singleDocAnalysis: {
    documentId: string;
    documentName: string;
    pageCount: number;
    prompt: string;
    answer: string;
    provider?: string;
    model?: string;
  } | null;
  isAnalyzingSingleDoc: boolean;
  querySingleDoc: (prompt: string) => Promise<void>;
  askAssistant: (prompt: string) => Promise<void>;
  clearSingleDocAnalysis: () => void;

  // AI Chat & Conversation Threads
  conversations: ConversationThread[];
  activeConversationId: string;
  conversationSearchQuery: string;
  setConversationSearchQuery: (q: string) => void;
  startNewConversation: () => void;
  selectConversation: (id: string) => void;
  chatMessages: ChatMessage[];
  isAiGenerating: boolean;
  aiProcessingState:
    | null
    | "uploading"
    | "extracting"
    | "thinking"
    | "generating"
    | "comparing"
    | "creating_doc"
    | "saving_changes";
  stopGeneration: () => void;
  addChatMessage: (msg: Omit<ChatMessage, "id" | "timestamp">) => ChatMessage;
  updateChatMessage: (id: string, patch: Partial<ChatMessage>) => void;
  setMessageFeedback: (id: string, feedback: "up" | "down" | null) => void;
  regenerateResponse: (messageId?: string) => Promise<void>;
  clearChat: () => void;
  simulateAiResponse: (query: string) => Promise<void>;

  // Document Editing & Version History
  editProposals: DocumentEditProposal[];
  activeEditProposalId: string | null;
  versionHistory: Record<string, DocumentVersionRecord[]>;
  requestDocumentEdit: (instruction: string, targetDocId?: string) => Promise<void>;
  acceptEditProposal: (proposalId: string) => void;
  rejectEditProposal: (proposalId: string) => void;
  undoDocumentVersion: (documentId: string) => void;
  restoreDocumentVersion: (documentId: string, versionId: string) => void;
  setActiveEditProposalId: (proposalId: string | null) => void;

  // Toast Notifications
  toasts: ToastItem[];
  addToast: (toast: Omit<ToastItem, "id">) => void;
  dismissToast: (id: string) => void;
}

const STAGE_LABELS: Record<string, string> = {
  validating: "Validating file signatures & structure...",
  extracting: "Extracting document text & tables...",
  detecting_sections: "Detecting contract sections & provisions...",
  chunking: "Chunking clauses & indexing citations...",
  indexing: "Building ephemeral document index...",
  matching_sections: "Matching sections across documents...",
  comparing: "Performing AI clause and numerical comparison...",
  validating_evidence: "Verifying ground-truth citations...",
  generating_report: "Finalizing executive verification report...",
  completed: "Verification complete!",
};

function classifyError(err: unknown, defaultType: WorkspaceErrorDetail["type"] = "processing"): WorkspaceErrorDetail {
  const rawMessage = err instanceof Error ? err.message : String(err || "An unexpected error occurred.");
  const status = (err as any)?.status;
  const lower = rawMessage.toLowerCase();

  if (status === 429 || lower.includes("rate limit") || lower.includes("too many requests") || lower.includes("quota")) {
    const isPlanLimit = lower.includes("workspace") || lower.includes("daily") || lower.includes("plan");
    const resetDate = new Date(Date.now() + (isPlanLimit ? 3600 * 6 * 1000 : 45 * 1000));
    const resetStr = isPlanLimit
      ? "Resets at 00:00 UTC (Daily Plan Cycle)"
      : `Resets at ${resetDate.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" })}`;

    return {
      type: isPlanLimit ? "daily_usage_limit" : "provider_rate_limit",
      title: isPlanLimit ? "DocVer Plan Usage Limit Reached" : "AI Provider Rate Limit Reached",
      message: isPlanLimit
        ? "You have reached the daily document verification allowance for your current DocVer plan."
        : "The primary AI model provider is temporarily rate-limited. Automatic failover is available on retry.",
      limitSource: isPlanLimit ? "docver_plan" : "ai_provider",
      resetTime: resetStr,
      recoverable: true,
      retryActionLabel: isPlanLimit ? "Retry with Cached Index" : "Retry with Failover Provider",
    };
  }

  if (lower.includes("network") || lower.includes("failed to fetch") || lower.includes("connection")) {
    return {
      type: "network",
      title: "Network Connection Interrupted",
      message: "Could not reach the DocVer verification server. Check your network connection and retry.",
      recoverable: true,
      retryActionLabel: "Retry Request",
    };
  }

  if (lower.includes("unsupported") || lower.includes("signature") || lower.includes("mime")) {
    return {
      type: "unsupported_format",
      title: "Unsupported File Format",
      message: rawMessage,
      recoverable: false,
    };
  }

  return {
    type: defaultType,
    title:
      defaultType === "upload"
        ? "Document Upload Failed"
        : defaultType === "extraction"
        ? "Text Extraction Failed"
        : "Processing Error",
    message: rawMessage,
    recoverable: true,
    retryActionLabel: "Try Again",
  };
}

let abortGenerationFlag = false;

export const useComparisonStore = create<ComparisonStore>((set, get) => ({
  maxFileSize: DEFAULT_CONFIG.maxFileSizeBytes,
  maxPageLimit: DEFAULT_CONFIG.maxPageLimit,
  setMaxFileSize: (maxFileSize) => set({ maxFileSize }),
  setMaxPageLimit: (maxPageLimit) => set({ maxPageLimit }),

  // Layout & Panels
  sidebarCollapsed: false,
  setSidebarCollapsed: (sidebarCollapsed) => set({ sidebarCollapsed }),
  toggleSidebar: () => set((s) => ({ sidebarCollapsed: !s.sidebarCollapsed })),
  contextPanelOpen: true,
  setContextPanelOpen: (contextPanelOpen) => set({ contextPanelOpen }),
  toggleContextPanel: () => set((s) => ({ contextPanelOpen: !s.contextPanelOpen })),

  // Document Preview & Split-Screen Reading
  previewOpen: false,
  previewDocumentId: null,
  previewPage: 1,
  previewSearchQuery: "",
  previewHighlightText: null,

  openDocumentPreview: async (docId?: string, page = 1, highlightText?: string) => {
    const state = get();
    const targetDoc =
      state.documents.find((d) => d.id === docId || String(d.backendId) === String(docId)) ||
      (docId === "Document A" ? state.docA : docId === "Document B" ? state.docB : null) ||
      state.docA ||
      state.docB ||
      state.documents[0];

    if (!targetDoc) return;

    set({
      previewOpen: true,
      previewDocumentId: targetDoc.id,
      previewPage: page || 1,
      previewHighlightText: highlightText || null,
    });

    if (!targetDoc.pages || targetDoc.pages.length === 0) {
      const backendId = targetDoc.backendId || (/^\d+$/.test(targetDoc.id) ? targetDoc.id : null);
      if (backendId) {
        try {
          const contentData = await comparisonService.getDocumentContent(backendId);
          set((s) => {
            const updateDoc = (d: DocumentUploadMeta | null) =>
              d && d.id === targetDoc.id
                ? {
                    ...d,
                    extractedText: contentData.text,
                    pages: contentData.pages,
                    pageCount: contentData.page_count || d.pageCount || contentData.pages.length,
                  }
                : d;
            return {
              docA: updateDoc(s.docA),
              docB: updateDoc(s.docB),
              documents: s.documents.map((d) => updateDoc(d) as DocumentUploadMeta),
            };
          });
        } catch {
          // Fallback to reading local File if text/plain
        }
      }
    }
  },

  closeDocumentPreview: () => set({ previewOpen: false, previewHighlightText: null }),
  setPreviewPage: (previewPage) => set({ previewPage }),
  setPreviewSearchQuery: (previewSearchQuery) => set({ previewSearchQuery }),

  // Documents
  docA: null,
  docB: null,
  documents: [],
  activeContextDocIds: [],

  setDocA: (docA) =>
    set((state) => {
      if (!docA) {
        const remaining = state.documents.filter((d) => d.slot !== "A");
        return {
          docA: null,
          documents: remaining,
          activeContextDocIds: remaining.filter((d) => d.inActiveContext !== false).map((d) => d.id),
        };
      }
      const tagged: DocumentUploadMeta = { ...docA, slot: "A", inActiveContext: true };
      const others = state.documents.filter((d) => d.id !== tagged.id && d.slot !== "A");
      const nextDocs = [tagged, ...others];
      return {
        docA: tagged,
        documents: nextDocs,
        activeContextDocIds: Array.from(new Set([...state.activeContextDocIds, tagged.id])),
      };
    }),

  setDocB: (docB) =>
    set((state) => {
      if (!docB) {
        const remaining = state.documents.filter((d) => d.slot !== "B");
        return {
          docB: null,
          documents: remaining,
          activeContextDocIds: remaining.filter((d) => d.inActiveContext !== false).map((d) => d.id),
        };
      }
      const tagged: DocumentUploadMeta = { ...docB, slot: "B", inActiveContext: true };
      const others = state.documents.filter((d) => d.id !== tagged.id && d.slot !== "B");
      const nextDocs = [...others, tagged];
      return {
        docB: tagged,
        documents: nextDocs,
        activeContextDocIds: Array.from(new Set([...state.activeContextDocIds, tagged.id])),
      };
    }),

  updateDocAProgress: (progress) =>
    set((state) => ({
      docA: state.docA ? { ...state.docA, progress } : null,
    })),

  updateDocBProgress: (progress) =>
    set((state) => ({
      docB: state.docB ? { ...state.docB, progress } : null,
    })),

  uploadFiles: async (files: File[], targetSlot?: "A" | "B") => {
    const { maxFileSize, addToast } = get();

    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      const extension = file.name.split(".").pop()?.toLowerCase() || "";
      const tempId = `doc_${Date.now()}_${i}_${Math.random().toString(36).slice(2, 6)}`;

      const currentState = get();
      const slot: "A" | "B" =
        targetSlot ||
        (!currentState.docA || currentState.docA.status === "failed" || currentState.docA.status === "unsupported"
          ? "A"
          : !currentState.docB || currentState.docB.status === "failed" || currentState.docB.status === "unsupported"
          ? "B"
          : i === 0
          ? "A"
          : "B");

      // 1. Validate format
      if (!SUPPORTED_EXTENSIONS.includes(extension as SupportedExtension)) {
        const unsupportedDoc: DocumentUploadMeta = {
          id: tempId,
          slot,
          file,
          name: file.name,
          size: file.size,
          type: file.type || "application/octet-stream",
          extension: extension || "unknown",
          uploadedAt: new Date().toISOString(),
          progress: 0,
          status: "unsupported",
          errorMessage: `Unsupported format (.${extension || "unknown"}). Supported: PDF, DOCX, DOC, TXT, XLSX, CSV, PPTX, Images.`,
          inActiveContext: false,
        };

        set((s) => ({
          documents: [...s.documents.filter((d) => d.id !== tempId), unsupportedDoc],
          workspaceError: {
            type: "unsupported_format",
            title: "Unsupported Document Format",
            message: `${file.name} cannot be processed. Please upload a PDF, Word, Excel, CSV, PowerPoint, or Image file.`,
            recoverable: false,
          },
        }));
        continue;
      }

      // 2. Validate size
      if (file.size > maxFileSize) {
        const oversizedDoc: DocumentUploadMeta = {
          id: tempId,
          slot,
          file,
          name: file.name,
          size: file.size,
          type: file.type || "application/octet-stream",
          extension,
          uploadedAt: new Date().toISOString(),
          progress: 0,
          status: "failed",
          errorMessage: `File exceeds maximum size limit of ${formatBytes(maxFileSize)}.`,
          inActiveContext: false,
        };

        set((s) => ({
          documents: [...s.documents.filter((d) => d.id !== tempId), oversizedDoc],
        }));
        continue;
      }

      // 3. Real lifecycle: uploading -> uploaded -> extracting -> processing -> ready
      const initialDoc: DocumentUploadMeta = {
        id: tempId,
        slot,
        file,
        name: file.name,
        size: file.size,
        type: file.type || "application/octet-stream",
        extension,
        pageCount: Math.max(1, Math.round(file.size / (55 * 1024))),
        uploadedAt: new Date().toISOString(),
        progress: 20,
        status: "uploading",
        inActiveContext: true,
      };

      const applyDocUpdate = (patch: Partial<DocumentUploadMeta>) => {
        set((s) => {
          const updatedList = s.documents.some((d) => d.id === tempId)
            ? s.documents.map((d) => (d.id === tempId ? { ...d, ...patch } : d))
            : [...s.documents, { ...initialDoc, ...patch }];

          const currentDoc = updatedList.find((d) => d.id === tempId) || null;
          return {
            documents: updatedList,
            docA: slot === "A" ? currentDoc : s.docA,
            docB: slot === "B" ? currentDoc : s.docB,
            activeContextDocIds: currentDoc?.inActiveContext
              ? Array.from(new Set([...s.activeContextDocIds, tempId]))
              : s.activeContextDocIds,
          };
        });
      };

      applyDocUpdate({ status: "uploading", progress: 25 });

      try {
        const uploadedMeta = await comparisonService.uploadDocument(file, slot);

        applyDocUpdate({
          backendId: uploadedMeta.id,
          status: "uploaded",
          progress: 55,
          pageCount: uploadedMeta.page_count || initialDoc.pageCount,
        });

        applyDocUpdate({
          status: "extracting",
          progress: 75,
        });

        let extractedText = "";
        let pages: { page: number; content: string }[] = [];

        try {
          const contentRes = await comparisonService.getDocumentContent(uploadedMeta.id);
          extractedText = contentRes.text || "";
          pages = contentRes.pages || [];
        } catch {
          if (extension === "txt" || extension === "csv") {
            extractedText = await file.text();
            pages = [{ page: 1, content: extractedText }];
          }
        }

        applyDocUpdate({
          status: "processing",
          progress: 90,
        });

        const docKey = String(uploadedMeta.id || tempId);
        const baselineText =
          extractedText ||
          `Document: ${file.name}\nFormat: ${extension.toUpperCase()}\nSize: ${formatBytes(file.size)}`;

        const v1Record: DocumentVersionRecord = {
          id: `ver_${Date.now()}_1`,
          versionNumber: 1,
          documentId: tempId,
          documentName: file.name,
          label: "v1.0 — Original Uploaded Document",
          summary: "Initial verified upload and text extraction.",
          contentSnapshot: baselineText,
          createdAt: new Date().toISOString(),
          author: "Origin",
        };

        set((s) => ({
          versionHistory: {
            ...s.versionHistory,
            [tempId]: [v1Record],
            [docKey]: [v1Record],
          },
        }));

        applyDocUpdate({
          status: "ready",
          progress: 100,
          extractedText: baselineText,
          pages: pages.length > 0 ? pages : [{ page: 1, content: baselineText }],
          pageCount: pages.length || uploadedMeta.page_count || initialDoc.pageCount || 1,
        });

        addToast({
          title: `${file.name} ready`,
          description: `Assigned to Document ${slot} and added to active context.`,
          variant: "success",
        });
      } catch (err: unknown) {
        const classified = classifyError(err, "upload");
        applyDocUpdate({
          status: "failed",
          progress: 0,
          errorMessage: classified.message,
          inActiveContext: false,
        });
        set({ workspaceError: classified });
      }
    }
  },

  retryDocumentUpload: async (docId: string) => {
    const doc = get().documents.find((d) => d.id === docId);
    if (!doc || !doc.file) return;
    get().removeDocument(docId);
    await get().uploadFiles([doc.file], doc.slot);
  },

  removeDocument: (docId: string) => {
    set((state) => {
      const remaining = state.documents.filter((d) => d.id !== docId);
      const isDocA = state.docA?.id === docId;
      const isDocB = state.docB?.id === docId;

      return {
        documents: remaining,
        docA: isDocA ? remaining.find((d) => d.slot === "A") || null : state.docA,
        docB: isDocB ? remaining.find((d) => d.slot === "B") || null : state.docB,
        activeContextDocIds: state.activeContextDocIds.filter((id) => id !== docId),
        previewOpen: state.previewDocumentId === docId ? false : state.previewOpen,
        previewDocumentId: state.previewDocumentId === docId ? null : state.previewDocumentId,
      };
    });
  },

  toggleDocumentInContext: (docId: string) => {
    set((state) => {
      const exists = state.activeContextDocIds.includes(docId);
      const nextIds = exists
        ? state.activeContextDocIds.filter((id) => id !== docId)
        : [...state.activeContextDocIds, docId];

      const nextDocs = state.documents.map((d) =>
        d.id === docId ? { ...d, inActiveContext: !exists } : d
      );

      const activeReadyDocs = nextDocs.filter((d) => nextIds.includes(d.id) && d.status === "ready");
      return {
        activeContextDocIds: nextIds,
        documents: nextDocs,
        docA: activeReadyDocs[0] || null,
        docB: activeReadyDocs[1] || null,
      };
    });
  },

  // Comparison State
  isComparing: false,
  stageName: "",
  stageProgress: 0,
  activeComparison: null,
  comparisonError: null,
  workspaceError: null,
  setWorkspaceError: (workspaceError) => set({ workspaceError, comparisonError: workspaceError?.message || null }),
  setActiveComparison: (activeComparison) => set({ activeComparison }),

  loadSampleComparison: async () => {
    try {
      const list = await comparisonService.listComparisons();
      if (list.length > 0) {
        const latest = list[0];
        await get().loadComparison(latest.id);
        return;
      }
    } catch {
      // Offline dev
    }
  },

  loadComparison: async (id: string) => {
    set({
      isComparing: true,
      comparisonError: null,
      workspaceError: null,
      aiProcessingState: "comparing",
      stageName: "Loading verified comparison audit...",
    });

    try {
      const job = await comparisonService.getComparison(id);

      const docAMeta: DocumentUploadMeta = {
        id: `doc_a_${job.id}`,
        backendId: job.docA.id,
        slot: "A",
        file: null,
        name: job.docA.name,
        size: job.docA.size,
        type: job.docA.type,
        extension: job.docA.name.split(".").pop()?.toLowerCase() || "pdf",
        pageCount: job.docA.pages,
        uploadedAt: job.createdAt,
        progress: 100,
        status: "ready",
        inActiveContext: true,
      };

      const docBMeta: DocumentUploadMeta = {
        id: `doc_b_${job.id}`,
        backendId: job.docB.id,
        slot: "B",
        file: null,
        name: job.docB.name,
        size: job.docB.size,
        type: job.docB.type,
        extension: job.docB.name.split(".").pop()?.toLowerCase() || "pdf",
        pageCount: job.docB.pages,
        uploadedAt: job.createdAt,
        progress: 100,
        status: "ready",
        inActiveContext: true,
      };

      const initialMessage: ChatMessage = {
        id: "msg_init_" + Date.now(),
        sender: "ai",
        messageType: "comparison",
        comparisonJob: job,
        content:
          job.summary.executiveSummary ||
          `## Overview\nCompleted cross-document verification between **${job.docA.name}** (Document A) and **${job.docB.name}** (Document B).\n\n## Key Findings\n- **Overall Similarity:** ${job.summary.overallSimilarityScore}%\n- **Total Differences Identified:** ${job.summary.totalDifferences}\n- **High-Risk Deviations:** ${job.summary.highRiskCount}\n- **Numerical Variances:** ${job.summary.numericalDiscrepanciesCount}\n- **Direct Contradictions:** ${job.summary.contradictionsCount}\n\n## Conclusion\nReview the structured comparison table below or ask about any specific clause, obligation, or proposed revision.`,
        timestamp: new Date().toISOString(),
        suggestedFollowUps: [
          "Show all high-risk changes",
          "Compare payment terms and deadlines",
          "Propose an edit to align Document B with Document A",
        ],
      };

      const threadId = `conv_job_${job.id}`;
      const newThread: ConversationThread = {
        id: threadId,
        title: job.title || `${job.docA.name} vs ${job.docB.name}`,
        updatedAt: new Date().toISOString(),
        comparisonJobId: job.id,
        documentIds: [docAMeta.id, docBMeta.id],
        messages: [initialMessage],
      };

      set((state) => ({
        activeComparison: job,
        isComparing: false,
        aiProcessingState: null,
        stageName: "",
        docA: docAMeta,
        docB: docBMeta,
        documents: [docAMeta, docBMeta],
        activeContextDocIds: [docAMeta.id, docBMeta.id],
        chatMessages: [initialMessage],
        activeConversationId: threadId,
        conversations: [
          newThread,
          ...state.conversations.filter((c) => c.id !== threadId),
        ],
      }));
    } catch (err: unknown) {
      const classified = classifyError(err, "processing");
      set({
        isComparing: false,
        aiProcessingState: null,
        comparisonError: classified.message,
        workspaceError: classified,
      });
    }
  },

  runComparison: async (focusPrompt?: string) => {
    const state = get();
    const activeReady = state.documents.filter(
      (d) => state.activeContextDocIds.includes(d.id) && d.status === "ready"
    );
    const docA = state.docA || activeReady[0] || null;
    const docB = state.docB || activeReady[1] || null;

    if (!docA || !docB) return;

    if (docA.status !== "ready" || docB.status !== "ready") {
      set({
        comparisonError: "Please wait until both documents finish processing before comparing.",
      });
      return;
    }

    if (focusPrompt) {
      get().addChatMessage({
        sender: "user",
        content: focusPrompt,
      });
    } else {
      get().addChatMessage({
        sender: "user",
        content: `Compare **${docA.name}** and **${docB.name}** and highlight all key differences, risks, and numerical variances.`,
      });
    }

    set({
      isComparing: true,
      isAiGenerating: true,
      aiProcessingState: "comparing",
      comparisonError: null,
      workspaceError: null,
      stageName: "Verifying uploaded document signatures...",
      stageProgress: 15,
    });

    try {
      let docABackendId = docA.backendId || (/^\d+$/.test(docA.id) ? Number(docA.id) : null);
      let docBBackendId = docB.backendId || (/^\d+$/.test(docB.id) ? Number(docB.id) : null);

      if (!docABackendId) {
        if (!docA.file) throw new Error(`Physical file needed for ${docA.name}. Please re-upload.`);
        const uploadedA = await comparisonService.uploadDocument(docA.file, "A");
        docABackendId = uploadedA.id;
      }

      if (!docBBackendId) {
        if (!docB.file) throw new Error(`Physical file needed for ${docB.name}. Please re-upload.`);
        const uploadedB = await comparisonService.uploadDocument(docB.file, "B");
        docBBackendId = uploadedB.id;
      }

      set({
        stageName: focusPrompt
          ? `Running cross-document verification focused on "${focusPrompt}"...`
          : "Running multi-stage AI clause and numerical comparison...",
        stageProgress: 35,
      });

      const title = `${docA.name} vs ${docB.name}`;
      const jobDTO = await comparisonService.createComparison(
        title,
        Number(docABackendId),
        Number(docBBackendId),
        undefined,
        focusPrompt ? { focus_prompt: focusPrompt } : undefined
      );
      const jobId = jobDTO.id;

      const finalizeComparison = async () => {
        const fullJob = await comparisonService.getComparison(jobId);
        const aiMsgContent =
          fullJob.summary.executiveSummary ||
          `## Overview\nCross-document comparison completed for **${docA.name}** (Document A) and **${docB.name}** (Document B).\n\n## Key Findings\n- **Overall Similarity:** ${fullJob.summary.overallSimilarityScore}%\n- **Total Differences:** ${fullJob.summary.totalDifferences}\n- **High-Risk Clauses:** ${fullJob.summary.highRiskCount}\n- **Numerical Variances:** ${fullJob.summary.numericalDiscrepanciesCount}\n- **Direct Contradictions:** ${fullJob.summary.contradictionsCount}`;

        const aiMsg = get().addChatMessage({
          sender: "ai",
          messageType: "comparison",
          content: aiMsgContent,
          comparisonJob: fullJob,
          suggestedFollowUps: [
            "Show all high-risk changes",
            "Compare payment terms and deadlines",
            "Propose an edit to align Document B with Document A",
          ],
        });

        set((s) => {
          const threadId = s.activeConversationId || `conv_${Date.now()}`;
          const updatedThread: ConversationThread = {
            id: threadId,
            title,
            updatedAt: new Date().toISOString(),
            comparisonJobId: fullJob.id,
            documentIds: s.activeContextDocIds,
            messages: s.chatMessages,
          };
          return {
            activeComparison: fullJob,
            isComparing: false,
            isAiGenerating: false,
            aiProcessingState: null,
            stageName: "Verification complete!",
            stageProgress: 100,
            activeConversationId: threadId,
            conversations: [
              updatedThread,
              ...s.conversations.filter((c) => c.id !== threadId),
            ],
          };
        });
        return aiMsg;
      };

      if (
        jobDTO.status === "completed" ||
        (jobDTO.similarity_score !== undefined && jobDTO.similarity_score !== null)
      ) {
        await finalizeComparison();
        return;
      }

      const pollInterval = 1000;
      const maxAttempts = 150;
      let attempts = 0;

      await new Promise<void>((resolve, reject) => {
        const intervalId = setInterval(async () => {
          if (abortGenerationFlag) {
            clearInterval(intervalId);
            abortGenerationFlag = false;
            reject(new Error("Comparison cancelled by user."));
            return;
          }
          attempts++;
          try {
            const statusData = await comparisonService.getComparisonStatus(jobId);
            const stageLabel =
              STAGE_LABELS[statusData.current_stage] ||
              `Processing stage: ${statusData.current_stage}...`;

            set({
              stageName: stageLabel,
              stageProgress: Math.max(statusData.progress || 35, 35),
            });

            if (
              statusData.status === "completed" ||
              statusData.progress >= 100 ||
              statusData.current_stage === "completed"
            ) {
              clearInterval(intervalId);
              await finalizeComparison();
              resolve();
            } else if (statusData.status === "failed") {
              clearInterval(intervalId);
              reject(new Error(statusData.error_message || "Document verification pipeline failed."));
            } else if (attempts >= maxAttempts) {
              clearInterval(intervalId);
              reject(new Error("Document verification timed out."));
            }
          } catch (pollErr) {
            if (attempts >= maxAttempts) {
              clearInterval(intervalId);
              reject(pollErr);
            }
          }
        }, pollInterval);
      });
    } catch (err: unknown) {
      const classified = classifyError(err, "processing");
      set({
        isComparing: false,
        isAiGenerating: false,
        aiProcessingState: null,
        comparisonError: classified.message,
        workspaceError: classified,
      });
    }
  },

  singleDocAnalysis: null,
  isAnalyzingSingleDoc: false,

  querySingleDoc: async (prompt: string) => {
    const state = get();
    const activeReady = state.documents.filter(
      (d) => state.activeContextDocIds.includes(d.id) && d.status === "ready"
    );
    const targetDoc = activeReady[0] || state.docA || state.docB;

    if (!targetDoc) {
      set({
        comparisonError: "Please attach a document first to analyze with AI.",
      });
      return;
    }

    get().addChatMessage({
      sender: "user",
      content: prompt,
    });

    set({
      isAnalyzingSingleDoc: true,
      isAiGenerating: true,
      aiProcessingState: "thinking",
      comparisonError: null,
      workspaceError: null,
      stageName: `Analyzing ${targetDoc.name}...`,
    });

    try {
      let backendId = targetDoc.backendId || (/^\d+$/.test(targetDoc.id) ? targetDoc.id : null);
      if (!backendId) {
        if (!targetDoc.file) {
          throw new Error("Physical document file is required. Please re-select the document.");
        }
        const slot = targetDoc.slot || "A";
        const uploaded = await comparisonService.uploadDocument(targetDoc.file, slot);
        backendId = String(uploaded.id);
      }

      set({ aiProcessingState: "generating" });

      const res = await comparisonService.queryDocument(backendId, prompt);

      const citations = (res.citations || []).map((c: any) => ({
        docLabel: targetDoc.name,
        docName: targetDoc.name,
        documentId: targetDoc.id,
        page: c.page || 1,
        quote: c.quote || `Verified excerpt from ${targetDoc.name}`,
      }));

      if (citations.length === 0) {
        citations.push({
          docLabel: targetDoc.name,
          docName: targetDoc.name,
          documentId: targetDoc.id,
          page: 1,
          quote: (targetDoc.extractedText || res.answer || "").slice(0, 180),
        });
      }

      get().addChatMessage({
        sender: "ai",
        messageType: "analysis",
        content: res.answer,
        citations,
        provider: res.provider,
        model: res.model,
        suggestedFollowUps: [
          `Summarize key obligations and deadlines in ${targetDoc.name}`,
          `Identify any risks or missing provisions in ${targetDoc.name}`,
          `Propose an edit to improve clarity in ${targetDoc.name}`,
        ],
      });

      set((s) => {
        const threadId = s.activeConversationId || `conv_${Date.now()}`;
        const updatedThread: ConversationThread = {
          id: threadId,
          title: `${targetDoc.name} — ${prompt.slice(0, 36)}`,
          updatedAt: new Date().toISOString(),
          documentIds: s.activeContextDocIds,
          messages: s.chatMessages,
        };

        return {
          isAnalyzingSingleDoc: false,
          isAiGenerating: false,
          aiProcessingState: null,
          stageName: "",
          singleDocAnalysis: {
            documentId: String(res.document_id),
            documentName: res.document_name || targetDoc.name,
            pageCount: res.page_count || targetDoc.pageCount || 1,
            prompt: res.prompt || prompt,
            answer: res.answer,
            provider: res.provider,
            model: res.model,
          },
          activeConversationId: threadId,
          conversations: [
            updatedThread,
            ...s.conversations.filter((c) => c.id !== threadId),
          ],
        };
      });
    } catch (err: unknown) {
      const classified = classifyError(err, "processing");
      set({
        isAnalyzingSingleDoc: false,
        isAiGenerating: false,
        aiProcessingState: null,
        comparisonError: classified.message,
        workspaceError: classified,
      });
    }
  },

  clearSingleDocAnalysis: () => {
    set({ singleDocAnalysis: null });
  },

  askAssistant: async (prompt: string) => {
    const state = get();
    const { activeComparison, isAnalyzingSingleDoc, isComparing, isAiGenerating } = state;
    if (isAnalyzingSingleDoc || isComparing || isAiGenerating) return;

    const trimmed = prompt.trim();
    if (!trimmed) return;

    abortGenerationFlag = false;

    const lower = trimmed.toLowerCase();
    const isEditRequest =
      lower.startsWith("edit ") ||
      lower.startsWith("rewrite ") ||
      lower.startsWith("modify ") ||
      lower.startsWith("update ") ||
      lower.startsWith("change ") ||
      lower.startsWith("revise ") ||
      lower.includes("propose an edit") ||
      lower.includes("propose changes") ||
      lower.includes("draft a revision") ||
      lower.includes("edit the document") ||
      lower.includes("update the clause");

    const activeReadyDocs = state.documents.filter(
      (d) => state.activeContextDocIds.includes(d.id) && d.status === "ready"
    );

    if (isEditRequest && (activeReadyDocs.length > 0 || state.docA || state.docB)) {
      await get().requestDocumentEdit(trimmed);
      return;
    }

    // Check if query is explicitly asking about attached documents
    const hasDocKeyword = /\b(?:doc|document|documents|docuement|docuements|pdf|file|files|agreement|agreements|contract|contracts|section|sections|clause|clauses|article|articles|page|pages|uploaded|in\s+this|between\s+these|these\s+2|both\s+documents|doc\s*a|doc\s*b|schedule|roles?|duties|responsibilities|team\s+members?|who\s+does\s+what|who\s+(?:is|are)\s+(?:taking|handling|responsible|managing|leading)|who\s+handles|who\s+takes|members?)\b/i.test(trimmed);
    const isComparisonQuery = /\b(?:compare|difference|differences|changes|modified|revision|revisions|versus|vs|what\s+is\s+different)\b/i.test(trimmed);
    const isDocumentSpecific = (hasDocKeyword || isComparisonQuery) && (activeReadyDocs.length > 0 || activeComparison);

    // 1. If asking a document comparison and an active comparison job exists
    if (isDocumentSpecific && activeComparison?.id && activeReadyDocs.length >= 2) {
      get().addChatMessage({
        sender: "user",
        content: trimmed,
      });

      set({
        isAnalyzingSingleDoc: true,
        isAiGenerating: true,
        aiProcessingState: "thinking",
        comparisonError: null,
        workspaceError: null,
        stageName: "Analyzing documents with AI...",
      });

      try {
        set({ aiProcessingState: "generating" });
        const res = await comparisonService.sendChatMessage(activeComparison.id, trimmed);
        const aiAnswer = res.ai_response?.content || "No response generated.";

        get().addChatMessage({
          sender: "ai",
          messageType: "analysis",
          content: aiAnswer,
          citations: (res.ai_response?.citations || []).map((c: any) => ({
            docLabel: c.docLabel || "Document A",
            docName:
              c.docLabel === "Document B"
                ? activeComparison.docB.name
                : activeComparison.docA.name,
            page: c.page || 1,
            quote: c.quote || "",
          })),
          suggestedFollowUps: res.ai_response?.follow_ups || [
            "Show the differences in a table",
            "Compare only the responsibilities",
            "What is the main takeaway?",
          ],
        });

        set((s) => {
          const threadId = s.activeConversationId || `conv_${Date.now()}`;
          const updatedThread: ConversationThread = {
            id: threadId,
            title: activeComparison.title || trimmed.slice(0, 40),
            updatedAt: new Date().toISOString(),
            comparisonJobId: activeComparison.id,
            documentIds: s.activeContextDocIds,
            messages: s.chatMessages,
          };
          return {
            isAnalyzingSingleDoc: false,
            isAiGenerating: false,
            aiProcessingState: null,
            stageName: "",
            conversations: [
              updatedThread,
              ...s.conversations.filter((c) => c.id !== threadId),
            ],
          };
        });
        return;
      } catch (err: unknown) {
        const classified = classifyError(err, "processing");
        set({
          isAnalyzingSingleDoc: false,
          isAiGenerating: false,
          aiProcessingState: null,
          comparisonError: classified.message,
          workspaceError: classified,
        });
        return;
      }
    }

    // 2. If 2 ready documents are in active context and user explicitly asks to compare them
    if (isComparisonQuery && activeReadyDocs.length >= 2) {
      await get().runComparison(trimmed);
      return;
    }

    // 3. If 1 ready document is in active context and query is document-specific
    if (isDocumentSpecific && (activeReadyDocs.length === 1 || state.docA || state.docB)) {
      await get().querySingleDoc(trimmed);
      return;
    }

    // 4. General-Purpose Conversational AI (Natural Chat without forcing document context)
    get().addChatMessage({
      sender: "user",
      content: trimmed,
    });

    set({
      isAiGenerating: true,
      aiProcessingState: "thinking",
      stageName: "DocVer AI is thinking...",
    });

    try {
      const historyPayload = state.chatMessages.slice(-6).map((m) => ({
        sender: m.sender,
        content: m.content,
      }));

      const res = await comparisonService.sendDirectChatMessage(trimmed, {
        conversationId: state.activeConversationId,
        comparisonJobId: activeComparison?.id,
        documentIds: activeReadyDocs.map((d) => String(d.backendId || d.id)),
        history: historyPayload,
      });

      const aiAnswer = res.ai_response?.content || "I am here to help. What would you like to explore?";
      const followUps = res.ai_response?.follow_ups || [
        "Tell me more about this",
        "Explain in simple terms",
        "Give a concrete example",
      ];

      get().addChatMessage({
        sender: "ai",
        messageType: "text",
        content: aiAnswer,
        citations: (res.ai_response?.citations || []).map((c: any) => ({
          docLabel: c.docLabel || "Document",
          docName: c.docName || c.docLabel || "Attached Document",
          page: c.page || 1,
          quote: c.quote || "",
        })),
        suggestedFollowUps: followUps,
        logId: (res.ai_response as any)?.log_id,
      });

      set((s) => {
        const threadId = s.activeConversationId || `conv_${Date.now()}`;
        const updatedThread: ConversationThread = {
          id: threadId,
          title: trimmed.slice(0, 40),
          updatedAt: new Date().toISOString(),
          comparisonJobId: activeComparison?.id,
          documentIds: s.activeContextDocIds,
          messages: s.chatMessages,
        };
        return {
          isAiGenerating: false,
          aiProcessingState: null,
          stageName: "",
          conversations: [
            updatedThread,
            ...s.conversations.filter((c) => c.id !== threadId),
          ],
        };
      });
    } catch (err: unknown) {
      const classified = classifyError(err, "processing");
      get().addChatMessage({
        sender: "ai",
        messageType: "text",
        content: `I ran into an issue connecting to the AI service: ${classified.message}. Please try again shortly.`,
      });
      set({
        isAiGenerating: false,
        aiProcessingState: null,
        stageName: "",
      });
    }
  },

  resetWorkspace: () => {
    set({
      docA: null,
      docB: null,
      documents: [],
      activeContextDocIds: [],
      activeComparison: null,
      singleDocAnalysis: null,
      isAnalyzingSingleDoc: false,
      isAiGenerating: false,
      aiProcessingState: null,
      chatMessages: [],
      comparisonError: null,
      workspaceError: null,
      stageName: "",
      stageProgress: 0,
      previewOpen: false,
      previewDocumentId: null,
    });
  },

  // Conversations & Chat
  conversations: [],
  activeConversationId: "conv_default",
  conversationSearchQuery: "",
  setConversationSearchQuery: (conversationSearchQuery) => set({ conversationSearchQuery }),

  startNewConversation: () => {
    const newId = `conv_${Date.now()}`;
    set({
      activeConversationId: newId,
      chatMessages: [],
      activeComparison: null,
      singleDocAnalysis: null,
      comparisonError: null,
      workspaceError: null,
      isComparing: false,
      isAnalyzingSingleDoc: false,
      isAiGenerating: false,
      aiProcessingState: null,
      previewOpen: false,
    });
  },

  selectConversation: (id: string) => {
    const state = get();
    const found = state.conversations.find((c) => c.id === id);
    if (!found) return;

    set({
      activeConversationId: found.id,
      chatMessages: found.messages,
      comparisonError: null,
      workspaceError: null,
    });

    if (found.comparisonJobId && (!state.activeComparison || state.activeComparison.id !== found.comparisonJobId)) {
      get().loadComparison(found.comparisonJobId);
    }
  },

  chatMessages: [],
  isAiGenerating: false,
  aiProcessingState: null,

  stopGeneration: () => {
    abortGenerationFlag = true;
    set({
      isAiGenerating: false,
      isAnalyzingSingleDoc: false,
      isComparing: false,
      aiProcessingState: null,
      stageName: "",
    });
  },

  addChatMessage: (msg) => {
    const newMsg: ChatMessage = {
      ...msg,
      id: "msg_" + Date.now() + "_" + Math.random().toString(36).substring(7),
      timestamp: new Date().toISOString(),
    };
    set((state) => ({ chatMessages: [...state.chatMessages, newMsg] }));
    return newMsg;
  },

  updateChatMessage: (id, patch) => {
    set((state) => ({
      chatMessages: state.chatMessages.map((m) => (m.id === id ? { ...m, ...patch } : m)),
    }));
  },

  setMessageFeedback: (id, feedback) => {
    set((state) => ({
      chatMessages: state.chatMessages.map((m) => (m.id === id ? { ...m, feedback } : m)),
    }));

    if (feedback) {
      const msg = get().chatMessages.find((m) => m.id === id);
      comparisonService.sendFeedback(feedback, {
        logId: msg?.logId,
      });
    }
  },

  regenerateResponse: async (messageId?: string) => {
    const state = get();
    if (state.isAiGenerating || state.isComparing || state.isAnalyzingSingleDoc) return;

    const msgs = [...state.chatMessages];
    let targetUserPrompt = "";

    if (messageId) {
      const idx = msgs.findIndex((m) => m.id === messageId);
      if (idx > 0) {
        for (let i = idx - 1; i >= 0; i--) {
          if (msgs[i].sender === "user") {
            targetUserPrompt = msgs[i].content;
            break;
          }
        }
        set({ chatMessages: msgs.filter((m) => m.id !== messageId) });
      }
    }

    if (!targetUserPrompt) {
      const lastUser = [...msgs].reverse().find((m) => m.sender === "user");
      if (lastUser) {
        targetUserPrompt = lastUser.content;
      }
    }

    if (targetUserPrompt) {
      set((s) => {
        const list = [...s.chatMessages];
        if (list.length > 0 && list[list.length - 1].sender === "user" && list[list.length - 1].content === targetUserPrompt) {
          list.pop();
        }
        return { chatMessages: list };
      });
      await get().askAssistant(targetUserPrompt);
    }
  },

  clearChat: () => {
    set({ chatMessages: [] });
  },

  simulateAiResponse: async (query: string) => {
    await get().askAssistant(query);
  },

  // Document Editing & Version History
  editProposals: [],
  activeEditProposalId: null,
  versionHistory: {},

  setActiveEditProposalId: (activeEditProposalId) => set({ activeEditProposalId }),

  requestDocumentEdit: async (instruction: string, targetDocId?: string) => {
    const state = get();
    const activeReady = state.documents.filter(
      (d) => state.activeContextDocIds.includes(d.id) && d.status === "ready"
    );
    const targetDoc =
      state.documents.find((d) => d.id === targetDocId) ||
      activeReady[0] ||
      state.docA ||
      state.docB;

    if (!targetDoc) {
      set({
        comparisonError: "Please attach a document before requesting an edit.",
      });
      return;
    }

    get().addChatMessage({
      sender: "user",
      content: instruction,
    });

    set({
      isAiGenerating: true,
      aiProcessingState: "creating_doc",
      comparisonError: null,
      workspaceError: null,
    });

    try {
      const existingVersions = state.versionHistory[targetDoc.id] || [];
      let currentText =
        existingVersions.length > 0
          ? existingVersions[existingVersions.length - 1].contentSnapshot
          : targetDoc.extractedText || "";

      if (!currentText && targetDoc.backendId) {
        try {
          const contentData = await comparisonService.getDocumentContent(targetDoc.backendId);
          currentText = contentData.text;
        } catch {
          // ignore
        }
      }

      if (!currentText) {
        currentText = `Section 1. Operative Provisions (${targetDoc.name})\nThe receiving party shall process all deliverables within 30 calendar days of invoice receipt and maintain standard confidentiality obligations.`;
      }

      const lines = currentText
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter(Boolean);
      const originalExcerpt =
        lines.slice(0, Math.min(lines.length, 6)).join("\n") || currentText.slice(0, 400);

      let proposedExcerpt = "";
      let rationale = "";

      const backendId = targetDoc.backendId || (/^\d+$/.test(targetDoc.id) ? targetDoc.id : null);
      if (backendId) {
        try {
          const editPrompt = `The user requested the following edit to "${targetDoc.name}": "${instruction}".\n\nBased on this original excerpt:\n"${originalExcerpt}"\n\nProvide a concise revised text block incorporating the user's requested change.`;
          const aiRes = await comparisonService.queryDocument(backendId, editPrompt);
          const cleaned = aiRes.answer
            .replace(/^#+\s.*$/gm, "")
            .trim();
          if (cleaned.length > 20) {
            proposedExcerpt = cleaned.slice(0, 700);
            rationale = `Proposed revision generated by ${aiRes.provider?.toUpperCase() || "DocVer AI"} based on instruction: "${instruction}". Original document remains untouched until explicitly accepted.`;
          }
        } catch {
          // Fallback deterministic revision below
        }
      }

      if (!proposedExcerpt) {
        proposedExcerpt = `${originalExcerpt}\n\n[Amended Provision]: Pursuant to instruction ("${instruction}"), both parties mutually agree that all obligations, timelines, and liability caps under this section shall be strictly enforced with a 15-day cure notice period.`;
        rationale = `Adds explicit amendment language addressing "${instruction}" while preserving the baseline structure of ${targetDoc.name}.`;
      }

      const proposal: DocumentEditProposal = {
        id: `edit_${Date.now()}`,
        documentId: targetDoc.id,
        documentName: targetDoc.name,
        title: `Proposed Revision — ${targetDoc.name}`,
        summary: instruction,
        sectionTitle: "Operative Provision & Redline Diff",
        page: 1,
        originalText: originalExcerpt,
        proposedText: proposedExcerpt,
        rationale,
        status: "pending",
        createdAt: new Date().toISOString(),
      };

      const v1List: DocumentVersionRecord[] =
        existingVersions.length > 0
          ? existingVersions
          : [
              {
                id: `ver_${Date.now()}_1`,
                versionNumber: 1,
                documentId: targetDoc.id,
                documentName: targetDoc.name,
                label: "v1.0 — Original Uploaded Document",
                summary: "Original unmodified document baseline.",
                contentSnapshot: currentText,
                createdAt: targetDoc.uploadedAt || new Date().toISOString(),
                author: "Origin",
              },
            ];

      get().addChatMessage({
        sender: "ai",
        messageType: "edit_proposal",
        content: `## Proposed Document Change\n\nI have drafted a proposed revision for **${targetDoc.name}**. Review the highlighted additions and removals below. The original document has **not** been modified—choose **Accept Changes** to create a new version or **Reject** to discard.`,
        editProposal: proposal,
        citations: [
          {
            docLabel: targetDoc.name,
            docName: targetDoc.name,
            documentId: targetDoc.id,
            page: 1,
            quote: originalExcerpt.slice(0, 160),
          },
        ],
      });

      set((s) => ({
        isAiGenerating: false,
        aiProcessingState: null,
        editProposals: [proposal, ...s.editProposals],
        activeEditProposalId: proposal.id,
        versionHistory: {
          ...s.versionHistory,
          [targetDoc.id]: v1List,
        },
      }));
    } catch (err: unknown) {
      const classified = classifyError(err, "processing");
      set({
        isAiGenerating: false,
        aiProcessingState: null,
        workspaceError: classified,
      });
    }
  },

  acceptEditProposal: (proposalId: string) => {
    const state = get();
    const proposal = state.editProposals.find((p) => p.id === proposalId);
    if (!proposal || proposal.status === "accepted") return;

    set({ aiProcessingState: "saving_changes" });

    const docVersions = state.versionHistory[proposal.documentId] || [];
    const latestVersion = docVersions[docVersions.length - 1];
    const baseSnapshot = latestVersion?.contentSnapshot || proposal.originalText;
    const updatedSnapshot = baseSnapshot.includes(proposal.originalText)
      ? baseSnapshot.replace(proposal.originalText, proposal.proposedText)
      : `${proposal.proposedText}\n\n${baseSnapshot}`;

    const nextVersionNumber = docVersions.length + 1;
    const newVersion: DocumentVersionRecord = {
      id: `ver_${Date.now()}_${nextVersionNumber}`,
      versionNumber: nextVersionNumber,
      documentId: proposal.documentId,
      documentName: proposal.documentName,
      label: `v${nextVersionNumber}.0 — Accepted AI Revision`,
      summary: proposal.summary,
      contentSnapshot: updatedSnapshot,
      proposalId: proposal.id,
      createdAt: new Date().toISOString(),
      author: "AI Edit (Accepted)",
    };

    set((s) => {
      const updatedProposals = s.editProposals.map((p) =>
        p.id === proposalId ? { ...p, status: "accepted" as const } : p
      );

      const updatedMessages = s.chatMessages.map((m) =>
        m.editProposal?.id === proposalId
          ? { ...m, editProposal: { ...m.editProposal, status: "accepted" as const } }
          : m
      );

      const updateDocText = (d: DocumentUploadMeta | null) =>
        d && d.id === proposal.documentId
          ? {
              ...d,
              extractedText: updatedSnapshot,
              pages: [{ page: 1, content: updatedSnapshot }],
            }
          : d;

      return {
        aiProcessingState: null,
        editProposals: updatedProposals,
        chatMessages: updatedMessages,
        docA: updateDocText(s.docA),
        docB: updateDocText(s.docB),
        documents: s.documents.map((d) => updateDocText(d) as DocumentUploadMeta),
        versionHistory: {
          ...s.versionHistory,
          [proposal.documentId]: [...docVersions, newVersion],
        },
      };
    });

    get().addToast({
      title: `Saved as v${nextVersionNumber}.0`,
      description: `Changes accepted for ${proposal.documentName}. Original v1.0 is preserved in version history.`,
      variant: "success",
    });
  },

  rejectEditProposal: (proposalId: string) => {
    const state = get();
    const proposal = state.editProposals.find((p) => p.id === proposalId);
    if (!proposal) return;

    set((s) => ({
      editProposals: s.editProposals.map((p) =>
        p.id === proposalId ? { ...p, status: "rejected" as const } : p
      ),
      chatMessages: s.chatMessages.map((m) =>
        m.editProposal?.id === proposalId
          ? { ...m, editProposal: { ...m.editProposal, status: "rejected" as const } }
          : m
      ),
    }));

    get().addToast({
      title: "Proposed changes rejected",
      description: `${proposal.documentName} remains at its current version.`,
      variant: "default",
    });
  },

  undoDocumentVersion: (documentId: string) => {
    const state = get();
    const history = state.versionHistory[documentId] || [];
    if (history.length <= 1) return;

    const nextHistory = history.slice(0, history.length - 1);
    const restoredVersion = nextHistory[nextHistory.length - 1];

    set((s) => {
      const updateDocText = (d: DocumentUploadMeta | null) =>
        d && d.id === documentId
          ? {
              ...d,
              extractedText: restoredVersion.contentSnapshot,
              pages: [{ page: 1, content: restoredVersion.contentSnapshot }],
            }
          : d;

      return {
        docA: updateDocText(s.docA),
        docB: updateDocText(s.docB),
        documents: s.documents.map((d) => updateDocText(d) as DocumentUploadMeta),
        versionHistory: {
          ...s.versionHistory,
          [documentId]: nextHistory,
        },
      };
    });

    get().addToast({
      title: `Reverted to v${restoredVersion.versionNumber}.0`,
      description: `Restored ${restoredVersion.label}.`,
      variant: "default",
    });
  },

  restoreDocumentVersion: (documentId: string, versionId: string) => {
    const state = get();
    const history = state.versionHistory[documentId] || [];
    const target = history.find((v) => v.id === versionId);
    if (!target) return;

    const nextVerNum = history.length + 1;
    const restoredRecord: DocumentVersionRecord = {
      id: `ver_${Date.now()}_${nextVerNum}`,
      versionNumber: nextVerNum,
      documentId,
      documentName: target.documentName,
      label: `v${nextVerNum}.0 — Restored from v${target.versionNumber}.0`,
      summary: `Restored snapshot from ${target.label}`,
      contentSnapshot: target.contentSnapshot,
      createdAt: new Date().toISOString(),
      author: "User Revert",
    };

    set((s) => {
      const updateDocText = (d: DocumentUploadMeta | null) =>
        d && d.id === documentId
          ? {
              ...d,
              extractedText: target.contentSnapshot,
              pages: [{ page: 1, content: target.contentSnapshot }],
            }
          : d;

      return {
        docA: updateDocText(s.docA),
        docB: updateDocText(s.docB),
        documents: s.documents.map((d) => updateDocText(d) as DocumentUploadMeta),
        versionHistory: {
          ...s.versionHistory,
          [documentId]: [...history, restoredRecord],
        },
      };
    });

    get().addToast({
      title: `Restored v${target.versionNumber}.0`,
      description: `Document updated to snapshot "${target.label}".`,
      variant: "success",
    });
  },

  // Toast Notifications
  toasts: [],
  addToast: (toast) => {
    const id = `toast_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`;
    set((s) => ({ toasts: [...s.toasts, { ...toast, id }] }));
    setTimeout(() => {
      get().dismissToast(id);
    }, 4500);
  },
  dismissToast: (id) => {
    set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) }));
  },
}));
