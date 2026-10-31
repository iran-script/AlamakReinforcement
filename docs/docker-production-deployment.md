# استقرار Docker همراه GeoServer

این Compose شامل Laravel/PHP-FPM، Nginx، queue worker، PostgreSQL/PostGIS و GeoServer رسمی است. داده‌های برنامه، PostgreSQL و GeoServer در volumeهای مستقل نگهداری می‌شوند. GeoServer از همان دامنه برنامه در `/geoserver` در دسترس است.

## اجرای خودکار روی سرور

پس از انتقال کامل پوشه پروژه و فایل dump به سرور، `.env.docker` را تنظیم کنید و از ریشه پروژه اجرا کنید:

```bash
chmod +x deploy-server.sh
./deploy-server.sh --dump /path/to/tamirat2-TIMESTAMP.dump
```

برای اجرای غیرتعاملی فقط در صورتی که مسیرها و فایل env را قبلاً بررسی کرده‌اید:

```bash
./deploy-server.sh --dump /path/to/database.dump --yes
```

اسکریپت Compose را اعتبارسنجی و build می‌کند، دیتابیس را با محافظت مقصد خالی restore می‌کند، فایل‌های موجود در `storage/app/public`، `storage/app/maps` و `storage/license` را وارد volume می‌کند، سرویس‌ها را بالا می‌آورد و migration و optimize را اجرا می‌کند.

## راه‌اندازی

```bash
cp .env.docker.example .env.docker
docker compose --env-file .env.docker build
docker compose --env-file .env.docker run --rm app php artisan key:generate --show
```

خروجی دستور سوم را در `APP_KEY` قرار دهید و تمام مقادیر `CHANGE_ME`، دامنه (بدون `/` پایانی) و پورت را عوض کنید. سپس:

```bash
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec app php artisan optimize
```

## انتقال کامل دیتابیس فعلی

روی سیستم Windows مبدأ، از ریشه پروژه اجرا کنید:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/backup-database.ps1
```

خروجی یک dump فشرده PostgreSQL و فایل SHA-256 در `storage/backups` است. هر دو فایل را با SCP/SFTP امن به سرور منتقل و hash را بررسی کنید. این مسیر در Git و Docker build نادیده گرفته می‌شود.

در اولین استقرار و پیش از اجرای migration، روی سرور اجرا کنید:

```bash
chmod +x scripts/restore-database.sh
./scripts/restore-database.sh /secure/path/tamirat2-TIMESTAMP.dump
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec app php artisan migrate --force
docker compose --env-file .env.docker exec app php artisan optimize
```

اسکریپت restore فقط روی دیتابیس فاقد جدول کاربری اجرا می‌شود و برای جلوگیری از overwrite شدن اطلاعات، روی دیتابیس دارای جدول متوقف می‌شود. جدول اولیه `spatial_ref_sys` که image مقصد می‌سازد مانع restore نیست؛ اسکریپت پس از این کنترل، دیتابیس خالی را از `template0` بازسازی می‌کند تا extension و اشیای PostGIS خود dump بدون تعارض بازیابی شوند. dump شامل schema، تمام رکوردها، sequenceها، constraintها، triggerها و اشیای PostGIS متعلق به دیتابیس است؛ مالکیت و privilegeهای سیستم مبدأ منتقل نمی‌شوند.

برنامه روی `HTTP_PORT` و GeoServer زیر `APP_URL/geoserver` ارائه می‌شود. در GeoServer یک PostGIS Store با میزبان `postgres`، پورت `5432` و اطلاعات دیتابیس `.env.docker` بسازید؛ یا از JNDI با نام `java:comp/env/jdbc/postgres` استفاده کنید.

برای افزودن WMS در برنامه، endpoint را به‌شکل `https://example.com/geoserver/wms` و نام منتشرشده را مانند `workspace:layer_name` ثبت کنید.

## عملیات و امنیت

- فایل `.env.docker` نباید commit شود.
- TLS را روی reverse proxy یا load balancer بیرونی فعال کنید.
- رمز پیش‌فرض GeoServer استفاده نشود و فایل `.env.docker` فقط برای حساب deployment قابل خواندن باشد.
- پورت PostgreSQL و GeoServer مستقیماً publish نشده‌اند.
- از volumeهای `postgres_data`، `geoserver_data` و `app_storage` منظم backup بگیرید.
- وضعیت: `docker compose --env-file .env.docker ps`
- گزارش‌ها: `docker compose --env-file .env.docker logs -f --tail=200`
- قبل از به‌روزرسانی imageها backup بگیرید و نسخه GeoServer را صریح نگه دارید.

فایل‌های محلی MBTiles، license و attachmentها عمداً وارد image نمی‌شوند. در صورت نیاز، آن‌ها را پس از ایجاد volume با روش امن سازمان خود provision کنید. مسیر MBTiles داخل container برابر `/var/www/storage/app/maps/osm.mbtiles` و مسیر فایل‌های license برابر `/var/www/storage/license` است.

Image رسمی GeoServer مسیر `/opt/geoserver_data` را برای داده پایدار، `SKIP_DEMO_DATA` را برای نصب بدون داده نمونه و `PROXY_BASE_URL` را برای اجرا پشت reverse proxy ارائه می‌کند.
