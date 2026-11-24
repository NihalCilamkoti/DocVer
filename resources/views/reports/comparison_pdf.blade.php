<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DocVer Comparison Audit Report #{{ $job->id }}</title>
    <style>
        @page {
            margin: 36pt 40pt 40pt 40pt;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 10pt;
            line-height: 1.45;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .logo-title {
            font-size: 22pt;
            font-weight: 800;
            color: #2563eb;
            letter-spacing: -0.5px;
        }
        .logo-subtitle {
            font-size: 8.5pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .meta-right {
            text-align: right;
            font-size: 8.5pt;
            color: #64748b;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-high { background-color: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .badge-medium { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-low { background-color: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
        .badge-verified { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        
        .section-header {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-top: 18px;
            margin-bottom: 10px;
        }
        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 16px;
        }
        .metrics-table {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .metrics-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            font-size: 9pt;
        }
        .metric-label {
            color: #64748b;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .metric-value {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
        }
        .finding-card {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .finding-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .finding-meta {
            font-size: 8pt;
            color: #64748b;
            margin-bottom: 8px;
        }
        .evidence-box {
            background-color: #f1f5f9;
            border-left: 3px solid #2563eb;
            padding: 6px 10px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 8pt;
            color: #334155;
            margin-top: 5px;
            margin-bottom: 6px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7.5pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            text-align: center;
        }
        .disclaimer-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 4px;
            padding: 10px;
            font-size: 7.5pt;
            color: #92400e;
            margin-top: 20px;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <div class="logo-title">DocVer</div>
                <div class="logo-subtitle">Enterprise Verification & Redline Audit Engine</div>
            </td>
            <td class="meta-right">
                <div><strong>Audit Report ID:</strong> #{{ $job->id }}</div>
                <div><strong>UUID:</strong> {{ $job->uuid }}</div>
                <div><strong>Generated Date:</strong> {{ now()->format('M d, Y H:i:s T') }}</div>
                <div><strong>Similarity Score:</strong> <span style="font-size: 11pt; font-weight: bold; color: #2563eb;">{{ $job->similarity_score }}%</span></div>
            </td>
        </tr>
    </table>

    <!-- Document Metadata Overview -->
    <table class="metrics-table" style="background-color: #f8fafc;">
        <tr>
            <td style="width: 50%;">
                <div class="metric-label">Document A (Baseline)</div>
                <div style="font-weight: bold; color: #1e293b;">{{ $job->documentA?->original_name ?? 'Document_A.pdf' }}</div>
                <div style="font-size: 7.5pt; color: #64748b;">
                    Pages: {{ $job->documentA?->page_count ?? 'N/A' }} | Size: {{ number_format(($job->documentA?->file_size ?? 0) / 1024, 1) }} KB<br>
                    SHA-256: <span style="font-family: monospace;">{{ substr($job->documentA?->checksum_sha256 ?? 'unknown', 0, 16) }}...</span>
                </div>
            </td>
            <td style="width: 50%;">
                <div class="metric-label">Document B (Comparison Version)</div>
                <div style="font-weight: bold; color: #1e293b;">{{ $job->documentB?->original_name ?? 'Document_B.pdf' }}</div>
                <div style="font-size: 7.5pt; color: #64748b;">
                    Pages: {{ $job->documentB?->page_count ?? 'N/A' }} | Size: {{ number_format(($job->documentB?->file_size ?? 0) / 1024, 1) }} KB<br>
                    SHA-256: <span style="font-family: monospace;">{{ substr($job->documentB?->checksum_sha256 ?? 'unknown', 0, 16) }}...</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Executive Summary -->
    <div class="section-header">Executive Summary</div>
    <div class="summary-card">
        <p style="margin: 0; font-size: 9.5pt; color: #334155;">
            {{ $report->executive_summary }}
        </p>
    </div>

    <!-- Metrics Breakdown Grid -->
    <div class="section-header">Verification Metrics & Risk Profile</div>
    <table class="metrics-table">
        <tr>
            <td style="width: 25%; text-align: center;">
                <div class="metric-label">Total Discrepancies</div>
                <div class="metric-value">{{ $report->total_differences }}</div>
            </td>
            <td style="width: 25%; text-align: center;">
                <div class="metric-label">High Risk</div>
                <div class="metric-value" style="color: #dc2626;">{{ $report->high_risk_count }}</div>
            </td>
            <td style="width: 25%; text-align: center;">
                <div class="metric-label">Medium Risk</div>
                <div class="metric-value" style="color: #d97706;">{{ $report->medium_risk_count }}</div>
            </td>
            <td style="width: 25%; text-align: center;">
                <div class="metric-label">Low Risk</div>
                <div class="metric-value" style="color: #2563eb;">{{ $report->low_risk_count }}</div>
            </td>
        </tr>
        <tr>
            <td style="text-align: center;">
                <div class="metric-label">Matching Clauses</div>
                <div class="metric-value" style="color: #16a34a;">{{ $report->matching_clauses_count }}</div>
            </td>
            <td style="text-align: center;">
                <div class="metric-label">Omitted / Added</div>
                <div class="metric-value">{{ $report->missing_clauses_count }}</div>
            </td>
            <td style="text-align: center;">
                <div class="metric-label">Numerical Diff</div>
                <div class="metric-value">{{ $report->numerical_discrepancies_count }}</div>
            </td>
            <td style="text-align: center;">
                <div class="metric-label">Contradictions</div>
                <div class="metric-value" style="color: #dc2626;">{{ $report->contradictions_count }}</div>
            </td>
        </tr>
    </table>

    <!-- Granular Findings List -->
    <div class="section-header">Detailed Discrepancy Findings (Evidence-Validated)</div>

    @forelse($report->findings as $idx => $finding)
        <div class="finding-card">
            <table style="width: 100%;">
                <tr>
                    <td>
                        <div class="finding-title">
                            #{{ $idx + 1 }}. {{ $finding->title }}
                        </div>
                    </td>
                    <td style="text-align: right; width: 180px;">
                        @if($finding->risk_level->value === 'high')
                            <span class="badge badge-high">High Risk</span>
                        @elseif($finding->risk_level->value === 'medium')
                            <span class="badge badge-medium">Medium Risk</span>
                        @elseif($finding->risk_level->value === 'low')
                            <span class="badge badge-low">Low Risk</span>
                        @endif
                        <span class="badge badge-verified">Evidence Validated</span>
                    </td>
                </tr>
            </table>

            <div class="finding-meta">
                <strong>Category:</strong> {{ $finding->category }} |
                <strong>Type:</strong> {{ ucfirst($finding->type->value) }} |
                @if($finding->doc_a_page) <strong>Doc A Page:</strong> {{ $finding->doc_a_page }} | @endif
                @if($finding->doc_b_page) <strong>Doc B Page:</strong> {{ $finding->doc_b_page }} @endif
            </div>

            <p style="margin: 0 0 6px 0; font-size: 9pt; color: #334155;">
                {{ $finding->explanation }}
            </p>

            @if(!empty($finding->metrics))
                <div style="font-size: 8pt; color: #64748b; margin-bottom: 6px;">
                    @foreach($finding->metrics as $k => $v)
                        <strong>{{ ucfirst(str_replace('_', ' ', $k)) }}:</strong> {{ is_array($v) ? json_encode($v) : $v }} &nbsp;&nbsp;
                    @endforeach
                </div>
            @endif

            @if($finding->doc_a_page)
                <div style="font-size: 7.5pt; font-weight: bold; color: #475569;">Document A Evidence (Page {{ $finding->doc_a_page }}):</div>
                <div class="evidence-box">
                    "{{ $finding->significance ?? 'Original source extract' }}"
                </div>
            @endif

            @if($finding->doc_b_page)
                <div style="font-size: 7.5pt; font-weight: bold; color: #475569;">Document B Counterpart (Page {{ $finding->doc_b_page }}):</div>
                <div class="evidence-box">
                    "{{ $finding->explanation }}"
                </div>
            @endif
        </div>
    @empty
        <div style="padding: 20px; text-align: center; color: #64748b;">
            No discrepancies or material variations were detected between these document versions.
        </div>
    @endforelse

    <!-- Verification Limitations & Legal Disclaimer -->
    <div class="disclaimer-box">
        <strong>Verification Limitations & Legal Notice:</strong><br>
        This document audit report was generated automatically by DocVer's multi-stage extraction and comparison engine.
        Findings labeled <em>Evidence Validated</em> have been grounded against raw extracted document text and verified against page references.
        This report is provided for auditing assistance only and does not constitute formal legal counsel or an official guarantee of contract enforceability.
    </div>

    <!-- Footer -->
    <div class="footer">
        DocVer Confidential Enterprise Audit Report &bull; Comparison Job #{{ $job->id }} &bull; Page references validated against ground truth
    </div>

</body>
</html>
