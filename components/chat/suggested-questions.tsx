"use client";

import * as React from "react";
import { Sparkles } from "lucide-react";
import { SUGGESTED_QUESTIONS } from "@/lib/constants";

interface SuggestedQuestionsProps {
  onSelect: (question: string) => void;
  disabled?: boolean;
}

export function SuggestedQuestions({ onSelect, disabled }: SuggestedQuestionsProps) {
  return (
    <div className="space-y-2">
      <div className="flex items-center space-x-1.5 text-[11px] font-semibold text-slate-500">
        <Sparkles className="h-3.5 w-3.5 text-indigo-600" />
        <span>Try asking...</span>
      </div>
      <div className="flex flex-wrap gap-1.5">
        {SUGGESTED_QUESTIONS.map((question, idx) => (
          <button
            key={idx}
            type="button"
            disabled={disabled}
            onClick={() => onSelect(question)}
            className="rounded-full bg-white border border-border px-3 py-1 text-xs text-text-main hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700 transition-colors disabled:pointer-events-none disabled:opacity-50 text-left shadow-2xs"
          >
            {question}
          </button>
        ))}
      </div>
    </div>
  );
}
