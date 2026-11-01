<?php

return [
    'default_base_tile_url' => env('GIS_BASE_TILE_URL', '/tiles/{z}/{x}/{y}.png'),
    'default_attribution' => env('GIS_BASE_TILE_ATTRIBUTION'),
    'allowed_internal_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('GIS_ALLOWED_INTERNAL_HOSTS', ''))))),

    // برای افزودن لایه جدید، یک عضو با کلید یکتا به این آرایه اضافه کنید.
    // vector_tile به endpoint شامل {z}/{x}/{y} نیاز دارد.
    // برای WMS، endpoint آدرس GetMap و layers نام لایه منتشرشده است.
    'layers' => [
        'riser' => [
            'title' => 'علمک‌ها',
            'type' => 'vector_tile',
            'endpoint' => '/tiles/riser/{z}/{x}/{y}.pbf',
            'visible' => true,
            'min_zoom' => 0,
            'max_zoom' => 22,
        ],

        // نمونه WMS (پس از تکمیل endpoint و layers، visible را true کنید):
        // 'service_zones' => [
        //     'title' => 'محدوده‌های خدماتی',
        //     'type' => 'wms',
        //     'endpoint' => 'https://maps.example.test/geoserver/wms',
        //     'layers' => 'workspace:service_zones',
        //     'format' => 'image/png',
        //     'transparent' => true,
        //     'version' => '1.3.0',
        //     'styles' => '',
        //     'server_type' => 'geoserver',
        //     'visible' => false,
        //     'min_zoom' => 0,
        //     'max_zoom' => 22,
        // ],
    ],
];
