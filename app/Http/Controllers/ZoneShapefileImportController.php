<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Services\ZoneShapefileImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ZoneShapefileImportController extends Controller
{
    public function index(Request $request, ZoneShapefileImportService $imports)
    {
        Gate::authorize('import', Zone::class);
        $imports->cleanupExpired();
        return $this->renderWizard($request, $imports, null);
    }

    public function show(Request $request, string $token, ZoneShapefileImportService $imports)
    {
        Gate::authorize('import', Zone::class);
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token), 404);
        $meta = $request->session()->get('zone_shapefile_imports.'.$token);
        abort_unless(is_array($meta) && ($meta['user_id'] ?? null) === $request->user()->id, 404);
        return $this->renderWizard($request, $imports, $token, $meta);
    }

    private function renderWizard(Request $request, ZoneShapefileImportService $imports, ?string $token, ?array $meta = null)
    {
        if ($meta && (($meta['created_at'] ?? 0) < time() - ZoneShapefileImportService::TOKEN_TTL)) {
            $imports->delete($meta, $request->user());
            $request->session()->forget('zone_shapefile_imports.'.$token);
            $meta = null;
        }

        return view('zones.import', [
            'meta' => $meta,
            'token' => $token,
            'organizations' => $imports->organizationsFor($request->user()),
            'encodings' => ['UTF-8', 'CP1256', 'CP1252', 'ISO-8859-6'],
            'previewUrl' => $token ? route('zones.import.geojson', $token) : null,
        ]);
    }

    public function upload(Request $request, ZoneShapefileImportService $imports)
    {
        Gate::authorize('import', Zone::class);
        $request->validate(['file' => ['required', 'file', 'max:25600']]);
        $file = $request->file('file');
        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return back()->withErrors(['file' => 'فقط فایل ZIP شیپ‌فایل پذیرفته می‌شود.']);
        }
        $result = $imports->upload($file, $request->user());
        $request->session()->put('zone_shapefile_imports.'.$result['token'], $result['meta']);
        return redirect()->route('zones.import.show', $result['token']);
    }

    public function preview(Request $request, string $token, ZoneShapefileImportService $imports)
    {
        Gate::authorize('import', Zone::class);
        $meta = $this->state($request, $token);
        $summary = $imports->preview($meta, $request->user(), $request->only(['name_field', 'organization_id', 'encoding']));
        $meta['status'] = 'previewed';
        $meta['mapping'] = $summary['mapping'];
        $meta['summary'] = $summary;
        $request->session()->put('zone_shapefile_imports.'.$token, $meta);
        return redirect()->route('zones.import.show', $token)->with('zone_import_previewed', true);
    }

    public function geojson(Request $request, string $token, ZoneShapefileImportService $imports)
    {
        Gate::authorize('import', Zone::class);
        $meta = $this->state($request, $token);
        $path = $imports->previewResponsePath($meta, $request->user());
        return response()->file($path, ['Content-Type' => 'application/geo+json; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function confirm(Request $request, string $token, ZoneShapefileImportService $imports)
    {
        Gate::authorize('import', Zone::class);
        $meta = $this->state($request, $token);
        abort_unless(($meta['status'] ?? null) === 'previewed' && isset($meta['mapping']), 409, 'ابتدا Preview و اعتبارسنجی را کامل کنید.');
        $validated = $request->validate([
            'duplicate_mode' => ['required', 'in:skip,reject,update'],
            'skip_invalid' => ['nullable', 'boolean'],
            'confirm_import' => ['required', 'accepted'],
        ]);
        $result = $imports->import($meta, $meta['mapping'], $request->user(), $validated['duplicate_mode'], $request->boolean('skip_invalid'));
        $imports->delete($meta, $request->user());
        $request->session()->forget('zone_shapefile_imports.'.$token);
        return redirect()->route('zones.import')->with('zone_import_result', $result);
    }

    public function cancel(Request $request, string $token, ZoneShapefileImportService $imports)
    {
        Gate::authorize('import', Zone::class);
        $meta = $this->state($request, $token);
        $imports->delete($meta, $request->user());
        $request->session()->forget('zone_shapefile_imports.'.$token);
        return redirect()->route('zones.import')->with('status', 'فایل موقت پاک شد.');
    }

    private function state(Request $request, string $token): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token), 404);
        $meta = $request->session()->get('zone_shapefile_imports.'.$token);
        abort_unless(is_array($meta), 404);
        abort_unless(($meta['user_id'] ?? null) === $request->user()->id, 404);
        return $meta;
    }
}
