import { apiClient } from "@/lib/api-client";
import {
  ComparisonJobDTO,
  DocumentMetadataDTO,
  ChatMessageDTO,
} from "@/types/api-contract";
import {
  ComparisonJob,
  ClauseComparisonItem,
  NumericalDiscrepancyItem,
  ContradictionItem,
  DifferenceType,
  RiskLevel,
} from "@/types/comparison";

export function transformBackendJobToFrontend(dto: ComparisonJobDTO): ComparisonJob {
  const report = dto.report;
  const summary = report?.summary || {
    overall_similarity_score: dto.similarity_score ?? 100,
    total_differences: 0,
    high_risk_count: 0,
    medium_risk_count: 0,
    low_risk_count: 0,
    matching_clauses_count: 0,
    missing_clauses_count: 0,
    modified_clauses_count: 0,
    numerical_discrepancies_count: 0,
    contradictions_count: 0,
  };

  const findings = report?.findings || [];
  const rawClauses = (report as any)?.clauses || [];
  const rawNumerical = (report as any)?.numerical_discrepancies || [];
  const rawContradictions = (report as any)?.contradictions || [];

  // Group findings if pre-split collections are empty
  const clauseFindings = rawClauses.length > 0
    ? rawClauses
    : findings.filter((f) => !["numerical", "financial", "contradiction"].includes(f.type));

  const numFindings = rawNumerical.length > 0
    ? rawNumerical
    : findings.filter((f) => ["numerical", "financial"].includes(f.type));

  const contraFindings = rawContradictions.length > 0
    ? rawContradictions
    : findings.filter((f) => f.type === "contradiction");

  const sanitizeText = (val: any, fallback: string) => {
    if (typeof val === "string" && (val.includes("documents/") || val.includes("staging/") || val.endsWith(".pdf"))) {
      return fallback;
    }
    return typeof val === "string" && val.trim() ? val : fallback;
  };

  const clauses: ClauseComparisonItem[] = clauseFindings.map((f: any, idx: number) => {
    const metrics = f.metrics || {};
    const fallbackText = f.explanation || f.title || "Operative contractual provision";
    const rawDocA = f.doc_a_content || metrics.doc_a_content || f.doc_a_evidence || metrics.doc_a_evidence || fallbackText;
    const rawDocB = f.doc_b_content || metrics.doc_b_content || f.doc_b_evidence || metrics.doc_b_evidence || fallbackText;

    return {
      id: String(f.id || `clause_${idx}`),
      category: f.category || "General Terms",
      type: (f.type || "modified") as DifferenceType,
      riskLevel: (f.risk_level || "low") as RiskLevel,
      title: f.title || "Clause Provision",
      docAContent: sanitizeText(rawDocA, fallbackText),
      docBContent: sanitizeText(rawDocB, fallbackText),
      docAPage: f.doc_a_page ?? 1,
      docBPage: f.doc_b_page ?? 1,
      explanation: f.explanation || "",
      evidenceSnippet: sanitizeText(f.doc_b_evidence || metrics.doc_b_evidence || f.doc_a_evidence || metrics.doc_a_evidence, fallbackText),
      significance: f.significance || metrics.impact || metrics.risk_rationale || "Operational and legal review recommended.",
    };
  });

  const numericalDiscrepancies: NumericalDiscrepancyItem[] = numFindings.map((f: any, idx: number) => {
    const metrics = f.metrics || {};
    return {
      id: String(f.id || `num_${idx}`),
      metric: f.metric || metrics.metric || f.title || "Value Audit",
      docAValue: f.doc_a_value || metrics.value_a || metrics.doc_a_value || "Baseline",
      docBValue: f.doc_b_value || metrics.value_b || metrics.doc_b_value || "Revised",
      variancePercentage: f.variance_percentage || metrics.variance || (metrics.variance_percent ? `${metrics.variance_percent}%` : undefined),
      context: f.explanation || f.title,
      riskLevel: (f.risk_level || "medium") as RiskLevel,
      docAPage: f.doc_a_page ?? 1,
      docBPage: f.doc_b_page ?? 1,
    };
  });

  const contradictions: ContradictionItem[] = contraFindings.map((f: any, idx: number) => {
    const metrics = f.metrics || {};
    return {
      id: String(f.id || `contra_${idx}`),
      topic: f.topic || metrics.topic || f.title || "Contradiction",
      docAClaim: f.doc_a_claim || metrics.doc_a_claim || metrics.doc_a_evidence || f.explanation,
      docBClaim: f.doc_b_claim || metrics.doc_b_claim || metrics.doc_b_evidence || f.explanation,
      legalImpact: f.legal_impact || metrics.legal_impact || f.significance || f.explanation,
      riskLevel: (f.risk_level || "high") as RiskLevel,
      docAPage: f.doc_a_page ?? 1,
      docBPage: f.doc_b_page ?? 1,
    };
  });

  return {
    id: String(dto.id),
    title: dto.title,
    status: dto.status,
    createdAt: dto.created_at,
    completedAt: dto.completed_at || undefined,
    docA: {
      id: dto.document_a?.id,
      name: dto.document_a?.name || "Document A",
      size: dto.document_a?.size || 0,
      pages: dto.document_a?.page_count || 1,
      type: dto.document_a?.mime_type || "application/pdf",
    },
    docB: {
      id: dto.document_b?.id,
      name: dto.document_b?.name || "Document B",
      size: dto.document_b?.size || 0,
      pages: dto.document_b?.page_count || 1,
      type: dto.document_b?.mime_type || "application/pdf",
    },
    summary: {
      overallSimilarityScore: dto.similarity_score ?? summary.overall_similarity_score ?? 100,
      totalDifferences: summary.total_differences ?? 0,
      highRiskCount: summary.high_risk_count ?? 0,
      mediumRiskCount: summary.medium_risk_count ?? 0,
      lowRiskCount: summary.low_risk_count ?? 0,
      matchingClausesCount: summary.matching_clauses_count ?? 0,
      missingClausesCount: summary.missing_clauses_count ?? 0,
      modifiedClausesCount: summary.modified_clauses_count ?? 0,
      numericalDiscrepanciesCount: summary.numerical_discrepancies_count ?? 0,
      contradictionsCount: summary.contradictions_count ?? 0,
      executiveSummary: report?.executive_summary || (summary as any)?.executive_summary || dto.report?.executive_summary,
    },
    clauses,
    numericalDiscrepancies,
    contradictions,
  };
}

export const comparisonService = {
  /**
   * Upload a physical document file to backend staging
   */
  async uploadDocument(file: File, slot: "A" | "B", workspaceId?: number): Promise<DocumentMetadataDTO> {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("slot", slot);
    if (workspaceId) {
      formData.append("workspace_id", String(workspaceId));
    }

    const endpoint = workspaceId
      ? `/workspaces/${workspaceId}/documents/upload`
      : "/upload";

    const res = await apiClient.post<DocumentMetadataDTO>(endpoint, formData);
    return res.data;
  },

  /**
   * Create and queue a new comparison job
   */
  async createComparison(
    title: string,
    docAId: number,
    docBId: number,
    workspaceId?: number,
    options?: { focus_prompt?: string; [key: string]: any }
  ): Promise<ComparisonJobDTO> {
    const payload = {
      title,
      document_a_id: docAId,
      document_b_id: docBId,
      options: options || {},
      focus_prompt: options?.focus_prompt,
    };

    const endpoint = workspaceId
      ? `/workspaces/${workspaceId}/comparisons`
      : "/comparisons";

    const res = await apiClient.post<ComparisonJobDTO>(endpoint, payload);
    return res.data;
  },

  /**
   * Poll status of an ongoing comparison
   */
  async getComparisonStatus(jobId: number | string): Promise<{
    job_id: number;
    status: string;
    progress: number;
    current_stage: string;
    error_message?: string;
  }> {
    const res = await apiClient.get<any>(`/comparisons/${jobId}/status`);
    const data = res.data || {};
    const status =
      data.status ||
      (data.progress >= 100 || data.stage === "completed" || data.current_stage === "completed"
        ? "completed"
        : data.stage === "failed" || data.current_stage === "failed"
        ? "failed"
        : "processing");

    return {
      job_id: data.job_id || Number(jobId),
      status,
      progress: data.progress ?? (status === "completed" ? 100 : 25),
      current_stage: data.current_stage || data.stage || "processing",
      error_message: data.error_message || data.meta?.error,
    };
  },

  /**
   * Fetch full comparison report and findings
   */
  async getComparison(jobId: number | string): Promise<ComparisonJob> {
    const res = await apiClient.get<ComparisonJobDTO>(`/comparisons/${jobId}`);
    return transformBackendJobToFrontend(res.data);
  },

  /**
   * List past comparisons
   */
  async listComparisons(workspaceId?: number): Promise<ComparisonJob[]> {
    const endpoint = workspaceId
      ? `/workspaces/${workspaceId}/comparisons`
      : "/comparisons";

    const res = await apiClient.get<ComparisonJobDTO[] | { data: ComparisonJobDTO[] }>(endpoint);
    const items = Array.isArray(res.data) ? res.data : (res.data as any)?.data || [];
    return items.map(transformBackendJobToFrontend);
  },

  /**
   * Cancel an ongoing comparison
   */
  async cancelComparison(jobId: number | string): Promise<void> {
    await apiClient.post(`/comparisons/${jobId}/cancel`);
  },

  /**
   * Send a contextual chat query to the comparison AI
   */
  async sendChatMessage(
    jobId: number | string,
    message: string
  ): Promise<{
    user_message: ChatMessageDTO;
    ai_response: ChatMessageDTO;
    is_quota_error?: boolean;
    quota_details?: Record<string, unknown> | null;
  }> {
    const res = await apiClient.post<{
      user_message: ChatMessageDTO;
      ai_response: ChatMessageDTO;
      is_quota_error?: boolean;
      quota_details?: Record<string, unknown> | null;
    }>(
      `/comparisons/${jobId}/chat`,
      { message }
    );
    return res.data;
  },

  /**
   * Send a general conversational AI inquiry or document-assisted chat message
   */
  async sendDirectChatMessage(
    message: string,
    options?: {
      conversationId?: string;
      comparisonJobId?: string | number | null;
      documentIds?: (string | number)[];
      history?: Array<{ sender: string; content: string }>;
    }
  ): Promise<{
    user_message?: ChatMessageDTO;
    ai_response: ChatMessageDTO;
    is_quota_error?: boolean;
    quota_details?: Record<string, unknown> | null;
  }> {
    const res = await apiClient.post<{
      user_message?: ChatMessageDTO;
      ai_response: ChatMessageDTO;
      is_quota_error?: boolean;
      quota_details?: Record<string, unknown> | null;
    }>('/chat', {
      message,
      conversation_id: options?.conversationId,
      comparison_job_id: options?.comparisonJobId,
      document_ids: options?.documentIds,
      history: options?.history,
    });
    return res.data;
  },

  /**
   * Clear chat history for a comparison
   */
  async clearChat(jobId: number | string): Promise<void> {
    await apiClient.delete(`/comparisons/${jobId}/chat`);
  },

  /**
   * Ask questions, summarize, or extract items from a single document
   */
  async queryDocument(
    documentId: number | string,
    prompt: string
  ): Promise<{
    document_id: number | string;
    document_name: string;
    page_count: number;
    prompt: string;
    answer: string;
    citations?: any[];
    provider?: string;
    model?: string;
    is_quota_error?: boolean;
    quota_details?: Record<string, unknown> | null;
  }> {
    const res = await apiClient.post<{
      document_id: number | string;
      document_name: string;
      page_count: number;
      prompt: string;
      answer: string;
      citations?: any[];
      provider?: string;
      model?: string;
      is_quota_error?: boolean;
      quota_details?: Record<string, unknown> | null;
    }>(`/documents/query`, {
      document_id: documentId,
      prompt,
    });
    return res.data;
  },

  /**
   * Fetch extracted document text and paginated pages for Document Preview & Editing
   */
  async getDocumentContent(
    documentId: number | string
  ): Promise<{
    id: number | string;
    name: string;
    mime_type: string;
    size: number;
    page_count: number;
    checksum: string;
    text: string;
    pages: { page: number; content: string }[];
  }> {
    const res = await apiClient.get<{
      id: number | string;
      name: string;
      mime_type: string;
      size: number;
      page_count: number;
      checksum: string;
      text: string;
      pages: { page: number; content: string }[];
    }>(`/documents/${documentId}/content`);
    return res.data;
  },
};
