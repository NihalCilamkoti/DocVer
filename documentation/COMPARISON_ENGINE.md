# Intelligent Comparison Engine

The core comparison engine evaluates two documents across multiple legal and financial dimensions without hallucinations.

```mermaid
flowchart TD
    Index["Ephemeral Index (Chunks A & B)"] --> Struct["DocumentStructureAnalyzer"]
    Struct --> Match["SectionMatcher (Cosine Similarity + Titles)"]
    Match --> Missing["MissingSectionDetector"]
    Match --> Clauses["ClauseComparisonService"]
    
    Clauses --> Num["NumericalComparisonService"]
    Clauses --> Dates["DateComparisonService"]
    Clauses --> Contra["ContradictionDetector"]
    
    Missing --> Ev["EvidenceValidator (Ground Truth Character Quotes)"]
    Clauses --> Ev
    
    Ev --> Report["ReportGenerator (PDF & Executive Summary)"]
```

## Deterministic Idempotency Keys

Every finding is assigned a unique idempotency key:
$$\text{id} = \text{hash}(\text{job\_id} + \text{type} + \text{title} + \text{pageA} + \text{pageB})$$
This ensures that retries and stage resumptions never introduce duplicate findings.
