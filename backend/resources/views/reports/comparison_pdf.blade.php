<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DocVer Verification & Audit Redline Report</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
        }
        .report-title {
            font-size: 16px;
            font-weight: 700;
            margin-top: 4px;
            color: #0f172a;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 4px 8px;
            font-size: 10px;
            border: 1px solid #e2e8f0;
        }
        .meta-label {
            font-weight: bold;
            background-color: #f8fafc;
            width: 25%;
        }
        .scorecard-row {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .scorecard-row td {
            width: 25%;
            padding: 10px;
            text-align: center;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }
        .score-num {
            font-size: 22px;
            font-weight: bold;
            color: #2563eb;
        }
        .score-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
        .section-header {
            font-size: 13px;
            font-weight: bold;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 10px;
            color: #0f172a;
        }
        .executive-summary {
            background-color: #f1f5f9;
            border-left: 3px solid #2563eb;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 10px;
            white-space: pre-wrap;
        }
        .finding-card {
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .finding-header {
            background-color: #f8fafc;
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: bold;
            font-size: 11px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-high { background-color: #fee2e2; color: #b91c1c; }
        .badge-medium { background-color: #fef3c7; color: #b45309; }
        .badge-low { background-color: #f1f5f9; color: #475569; }
        .finding-body {
            padding: 8px 10px;
            font-size: 10px;
        }
        .diff-grid {
            width: 100%;
            margin-top: 6px;
            border-collapse: collapse;
        }
        .diff-grid td {
            width: 50%;
            vertical-align: top;
            padding: 6px;
            font-size: 9px;
            border: 1px solid #e2e8f0;
        }
        .doc-a-side {
            background-color: #fff1f2;
            color: #9f1239;
        }
        .doc-b-side {
            background-color: #f0fdf4;
            color: #166534;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">DocVer Verification Engine</div>
        <div class="report-title">{{ $job->title ?? 'Document Comparison & Verification Redline' }}</div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Audit Job ID:</td>
            <td>#{{ $job->id }} ({{ $job->uuid }})</td>
            <td class="meta-label">Generated Date:</td>
            <td>{{ now()->format('Y-m-d H:i:s T') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Document A (Baseline):</td>
            <td>{{ $job->documentA?->original_name ?? 'Document A' }}</td>
            <td class="meta-label">Document B (Target):</td>
            <td>{{ $job->documentB?->original_name ?? 'Document B' }}</td>
        </tr>
    </table>

    <table class="scorecard-row">
        <tr>
            <td>
                <div class="score-num">{{ $job->similarity_score ?? $report->summary['overall_similarity_score'] ?? 0 }}%</div>
                <div class="score-label">Overall Similarity</div>
            </td>
            <td>
                <div class="score-num" style="color: #dc2626;">{{ $report->high_risk_count ?? 0 }}</div>
                <div class="score-label">High-Risk Variances</div>
            </td>
            <td>
                <div class="score-num" style="color: #d97706;">{{ $report->numerical_discrepancies_count ?? 0 }}</div>
                <div class="score-label">Numerical Variances</div>
            </td>
            <td>
                <div class="score-num" style="color: #4f46e5;">{{ $report->contradictions_count ?? 0 }}</div>
                <div class="score-label">Direct Contradictions</div>
            </td>
        </tr>
    </table>

    @if(!empty($report->executive_summary))
        <div class="section-header">Executive Summary & AI Audit Synthesis</div>
        <div class="executive-summary">{{ $report->executive_summary }}</div>
    @endif

    <div class="section-header">Detailed Discrepancy Redline ({{ count($report->findings ?? []) }} Items)</div>
    @forelse($report->findings ?? [] as $finding)
        <div class="finding-card">
            <div class="finding-header">
                <span class="badge {{ $finding->risk_level?->value === 'high' ? 'badge-high' : ($finding->risk_level?->value === 'medium' ? 'badge-medium' : 'badge-low') }}">
                    {{ strtoupper($finding->risk_level?->value ?? 'INFO') }}
                </span>
                &nbsp; {{ $finding->title }}
                <span style="float: right; font-weight: normal; color: #64748b; font-size: 9px;">
                    Doc A p.{{ $finding->doc_a_page ?? 1 }} → Doc B p.{{ $finding->doc_b_page ?? 1 }}
                </span>
            </div>
            <div class="finding-body">
                @if($finding->explanation)
                    <p style="margin: 0 0 4px 0;"><strong>Finding Analysis:</strong> {{ $finding->explanation }}</p>
                @endif
                @if($finding->significance)
                    <p style="margin: 0 0 6px 0; color: #475569;"><strong>Risk & Impact:</strong> {{ $finding->significance }}</p>
                @endif

                @php
                    $metrics = $finding->metrics ?? [];
                    $excerptA = $finding->doc_a_evidence ?? $metrics['doc_a_evidence'] ?? $metrics['doc_a_content'] ?? null;
                    $excerptB = $finding->doc_b_evidence ?? $metrics['doc_b_evidence'] ?? $metrics['doc_b_content'] ?? null;
                @endphp

                @if($excerptA || $excerptB)
                    <table class="diff-grid">
                        <tr>
                            <td class="doc-a-side">
                                <strong>Original (Doc A):</strong><br>
                                {{ $excerptA ?: 'None / Omitted' }}
                            </td>
                            <td class="doc-b-side">
                                <strong>Revised (Doc B):</strong><br>
                                {{ $excerptB ?: 'None / Removed' }}
                            </td>
                        </tr>
                    </table>
                @endif
            </div>
        </div>
    @empty
        <p style="font-size: 10px; color: #64748b;">No discrepancies detected. The documents appear identical or aligned.</p>
    @endforelse

    <div class="footer">
        Generated securely by DocVer AI Engine — Immutable Verification Report
    </div>
</body>
</html>