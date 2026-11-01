<aside id="sidebar" aria-label="منوی اصلی">
    <div class="rail-brand">
        <div class="plate-mark"><i class="bi bi-diagram-3"></i></div>
        <div>
            <div class="rail-title">سامانه علمک‌ها</div>
            <div class="rail-sub">FIELD OPS · GIS</div>
        </div>
    </div>

    <button class="toggle-btn d-none d-lg-flex" type="button" onclick="toggleSidebar()" aria-label="جمع کردن منو">
        <i class="bi bi-list"></i><span class="text">جمع کردن منو</span>
    </button>

    <div class="sidebar-user">
        <span class="dot-live"></span><span class="text">{{ auth()->user()?->name }}</span>
    </div>
    <hr>

    <nav class="sidebar-nav" aria-label="بخش‌های سامانه">
        @foreach ($menus as $menu)
            @include('menus.item', ['menu' => $menu])
        @endforeach
    </nav>

    <div class="sidebar-logout">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">
                <i class="bi bi-box-arrow-right"></i><span class="text">خروج</span>
            </button>
        </form>
    </div>
</aside>
