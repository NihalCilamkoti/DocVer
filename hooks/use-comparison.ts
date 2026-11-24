import { useComparisonStore } from "@/store/comparison-store";

export function useComparison() {
  const store = useComparisonStore();

  return {
    docA: store.docA,
    docB: store.docB,
    setDocA: store.setDocA,
    setDocB: store.setDocB,
    maxFileSize: store.maxFileSize,
    maxPageLimit: store.maxPageLimit,
    setMaxFileSize: store.setMaxFileSize,
    setMaxPageLimit: store.setMaxPageLimit,
    isComparing: store.isComparing,
    activeComparison: store.activeComparison,
    runComparison: store.runComparison,
    loadSampleComparison: store.loadSampleComparison,
    resetWorkspace: store.resetWorkspace,
    canCompare:
      store.docA !== null &&
      store.docB !== null &&
      store.docA.status === "ready" &&
      store.docB.status === "ready",
  };
}
