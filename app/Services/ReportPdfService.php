<?php

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class ReportPdfService
{
    public function download(array $data, string $filename)
    {
        $temporaryDirectory = storage_path('app/mpdf');

        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0750, true);
        }

        $html = view('report.pdf', compact('data'))->render();

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font' => 'dejavusans',
            'directionality' => 'rtl',
            'tempDir' => $temporaryDirectory,
            'margin_top' => 24,
            'margin_right' => 12,
            'margin_bottom' => 22,
            'margin_left' => 12,
        ]);

        $pdf->SetDirectionality('rtl');
        $pdf->SetDisplayMode('fullpage');
        $pdf->SetHTMLHeader('<div style="border-bottom:1px solid #d7e0ea; color:#17324d; font-size:10.5pt; padding-bottom:5pt; text-align:right;">شرکت گاز استان مرکزی <span style="color:#a96b18;">|</span> گزارش مدیریتی عملیات و تعمیرات علمک‌ها</div>');
        $pdf->SetHTMLFooter('<div style="border-top:1px solid #d7e0ea; color:#6b7c8f; font-size:8.5pt; padding-top:5pt; text-align:center;">سامانه مدیریت عملیات <span style="color:#a96b18;">|</span> صفحه {PAGENO} از {nbpg}</div>');
        $pdf->WriteHTML($html);

        return response($pdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
