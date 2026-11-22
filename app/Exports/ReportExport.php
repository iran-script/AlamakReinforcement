<?php

namespace App\Exports;

use App\Models\Riser;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/** A single scope-enforced context shared by every report worksheet. */
final class ReportExport implements Export, WithMultipleSheets
{
    public const ROW_LIMIT = 5000;

    public function __construct(private readonly Request $request) {}

    public function cases(): EloquentBuilder
    {
        $service = app(ReportService::class);
        $cases = $service->maintenanceCasesQuery($this->dataset() === 'maintenance_cases' ? $this->request : $this->scopeRequest());
        if ($this->dataset() !== 'maintenance_cases') $cases->whereIn('maintenance_cases.id', $this->operations()->whereNotNull('maintenance_case_id')->select('operations.maintenance_case_id'));
        return $cases;
    }

    public function operations(): EloquentBuilder
    {
        $query = app(ReportService::class)->operationsQuery($this->dataset() === 'maintenance_cases' ? $this->scopeRequest() : $this->request);
        if ($this->dataset() === 'maintenance_cases') {
            $query->whereIn('operations.maintenance_case_id', app(ReportService::class)->maintenanceCasesQuery($this->request)->select('maintenance_cases.id'));
        }
        return $query;
    }

    public function operationIds(): QueryBuilder|EloquentBuilder
    {
        return (clone $this->operations())->reorder()->select('operations.id');
    }

    public function riserIds(): QueryBuilder|EloquentBuilder
    {
        if ($this->dataset() === 'maintenance_cases') {
            return (clone app(ReportService::class)->maintenanceCasesQuery($this->request))->reorder()->select('maintenance_cases.riser_id')->distinct();
        }
        return (clone $this->operations())->reorder()->select('operations.riser_id')->distinct();
    }

    public function dataset(): string
    {
        return in_array($this->request->input('dataset'), ['operations', 'materials', 'maintenance_cases'], true)
            ? $this->request->input('dataset') : 'operations';
    }

    public function filters(): array
    {
        return $this->request->only(['from', 'to', 'zone', 'operation_category', 'status', 'priority', 'contract', 'contractor', 'supervisor', 'group_user_id', 'case_status', 'material', 'dataset', 'columns', 'sort', 'direction', 'group_by']);
    }

    public function activeFilters(): array
    {
        return array_filter($this->filters(), static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    public function requestValue(string $key): mixed { return $this->request->input($key); }
    public function userName(): string { return (string) ($this->request->user()?->name ?? 'سامانه'); }
    public function organizationName(): ?string { return $this->request->user()?->organization?->name; }
    public function roleNames(): string { return $this->request->user()?->getRoleNames()->implode('، ') ?? ''; }

    public function hasRows(): bool
    {
        return $this->dataset() === 'maintenance_cases'
            ? (clone app(ReportService::class)->maintenanceCasesQuery($this->request))->exists()
            : (clone $this->operations())->exists();
    }

    private function scopeRequest(): Request
    {
        $request = Request::create($this->request->url(), 'GET');
        $user = $this->request->user();
        $request->setUserResolver(static fn () => $user);
        return $request;
    }

    public function sheets(): array
    {
        $ctx = $this;
        return [
            new ReportDashboardSheet($ctx),
            new ReportRisersSheet($ctx),
            new ReportCasesSheet($ctx),
            new ReportOperationsSheet($ctx),
            new ReportMaterialDetailSheet($ctx),
            new ReportMaterialSummarySheet($ctx),
            new ReportWorkflowSheet($ctx),
            new ReportDefinitionSheet($ctx),
        ];
    }
}

abstract class ReportQuerySheet extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles, WithCustomValueBinder
{
    public function __construct(protected ReportExport $context) {}
    abstract protected function source(): QueryBuilder|EloquentBuilder;
    abstract protected function columns(): array;
    abstract protected function row(mixed $record): array;
    abstract public function title(): string;
    public function query(): QueryBuilder|EloquentBuilder
    {
        $source = $this->source();
        if ($source instanceof EloquentBuilder) {
            $key = $source->getModel()->getQualifiedKeyName();
            $boundedKeys = clone $source;
            $boundedKeys->select($key)->orderBy($key)->limit(ReportExport::ROW_LIMIT);
            return $source->whereIn($key, $boundedKeys);
        }

        $bounded = DB::query()->fromSub((clone $source)->limit(ReportExport::ROW_LIMIT), 'bounded_report')->select('bounded_report.*');
        return match ($this->title()) {
            'ریز مصرف مصالح' => $bounded->orderBy('use_id'),
            'خلاصه مصالح' => $bounded->orderBy('material_id')->orderBy('unit'),
            'گردش‌کار' => $bounded->orderBy('sent_at')->orderBy('record_id'),
            default => $bounded,
        };
    }
    public function headings(): array { return $this->columns(); }
    public function map(mixed $record): array { return array_map([self::class, 'safeCell'], $this->row($record)); }
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
    public static function safeCell(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        return preg_match('/^[\s]*[=+@]/u', $value) === 1 || preg_match('/^[\s]*-[^0-9]/u', $value) === 1 ? "'".$value : $value;
    }
    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial', 'size' => 11],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => 'right', 'vertical' => 'center', 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => 'thin', 'color' => ['rgb' => '9EADBF']]],
        ]);
        $sheet->getDefaultRowDimension()->setRowHeight(22);
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().$sheet->getHighestRow())->applyFromArray([
            'alignment' => ['wrapText' => true, 'vertical' => 'top'],
            'borders' => ['allBorders' => ['borderStyle' => 'hair', 'color' => ['rgb' => 'D8E0EA']]],
        ]);
        for ($row = 2; $row <= $sheet->getHighestRow(); $row += 2) {
            $sheet->getStyle('A'.$row.':'.$sheet->getHighestColumn().$row)->getFill()->setFillType('solid')->getStartColor()->setRGB('F3F6FA');
        }
        return [];
    }
}

abstract class ReportArraySheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected ReportExport $context) {}
    public function styles(Worksheet $sheet): array
    {
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '17365D']],
            'alignment' => ['horizontal' => 'right', 'wrapText' => true],
        ]);
        $sheet->getDefaultRowDimension()->setRowHeight(22);
        return [];
    }
}

final class ReportDashboardSheet extends ReportArraySheet implements WithCharts
{
    public function title(): string { return 'داشبورد'; }
    public function styles(Worksheet $sheet): array
    {
        parent::styles($sheet);
        $sheet->freezePane('A4');
        $sheet->getStyle('A1:B2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '17365D'], 'name' => 'Arial', 'size' => 14],
            'alignment' => ['horizontal' => 'right', 'vertical' => 'center'],
        ]);
        $sheet->getStyle('A3:B3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '17365D']],
        ]);
        $sheet->setAutoFilter('A3:B16');
        return [];
    }
    public function array(): array
    {
        $operations = $this->context->operations();
        $cases = $this->context->cases();
        $statuses = (clone $operations)->reorder()->whereNotNull('status')->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->orderBy('status')->get();
        $statusRows = [['وضعیت عملیات', 'تعداد']];
        foreach ($statuses as $status) $statusRows[] = [ReportQuerySheet::safeCell((string) $status->status), (int) $status->total];
        $meta = [
            ['شرکت گاز استان مرکزی'], ['گزارش جامع عملکرد عملیات و تعمیرات علمک‌ها'],
            ['شاخص', 'مقدار'], ['زمان تولید', now()->format('Y-m-d H:i:s')],
            ['تهیه‌کننده', ReportQuerySheet::safeCell($this->context->userName())],
            ['سازمان', ReportQuerySheet::safeCell($this->context->organizationName())],
            ['نوع گزارش', $this->context->dataset()], ['بازه از', $this->context->requestValue('from')], ['بازه تا', $this->context->requestValue('to')],
            ['علمک‌های دارای داده در محدوده', (clone $this->context->riserIds())->distinct()->count()],
            ['پرونده‌های تعمیراتی', (clone $cases)->reorder()->count('maintenance_cases.id')],
            ['عملیات ثبت‌شده', (clone $operations)->reorder()->count('operations.id')],
            ['پرونده‌های تکمیل‌شده', (clone $cases)->where('maintenance_cases.status', 'completed')->count()],
            ['در انتظار بررسی', (clone $cases)->where('maintenance_cases.status', 'submitted')->count()],
            ['پرونده‌های برگشتی', (clone $cases)->where('maintenance_cases.status', 'returned')->count()],
            ['انواع مصالح مصرفی', DB::table('operation_materials')->whereIn('operation_id', $this->context->operationIds())->distinct()->count('material_id')],
        ];
        $filters = $this->context->activeFilters();
        if ($filters !== []) {
            $meta[] = ['فیلترهای فعال', 'مقدار'];
            foreach ($filters as $key => $value) $meta[] = [ReportQuerySheet::safeCell((string) $key), ReportQuerySheet::safeCell(is_array($value) ? implode('، ', $value) : (string) $value)];
        }
        $meta[] = $statusRows[0];
        return array_merge($meta, array_slice($statusRows, 1));
    }

    public function charts(): Chart|array
    {
        $count = (clone $this->context->operations())->reorder()->whereNotNull('status')->select('status')->distinct()->count();
        if ($count < 1) return [];
        $filterCount = count($this->context->activeFilters());
        $first = 18 + ($filterCount > 0 ? $filterCount + 1 : 0);
        $last = $first + $count - 1;
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'داشبورد'!\$A\$".$first.':\$A\$'.$last, null, $count)];
        $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'داشبورد'!\$B\$".$first.':\$B\$'.$last, null, $count)];
        $series = new DataSeries(DataSeries::TYPE_PIECHART, DataSeries::GROUPING_STANDARD, range(0, count($values) - 1), [], $labels, $values);
        $chart = new Chart('status_distribution', new Title('توزیع وضعیت عملیات'), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, [$series]));
        $chart->setTopLeftPosition('D3')->setBottomRightPosition('K17');
        return [$chart];
    }
}

final class ReportDefinitionSheet extends ReportArraySheet
{
    public function title(): string { return 'تعاریف و فیلترها'; }
    public function array(): array
    {
        $rows = [['مشخصه', 'مقدار'], ['شرکت', 'شرکت گاز استان مرکزی'], ['عنوان گزارش', 'گزارش جامع عملکرد عملیات و تعمیرات علمک‌ها'],
            ['زمان تولید', now()->format('Y-m-d H:i:s')], ['کاربر', ReportQuerySheet::safeCell($this->context->userName())],
            ['نقش‌ها', ReportQuerySheet::safeCell($this->context->roleNames())], ['نوع Dataset', $this->context->dataset()],
            ['محدوده', 'تمام داده‌ها از Queryهای مجاز ReportService و Scope کاربر استخراج شده‌اند.'],
            ['قاعده مصالح', 'تجمیع بر اساس شناسه مصالح و واحد؛ فقط عملیات فعال در محدوده گزارش.'],
            ['محدودیت ردیف', 'حداکثر ۵۰۰۰ ردیف در هر شیت جزئیات.'], ['فیلتر', 'مقدار']];
        foreach ($this->context->filters() as $key => $value) $rows[] = [self::safeCell((string) $key), self::safeCell(is_array($value) ? implode(', ', $value) : (string) $value)];
        return $rows;
    }
    private static function safeCell(string $value): string { return ReportQuerySheet::safeCell($value); }
}

final class ReportRisersSheet extends ReportQuerySheet
{
    protected function source(): QueryBuilder|EloquentBuilder
    {
        return Riser::query()->whereIn('riser.id', $this->context->riserIds())
            ->select(['riser.id','riser.r_giscode','riser.giscode','riser.status','riser.created_at','riser.updated_at'])
            ->selectRaw('ST_X(ST_PointOnSurface(riser.geom)) as report_longitude, ST_Y(ST_PointOnSurface(riser.geom)) as report_latitude')
            ->selectSub(DB::table('zone')->join('organizations','organizations.id','=','zone.organization_id')->select('organizations.name')
                ->whereRaw('ST_Intersects(zone.geom, (SELECT current_riser.geom FROM riser as current_riser WHERE current_riser.id = riser.id))')->orderBy('zone.id')->limit(1), 'organization_name')
            ->selectSub(DB::table('zone')->select('zone.name')->whereRaw('ST_Intersects(zone.geom, (SELECT current_riser.geom FROM riser as current_riser WHERE current_riser.id = riser.id))')->orderBy('zone.id')->limit(1), 'zone_name')
            ->orderBy('riser.id');
    }
    protected function columns(): array { return ['شناسه علمک','کد GIS ثبت‌شده','کد GIS','سازمان','زون جغرافیایی','طول جغرافیایی','عرض جغرافیایی','وضعیت','تاریخ ثبت','آخرین تغییر']; }
    protected function row(mixed $r): array { return [(string)$r->id,(string)$r->r_giscode,(string)$r->giscode,$r->organization_name,$r->zone_name,$r->report_longitude,$r->report_latitude,$r->status,$r->created_at,$r->updated_at]; }
    public function title(): string { return 'علمک‌ها'; }
}

final class ReportCasesSheet extends ReportQuerySheet
{
    protected function source(): QueryBuilder|EloquentBuilder
    {
        return (clone $this->context->cases())->with(['riser:id,r_giscode,giscode','reporter:id,name','assignedGroup:id,name','lastEditor:id,name','operationCategory:id,title'])->withCount('operations')->orderBy('maintenance_cases.id');
    }
    protected function columns(): array { return ['شناسه پرونده','شناسه علمک','کد GIS','سازمان','زون','گروه','ناظر ثبت‌کننده','دسته‌بندی عملیات','وضعیت','اولویت','تاریخ شناسایی','تاریخ ارسال','تاریخ بسته‌شدن','توضیحات','تعداد عملیات','تعداد تصاویر','تعداد تعمیرات ثبت‌شده','آخرین ویرایشگر','آخرین تغییر']; }
    protected function row(mixed $r): array { return [(string)$r->id,(string)$r->riser_id,(string)($r->riser?->r_giscode ?: $r->riser?->giscode),$r->organization_name??null,$r->zone_name??null,$r->assignedGroup?->name,$r->reporter?->name,$r->operationCategory?->title,$r->status,$r->priority,$r->identified_at,$r->submitted_at,$r->closed_at,$r->description,(int)$r->operations_count,(int)$r->photos_count,(int)$r->repair_count,$r->lastEditor?->name,$r->updated_at]; }
    public function title(): string { return 'پرونده‌های تعمیراتی'; }
}

final class ReportOperationsSheet extends ReportQuerySheet
{
    protected function source(): QueryBuilder|EloquentBuilder
    {
        return (clone $this->context->operations())->with(['riser:id,r_giscode,giscode','operationCategory:id,title','user:id,name','groupUser:id,name','maintenanceCase:id,status','contract:id,contractor_name,contract_number','supervisors:id,name'])
            ->selectSub(DB::table('zone')->join('organizations','organizations.id','=','zone.organization_id')->select('organizations.name')
                ->whereRaw('ST_Intersects(zone.geom, (SELECT riser.geom FROM riser WHERE riser.id = operations.riser_id))')->orderBy('zone.id')->limit(1), 'report_organization')
            ->selectSub(DB::table('zone')->select('zone.name')->whereRaw('ST_Intersects(zone.geom, (SELECT riser.geom FROM riser WHERE riser.id = operations.riser_id))')->orderBy('zone.id')->limit(1), 'report_zone')
            ->orderBy('operations.id');
    }
    protected function columns(): array { return ['شناسه عملیات','شناسه پرونده','شناسه علمک','کد GIS','سازمان','زون','عنوان عملیات','دسته‌بندی','وضعیت','اولویت','توضیحات','ثبت‌کننده','گروه','پیمانکار','شماره قرارداد','ناظران و وضعیت تأیید','تاریخ عملیات','ساعت شروع','ساعت پایان','هزینه ثبت‌شده','وضعیت پرونده','تاریخ ثبت','آخرین تغییر']; }
    protected function row(mixed $r): array { return [(string)$r->id,(string)$r->maintenance_case_id,(string)$r->riser_id,(string)($r->riser?->r_giscode ?: $r->riser?->giscode),$r->report_organization,$r->report_zone,$r->operationCategory?->title,$r->operationCategory?->title,$r->status,$r->priority,$r->description,$r->user?->name,$r->groupUser?->name,$r->contract?->contractor_name,$r->contract?->contract_number,$r->supervisors->map(fn ($supervisor) => $supervisor->name.' ('.$supervisor->pivot->status.')')->implode('؛ '),$r->operation_date,$r->start_time,$r->end_time,$r->total_cost,$r->maintenanceCase?->status,$r->created_at,$r->updated_at]; }
    public function title(): string { return 'عملیات'; }
}

final class ReportMaterialDetailSheet extends ReportQuerySheet
{
    protected function source(): QueryBuilder|EloquentBuilder
    {
        return DB::table('operation_materials as om')->join('materials as m','m.id','=','om.material_id')->join('operations as o','o.id','=','om.operation_id')->leftJoin('operation_categories as cat','cat.id','=','o.operation_category_id')
            ->leftJoin('maintenance_cases as c','c.id','=','o.maintenance_case_id')->leftJoin('riser as r','r.id','=','o.riser_id')->leftJoin('users as u','u.id','=','o.user_id')
            ->whereIn('o.id',$this->context->operationIds())->select(['om.id as use_id','om.material_id','m.title as material_title','m.unit','om.qty','o.id as operation_id','cat.title as operation_title','c.id as case_id','o.riser_id','r.r_giscode','r.giscode','u.name as supervisor','o.operation_date','om.created_at'])
            ->selectSub(DB::table('zone')->join('organizations','organizations.id','=','zone.organization_id')->select('organizations.name')
                ->whereRaw('ST_Intersects(zone.geom, (SELECT r2.geom FROM riser as r2 WHERE r2.id = o.riser_id))')->orderBy('zone.id')->limit(1), 'organization_name')
            ->selectSub(DB::table('zone')->select('zone.name')->whereRaw('ST_Intersects(zone.geom, (SELECT r2.geom FROM riser as r2 WHERE r2.id = o.riser_id))')->orderBy('zone.id')->limit(1), 'zone_name')->orderBy('om.id');
    }
    protected function columns(): array { return ['شناسه مصرف','شناسه مصالح','عنوان مصالح','واحد','مقدار','شناسه عملیات','عنوان عملیات','شناسه پرونده','شناسه علمک','کد GIS','سازمان','زون','ناظر/ثبت‌کننده','تاریخ ثبت']; }
    protected function row(mixed $r): array { return [(string)$r->use_id,(string)$r->material_id,$r->material_title,$r->unit,(string)$r->qty,(string)$r->operation_id,$r->operation_title,(string)$r->case_id,(string)$r->riser_id,(string)($r->r_giscode?:$r->giscode),$r->organization_name,$r->zone_name,$r->supervisor,$r->created_at]; }
    public function title(): string { return 'ریز مصرف مصالح'; }
}

final class ReportMaterialSummarySheet extends ReportQuerySheet
{
    protected function source(): QueryBuilder|EloquentBuilder
    {
        return DB::table('operation_materials as om')->join('materials as m','m.id','=','om.material_id')->join('operations as o','o.id','=','om.operation_id')
            ->whereIn('o.id',$this->context->operationIds())->select('m.id as material_id','m.title','m.unit',DB::raw('SUM(om.qty) as total_qty'),DB::raw('COUNT(DISTINCT o.id) as operations_count'),DB::raw('COUNT(DISTINCT o.maintenance_case_id) as cases_count'))
            ->groupBy('m.id','m.title','m.unit')->orderBy('m.title')->orderBy('m.unit');
    }
    protected function columns(): array { return ['شناسه مصالح','عنوان مصالح','واحد','مجموع مصرف','تعداد عملیات','تعداد پرونده‌ها','بازه گزارش']; }
    protected function row(mixed $r): array { return [(string)$r->material_id,$r->title,$r->unit,(string)$r->total_qty,(int)$r->operations_count,(int)$r->cases_count,trim(($this->context->requestValue('from')??'آغاز').' تا '.($this->context->requestValue('to')??'امروز'))]; }
    public function title(): string { return 'خلاصه مصالح'; }
}

final class ReportWorkflowSheet extends ReportQuerySheet
{
    protected function source(): QueryBuilder|EloquentBuilder
    {
        $ids = $this->context->operationIds();
        $transfers = DB::table('workflow_transfers as wt')->leftJoin('users as sender','sender.id','=','wt.user_id_from')->leftJoin('users as reviewer','reviewer.id','=','wt.user_id_to')
            ->whereIn('wt.operation_id',$ids)->selectRaw("wt.id as record_id, wt.operation_id, wt.riser_id, CASE WHEN wt.receive_date IS NULL THEN 'pending' ELSE 'reviewed' END as state, sender.name as sender_name, reviewer.name as reviewer_name, wt.send_date as sent_at, wt.receive_date as reviewed_at, wt.type as result, wt.comment as note, 'workflow' as source");
        $audit = DB::table('audit_logs as al')->leftJoin('users as actor','actor.id','=','al.actor_id')
            ->whereIn('al.operation_id',$this->context->operationIds())->selectRaw("al.id as record_id, al.operation_id, al.riser_id, al.actor_role as state, actor.name as sender_name, NULL as reviewer_name, al.created_at as sent_at, NULL as reviewed_at, al.event as result, NULL as note, 'audit' as source");
        $approvals = DB::table('operation_supervisors as os')->join('operations as o','o.id','=','os.operation_id')->leftJoin('users as reviewer','reviewer.id','=','os.user_id')
            ->whereIn('os.operation_id',$this->context->operationIds())->selectRaw("os.user_id as record_id, os.operation_id, o.riser_id, os.status as state, NULL as sender_name, reviewer.name as reviewer_name, os.created_at as sent_at, os.approved_at as reviewed_at, os.status as result, os.comment as note, 'approval' as source");
        return DB::query()->fromSub($transfers->unionAll($audit)->unionAll($approvals), 'report_events')->orderBy('sent_at')->orderBy('record_id');
    }
    protected function columns(): array { return ['منبع','شناسه رویداد','شناسه عملیات','شناسه علمک','وضعیت/نقش','فرستنده','بررسی‌کننده','تاریخ ارسال/ثبت','تاریخ بررسی','نتیجه/نوع','توضیحات']; }
    protected function row(mixed $r): array { return [$r->source,(string)$r->record_id,(string)$r->operation_id,(string)$r->riser_id,$r->state,$r->sender_name,$r->reviewer_name,$r->sent_at,$r->reviewed_at,$r->result,$r->note]; }
    public function title(): string { return 'گردش‌کار'; }
}

// Explicitly allow only non-sensitive audit columns; JSON payloads, network details and user agent are excluded.
