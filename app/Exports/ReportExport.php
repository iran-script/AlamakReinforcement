<?php

namespace App\Exports;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private const COLUMNS = [
        'riser' => 'کد علمک', 'operation' => 'نوع عملیات', 'date' => 'تاریخ',
        'status' => 'وضعیت', 'priority' => 'اولویت', 'user' => 'ناظر ثبت‌کننده',
        'group' => 'گروه تعمیراتی', 'supervisor' => 'ناظر ثبت‌کننده', 'case_status' => 'وضعیت پرونده تعمیر',
        'cost' => 'مقدار ذخیره‌شده total_cost', 'materials' => 'مصالح (مقدار و واحد)',
        'material_title' => 'مصالح', 'unit' => 'واحد', 'qty' => 'مقدار مصرف',
        'organization' => 'سازمان', 'zone' => 'Zone جغرافیایی', 'identified_at' => 'تاریخ شناسایی',
        'description' => 'شرح خرابی', 'operation_date' => 'تاریخ تعمیر', 'submitted_at' => 'زمان ارسال گزارش',
        'last_editor' => 'آخرین ویرایش‌کننده', 'photos_count' => 'تعداد تصاویر', 'repair_count' => 'دفعات تعمیر علمک',
    ];

    public function __construct(private readonly Request $request)
    {
    }

    public function query()
    {
        $service = app(ReportService::class);
        if ($this->request->input('dataset') === 'maintenance_cases') {
            return $service->maintenanceCasesQuery($this->request)
                ->with(['riser:id,r_giscode,giscode', 'reporter:id,name', 'assignedGroup:id,name', 'lastEditor:id,name', 'operation.operationCategory', 'operationCategory:id,title'])
                ->limit(5000);
        }
        $operations = $service->operationsQuery($this->request);
        if ($this->request->input('dataset') === 'materials') {
            return \Illuminate\Support\Facades\DB::table('operation_materials')
                ->join('materials', 'materials.id', '=', 'operation_materials.material_id')
                ->whereIn('operation_materials.operation_id', (clone $operations)->reorder()->select('operations.id'))
                ->select('materials.id as material_id', 'materials.title as material_title', 'materials.unit')
                ->selectRaw('SUM(operation_materials.qty) AS qty')
                ->groupBy('materials.id', 'materials.title', 'materials.unit')
                ->orderBy('materials.title')->orderBy('materials.unit')->limit(5000);
        }

        // Bound export volume and let Laravel Excel process query chunks.
        return $operations
            ->with(['riser', 'user', 'operationCategory', 'operationMaterial', 'groupUser', 'maintenanceCase'])
            ->limit(5000);
    }

    public function headings(): array
    {
        $defaultColumns = $this->request->input('dataset') === 'materials'
            ? ['material_title', 'unit', 'qty']
            : ($this->request->input('dataset') === 'maintenance_cases'
                ? ['riser', 'organization', 'zone', 'supervisor', 'group', 'identified_at', 'description', 'priority', 'status', 'operation_date', 'operation', 'submitted_at', 'last_editor', 'photos_count', 'repair_count']
                : ['riser', 'operation', 'date', 'status', 'priority', 'user', 'cost', 'materials']);
        $columns = $this->request->input('columns', $defaultColumns);
        return array_map(fn ($column) => self::COLUMNS[$column], $columns);
    }

    public function map($operation): array
    {
        if ($this->request->input('dataset') === 'maintenance_cases') {
            $values = [
                'riser' => $operation->riser?->r_giscode ?: $operation->riser?->giscode,
                'organization' => $operation->organization_name,
                'zone' => $operation->zone_name,
                'supervisor' => $operation->reporter?->name,
                'group' => $operation->assignedGroup?->name,
                'identified_at' => $operation->identified_at?->format('Y-m-d H:i:s') ?? $operation->identified_at,
                'description' => $operation->description,
                'priority' => $operation->priority,
                'status' => $operation->status,
                'operation_date' => $operation->operation?->operation_date,
                'operation' => $operation->operation?->operationCategory?->title ?? $operation->operationCategory?->title,
                'submitted_at' => $operation->submitted_at?->format('Y-m-d H:i:s') ?? $operation->submitted_at,
                'last_editor' => $operation->lastEditor?->name,
                'photos_count' => $operation->photos_count,
                'repair_count' => $operation->repair_count,
            ];
            $columns = $this->request->input('columns', array_keys($values));
            return array_map(fn ($column) => $values[$column], $columns);
        }

        if ($this->request->input('dataset') === 'materials') {
            $values = ['material_title' => $operation->material_title, 'unit' => $operation->unit, 'qty' => (float) $operation->qty];
            return array_map(fn ($column) => $values[$column], $this->request->input('columns', ['material_title', 'unit', 'qty']));
        }

        $materialUsage = $operation->operationMaterial
            ->map(fn ($material) => $material->title.' ('.$material->pivot->qty.' '.$material->unit.')')
            ->implode('; ');

        $values = [
            'riser' => $operation->riser?->giscode,
            'operation' => $operation->operationCategory?->title,
            'date' => $operation->operation_date,
            'status' => $operation->status,
            'priority' => $operation->priority,
            'user' => $operation->user?->name,
            'supervisor' => $operation->user?->name,
            'group' => $operation->groupUser?->name,
            'case_status' => $operation->maintenanceCase?->status,
            'cost' => $operation->total_cost,
            'materials' => $materialUsage,
        ];

        return array_map(fn ($column) => $values[$column], $this->request->input('columns', ['riser', 'operation', 'date', 'status', 'priority', 'user', 'cost', 'materials']));
    }
}
