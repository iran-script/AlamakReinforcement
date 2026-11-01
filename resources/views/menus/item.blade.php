@php
    $hasChildren = $menu->children->isNotEmpty();
    $isGroupActive = $hasChildren && $menu->hasActiveDescendant();
@endphp

@if ($hasChildren)
    <section class="menu-group {{ $isGroupActive ? 'is-active' : '' }}">
        <a href="#"
           class="menu-toggle {{ $isGroupActive ? 'open' : '' }}"
           data-menu="{{ $menu->route }}"
           aria-controls="submenu{{ $menu->id }}"
           aria-expanded="{{ $isGroupActive ? 'true' : 'false' }}">
            <span class="menu-label">
                @if ($menu->icon)<i class="{{ $menu->icon }}" aria-hidden="true"></i>@endif
                <span class="text">{{ $menu->title }}</span>
            </span>
            <i class="bi bi-chevron-down arrow" aria-hidden="true"></i>
        </a>
        <div id="submenu{{ $menu->id }}" class="submenu {{ $isGroupActive ? 'show' : '' }}">
            @foreach ($menu->children as $child)
                @include('menus.item', ['menu' => $child])
            @endforeach
        </div>
    </section>
@elseif ($menu->route && \Illuminate\Support\Facades\Route::has($menu->route))
    <a href="{{ route($menu->route) }}"
       class="menu-link {{ $menu->isActive() ? 'active-menu' : '' }}"
       @if ($menu->isActive()) aria-current="page" @endif>
        @if ($menu->icon)<i class="{{ $menu->icon }}" aria-hidden="true"></i>@endif
        <span class="text">{{ $menu->title }}</span>
    </a>
@endif
