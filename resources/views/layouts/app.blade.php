<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>

    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icon.css') }}" rel="stylesheet">
    <link href="{{ url('css/boot-icon.css') }}" rel="stylesheet">
    <script src="{{ url('js/bootstrap.js') }}"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">

    <script src="{{ asset('js/sidebar.js') }}"></script>

    <style>
        .designer-signature {
            position: fixed;
            bottom: 10px;
            left: 14px;
            z-index: 999;

            font-size: 11px;
            color: #969da7;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: .04em;

            opacity: .55;
            user-select: none;
            pointer-events: none;
            transition: .2s;
        }

        .designer-signature strong {
            color: #5f636d;
            font-weight: 700;
        }

        .designer-signature:hover {
            opacity: .9;
        }

        /* ===================================================
           DESIGN TOKENS — پالت روشن و آرام برای کار طولانی با داده
        =================================================== */
        :root {
            --bg: #F4F6FA;
            --surface: #FFFFFF;
            --surface-2: #F8F9FC;
            --border: #E6E9F0;
            --border-2: #DCE1EA;

            --text: #1F2430;
            --text-muted: #667085;
            --text-faint: #98A1B3;

            --primary: #3E63DD;
            --primary-dark: #3453BE;
            --primary-soft: #EAEEFD;
            --primary-ink: #FFFFFF;

            --success: #12946F;
            --success-soft: #E3F6EF;
            --warning: #B5730A;
            --warning-soft: #FDF2E0;
            --danger: #D64545;
            --danger-soft: #FDECEC;

            --font-body: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;

            --radius: 12px;
            --radius-sm: 9px;
            --shadow-sm: 0 1px 2px rgba(16, 24, 40, .05);
            --shadow-md: 0 10px 28px -14px rgba(16, 24, 40, .18);

            --sidebar-w: 258px;
            --sidebar-w-col: 76px;
            --topbar-h: 58px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            background: var(--bg);
        }

        body {
            font-family: var(--font-body);
            color: var(--text);
            overflow-x: hidden;
            line-height: 1.75;
            font-size: 14.5px;
        }

        a {
            text-decoration: none;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: var(--font-body);
            font-weight: 800;
        }

        /* ============== MOBILE TOPBAR ============== */
        #topbar {
            display: none;
            position: sticky;
            top: 0;
            z-index: 1020;
            height: var(--topbar-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            align-items: center;
            justify-content: space-between;
            padding: 0 14px;
        }

        #topbar .topbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 14.5px;
            color: var(--text);
        }

        #topbar .topbar-brand .plate-mark {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .hamburger-btn {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(20, 24, 34, .38);
            z-index: 1035;
            opacity: 0;
            transition: opacity .2s ease;
        }

        #sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* ============== SIDEBAR ============== */
        #sidebar {
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--surface);
            border-inline-start: 1px solid var(--border);
            color: var(--text);
            position: fixed;
            right: 0;
            top: 0;
            transition: width .2s ease, transform .25s ease;
            padding: 16px 12px;
            z-index: 1040;
            display: flex;
            flex-direction: column;
        }

        #sidebar.collapsed {
            width: var(--sidebar-w-col);
        }

        .rail-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 6px 16px 6px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 12px;
        }

        .rail-brand .plate-mark {
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            border-radius: 9px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
        }

        .rail-brand .rail-title {
            font-weight: 800;
            font-size: 14.5px;
            line-height: 1.3;
            white-space: nowrap;
        }

        .rail-brand .rail-sub {
            font-size: 10.5px;
            color: var(--text-faint);
            font-family: var(--font-mono);
            letter-spacing: .04em;
        }

        #sidebar.collapsed .rail-title,
        #sidebar.collapsed .rail-sub {
            display: none;
        }

        .toggle-btn {
            background: var(--surface-2);
            color: var(--text-muted);
            border: 1px solid var(--border);
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            transition: .15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .toggle-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
            background: var(--primary-soft);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 8px 14px;
            margin-bottom: 4px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .sidebar-user .dot-live {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 3px var(--success-soft);
            flex: 0 0 auto;
        }

        #sidebar hr {
            border-color: var(--border);
            opacity: 1;
            margin: 4px 0 10px;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
        }

        #sidebar a {
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: var(--radius-sm);
            white-space: nowrap;
            font-size: 13.5px;
            font-weight: 600;
            transition: .12s;
        }

        #sidebar a i {
            font-size: 15.5px;
            width: 18px;
            text-align: center;
            color: var(--text-faint);
        }

        #sidebar a:hover {
            color: var(--text);
            background: var(--surface-2);
        }

        #sidebar a:hover i {
            color: var(--primary);
        }

        #sidebar a span.text {
            margin-inline-start: 1px;
        }

        #sidebar.collapsed a span.text {
            display: none;
        }

        #sidebar.collapsed .menu-toggle {
            justify-content: center;
        }

        #sidebar.collapsed a {
            justify-content: center;
        }

        .menu-group {
            margin-bottom: 2px;
        }

        .menu-toggle {
            cursor: pointer;
            justify-content: space-between !important;
        }

        .submenu {
            display: none;
            margin-inline-start: 10px;
            padding-inline-start: 10px;
            border-inline-start: 1px dashed var(--border-2);
        }

        .submenu.show {
            display: block;
        }

        .submenu a {
            font-size: 13px;
            padding: 8px 10px;
            font-weight: 500;
        }

        .active-menu {
            background: var(--primary-soft) !important;
            color: var(--primary) !important;
        }

        .active-menu i {
            color: var(--primary) !important;
        }

        .arrow {
            transition: .2s;
            color: var(--text-faint);
            font-size: 11px !important;
        }

        .menu-toggle.open .arrow {
            transform: rotate(180deg);
        }

        .sidebar-logout {
            border-top: 1px solid var(--border);
            padding-top: 8px;
            margin-top: 6px;
        }

        .sidebar-logout button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            width: 100%;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-weight: 600;
        }

        .sidebar-logout button:hover {
            color: var(--danger);
            background: var(--danger-soft);
        }

        /* ============== CONTENT ============== */
        #content {
            margin-inline-start: var(--sidebar-w);
            padding: 26px 28px 44px;
            transition: margin .2s ease;
            min-height: 100vh;
        }

        #content.full {
            margin-inline-start: var(--sidebar-w-col);
        }

        /* ============== SHARED COMPONENTS ============== */
        .panel,
        .card-box,
        .card,
        .card-custom {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            color: var(--text);
        }

        .card-header,
        .panel-head {
            background: var(--surface-2) !important;
            border-bottom: 1px solid var(--border) !important;
            color: var(--text) !important;
            font-weight: 700;
            border-radius: var(--radius) var(--radius) 0 0 !important;
        }

        .card-header.bg-primary,
        .card.bg-primary.text-white {
            background: var(--surface-2) !important;
            border-bottom: 1px solid var(--border) !important;
            color: var(--text) !important;
            position: relative;
            padding-inline-start: 18px;
        }

        .card-header.bg-primary::before {
            content: "";
            position: absolute;
            inset-inline-start: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary);
            border-radius: var(--radius) 0 0 0;
        }

        .table {
            color: var(--text);
            margin-bottom: 0;
        }

        .table thead.table-dark,
        .table thead.table-light,
        .table thead {
            background: var(--surface-2) !important;
            color: var(--text-muted) !important;
            font-size: 12.5px;
            font-weight: 700;
        }

        .table-striped>tbody>tr:nth-of-type(odd)>* {
            background: var(--surface-2);
            color: var(--text);
        }

        .table-hover>tbody>tr:hover>* {
            background: var(--primary-soft);
            color: var(--text);
        }

        .table-bordered,
        .table-bordered> :not(caption)>* {
            border-color: var(--border);
        }

        .table> :not(caption)>*>* {
            border-bottom-color: var(--border);
            padding: 11px 12px;
            vertical-align: middle;
        }

        .table-responsive {
            border-radius: var(--radius-sm);
        }

        /* plate — نشان کد علمک، موتیف اصلی طراحی */
        .plate {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-mono);
            font-weight: 600;
            font-size: 12.5px;
            letter-spacing: .02em;
            color: var(--primary-dark);
            background: var(--primary-soft);
            border: 1px solid #DCE3FB;
            border-radius: 6px;
            padding: 4px 10px;
        }

        .plate::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--primary);
        }

        .plate.plate-lg {
            font-size: 19px;
            padding: 8px 16px;
        }

        /* status pill — به‌جای بج معمولی بوت‌استرپ */
        .status-pill,
        .led {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 700;
            padding: 4px 12px 4px 10px;
            border-radius: 999px;
        }

        .status-pill::before,
        .led::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .status-done,
        .led-done,
        .led-success,
        .status-success {
            color: var(--success);
            background: var(--success-soft);
        }

        .status-done::before,
        .led-done::before,
        .led-success::before,
        .status-success::before {
            background: var(--success);
        }

        .status-pending,
        .led-pending,
        .led-warning,
        .status-warning {
            color: var(--warning);
            background: var(--warning-soft);
        }

        .status-pending::before,
        .led-pending::before,
        .led-warning::before,
        .status-warning::before {
            background: var(--warning);
        }

        .status-reject,
        .led-reject,
        .led-danger,
        .status-danger {
            color: var(--danger);
            background: var(--danger-soft);
        }

        .status-reject::before,
        .led-reject::before,
        .led-danger::before,
        .status-danger::before {
            background: var(--danger);
        }

        .badge.bg-success {
            background: var(--success-soft) !important;
            color: var(--success) !important;
            font-weight: 700;
        }

        .badge.bg-danger {
            background: var(--danger-soft) !important;
            color: var(--danger) !important;
            font-weight: 700;
        }

        .badge.bg-primary {
            background: var(--primary-soft) !important;
            color: var(--primary-dark) !important;
            font-weight: 700;
        }

        .badge.bg-warning {
            background: var(--warning-soft) !important;
            color: var(--warning) !important;
            font-weight: 700;
        }

        .btn {
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 13.5px;
            padding: 8px 16px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12.5px;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            color: #fff;
        }

        .btn-success {
            background: var(--success);
            border-color: var(--success);
        }

        .btn-success:hover {
            background: #0d7a5c;
            border-color: #0d7a5c;
        }

        .btn-danger {
            background: var(--danger);
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: #bb3a3a;
            border-color: #bb3a3a;
        }

        .btn-warning {
            background: var(--warning-soft);
            border-color: #F0DDB0;
            color: var(--warning);
        }

        .btn-warning:hover {
            background: #FBE7C2;
            border-color: #F0DDB0;
            color: var(--warning);
        }

        .btn-secondary {
            background: var(--surface-2);
            border-color: var(--border);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #EEF0F5;
            border-color: var(--border-2);
            color: var(--text);
        }

        .btn-outline-danger {
            color: var(--danger);
            border-color: #F3C6C6;
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: #fff;
        }

        .form-control,
        .form-select {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: var(--radius-sm);
            font-size: 13.75px;
        }

        .form-control:focus,
        .form-select:focus {
            background: var(--surface);
            border-color: var(--primary);
            color: var(--text);
            box-shadow: 0 0 0 .18rem var(--primary-soft);
        }

        .form-control::placeholder {
            color: var(--text-faint);
        }

        .form-label {
            color: var(--text-muted);
            font-size: 12.5px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .invalid-feedback,
        .text-danger {
            font-size: 12px;
        }

        .alert-success {
            background: var(--success-soft);
            color: #0a6e51;
            border: 1px solid #C7EEE0;
            border-radius: var(--radius-sm);
        }

        .alert-danger {
            background: var(--danger-soft);
            color: #a33333;
            border: 1px solid #F5CACA;
            border-radius: var(--radius-sm);
        }

        .modal-content {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .modal-header,
        .modal-footer {
            border-color: var(--border) !important;
        }

        .page-title,
        h1,
        h2,
        h3,
        h4,
        h5 {
            font-family: var(--font-body);
        }

        .pagination .page-link {
            color: var(--text);
            border-color: var(--border);
        }

        .pagination .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-2);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        /* ===== reusable page-header block ===== */
        .page-eyebrow {
            font-family: var(--font-mono);
            font-size: 11px;
            letter-spacing: .08em;
            color: var(--text-faint);
            margin-bottom: 4px;
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .page-heading h4,
        .page-heading h3 {
            font-weight: 800;
            margin: 0;
        }

        /* ===== stat cards (dashboard/report) ===== */
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 18px;
            text-align: center;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* ===== empty state ===== */
        .empty-state {
            text-align: center;
            padding: 44px 20px;
            color: var(--text-faint);
        }

        .empty-state i {
            font-size: 26px;
            display: block;
            margin-bottom: 8px;
        }

        /* ============== RESPONSIVE ============== */

        @media (max-width: 991.98px) {

            #topbar {
                display: flex;
            }


            #sidebar {

                width: 280px;
                max-width: 82vw;

                transform: translateX(110%);

                box-shadow: -10px 0 40px -10px rgba(16, 24, 40, .25);

            }



            #sidebar.mobile-open {

                transform: translateX(0);

            }



            #sidebar.collapsed {

                width: 280px;

            }



            #sidebar.collapsed .rail-title,
            #sidebar.collapsed .rail-sub,
            #sidebar.collapsed a span.text {

                display: block;

            }



            #sidebar.collapsed a {

                justify-content: flex-start;

            }



            #content,
            #content.full {

                width: 100%;

                margin: 0 !important;

                padding: 18px 16px 32px;

            }

        }



        @media (max-width:575.98px) {

            #content,
            #content.full {

                width: 100%;

                padding: 15px 12px 25px;

            }


            .page-heading {

                flex-direction: column;

                align-items: flex-start;

            }


            .stat-card .stat-value {

                font-size: 22px;

            }

        }
    </style>

    @stack('styles')
    @yield('styles')
</head>

<body>
    <div class="designer-signature">
        Designed & Developed by
        <strong>Saeed Zolfeli</strong>
    </div>

    <div id="sidebar-overlay" onclick="closeMobileSidebar()"></div>

    <!-- MOBILE TOPBAR -->
    <div id="topbar">
        <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="باز کردن منو">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-brand">
            <span class="plate-mark"><i class="bi bi-diagram-3"></i></span>
            سامانه علمک‌ها
        </div>
        <span style="width:40px;"></span>
    </div>

    <x-sidebar />

    <!-- CONTENT -->
    <div id="content">

        @yield('content')

    </div>

    <script>
        function isDesktop() {

            return window.matchMedia('(min-width: 992px)').matches;

        }



        function toggleSidebar() {


            const sidebar = document.getElementById('sidebar');

            const content = document.getElementById('content');

            const overlay = document.getElementById('sidebar-overlay');



            if (isDesktop()) {


                sidebar.classList.toggle('collapsed');

                content.classList.toggle('full');


            } else {


                if (sidebar.classList.contains('mobile-open')) {


                    closeMobileSidebar();


                } else {


                    sidebar.classList.add('mobile-open');

                    overlay.classList.add('show');


                }


            }

        }





        function closeMobileSidebar() {


            document
                .getElementById('sidebar')
                .classList.remove('mobile-open');



            document
                .getElementById('sidebar-overlay')
                .classList.remove('show');


        }





        // بستن منو بعد انتخاب لینک در موبایل

        document.querySelectorAll('#sidebar a:not(.menu-toggle)')
            .forEach(function(link) {


                link.addEventListener('click', function() {


                    if (!isDesktop()) {


                        closeMobileSidebar();


                    }


                });


            });





        // کلیک روی محتوای صفحه منو را ببندد

        document
            .getElementById('content')
            .addEventListener('click', function() {


                if (!isDesktop()) {


                    closeMobileSidebar();


                }


            });





        // تغییر سایز صفحه

        window.addEventListener('resize', function() {


            if (isDesktop()) {


                document
                    .getElementById('sidebar-overlay')
                    .classList.remove('show');



                document
                    .getElementById('sidebar')
                    .classList.remove('mobile-open');


            }


        });
    </script>

    <script>
        document.querySelectorAll('.menu-toggle').forEach(function(item) {

            item.addEventListener('click', function(e) {

                e.preventDefault();

                let id = this.dataset.menu;

                let submenu = document.getElementById('submenu' + id);

                this.classList.toggle('open');

                if (submenu.style.display == "block") {

                    submenu.style.display = "none";

                } else {

                    submenu.style.display = "block";

                }

            });

        });
    </script>

    @stack('scripts')
    @yield('scripts')

</body>

</html>
