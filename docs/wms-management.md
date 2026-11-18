# مدیریت سرویس‌های WMS

منوی «مدیریت لایه‌های WMS» در تنظیمات GIS با مجوز `wms.manage` در مسیر `/settings/gis-map/wms` قرار دارد. ثبت سرویس، GetCapabilities را از Backend دریافت می‌کند و فقط Layerهایی که Name دارند در `wms_layers` ذخیره می‌شوند. Metadata شامل نسخه، CRS، فرمت‌ها، Abstract، Queryable و Legend URL نگهداری می‌شود.

جداول `wms_services` و `wms_layers` در migration جدید ایجاد شده‌اند؛ این migration عمداً روی دیتابیس عملیاتی اجرا نشده است. سرویس‌های global برای کاربران مجاز سازمان‌ها خواندنی هستند و سرویس سازمانی فقط در همان سازمان نمایش داده می‌شود.

نقشه وب فهرست را از `/api/map/wms-layers` می‌خواند و برای هر لایه یک TileWMS مستقل می‌سازد. URL درخواست تصویر از Proxy محدود `/map/wms/{service}/{layer}` عبور می‌کند؛ Proxy فقط لایه ثبت‌شده را می‌پذیرد. روشن/خاموش و opacity در Local Storage با کلید نسخه‌دار `alamak:wms-layers:v1` ذخیره می‌شود.

در محیط اداره، پس از بررسی allowlist شبکه در `GIS_ALLOWED_INTERNAL_HOSTS`، migration جدید باید با مجوز مالک دیتابیس اجرا شود و سپس `php artisan db:seed --class=RolePermissionSeeder --class=MenuSeeder` یا معادل فرایند استقرار سازمان اجرا گردد. تست سرویس از Backend انجام می‌شود و Token/Password در URL پذیرفته یا ثبت نمی‌شود.
