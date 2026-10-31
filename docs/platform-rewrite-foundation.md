# نقشهٔ بازنویسی پلتفرم

## تصمیم معماری

پروژه از یک سامانهٔ تخصصی «عملیات علمک» به یک **modular monolith** در Laravel تبدیل می‌شود. هر قابلیت مرز ماژولی، migration، policy، service و API مستقل دارد؛ اما تا زمانی که نیاز واقعی به جداسازی سرویس‌ها وجود نداشته باشد، در یک برنامه و یک دیتابیس باقی می‌ماند.

## اصل مهاجرت بدون اختلال

- جدول‌های `riser`، `operations` و `workflow_transfers` تا پایان مهاجرت فعال باقی می‌مانند.
- هستهٔ جدید ابتدا به‌صورت افزایشی و nullable ایجاد می‌شود.
- علمک نخستین `AssetType` است؛ یک job یا command جداگانه، پس از تأیید داده، علمک‌های موجود را به `assets` منتقل می‌کند.
- هر رکورد منتقل‌شده `legacy_source` و `legacy_id` دارد تا traceability حفظ شود.
- عملیات جدید پس از فاز سازگاری به `asset_id` متصل خواهد شد؛ عملیات قبلی همچنان با `riser_id` خوانده می‌شود.

## هستهٔ پلتفرم

| ماژول | مسئولیت |
| --- | --- |
| Organization | جداسازی داده و ساختار سازمانی |
| Asset Registry | دارایی عمومی، نوع دارایی، ویژگی‌های پویا و رابطهٔ بین دارایی‌ها |
| Spatial Scope | محدودهٔ قابل مشاهده/ویرایش برای کاربر یا نقش |
| Form Studio | schema، UI schema، نسخه و پاسخ فرم |
| Workflow Studio | تعریف، نسخه، وضعیت، transition، SLA و instance |
| Rules | شرط‌های تجاری و مکانی مستقل از workflow |
| Work Management | درخواست کار، عملیات، چک‌لیست، تصویر و مصالح |
| Audit & Integration | تاریخچهٔ تغییر، webhook، import/export و API |

## قراردادهای غیرقابل‌تغییر

1. هر دادهٔ قابل‌مالکیت باید `organization_id` داشته باشد.
2. هر پیکربندی منتشرشده immutable است؛ اصلاح، نسخهٔ تازه ایجاد می‌کند.
3. دسترسی در سه سطح action، resource و spatial scope ارزیابی می‌شود.
4. پاسخ فرم، schema version و workflow version همواره کنار هم نگهداری می‌شوند.
5. دادهٔ مکانی با SRID 4326 ذخیره و برای نمایش/تحلیل در سرویس تبدیل می‌شود.

## ترتیب پیاده‌سازی

1. Foundation: سازمان، Asset Registry و Spatial Scope.
2. Policy engine و audit log.
3. مهاجرت سازگار علمک به Asset Registry.
4. Form Studio نسخه‌دار.
5. Workflow Studio و Rule Engine.
6. Work Management عمومی و انتقال تعمیرات فعلی.
7. تحلیل مکانی، API استاندارد و اپ میدانی offline-first.
