-- ============================================================================
-- Lote 2026-08-25 — Slug editable tipo WordPress + prefijos telefónicos.
--
-- CORRER ESTO **ANTES** DE SUBIR EL CÓDIGO.
--
-- Producción no tiene `php artisan migrate`, así que la migración
-- 2026_08_25_100000_create_slug_redirects_table se aplica acá, por phpMyAdmin
-- (cPanel → phpMyAdmin → BD `limaview_limaprogramacion` → pestaña SQL).
--
-- El orden importa: el código nuevo consulta `slug_redirects` en cada
-- resolución de URL que no encuentra ficha y en cada render del formulario de
-- slug del panel. Si el código sube primero, esas rutas devuelven un error 500
-- de "table doesn't exist" hasta que se cree la tabla.
--
-- Es idempotente: se puede correr dos veces sin romper nada.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `slug_redirects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `redirectable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `redirectable_id` bigint unsigned NOT NULL,
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug_redirects_unique_old_slug` (`redirectable_type`,`redirectable_id`,`locale`,`old_slug`),
  KEY `slug_redirects_lookup` (`redirectable_type`,`locale`,`old_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrar la migración para que un `migrate` futuro no la vuelva a correr.
SET @batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);

INSERT INTO migrations (migration, batch)
SELECT '2026_08_25_100000_create_slug_redirects_table', @batch
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM migrations) m
    WHERE m.migration = '2026_08_25_100000_create_slug_redirects_table'
);

-- ============================================================================
-- OPCIONAL — limpiar el slug con "-2" del tour de Huacachina.
--
-- Es el pendiente viejo: `Tour::makeUniqueSlug()` contaba con `LIKE 'slug%'` y
-- le pegó un "-2" a un slug que no tenía ningún duplicado real (el bug ya está
-- corregido en el código de este lote). Hasta hoy había que tocarlo por SQL
-- porque el campo estaba bloqueado; ahora se edita desde el panel y el 301 de
-- la dirección vieja lo pone `slug_redirects` solo.
--
-- => NO correr esto. Hacerlo desde Filament → Tours → ese tour → SEO Español,
--    para que quede el redirect. Se deja documentado el caso, no el UPDATE.
--
--   SELECT id, slug FROM tours WHERE slug LIKE '%huacachina%';
-- ============================================================================
