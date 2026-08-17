<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Feed/auditoría de acciones de negocio (patrón comfy_back_v2).
 * Convive con ModeracionAccion (historial específico de moderación).
 *
 * @property int $id
 * @property string|null $userable_type
 * @property int|null $userable_id
 * @property string|null $modelable_type
 * @property int|null $modelable_id
 * @property string $status_text
 * @property string $status
 * @property string $color
 * @property string $message
 * @property string|null $ip
 * @property array<string, mixed>|null $data
 */
class Activity extends Model
{
    public const COLOR_INFO = 'info';

    public const COLOR_PRIMARY = 'primary';

    public const COLOR_SUCCESS = 'success';

    public const COLOR_DANGER = 'danger';

    public const COLOR_DARK = 'dark';

    public const COLOR_WARNING = 'warning';

    public const COLOR_SECONDARY = 'secondary';

    protected $fillable = [
        'userable_type',
        'userable_id',
        'modelable_type',
        'modelable_id',
        'message',
        'status_text',
        'status',
        'color',
        'ip',
        'data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function userable(): MorphTo
    {
        return $this->morphTo();
    }

    public function modelable(): MorphTo
    {
        return $this->morphTo();
    }
}
