<?php

return [
    'ogrinfo' => env('GDAL_OGRINFO', 'ogrinfo'),
    'ogr2ogr' => env('GDAL_OGR2OGR', 'ogr2ogr'),
    'gdal_data' => env('GDAL_DATA'),
    'proj_data' => env('PROJ_DATA', env('PROJ_LIB')),
];
