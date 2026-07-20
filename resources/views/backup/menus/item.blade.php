@php

    $canView = empty($menu->permission) 
        || (auth()->check() && auth()->user()->can($menu->permission));


    $hasVisibleChildren = $menu->children
        ->filter(function($child){

            return empty($child->permission)
                || (auth()->check() && auth()->user()->can($child->permission));

        })
        ->count() > 0;


@endphp


@if($canView || $hasVisibleChildren)


    @php
        $hasChildren = $menu->children->count() > 0;
    @endphp


    @if($hasChildren)


        <div class="menu-group">


            <a href="#"
               class="menu-toggle"
               data-menu="{{ $menu->id }}">


                <div>


                    @if($menu->icon)

                        <i class="{{ $menu->icon }}"></i>

                    @endif


                    <span class="text">
                        {{ $menu->title }}
                    </span>


                </div>


                <i class="bi bi-chevron-down arrow"></i>


            </a>



            <div
                id="submenu{{ $menu->id }}"
                class="submenu">


                @foreach($menu->children as $child)


                    @include('menus.item',['menu'=>$child])


                @endforeach


            </div>


        </div>


    @else



        <a
            href="{{ route($menu->route) }}"
            class="{{ $menu->isActive() ? 'active-menu' : '' }}">



            @if($menu->icon)

                <i class="{{ $menu->icon }}"></i>

            @endif



            <span class="text">

                {{ $menu->title }}

            </span>


        </a>



    @endif


@endif