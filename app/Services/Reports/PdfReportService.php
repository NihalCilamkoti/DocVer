<?php

namespace App\Services\Reports;

use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfReportService
{
    /**
     * Generate professional PDF audit report.
     * Enforces strict local sandboxing (no external SSRF HTTP requests allowed).
     */
    public function generatePdfResponse(ComparisonJob $job, ComparisonReport $report): Response
    {
        $job->loadMissing(['documentA', 'documentB']);
        $report->loadMissing('findings');

        $pdf = Pdf::loadView('reports.comparison_pdf', [
            'job' => $job,
            'report' => $report,
        ]);

        $pdf->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false, // SSRF prevention: never fetch remote assets
                'defaultFont' => 'Helvetica',
            ]);

        $filename = "DocVer-Audit-Report-Job-{$job->id}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-DocVer-Report-Id' => (string) $report->id,
            'X-DocVer-Job-Id' => (string) $job->id,
        ]);
    }
}
