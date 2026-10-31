# دسترسی و Audit Trail

## تصمیم دسترسی

هر درخواست با سه محور ارزیابی می‌شود: `action`، `resource_key` و `scope`.

- action: view، create، update، approve، export یا configure
- resource_key: مانند assets، work_orders، forms یا workflows
- scope: سازمان، شرط داده و محدودهٔ مکانی

`data_access_policies` policy قابل‌پیکربندی برای user یا role نگه می‌دارد. اثر policy می‌تواند allow یا deny باشد و deny همیشه اولویت دارد. `field_rules` برای Form Studio رزرو شده است؛ نمونه:

```json
{
  "total_cost": "readonly",
  "approval_comment": "edit",
  "internal_note": "hidden"
}
```

## دسترسی مکانی

`spatial_scopes` یک MultiPolygon است و به کاربر با access level متصل می‌شود. `AccessPolicyService::visibleAssets()` فقط assetهایی را برمی‌گرداند که geometry آن‌ها با scope کاربر intersect دارد. کنترلرهای ماژول جدید باید از همین query استفاده کنند، نه `Asset::query()`.

## Audit

`AuditLogger` API واحد ثبت رخداد است. در فازهای بعد observerهای مدل، transitionهای workflow، انتشار فرم/فرآیند، export، ورود و تغییر دسترسی از آن استفاده خواهند کرد. password و remember token لاگ نمی‌شوند.
