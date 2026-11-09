<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Material;
use App\Models\OperationCategory;
use App\Services\ReportService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;
use App\Models\Contract;
use App\Models\Zone;
use App\Models\SavedReport;
use App\Models\Operation;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLogger;




class ReportController extends Controller
{

    protected ReportService $reportService;


    public function __construct(ReportService $reportService, private AuditLogger $audit)
    {
        $this->reportService = $reportService;
    }



    public function index(Request $request)
    {
        $user = $request->user();
        $visible = fn ($query) => $query
            ->when(! $user->hasRole('superadmin'), fn ($operations) => $operations->whereHas('riser', fn ($riser) => $riser->visibleTo($user)))
            ->when($user->hasRole('boss'), function ($operations) use ($user): void {
                $groupIds = $user->managedGroupIds();
                if ($groupIds === []) $operations->whereRaw('1 = 0');
                else $operations->whereIn('operations.group_user_id', $groupIds);
            });
        $visibleOperations = Operation::query()->where($visible);

        $zones = $user->hasRole('superadmin')
            ? Zone::orderBy('name')->get()
            : ($user->isGlobalMaintenanceViewer()
                ? Zone::orderBy('name')->get()
                : Zone::where('id', $user->zone_id)->get());
        $groups = $user->hasRole('superadmin')
            ? \App\Models\GroupUser::orderBy('name')->get()
            : ($user->isGlobalMaintenanceViewer()
                ? \App\Models\GroupUser::orderBy('name')->get()
                : \App\Models\GroupUser::whereIn('id', $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id])->orderBy('name')->get());
        $supervisors = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'supervisor')->where('guard_name', 'web'))
            ->when(! $user->hasRole('superadmin') && ! $user->isGlobalMaintenanceViewer(), fn ($query) => $query->where('zone_id', $user->zone_id))
            ->when($user->isGlobalMaintenanceViewer(), fn ($query) => $query)
            ->when(! $user->hasRole('superadmin') && ! $user->isGlobalMaintenanceViewer(), fn ($query) => $query->whereIn('group_user_id', $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id]))
            ->orderBy('name')->get();

        return view('report.index', [

            'users' => $user->hasRole('superadmin')
                ? User::orderBy('name')->get()
                : User::query()
                    ->when(! $user->isGlobalMaintenanceViewer(), fn ($query) => $query->where('organization_id', $user->organization_id)->where('zone_id', $user->zone_id))
                    ->when($user->hasRole('boss'), fn ($query) => $query->whereIn('group_user_id', $user->managedGroupIds()))->orderBy('name')->get(),

            'materials' => Material::query()->whereHas('operations', $visible)->orderBy('title')->get(),

            'operations' => OperationCategory::query()->whereHas('operations', $visible)->orderBy('title')->get(),

            'statuses' => (clone $visibleOperations)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'supervisors' => User::query()->whereIn('id', DB::table('operation_supervisors')->whereIn('operation_id', (clone $visibleOperations)->select('operations.id'))->select('user_id'))->orderBy('name')->get(),
            'fieldSupervisors' => $supervisors,
            'groups' => $groups,

            'contracts' => $user->hasRole('superadmin')
                ? Contract::orderBy('contractor_name')->get()
                : Contract::whereHas('operations.riser', fn ($q) => $q->visibleTo($user))->orderBy('contractor_name')->get(),

            'zones' => $zones,
            'savedReports' => SavedReport::query()->where('user_id', $user->id)->latest('updated_at')->limit(30)->get(),

        ]);
    }





    public function data(Request $request)
    {

        $data = $this->reportService
            ->generate($request);


        return response()->json($data);
    }




    public function excel(Request $request)
    {
        $this->audit->record('report.export_excel', null, null, null, ['filters' => $request->only([
            'from', 'to', 'zone', 'operation_category', 'status', 'priority', 'contract', 'contractor',
            'supervisor', 'group_user_id', 'case_status', 'material', 'dataset', 'columns', 'sort', 'direction', 'group_by',
        ])]);

        return Excel::download(

            new ReportExport($request),

            'Report.xlsx'

        );
    }



    public function pdf(Request $request)
    {

        $data = $this->reportService
            ->generate($request);

        $this->audit->record('report.export_pdf', null, null, null, ['filters' => $request->only([
            'from', 'to', 'zone', 'operation_category', 'status', 'priority', 'contract', 'contractor',
            'supervisor', 'group_user_id', 'case_status', 'material', 'dataset', 'columns', 'sort', 'direction', 'group_by',
        ])]);


        return view(
            'report.pdf',
            compact('data')
        );
    }

    public function storeSaved(Request $request)
    {
        abort_unless(! $request->user()->isGlobalMaintenanceViewer(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'configuration' => ['required', 'array'],
            'configuration.dataset' => ['nullable', 'in:operations,materials,maintenance_cases'],
            'configuration.from' => ['nullable', 'date'],
            'configuration.to' => ['nullable', 'date', 'after_or_equal:configuration.from'],
            'configuration.zone' => ['nullable', 'integer', 'exists:zone,id'],
            'configuration.operation_category' => ['nullable', 'integer', 'exists:operation_categories,id'],
            'configuration.status' => ['nullable', 'string', 'max:100'],
            'configuration.contract' => ['nullable', 'integer', 'exists:contracts,id'],
            'configuration.contractor' => ['nullable', 'integer', 'exists:users,id'],
            'configuration.supervisor' => ['nullable', 'integer', 'exists:users,id'],
            'configuration.group_user_id' => ['nullable', 'integer', 'exists:group_users,id'],
            'configuration.case_status' => ['nullable', 'in:open,in_progress,submitted,returned,completed'],
            'configuration.material' => ['nullable', 'integer', 'exists:materials,id'],
            'configuration.priority' => ['nullable', 'string', 'max:100'],
            'configuration.columns' => ['nullable', 'array', 'min:1', 'max:15'],
            'configuration.columns.*' => ['string', 'in:riser,organization,zone,operation,date,identified_at,submitted_at,operation_date,description,status,priority,user,group,supervisor,last_editor,photos_count,repair_count,case_status,cost,materials,material_title,unit,qty'],
            'configuration.sort' => ['nullable', 'in:date,status,priority'],
            'configuration.direction' => ['nullable', 'in:asc,desc'],
            'configuration.group_by' => ['nullable', 'in:status,operation,priority,user,zone,group,supervisor,case_status'],
        ]);

        $user = $request->user();
        $configuration = array_filter($validated['configuration'], fn ($value) => $value !== null && $value !== '');
        $dataset = $configuration['dataset'] ?? 'operations';
        $allowedColumns = $dataset === 'materials'
            ? ['material_title', 'unit', 'qty']
            : ($dataset === 'maintenance_cases'
                ? ['riser', 'organization', 'zone', 'supervisor', 'group', 'identified_at', 'description', 'priority', 'status', 'operation_date', 'operation', 'submitted_at', 'last_editor', 'photos_count', 'repair_count']
                : ['riser', 'operation', 'date', 'status', 'priority', 'user', 'supervisor', 'group', 'case_status', 'cost', 'materials']);
        if (isset($configuration['columns']) && array_diff($configuration['columns'], $allowedColumns)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['configuration.columns' => 'ستون‌ها با نوع گزارش سازگار نیستند.']);
        }
        if ($user->isGlobalMaintenanceViewer() && isset($configuration['zone'])
            && ! Zone::whereKey($configuration['zone'])->exists()) {
            abort(403, 'Zone خارج از سازمان مجاز است.');
        }
        if (! $user->hasRole('superadmin') && ! $user->isGlobalMaintenanceViewer() && isset($configuration['zone'])
            && (int) $configuration['zone'] !== (int) $user->zone_id) {
            abort(403, 'Zone خارج از دسترسی شماست.');
        }
        if (! $user->hasRole('superadmin') && isset($configuration['group_user_id'])
            && ! in_array((int) $configuration['group_user_id'], $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id], true)) {
            abort(403, 'گروه کاری خارج از دسترسی شماست.');
        }

        $saved = DB::transaction(fn () => SavedReport::query()->create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'name' => $validated['name'],
            'configuration' => $configuration,
        ]));

        return response()->json(['id' => $saved->id, 'name' => $saved->name], 201);
    }

    public function openSaved(Request $request, int $savedReport)
    {
        $report = SavedReport::query()->where('user_id', $request->user()->id)->findOrFail($savedReport);

        return redirect()->to(route('reports.index').'?'.http_build_query($report->configuration, '', '&', PHP_QUERY_RFC3986));
    }

    public function destroySaved(Request $request, int $savedReport)
    {
        abort_unless(! $request->user()->isGlobalMaintenanceViewer(), 403);
        $report = SavedReport::query()->where('user_id', $request->user()->id)->findOrFail($savedReport);
        $report->delete();

        return response()->json(['deleted' => true]);
    }
}
