/**
 * DocVer API Contract — TypeScript Types for Next.js Frontend
 * Conforms to API version: /api/v1/
 */

export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]>;
}

export interface UserDTO {
  id: number;
  name: string;
  email: string;
  role: "user" | "workspace_owner" | "workspace_admin" | "super_admin";
  avatar_url?: string | null;
  phone?: string | null;
  email_verified: boolean;
  created_at: string;
  workspaces?: WorkspaceDTO[];
}

export interface WorkspaceDTO {
  id: number;
  name: string;
  slug: string;
  plan: "starter" | "professional" | "enterprise";
  status: "active" | "suspended" | "archived";
  max_file_size_mb: number;
  max_page_limit: number;
  retention_days: number;
  role: "owner" | "admin" | "member" | "viewer";
  created_at: string;
}

export interface DocumentMetadataDTO {
  id: number;
  slot: "A" | "B";
  name: string;
  size: number;
  mime_type: string;
  page_count: number | null;
  checksum: string;
  expires_at: string;
  created_at: string;
}

export interface ComparisonJobDTO {
  id: number;
  uuid: string;
  title: string;
  status: "queued" | "processing" | "completed" | "failed";
  similarity_score: number | null;
  error_message: string | null;
  document_a?: DocumentMetadataDTO;
  document_b?: DocumentMetadataDTO;
  report?: ComparisonReportDTO;
  started_at: string | null;
  completed_at: string | null;
  created_at: string;
}

export interface ComparisonFindingDTO {
  id: number;
  type: "matching" | "missing" | "modified" | "numerical" | "contradiction";
  risk_level: "high" | "medium" | "low" | "none";
  category: string;
  title: string;
  doc_a_page: number | null;
  doc_b_page: number | null;
  explanation: string;
  significance: string | null;
  metrics?: Record<string, unknown> | null;
  order_index: number;
}

export interface ComparisonReportDTO {
  id: number;
  comparison_job_id: number;
  summary: {
    overall_similarity_score: number;
    total_differences: number;
    high_risk_count: number;
    medium_risk_count: number;
    low_risk_count: number;
    matching_clauses_count: number;
    missing_clauses_count: number;
    modified_clauses_count: number;
    numerical_discrepancies_count: number;
    contradictions_count: number;
  };
  executive_summary: string | null;
  findings?: ComparisonFindingDTO[];
  generated_at: string;
}

export interface ChatMessageDTO {
  id: number;
  sender: "user" | "ai";
  content: string;
  is_quota_error?: boolean;
  quota_details?: Record<string, unknown> | null;
  citations?: {
    docLabel: "Document A" | "Document B";
    page: number;
    quote: string;
  }[] | null;
  suggested_follow_ups?: string[] | null;
  follow_ups?: string[] | null;
  created_at: string;
}

export interface UsageSummaryDTO {
  billing_cycle_start: string;
  plan: string;
  metrics: {
    comparisons: { used: number; limit: number; percentage: number };
    pages_processed: { used: number; limit: number; percentage: number };
    chat_queries: { used: number; limit: number };
    storage: { used_gb: number; limit_gb: number };
  };
}
