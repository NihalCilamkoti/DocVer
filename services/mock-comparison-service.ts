/**
 * Legacy compatibility adapter redirecting to real ComparisonService.
 * Mock data has been completely removed in favor of the production AI pipeline.
 */
import { comparisonService } from "./api/comparison-service";

export const mockComparisonService = {
  getRecentComparisons: () => comparisonService.listComparisons(),
  getComparisonById: (id: string) => comparisonService.getComparison(id),
  executeComparison: async (docA: any, docB: any) => {
    if (docA.file && docB.file) {
      const [metaA, metaB] = await Promise.all([
        comparisonService.uploadDocument(docA.file, "A"),
        comparisonService.uploadDocument(docB.file, "B"),
      ]);
      const jobDTO = await comparisonService.createComparison(
        `${docA.name} vs ${docB.name}`,
        metaA.id,
        metaB.id
      );
      return comparisonService.getComparison(jobDTO.id);
    }
    const list = await comparisonService.listComparisons();
    return list[0];
  },
};

export const RECENT_COMPARISONS_LIST = [];
