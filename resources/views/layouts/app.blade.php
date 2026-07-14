<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>@yield('title')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            background: #f4f6f9;
            overflow-x: hidden;
        }

        /* Sidebar */
        #sidebar {
            width: 250px;
            height: 100vh;
            background: #111827;
            color: white;
            position: fixed;
            right: 0;
            top: 0;
            transition: 0.3s;
            padding: 15px;
        }

        /* collapsed */
        #sidebar.collapsed {
            width: 70px;
        }

        /* links */
        #sidebar a {
            color: #ddd;
            display: block;
            padding: 10px;
            text-decoration: none;
            white-space: nowrap;
        }

        #sidebar a:hover {
            color: #38bdf8;
        }

        #sidebar a span.text {
            margin-right: 8px;
        }

        /* hide text when collapsed */
        #sidebar.collapsed a span.text {
            display: none;
        }

        /* content */
        #content {
            margin-right: 250px;
            padding: 20px;
            transition: 0.3s;
        }

        #content.full {
            margin-right: 70px;
        }

        /* toggle */
        .toggle-btn {
            background: #1f2937;
            color: white;
            border: none;
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        /* card style */
        .card-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
        }
    </style>

</head>

<body>

    <!-- SIDEBAR -->
    <div id="sidebar">

        <button class="toggle-btn" onclick="toggleSidebar()">☰ منو</button>

        <a href="{{ route('dashboard') }}">
            <span>📊</span><span class="text">داشبورد</span>
        </a>

        <a href="{{ route('map') }}">
            <span>🗺</span><span class="text">نقشه GIS</span>
        </a>

        @foreach ($menus as $menu)
            @if ($menu->children->count())
                <div class="mb-2">

                    <a href="#" class="menu-toggle d-flex justify-content-between align-items-center"
                        data-menu="{{ $menu->id }}">

                        <div>

                            <i class="{{ $menu->icon }}"></i>

                            <span class="text">

                                {{ $menu->title }}

                            </span>

                        </div>

                        <i class="bi bi-chevron-down text"></i>

                    </a>

                    <div id="submenu{{ $menu->id }}" class="submenu" style="display:none;">

                        @foreach ($menu->children as $child)
                            @can($child->permission ?? '')
                                <a href="{{ route($child->route) }}" class="ps-4">

                                    <i class="{{ $child->icon }}"></i>

                                    <span class="text">

                                        {{ $child->title }}

                                    </span>

                                </a>
                            @else
                                @if (empty($child->permission))
                                    <a href="{{ route($child->route) }}" class="ps-4">

                                        <i class="{{ $child->icon }}"></i>

                                        <span class="text">

                                            {{ $child->title }}

                                        </span>

                                    </a>
                                @endif
                            @endcan
                        @endforeach

                    </div>

                </div>
            @else
                <a href="{{ route($menu->route) }}">

                    <i class="{{ $menu->icon }}"></i>

                    <span class="text">

                        {{ $menu->title }}

                    </span>

                </a>
            @endif
        @endforeach

    </div>

    <!-- CONTENT -->
    <div id="content">

        @yield('content')

    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('content').classList.toggle('full');
        }
    </script>

    <script>
        document.querySelectorAll('.menu-toggle').forEach(function(item) {

            item.addEventListener('click', function(e) {

                e.preventDefault();

                let id = this.dataset.menu;

                let submenu = document.getElementById('submenu' + id);

                if (submenu.style.display == "block") {

                    submenu.style.display = "none";

                } else {

                    submenu.style.display = "block";

                }

            });

        });
    </script>

</body>

</html>
