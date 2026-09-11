-- ============================================================================
-- Lima View Tours — Lote "slug + metas + JSON-LD por idioma" (propuesta ESPASEO)
-- Fecha: 2026-08-18
--
-- Aplicar ANTES de subir los archivos PHP (el codigo nuevo lee estas columnas).
-- Ejecutar en phpMyAdmin sobre la BD de produccion, bloque por bloque.
--
-- SOLO ADITIVO: no hay DROP ni ALTER de columnas existentes. La columna `slug`
-- actual NO se toca (sigue siendo el slug espanol, ya indexado). Las nuevas son
-- nullable: si estan vacias el codigo usa `slug` como fallback, asi que ninguna
-- URL viva cambia el dia del deploy.
--
-- Las columnas legacy seo_title / seo_description NO se eliminan: quedan
-- huerfanas a proposito para poder revertir sin perder contenido.
-- ============================================================================

-- ── 0. Respaldo del esquema previo (correr y GUARDAR la salida antes de nada) ──
-- SHOW CREATE TABLE `tours`;
-- SHOW CREATE TABLE `pages`;
-- SHOW CREATE TABLE `blog_posts`;

-- ── 1. TOURS ────────────────────────────────────────────────────────────────
ALTER TABLE `tours`
  ADD `slug_en` varchar(60) NULL AFTER `slug`,
  ADD `slug_pt` varchar(60) NULL AFTER `slug_en`,
  ADD `meta_title_es` varchar(70) NULL AFTER `seo_keywords`,
  ADD `meta_title_en` varchar(70) NULL AFTER `meta_title_es`,
  ADD `meta_title_pt` varchar(70) NULL AFTER `meta_title_en`,
  ADD `meta_description_es` varchar(160) NULL AFTER `meta_title_pt`,
  ADD `meta_description_en` varchar(160) NULL AFTER `meta_description_es`,
  ADD `meta_description_pt` varchar(160) NULL AFTER `meta_description_en`,
  ADD `schema_jsonld_es` text NULL AFTER `meta_description_pt`,
  ADD `schema_jsonld_en` text NULL AFTER `schema_jsonld_es`,
  ADD `schema_jsonld_pt` text NULL AFTER `schema_jsonld_en`;

ALTER TABLE `tours` ADD UNIQUE `tours_slug_en_unique` (`slug_en`);
ALTER TABLE `tours` ADD UNIQUE `tours_slug_pt_unique` (`slug_pt`);

-- Backfill: conservar lo cargado en la pestana SEO global vieja.
-- SUBSTR obligatorio: meta_title_es es varchar(70) y con STRICT_TRANS_TABLES
-- un seo_title mas largo aborta la sentencia entera (verificado en local).
UPDATE `tours` SET `meta_title_es`       = SUBSTR(`seo_title`, 1, 70)        WHERE `seo_title` IS NOT NULL;
UPDATE `tours` SET `meta_description_es` = SUBSTR(`seo_description`, 1, 160) WHERE `seo_description` IS NOT NULL;

-- ── 2. PAGES ────────────────────────────────────────────────────────────────
-- OJO: el AFTER `seo_image` exige que esa columna exista en produccion.
-- Si no existe, quitar los AFTER (el orden de columnas es cosmetico).
ALTER TABLE `pages`
  ADD `slug_en` varchar(60) NULL AFTER `slug`,
  ADD `slug_pt` varchar(60) NULL AFTER `slug_en`,
  ADD `meta_title_es` varchar(70) NULL AFTER `seo_image`,
  ADD `meta_title_en` varchar(70) NULL AFTER `meta_title_es`,
  ADD `meta_title_pt` varchar(70) NULL AFTER `meta_title_en`,
  ADD `meta_description_es` varchar(160) NULL AFTER `meta_title_pt`,
  ADD `meta_description_en` varchar(160) NULL AFTER `meta_description_es`,
  ADD `meta_description_pt` varchar(160) NULL AFTER `meta_description_en`,
  ADD `schema_jsonld_es` text NULL AFTER `meta_description_pt`,
  ADD `schema_jsonld_en` text NULL AFTER `schema_jsonld_es`,
  ADD `schema_jsonld_pt` text NULL AFTER `schema_jsonld_en`;

ALTER TABLE `pages` ADD UNIQUE `pages_slug_en_unique` (`slug_en`);
ALTER TABLE `pages` ADD UNIQUE `pages_slug_pt_unique` (`slug_pt`);

UPDATE `pages` SET `meta_title_es`       = SUBSTR(`seo_title`, 1, 70)        WHERE `seo_title` IS NOT NULL;
UPDATE `pages` SET `meta_description_es` = SUBSTR(`seo_description`, 1, 160) WHERE `seo_description` IS NOT NULL;

-- ── 3. BLOG_POSTS ───────────────────────────────────────────────────────────
-- blog_posts YA tenia meta_title_* y meta_description_* por idioma desde
-- 2026_06_29_000010. No se duplican: solo falta slug traducido y JSON-LD.
ALTER TABLE `blog_posts`
  ADD `slug_en` varchar(60) NULL AFTER `slug`,
  ADD `slug_pt` varchar(60) NULL AFTER `slug_en`,
  ADD `schema_jsonld_es` text NULL AFTER `meta_description_pt`,
  ADD `schema_jsonld_en` text NULL AFTER `schema_jsonld_es`,
  ADD `schema_jsonld_pt` text NULL AFTER `schema_jsonld_en`;

ALTER TABLE `blog_posts` ADD UNIQUE `blog_posts_slug_en_unique` (`slug_en`);
ALTER TABLE `blog_posts` ADD UNIQUE `blog_posts_slug_pt_unique` (`slug_pt`);

-- ── 4. Registrar las migraciones para que artisan no las repita ─────────────
-- Usar el MISMO numero de batch para las tres (siguiente al maximo actual).
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_08_17_150000_add_i18n_seo_fields_to_tours_table',       (SELECT * FROM (SELECT COALESCE(MAX(`batch`),0)+1 FROM `migrations`) AS b)),
  ('2026_08_17_150100_add_i18n_seo_fields_to_pages_table',       (SELECT * FROM (SELECT MAX(`batch`) FROM `migrations`) AS b2)),
  ('2026_08_17_150200_add_i18n_slug_and_jsonld_to_blog_posts_table', (SELECT * FROM (SELECT MAX(`batch`) FROM `migrations`) AS b3));

-- ── 5. Verificacion (debe devolver 11, 11 y 5) ──────────────────────────────
-- SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
--   AND TABLE_NAME='tours' AND COLUMN_NAME IN ('slug_en','slug_pt','meta_title_es','meta_title_en','meta_title_pt','meta_description_es','meta_description_en','meta_description_pt','schema_jsonld_es','schema_jsonld_en','schema_jsonld_pt');

-- ============================================================================
-- REVERSA (solo si hay que dar marcha atras; los datos viejos siguen en seo_*)
-- ALTER TABLE `tours` DROP `slug_en`, DROP `slug_pt`, DROP `meta_title_es`, DROP `meta_title_en`, DROP `meta_title_pt`, DROP `meta_description_es`, DROP `meta_description_en`, DROP `meta_description_pt`, DROP `schema_jsonld_es`, DROP `schema_jsonld_en`, DROP `schema_jsonld_pt`;
-- ALTER TABLE `pages` DROP `slug_en`, DROP `slug_pt`, DROP `meta_title_es`, DROP `meta_title_en`, DROP `meta_title_pt`, DROP `meta_description_es`, DROP `meta_description_en`, DROP `meta_description_pt`, DROP `schema_jsonld_es`, DROP `schema_jsonld_en`, DROP `schema_jsonld_pt`;
-- ALTER TABLE `blog_posts` DROP `slug_en`, DROP `slug_pt`, DROP `schema_jsonld_es`, DROP `schema_jsonld_en`, DROP `schema_jsonld_pt`;
-- DELETE FROM `migrations` WHERE `migration` LIKE '2026_08_17_15%';
-- ============================================================================
