<div id="sidebar">

    <div class="rail-brand">
        <div class="plate-mark">
            <i class="bi bi-diagram-3"></i>
        </div>
        <div>
            <div class="rail-title">سامانه علمک‌ها</div>
            <div class="rail-sub">FIELD OPS · GIS</div>
        </div>
    </div>

    <button class="toggle-btn d-none d-lg-flex" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
        <span class="text">جمع کردن منو</span>
    </button>

    <div class="sidebar-user">
        <span class="dot-live"></span>
        <span class="text">{{ auth()->user()->name }}</span>
    </div>

    <hr>

    <div class="sidebar-nav">
        @foreach ($menus as $menu)
            @include('menus.item', ['menu' => $menu])
        @endforeach
        @can('viewAny', App\Models\FormDefinition::class)
            <a href="{{ route('platform.forms.index') }}" class="{{ request()->routeIs('platform.forms.*') ? 'active-menu' : '' }}">
                <i class="bi bi-ui-checks-grid"></i>
                <span class="text">فرم‌ساز / مدیریت فرم‌ها</span>
            </a>
        @endcan
        @can('viewAny', App\Models\WorkflowDefinition::class)
            <a href="{{ route('platform.workflows.index') }}" class="{{ request()->routeIs('platform.workflows.*') ? 'active-menu' : '' }}">
                <i class="bi bi-diagram-3"></i><span class="text">مدیریت گردش‌کار</span>
            </a>
        @endcan
        @can('create', App\Models\GisMapSetting::class)
            <a href="{{ route('settings.gis.edit') }}" class="{{ request()->routeIs('settings.gis.*') ? 'active-menu' : '' }}">
                <i class="bi bi-map"></i><span class="text">تنظیمات نقشه GIS</span>
            </a>
        @endcan
    </div>


    <div class="sidebar-logout">

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit">
                <i class="bi bi-box-arrow-right"></i>
                <span class="text">
                    خروج
                </span>
            </button>

        </form>

    </div>
</div>
