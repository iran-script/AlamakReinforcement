# تنظیم پروفایل نقشه

تنظیمات نقشه پایه در همان جدول `gis_map_settings` نگهداری می‌شود. هر پروفایل نام، نوع سرویس، Tile URL، Attribution، Style URL اختیاری و محدوده Zoom دارد. در هر محدوده سازمانی حداکثر یک پروفایل فعال است. تنظیم سازمان بر تنظیم عمومی اولویت دارد.

مدیر مجاز از `/settings/gis-map` پروفایل می‌سازد، ویرایش می‌کند، فعال می‌کند و پروفایل غیرفعال را حذف می‌کند. تغییرها از طریق `AuditLogger` ثبت می‌شوند. نوع‌های پشتیبانی‌شده `xyz_raster` و `vector_mvt` هستند. انتخاب نقشه پایه، لایه عملیاتی علمک MVT را تغییر نمی‌دهد.

## Flutter API

پس از ورود با Sanctum و داشتن مجوز `map.view`، کلاینت این endpoint را می‌خواند:

```http
GET /api/map/config
Authorization: Bearer <sanctum-token>
Accept: application/json
```

پاسخ فقط پروفایل فعال و `schema_version` را دارد؛ URL ورود، توکن و تنظیم Backend در پاسخ قرار نمی‌گیرد. URLهای نسبی Tile و Style را Flutter باید نسبت به نشانی سروری که کاربر در تنظیم اولیه انتخاب کرده resolve کند. نشانی سرور باید فقط در Secure Storage کلاینت نگهداری شود، هیچ توکنی به دامنه Tile دیگری فرستاده نشود و توکن داخل Query String قرار نگیرد.

برای حالت بدون اتصال، کلاینت می‌تواند آخرین پروفایل دریافت‌شده را به‌عنوان cache نشان دهد. بعد از اتصال مجدد باید endpoint را دوباره بخواند و از پاسخ و مجوز فعلی سرور پیروی کند.

نمونه پاسخ:

```json
{
  "schema_version": 1,
  "map_profile": {
    "id": 12,
    "name": "شبکه داخلی",
    "service_type": "xyz_raster",
    "tile_url": "/maps/tiles/{z}/{x}/{y}.png",
    "style_url": null,
    "attribution": "نقشه داخلی",
    "min_zoom": 2,
    "max_zoom": 19
  }
}
```

لایه علمک‌ها منبع جداگانه `/tiles/riser/{z}/{x}/{y}.pbf` است و مجوز Zone و `map.view` آن در Backend کنترل می‌شود. پیکربندی Tile پایه، API احراز هویت یا مجوز داده‌های علمک را عوض نمی‌کند.

## امنیت URL

- URLهای دارای username/password، Query String، Fragment، backslash یا مسیر traversal رد می‌شوند؛ بنابراین توکن در URL قابل ذخیره نیست.
- در `local` می‌توان OSM HTTPS را برای توسعه تعریف کرد.
- در Production مسیر داخلی همان Laravel مجاز است. URL کامل فقط با HTTPS، پورت 443 و Host صریح در `GIS_ALLOWED_INTERNAL_HOSTS` پذیرفته می‌شود؛ Host عمومی و redirect-based probing پشتیبانی نمی‌شود.
- تنظیم نمونه Production:

```dotenv
GIS_ALLOWED_INTERNAL_HOSTS=tiles.intra.example,maps.intra.example
```

- Style JSON و تمام Glyph/Sprite/Sourceهای مورد اشاره در آن نیز باید روی همان سرویس داخلی میزبانی شوند. API، URL خود Style را اعتبارسنجی می‌کند؛ محتوای سند Style از سمت Backend واکشی نمی‌شود.
- تست اتصال در صفحه تنظیمات از خود مرورگر Tile می‌گیرد. سرور URL دلخواه را واکشی نمی‌کند و در معرض SSRF قرار نمی‌گیرد.

## Migration و نصب

Migration `2026_11_04_000000_extend_gis_map_settings_to_profiles` ستون‌های پروفایل را به همان جدول اضافه می‌کند، unique تک‌پروفایل را به قیدهای چندپروفایلی/تک‌فعال تبدیل می‌کند و ردیف قبلی را حفظ می‌کند. برای برگشت، ابتدا باید هر Scope را به یک پروفایل کاهش داد.

در Production پیش از استفاده، Migration باید طبق فرایند انتشار همان محیط اجرا شود؛ این تغییر در این مأموریت فقط روی دیتابیس تست مستقل اعمال شد.
