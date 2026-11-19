<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Hoja de reclamación del Libro de Reclamaciones virtual.
 * Registro legal: sin SoftDeletes ni borrado desde el panel.
 */
class Complaint extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ANSWERED = 'answered';

    public const TYPE_CLAIM = 'claim';
    public const TYPE_COMPLAINT = 'complaint';

    /** Plazo legal de respuesta, en días hábiles. */
    public const RESPONSE_BUSINESS_DAYS = 15;

    protected $guarded = ['id', 'code'];

    protected $casts = [
        'is_minor' => 'boolean',
        'accepted_declaration' => 'boolean',
        'claimed_amount' => 'decimal:2',
        'response_due_at' => 'date',
        'responded_at' => 'datetime',
    ];

    /** Crea la hoja y le asigna el correlativo R-AAAA-000001. */
    public static function register(array $attributes): self
    {
        $attributes['response_due_at'] = static::dueDateFrom(Carbon::now());

        $complaint = static::create($attributes);
        $complaint->forceFill(['code' => static::formatCode($complaint->id, $complaint->created_at)])->save();

        return $complaint;
    }

    public static function formatCode(int $id, ?Carbon $date = null): string
    {
        return 'R-'.($date ?? Carbon::now())->format('Y').'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** 15 días hábiles (lunes a viernes; los feriados no se descuentan). */
    public static function dueDateFrom(Carbon $from): Carbon
    {
        return $from->copy()->startOfDay()->addWeekdays(self::RESPONSE_BUSINESS_DAYS);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', '!=', self::STATUS_ANSWERED);
    }
}
