<?php

namespace App\Models;

use App\Support\ImagePath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Testimonial extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * submitter_email es solo para contactar al autor de una reseña enviada
     * desde el formulario público — nunca debe llegar a una vista ni a un
     * array/JSON serializado del modelo.
     */
    protected $hidden = ['submitter_email'];

    protected $casts = [
        'is_featured'  => 'boolean',
        'is_active'    => 'boolean',
        'rating'       => 'decimal:1',
        'photos'       => 'array',
        'review_date'  => 'date',
        'helpful_count' => 'integer',
    ];

    /**
     * Valores válidos para `traveler_type`. Nullable a propósito: sin valor,
     * la vista no debe pintar la línea "Viaje en pareja/familia/…".
     */
    public const TRAVELER_TYPES = ['solo', 'pareja', 'familia', 'amigos', 'negocios'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected static function booted(): void
    {
        // Los agregados de Tour::reviewStats() se cachean por tour_id; cualquier
        // alta/edición/borrado de una reseña (incluida una aprobación/rechazo
        // desde el panel) debe invalidar esa caché para no quedar sirviendo un
        // promedio/conteo viejo. Se limpia tanto el tour_id actual como el
        // original (por si alguna vez se reasigna la reseña a otro tour).
        $forget = function (self $testimonial): void {
            foreach (array_filter([$testimonial->tour_id, $testimonial->getOriginal('tour_id')]) as $tourId) {
                Cache::forget("tour.review_stats.{$tourId}");
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Único scope que debe usar CUALQUIER superficie pública (controllers,
     * vistas, agregados, sitemap, JSON-LD, comandos artisan…): exige a la vez
     * `is_active` Y `status = approved`. Antes de esto había dos interruptores
     * independientes y era fácil filtrar solo uno, dejando una reseña sin
     * moderar visible en producción. El panel de Filament es la ÚNICA
     * excepción (debe seguir viendo las pendientes) y por eso NO usa este
     * scope ni un global scope equivalente.
     */
    public function scopePublished($query)
    {
        return $query->where('is_active', true)->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getQuoteAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{"quote_{$locale}"} ?: $this->quote_es;
    }

    /**
     * Título corto de la reseña ("¡Una experiencia completa!"), con el mismo
     * criterio de fallback que quote/Tour::title. Puede ser null: title_* es
     * opcional y sin valor la vista simplemente no pinta la línea.
     */
    public function getTitleAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $this->{"title_{$locale}"} ?: $this->title_es;
    }

    /**
     * Fecha real a mostrar ("Hace 3 días"): review_date si está cargada,
     * created_at si no. Resuelto acá para que el Blade nunca tenga que
     * decidir el fallback por su cuenta.
     */
    public function getDisplayDateAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->review_date ?: ($this->created_at ? $this->created_at->copy() : null);
    }

    /**
     * URLs absolutas de las fotos subidas por el viajero (disco "public",
     * mismo mecanismo que Tour::gallery_urls). No se consume hoy en ninguna
     * vista pública (fase 2, ver brief) pero se deja lista para no repetir
     * este cálculo cuando se construya la galería.
     *
     * @return array<int, string>
     */
    public function getPhotoUrlsAttribute(): array
    {
        $photos = $this->photos ?? [];
        if (! is_array($photos) || count($photos) === 0) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($p) => ImagePath::url($p), $photos)));
    }
}
