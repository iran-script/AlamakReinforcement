<?php

namespace Tests\Unit;

use App\Services\ReportPdfService;
use Tests\TestCase;

class ReportPdfServiceTest extends TestCase
{
    public function test_pdf_service_returns_real_pdf_with_embedded_rtl_font(): void
    {
        $response = app(ReportPdfService::class)->download([
            'meta' => [
                'company' => 'شرکت گاز استان مرکزی',
                'title' => 'گزارش عملیات',
                'generated_at' => '۱۴۰۵/۰۸/۲۷ ۱۴:۳۰',
                'prepared_by' => 'مدیر گزارش‌گیری',
                'from' => null,
                'to' => null,
                'filters' => [],
            ],
            'cards' => ['total' => 1, 'done' => 1, 'progress' => 0, 'returned' => 0, 'total_cost' => 0],
            'dataset' => 'maintenance_cases',
            'columns' => [['key' => 'riser', 'label' => 'شناسه علمک']],
            'table' => [['riser' => 'R-2026-001']],
            'grouping' => [],
            'materialUsage' => [],
            'riserMaterials' => [],
            'monthChart' => ['labels' => [], 'data' => []],
            'statusChart' => ['labels' => [], 'data' => []],
            'priorityChart' => [],
            'contractorChart' => [],
            'materials' => [],
            'supervisors' => [],
        ], 'report-test.pdf');

        $content = $response->getContent();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('DejaVuSans', $content);
    }
}
