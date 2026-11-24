import { useComparisonStore } from "@/stores/comparison-store";

export function useChat() {
  const {
    chatMessages,
    isAiGenerating,
    clearChat,
    askAssistant,
  } = useComparisonStore();

  return {
    messages: chatMessages,
    isGenerating: isAiGenerating,
    sendMessage: async (text: string) => {
      await askAssistant(text);
    },
    clearChat,
  };
}
