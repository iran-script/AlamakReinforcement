<?php

namespace App\Services;

use App\Models\Operation;
use App\Models\Riser;
use App\Models\User;
use App\Models\WorkflowTransfer;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * مرحله ۱: ناظر علمک را علامت می‌زند که نیاز به عملیات دارد.
     * اگر همین الان یک تسک باز (flag یا rejected) روی این علمک برای همین کاربر باشد، تکراری نمی‌سازد.
     */
    public function flagRiser(Riser $riser, User $supervisor, ?string $comment = null): WorkflowTransfer
    {
        $existing = WorkflowTransfer::query()
            ->where('riser_id', $riser->id)
            ->where('user_id_to', $supervisor->id)
            ->pending()
            ->whereIn('type', [
                WorkflowTransfer::TYPE_FLAG_OPERATION,
                WorkflowTransfer::TYPE_LEAK_CHECK_REJECTED,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        return WorkflowTransfer::create([
            'user_id_from' => $supervisor->id,
            'user_id_to'   => $supervisor->id,
            'riser_id'     => $riser->id,
            'operation_id' => null,
            'type'         => WorkflowTransfer::TYPE_FLAG_OPERATION,
            'title'        => 'نیاز به ثبت عملیات دارد',
            'comment'      => $comment,
            'send_date'    => now(),
        ]);
    }

    /**
     * مرحله ۲: ناظر عملیات را ثبت کرده و برای بررسی فنی نشتی می‌فرستد.
     * هر تسک باز روی این علمک برای همین ناظر (چه flag، چه rejected) بسته می‌شود.
     */
    public function submitOperationForLeakCheck(Operation $operation, User $technicalInspector, ?string $comment = null): WorkflowTransfer
    {
        WorkflowTransfer::query()
            ->where('riser_id', $operation->riser_id)
            ->where('user_id_to', $operation->user_id)
            ->pending()
            ->update(['receive_date' => now()]);

        return WorkflowTransfer::create([
            'user_id_from' => $operation->user_id,
            'user_id_to'   => $technicalInspector->id,
            'riser_id'     => $operation->riser_id,
            'operation_id' => $operation->id,
            'type'         => WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST,
            'title'        => "بررسی فنی نشتی عملیات #{$operation->id}",
            'comment'      => $comment,
            'send_date'    => now(),
        ]);
    }

    /**
     * مرحله ۳: ناظر بازرسی فنی تصمیم می‌گیرد.
     * تایید = زنجیره همین‌جا بسته می‌شود. رد = یک ارجاع جدید به همان ناظر باز می‌شود.
     */
    public function resolveLeakCheck(WorkflowTransfer $transfer, User $inspector, bool $approved, ?string $comment = null): void
    {
        abort_unless($transfer->user_id_to === $inspector->id, 403);
        abort_unless($transfer->type === WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST, 422);
        abort_unless($transfer->isPending(), 422, 'این مورد قبلاً رسیدگی شده است.');

        $transfer->update(['receive_date' => now()]);

        if (! $approved) {
            WorkflowTransfer::create([
                'user_id_from' => $inspector->id,
                'user_id_to'   => $transfer->user_id_from,
                'riser_id'     => $transfer->riser_id,
                'operation_id' => $transfer->operation_id,
                'type'         => WorkflowTransfer::TYPE_LEAK_CHECK_REJECTED,
                'title'        => 'رد فنی — نیاز به اصلاح مجدد',
                'comment'      => $comment,
                'send_date'    => now(),
            ]);
        }
    }

    /**
     * زون علمک را از روی تقاطع geometry پیدا می‌کند (هم‌سبک با TileController).
     */
    public function resolveZoneIdsForRiser(Riser $riser): array
    {
        return DB::table('zone')
            ->select('id')
            ->whereRaw(
                'ST_Intersects(ST_Transform(geom,3857), ?)',
                [$riser->geom_3857]
            )
            ->pluck('id')
            ->toArray();
    }

    /**
     * ناظر بازرسی فنی مسئول زون این علمک را برمی‌گرداند.
     */
    public function resolveTechnicalInspector(Riser $riser): ?User
    {
        $zoneIds = $this->resolveZoneIdsForRiser($riser);

        if (!empty($zoneIds)) {

            $inspector = User::role('supervisor')
                ->whereIn('zone_id', $zoneIds)
                ->first();

            if ($inspector) {
                return $inspector;
            }
        }

        // اگر ناظر زون پیدا نشد، یک SuperAdmin برگردان
        $superAdmin = User::role('superadmin')->first();

        if ($superAdmin) {
            return $superAdmin;
        }

        // در نهایت ناظر سراسری (بدون زون)
        return User::role('supervisor')
            ->whereNull('zone_id')
            ->first();
    }

    /**
     * مرحله فعلی گردش‌کار یک علمک — برای نمایش در UI (مثل stepper).
     */
    public function getCurrentStage(Riser $riser): array
    {
        $latest = $riser->latestWorkflowTransfer;

        if (! $latest) {
            return ['step' => 1, 'label' => 'بدون گردش‌کار فعال', 'color' => 'pending'];
        }

        return match (true) {
            $latest->type === WorkflowTransfer::TYPE_FLAG_OPERATION && $latest->isPending()
            => ['step' => 2, 'label' => 'نیاز به ثبت عملیات', 'color' => 'pending'],

            $latest->type === WorkflowTransfer::TYPE_LEAK_CHECK_REJECTED && $latest->isPending()
            => ['step' => 2, 'label' => 'رد فنی — نیاز به اصلاح مجدد', 'color' => 'reject'],

            $latest->type === WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST && $latest->isPending()
            => ['step' => 3, 'label' => 'در انتظار بررسی فنی نشتی', 'color' => 'pending'],

            $latest->type === WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST && ! $latest->isPending()
            => ['step' => 4, 'label' => 'تایید نهایی شده', 'color' => 'done'],

            default => ['step' => 1, 'label' => 'بدون گردش‌کار فعال', 'color' => 'pending'],
        };
    }
}
