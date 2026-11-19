<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Datos legales del proveedor (razón social, RUC, dirección fiscal, teléfono)
 * administrables desde Filament → Configuración → "Datos legales".
 *
 * Términos, Privacidad, Política de devoluciones y Libro de Reclamaciones leen
 * de acá. Si un dato no está cargado devuelve null y la vista oculta la línea:
 * jamás se inventa un RUC.
 */
class LegalInfo
{
    /** Mismo fallback que el footer y /contacto: un único teléfono en todo el sitio. */
    public const DEFAULT_PHONE = '+51 925 886 725';

    public static function businessName(): ?string
    {
        return static::clean('legal_business_name');
    }

    public static function ruc(): ?string
    {
        return static::clean('legal_ruc');
    }

    public static function address(): ?string
    {
        return static::clean('legal_address');
    }

    public static function phone(): string
    {
        return static::clean('contact_phone') ?: self::DEFAULT_PHONE;
    }

    public static function phoneTel(): string
    {
        return preg_replace('/[^0-9+]/', '', static::phone()) ?: '';
    }

    public static function email(): string
    {
        return (string) (static::clean('contact_email') ?: config('mail.from.address'));
    }

    /**
     * Líneas "Etiqueta => valor" ya listas para pintar (solo las cargadas).
     *
     * @return array<string, string>
     */
    public static function lines(): array
    {
        return array_filter([
            __('legal.company_name') => static::businessName(),
            __('legal.company_ruc') => static::ruc(),
            __('legal.company_address') => static::address(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected static function clean(string $key): ?string
    {
        $value = Setting::get($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
