<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>@yield('title')</title>

    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{  url('css/bootstrap-icon.css')  }}" rel="stylesheet">
    <link href="{{  url('css/boot-icon.css')  }}" rel="stylesheet">
    <script src="{{ asset('js/sidebar.js') }}"></script>

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
    <style>
        .menu-group {
            margin-bottom: 5px;
        }

        .menu-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .submenu {
            display: none;
            margin-right: 15px;
        }

        .submenu.show {
            display: block;
        }

        .submenu a {
            font-size: 14px;
            color: #d0d0d0;
        }

        .submenu a:hover {
            color: #38bdf8;
        }

        .active-menu {
            background: #1f2937;
            color: #38bdf8 !important;
            border-radius: 6px;
        }

        .arrow {
            transition: .3s;
        }

        .menu-toggle.open .arrow {
            transform: rotate(180deg);
        }
    </style>

</head>

<body>

    <x-sidebar />

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
