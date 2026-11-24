export type RiskLevel = "high" | "medium" | "low" | "none";
export type DifferenceType = "matching" | "missing" | "modified" | "numerical" | "contradiction" | "added";

export type DocumentProcessingStatus =
  | "idle"
  | "uploading"
  | "uploaded"
  | "extracting"
  | "processing"
  | "ready"
  | "failed"
  | "error"
  | "unsupported";

export interface DocumentPageContent {
  page: number;
  content: string;
}

export interface DocumentUploadMeta {
  id: string;
  backendId?: number | string;
  slot?: "A" | "B";
  file: File | null;
  name: string;
  size: number;
  type: string;
  extension: string;
  pageCount?: number;
  uploadedAt: string;
  progress: number;
  status: DocumentProcessingStatus;
  errorMessage?: string;
  extractedText?: string;
  pages?: DocumentPageContent[];
  inActiveContext?: boolean;
}

export interface DocumentEditProposal {
  id: string;
  documentId: string;
  documentName: string;
  title: string;
  summary: string;
  sectionTitle: string;
  page: number;
  originalText: string;
  proposedText: string;
  rationale: string;
  status: "pending" | "accepted" | "rejected";
  createdAt: string;
}

export interface DocumentVersionRecord {
  id: string;
  versionNumber: number;
  documentId: string;
  documentName: string;
  label: string;
  summary: string;
  contentSnapshot: string;
  proposalId?: string;
  createdAt: string;
  author: "Origin" | "AI Edit (Accepted)" | "User Revert";
}

export interface WorkspaceErrorDetail {
  type:
    | "upload"
    | "extraction"
    | "provider_rate_limit"
    | "daily_usage_limit"
    | "network"
    | "unsupported_format"
    | "processing";
  title: string;
  message: string;
  limitSource?: "docver_plan" | "ai_provider";
  resetTime?: string;
  recoverable: boolean;
  retryActionLabel?: string;
}

export interface ClauseComparisonItem {
  id: string;
  category: string;
  type: DifferenceType;
  riskLevel: RiskLevel;
  title: string;
  docAContent: string;
  docBContent: string;
  docAPage: number;
  docBPage: number;
  explanation: string;
  evidenceSnippet?: string;
  significance: string;
  evidenceVerified?: boolean;
}

export interface NumericalDiscrepancyItem {
  id: string;
  metric: string;
  docAValue: string;
  docBValue: string;
  variancePercentage?: string;
  context: string;
  riskLevel: RiskLevel;
  docAPage: number;
  docBPage: number;
}

export interface ContradictionItem {
  id: string;
  topic: string;
  docAClaim: string;
  docBClaim: string;
  legalImpact: string;
  riskLevel: RiskLevel;
  docAPage: number;
  docBPage: number;
}

export interface ComparisonJob {
  id: string;
  title: string;
  status: "queued" | "processing" | "completed" | "failed";
  createdAt: string;
  completedAt?: string;
  docA: {
    id?: number | string;
    name: string;
    size: number;
    pages: number;
    type: string;
  };
  docB: {
    id?: number | string;
    name: string;
    size: number;
    pages: number;
    type: string;
  };
  summary: {
    overallSimilarityScore: number; // 0 - 100
    totalDifferences: number;
    highRiskCount: number;
    mediumRiskCount: number;
    lowRiskCount: number;
    matchingClausesCount: number;
    missingClausesCount: number;
    modifiedClausesCount: number;
    numericalDiscrepanciesCount: number;
    contradictionsCount: number;
    executiveSummary?: string;
  };
  clauses: ClauseComparisonItem[];
  numericalDiscrepancies: NumericalDiscrepancyItem[];
  contradictions: ContradictionItem[];
}
