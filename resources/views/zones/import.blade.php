@extends('layouts.app')

@section('title', 'ورود زون از Shapefile')

@section('content')
<link rel="stylesheet" href="{{ url('css/ol.css') }}">
<script src="{{ asset('js/ol.js') }}"></script>
<style>
    .zone-import{direction:rtl;max-width:1440px;margin:auto;color:#172033}
    .zi-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}
    .zi-head h1{font-size:1.35rem;margin:.2rem 0}.zi-muted{color:#667085}
    .zi-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:.55rem;margin:1rem 0}
    .zi-step{padding:.75rem;border:1px solid #dbe3ed;border-radius:.7rem;background:#fff;color:#667085;font-weight:700}
    .zi-step.active{border-color:#2563eb;background:#eff6ff;color:#1746a2}.zi-step.done{border-color:#9bd8c6;color:#087567}
    .zi-card{background:#fff;border:1px solid #dbe3ed;border-radius:.9rem;padding:1rem;margin-bottom:1rem;box-shadow:0 8px 28px -26px #172033}
    .zi-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.zi-field{display:grid;gap:.35rem}
    .zi-field label{font-weight:700}.zi-field small{color:#667085}.zi-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
    .zi-stats{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:.65rem}.zi-stat{border:1px solid #e2e8f0;border-radius:.7rem;padding:.8rem;background:#f8fafc}.zi-stat strong{display:block;font-size:1.3rem;color:#17365d}
    .zi-map{height:440px;border:1px solid #dbe3ed;border-radius:.75rem;overflow:hidden;background:#f1f5f9}.zi-table-wrap{overflow:auto;max-height:300px}
    .zi-table{width:100%;border-collapse:collapse}.zi-table th,.zi-table td{text-align:right;padding:.55rem;border-bottom:1px solid #e8edf3;white-space:nowrap}.zi-table th{background:#17365d;color:#fff;position:sticky;top:0}
    .zi-warning{padding:.75rem 1rem;border:1px solid #f4d486;background:#fff8e6;color:#7b4b00;border-radius:.7rem;margin:.7rem 0}
    .zi-error{padding:.75rem 1rem;border:1px solid #fecdd3;background:#fff0f1;color:#982934;border-radius:.7rem;margin:.7rem 0}
    @media(max-width:800px){.zi-grid{grid-template-columns:1fr}.zi-steps{grid-template-columns:repeat(2,1fr)}.zi-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.zi-head{align-items:flex-start;flex-direction:column}.zi-map{height:340px}}
</style>

<main class="zone-import">
    <div class="zi-head">
        <div><div class="zi-muted">GIS / ZONES / IMPORT</div><h1>ورود زون از Shapefile</h1><p class="zi-muted mb-0">فایل ابتدا خصوصی بررسی و روی نقشه پیش‌نمایش می‌شود؛ تا تأیید نهایی جدول زون تغییر نمی‌کند.</p></div>
        <a href="{{ route('zones.manage') }}" class="btn btn-outline-secondary">بازگشت به مدیریت زون‌ها</a>
    </div>

    @if(session('zone_import_result'))
        @php($result = session('zone_import_result'))
        <div class="alert alert-success">ورود کامل شد: {{ $result['created'] }} ایجاد، {{ $result['updated'] }} به‌روزرسانی، {{ $result['skipped'] }} رد/تکراری و {{ $result['failed'] }} نامعتبر.</div>
    @endif
    @if(session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="zi-error"><strong>ورود انجام نشد.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @php($step = ! $meta ? 1 : (($meta['status'] ?? '') === 'previewed' ? 4 : 2))
    <nav class="zi-steps" aria-label="مراحل ورود">
        @foreach(['آپلود فایل','Mapping فیلدها','پیش‌نمایش و اعتبارسنجی','تأیید و ثبت نهایی'] as $index => $label)
            <div class="zi-step {{ $step === $index + 1 ? 'active' : ($step > $index + 1 ? 'done' : '') }}">{{ $index + 1 }}. {{ $label }}</div>
        @endforeach
    </nav>

    @if(! $meta)
        <section class="zi-card">
            <h2 class="h5">۱. انتخاب ZIP شیپ‌فایل</h2>
            <p class="zi-muted">ZIP باید شامل فایل‌های هم‌نام SHP، SHX، DBF و PRJ باشد. فایل CPG برای تشخیص Encoding قابل‌قبول است.</p>
            <form method="POST" action="{{ route('zones.import.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="zi-field"><label for="shapefile">فایل ZIP (حداکثر ۲۵ مگابایت)</label><input id="shapefile" class="form-control" type="file" name="file" accept=".zip,application/zip" required></div>
                <div class="zi-actions"><button class="btn btn-primary">آپلود و بررسی ساختار</button></div>
            </form>
            <div class="zi-warning">در Schema فعلی زون فقط نام، سازمان، سازنده و هندسه ذخیره می‌شوند؛ کد زون یا توضیحات ستونی در جدول ندارند.</div>
        </section>
    @else
        <section class="zi-card">
            <h2 class="h5">۱. فایل شناسایی‌شده</h2>
            <div class="zi-grid">
                <div><strong>هندسه:</strong> {{ $meta['geometry'] }}</div>
                <div><strong>تعداد رکورد:</strong> {{ number_format($meta['feature_count']) }}</div>
                <div><strong>CRS منبع:</strong> {{ $meta['crs'] }}</div>
                <div><strong>خروجی پایگاه:</strong> EPSG:4326 (با تبدیل GDAL)</div>
            </div>
        </section>

        @if(($meta['status'] ?? '') !== 'previewed')
            <section class="zi-card">
                <h2 class="h5">۲. نگاشت فیلدها</h2>
                <p class="zi-muted">فیلدهای DBF واقعی شناسایی‌شده را انتخاب کنید. سازمان به‌صورت ثابت انتخاب می‌شود و قابل‌مپ از فایل نیست.</p>
                <form method="POST" action="{{ route('zones.import.preview', $token) }}">
                    @csrf
                    <div class="zi-grid">
                        <div class="zi-field">
                            <label for="name_field">نام زون ← ستون مبدأ DBF (اجباری)</label>
                            <select class="form-select" name="name_field" id="name_field" required>
                                <option value="">انتخاب ستون</option>
                                @foreach($meta['fields'] as $field)
                                    <option value="{{ $field['name'] }}" @selected(old('name_field') === $field['name'])>{{ $field['name'] }} — {{ $field['type'] }}</option>
                                @endforeach
                            </select>
                            <small>نام مقصد از نوع رشته و حداکثر ۱۵۰ نویسه است.</small>
                        </div>
                        <div class="zi-field">
                            <label for="organization_id">سازمان مقصد</label>
                            <select class="form-select" name="organization_id" id="organization_id" required>
                                <option value="">انتخاب سازمان مجاز</option>
                                @foreach($organizations as $organization)
                                    <option value="{{ $organization->id }}" @selected((string)old('organization_id') === (string)$organization->id)>{{ $organization->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="zi-field">
                            <label for="encoding">Encoding متن DBF</label>
                            <select class="form-select" name="encoding" id="encoding" required>
                                <option value="">انتخاب Encoding</option>
                                @foreach($encodings as $encoding)
                                    <option value="{{ $encoding }}" @selected(old('encoding', $meta['encoding_hint']) === $encoding)>{{ $encoding }}{{ $meta['encoding_hint'] === $encoding ? ' — تشخیص از CPG' : '' }}</option>
                                @endforeach
                            </select>
                            <small>در نبود CPG، Encoding را بر اساس فایل مبدأ انتخاب کنید.</small>
                        </div>
                    </div>
                    <h3 class="h6 mt-4">فیلدهای منبع</h3>
                    <div class="zi-table-wrap"><table class="zi-table"><thead><tr><th>نام DBF</th><th>نوع تشخیص‌داده‌شده</th><th>نمونه</th></tr></thead><tbody>
                    @foreach($meta['fields'] as $field)<tr><td>{{ $field['name'] }}</td><td>{{ $field['type'] }}</td><td class="zi-muted">نمونه هنگام Preview نمایش داده می‌شود</td></tr>@endforeach
                    </tbody></table></div>
                    <div class="zi-actions"><button class="btn btn-primary">بررسی Mapping و پیش‌نمایش</button></div>
                </form>
            </section>
        @else
            @php($summary = $meta['summary'])
            <section class="zi-card">
                <h2 class="h5">۳. پیش‌نمایش و اعتبارسنجی</h2>
                <div class="zi-stats">
                    <div class="zi-stat"><strong>{{ number_format($summary['feature_count']) }}</strong>کل رکوردها</div>
                    <div class="zi-stat"><strong>{{ number_format($summary['valid_count']) }}</strong>هندسه/نام معتبر</div>
                    <div class="zi-stat"><strong>{{ number_format($summary['invalid_count']) }}</strong>نامعتبر</div>
                    <div class="zi-stat"><strong>{{ number_format($summary['existing_duplicate_count'] + $summary['file_duplicate_count']) }}</strong>تکراری</div>
                </div>
                <p class="zi-muted mt-3">نام مقصد ← {{ $summary['mapping']['name_field'] }}؛ سازمان ثابت ← {{ $organizations->firstWhere('id', $summary['mapping']['organization_id'])?->name }}؛ Encoding ← {{ $summary['mapping']['encoding'] }}.</p>
                @if($summary['invalid_count'] > 0)<div class="zi-warning">موارد نامعتبر وارد نمی‌شوند. در مرحله تأیید باید صریحاً انتخاب کنید که فقط موارد معتبر ثبت شوند.</div>@endif
                @if($summary['existing_duplicate_count'] > 0)<div class="zi-warning">تکراری‌ها بر اساس نام نرمال‌شده در همان سازمان پیدا می‌شوند. {{ $summary['existing_linked_count'] }} مورد به کاربران، علمک‌ها یا پرونده‌ها مرتبط است و حتی در حالت Update نیز تغییر نمی‌کند.</div>@endif
                @if($summary['issues'])
                    <div class="zi-table-wrap"><table class="zi-table"><thead><tr><th>ردیف</th><th>علت رد</th></tr></thead><tbody>@foreach($summary['issues'] as $issue)<tr><td>{{ $issue['row'] }}</td><td>{{ $issue['reason'] }}</td></tr>@endforeach</tbody></table></div>
                @endif
                <div class="zi-grid mt-3"><div><strong>نمونه مقادیر DBF ({{ count($summary['samples']) }} رکورد)</strong><div class="zi-table-wrap"><table class="zi-table"><thead><tr>@foreach($meta['fields'] as $field)<th>{{ $field['name'] }}</th>@endforeach</tr></thead><tbody>@foreach($summary['samples'] as $sample)<tr>@foreach($meta['fields'] as $field)<td>{{ $sample['source_values'][$field['name']] ?? '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div></div>
                    <div><strong>پیش‌نمایش نقشه (حداکثر ۱۰۰ هندسه معتبر)</strong><div id="zoneImportMap" class="zi-map" aria-label="پیش‌نمایش هندسه زون‌ها"></div></div>
                </div>
            </section>
            <section class="zi-card">
                <h2 class="h5">۴. تأیید و ثبت نهایی</h2>
                <form method="POST" action="{{ route('zones.import.confirm', $token) }}">
                    @csrf
                    <div class="zi-field"><label for="duplicate_mode">نحوه برخورد با زون تکراری</label>
                        <select class="form-select" name="duplicate_mode" id="duplicate_mode" required>
                            <option value="skip" selected>فقط زون‌های جدید؛ تکراری‌ها رد شوند (پیشنهادی)</option>
                            <option value="reject">اگر مورد تکراری هست کل Import متوقف شود</option>
                            <option value="update">به‌روزرسانی هندسه فقط برای زون تکراریِ بدون ارتباط</option>
                        </select>
                    </div>
                    @if($summary['invalid_count'] > 0)
                        <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="skip_invalid" value="1" required> فقط رکوردهای معتبر را وارد کن و موارد نامعتبر را رد کن.</label>
                    @endif
                    <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="confirm_import" value="1" required> فایل، Mapping، سازمان مقصد و نتیجه Preview را بررسی و ثبت نهایی را تأیید می‌کنم.</label>
                    <div class="zi-actions"><button class="btn btn-success">تأیید و ثبت زون‌ها</button></div>
                </form>
            </section>
            <script>
                document.addEventListener('DOMContentLoaded', async () => {
                    const target = document.getElementById('zoneImportMap');
                    if (!target || !window.ol) return;
                    const source = new ol.source.Vector();
                    const layer = new ol.layer.Vector({source, style: new ol.style.Style({
                        fill: new ol.style.Fill({color: 'rgba(37,99,235,.18)'}),
                        stroke: new ol.style.Stroke({color: '#1d4ed8', width: 2})
                    })});
                    const map = new ol.Map({target, layers: [new ol.layer.Tile({source: new ol.source.OSM()}), layer], view: new ol.View({center: ol.proj.fromLonLat([49.6892,34.0954]), zoom: 8})});
                    try {
                        const response = await fetch(@json($previewUrl), {headers: {'Accept':'application/geo+json'}, credentials:'same-origin'});
                        if (!response.ok) throw new Error('دریافت پیش‌نمایش ناموفق بود.');
                        const data = await response.json();
                        const features = new ol.format.GeoJSON().readFeatures(data, {dataProjection:'EPSG:4326', featureProjection:'EPSG:3857'});
                        source.addFeatures(features);
                        if (features.length) map.getView().fit(source.getExtent(), {padding:[24,24,24,24], maxZoom:15});
                    } catch (error) { target.textContent = error.message; }
                });
            </script>
        @endif

        <div class="zi-actions"><form method="POST" action="{{ route('zones.import.cancel', $token) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">لغو و پاک‌کردن فایل موقت</button></form></div>
    @endif
</main>
@endsection
