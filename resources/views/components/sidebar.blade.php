<div id="sidebar">

    <button class="toggle-btn" onclick="toggleSidebar()">
        ☰ منو
    </button>

    {{ auth()->user()->name }}
    <hr>

    @foreach ($menus as $menu)
        @include('menus.item', ['menu' => $menu])
    @endforeach


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
