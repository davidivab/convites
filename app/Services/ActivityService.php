<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Doble registro: fila en `activities` + Log::info (patrón comfy_back_v2).
 */
class ActivityService
{
    /**
     * @param  array{
     *     message: string,
     *     status_text?: string,
     *     status?: string,
     *     color?: string,
     *     data?: array<string, mixed>|null,
     *     userable_type?: string|null,
     *     userable_id?: int|null,
     *     modelable_type?: string|null,
     *     modelable_id?: int|null,
     *     ip?: string|null
     * }  $data
     */
    public function createActivity(array $data): Activity
    {
        $activity = new Activity;

        DB::transaction(function () use ($data, &$activity) {
            $user = request()->user() ?? Auth::user();
            if ($user && empty($data['userable_id'])) {
                $data['userable_type'] = $user::class;
                $data['userable_id'] = $user->getKey();
            }

            $data['ip'] ??= request()->ip();
            $data['status_text'] ??= 'creado';
            $data['status'] ??= 'info';
            $data['color'] ??= Activity::COLOR_INFO;

            $activity = Activity::query()->create($data);

            Log::info($activity->message, [
                'activity_id' => $activity->id,
                'userable_type' => $activity->userable_type,
                'userable_id' => $activity->userable_id,
                'modelable_type' => $activity->modelable_type,
                'modelable_id' => $activity->modelable_id,
                'message' => $activity->message,
                'status_text' => $activity->status_text,
                'status' => $activity->status,
                'color' => $activity->color,
                'ip' => $activity->ip,
                'data' => $activity->data,
            ]);
        });

        return $activity;
    }

    /**
     * @param  array{
     *     message: string,
     *     status_text?: string,
     *     status?: string,
     *     color?: string,
     *     data?: array<string, mixed>|null
     * }  $data
     */
    public function createActivityForModel(array $data, Model $model): Activity
    {
        $data['modelable_type'] = $model::class;
        $data['modelable_id'] = $model->getKey();

        return $this->createActivity($data);
    }

    /**
     * @param  array{
     *     message: string,
     *     status_text?: string,
     *     status?: string,
     *     color?: string,
     *     data?: array<string, mixed>|null
     * }  $data
     * @param  list<Model>  $modelList
     */
    public function createActivityForModels(array $data, array $modelList): void
    {
        DB::transaction(function () use ($data, $modelList) {
            foreach ($modelList as $model) {
                $this->createActivityForModel($data, $model);
            }
        });
    }
}
