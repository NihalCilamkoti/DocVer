import { ComparisonJob, DocumentEditProposal } from "./comparison";

export interface ChatCitation {
  docLabel: "Document A" | "Document B" | string;
  docName?: string;
  documentId?: string | number;
  page: number;
  quote: string;
  section?: string;
}

export interface QuotaDetails {
  type: "daily_user" | "provider_rate_limit" | "provider_daily" | "provider_monthly" | "provider_outage" | "invalid_config";
  friendly_message: string;
  remaining_seconds: number;
  remaining_time_human: string;
  resets_at?: string | null;
  resets_at_human?: string | null;
  fallback_provider_attempted?: string | null;
  retry_allowed?: boolean;
}

export interface ChatMessage {
  id: string;
  sender: "user" | "ai";
  content: string;
  timestamp: string;
  isStreaming?: boolean;
  messageType?: "text" | "analysis" | "comparison" | "edit_proposal" | "error";
  isQuotaError?: boolean;
  quotaDetails?: QuotaDetails | null;
  citations?: ChatCitation[];
  suggestedFollowUps?: string[];
  logId?: number;
  comparisonJob?: ComparisonJob;
  editProposal?: DocumentEditProposal;
  feedback?: "up" | "down" | null;
  provider?: string;
  model?: string;
}

export interface ConversationThread {
  id: string;
  title: string;
  updatedAt: string;
  comparisonJobId?: string;
  documentIds: string[];
  messages: ChatMessage[];
}

export interface ChatSession {
  comparisonId: string;
  messages: ChatMessage[];
  isGenerating: boolean;
}
