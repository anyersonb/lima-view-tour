<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Slug viejo de un contenido → 301 al slug vigente de ese mismo contenido.
 * Se alimenta solo desde HasLocalizedSlug al detectar un cambio de slug
 * efectivo, y se consume en HasLocalizedSlug::resolveForLocale() cuando la
 * URL pedida no corresponde a ningún registro vivo.
 *
 * Ver la migración create_slug_redirects_table para el porqué.
 */
class SlugRedirect extends Model
{
    protected $guarded = ['id'];

    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Registra que $model dejó de responder a $oldSlug en $locale.
     *
     * @param  string  $currentSlug  Slug efectivo NUEVO, para poder limpiar el
     *                               historial si el editor deshizo el cambio.
     */
    public static function remember(Model $model, string $locale, string $oldSlug, string $currentSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $currentSlug) {
            return;
        }

        // El editor volvió a un slug que ya había usado: esa URL vuelve a ser
        // la viva, así que su fila de historial tiene que desaparecer o el
        // 301 competiría con la ficha real.
        static::query()
            ->where('redirectable_type', $model->getMorphClass())
            ->where('redirectable_id', $model->getKey())
            ->where('locale', $locale)
            ->where('old_slug', $currentSlug)
            ->delete();

        static::query()->updateOrCreate([
            'redirectable_type' => $model->getMorphClass(),
            'redirectable_id'   => $model->getKey(),
            'locale'            => $locale,
            'old_slug'          => $oldSlug,
        ]);
    }

    /**
     * ¿Quién usaba esta URL antes? Devuelve el id del contenido, o null.
     */
    public static function ownerIdFor(string $morphClass, string $locale, string $slug): ?int
    {
        $id = static::query()
            ->where('redirectable_type', $morphClass)
            ->where('locale', $locale)
            ->where('old_slug', $slug)
            ->latest('id')
            ->value('redirectable_id');

        return $id ? (int) $id : null;
    }
}
