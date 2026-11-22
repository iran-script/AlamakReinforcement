<?php

namespace Tests\Unit;

use App\Exports\ReportExport;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class ReportExportStructureTest extends TestCase
{
    public function test_report_export_exposes_eight_named_sheets_and_expected_headers(): void
    {
        $export = new ReportExport(Request::create('/reports/excel'));
        $sheets = $export->sheets();

        self::assertCount(8, $sheets);
        self::assertSame(['داشبورد', 'علمک‌ها', 'پرونده‌های تعمیراتی', 'عملیات', 'ریز مصرف مصالح', 'خلاصه مصالح', 'گردش‌کار', 'تعاریف و فیلترها'], array_map(fn ($sheet) => $sheet->title(), $sheets));
        self::assertContains('کد GIS', $sheets[1]->headings());
        self::assertContains('وضعیت پرونده', $sheets[3]->headings());
        self::assertContains('مقدار', $sheets[4]->headings());
        self::assertContains('مجموع مصرف', $sheets[5]->headings());
    }

    public function test_eight_sheet_fixture_is_a_valid_openable_xlsx(): void
    {
        $bytes = Excel::raw(new ReportExportXlsxFixture(), \Maatwebsite\Excel\Excel::XLSX);
        self::assertStringStartsWith("PK\x03\x04", $bytes);

        $path = tempnam(sys_get_temp_dir(), 'report-xlsx-');
        try {
            file_put_contents($path, $bytes);
            $zip = new ZipArchive();
            self::assertTrue($zip->open($path));
            self::assertNotFalse($zip->locateName('xl/workbook.xml'));
            self::assertNotFalse($zip->locateName('xl/worksheets/sheet8.xml'));
            $zip->close();

            $workbook = IOFactory::load($path);
            self::assertCount(8, $workbook->getAllSheets());
            self::assertSame('نمونه فارسی', $workbook->getSheet(0)->getCell('A1')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function test_scoped_detail_queries_compile_without_database_access(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturnUsing(fn (string $role): bool => $role === 'superadmin');
        foreach (['operations', 'materials', 'maintenance_cases'] as $dataset) {
            $request = Request::create('/reports/excel', 'GET', ['dataset' => $dataset]);
            $request->setUserResolver(static fn () => $user);
            $sheets = (new ReportExport($request))->sheets();
            foreach (array_slice($sheets, 1, 6) as $sheet) {
                self::assertNotSame('', $sheet->query()->toSql(), $dataset.' / '.$sheet->title().' query did not compile');
            }
        }
    }
}

final class ReportExportXlsxFixture implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return array_map(fn ($index) => new ReportExportFixtureSheet($index), range(1, 8));
    }
}

final class ReportExportFixtureSheet implements FromArray, WithTitle
{
    public function __construct(private readonly int $index) {}
    public function title(): string { return 'Sheet '.$this->index; }
    public function array(): array { return [['نمونه فارسی', 'کد GIS'], ['ردیف ۰۰۱', '00123']]; }
}
