-- ============================================================
-- Lima View Tours — Columnas de Portugués (pt) para STAGING/PROD
-- Ejecutar en phpMyAdmin sobre la BD: limaview_limaprogramacion
-- (prod no tiene CLI, por eso se aplica como SQL directo)
-- Equivale a la migración 2026_06_27_000001_add_pt_columns_to_all_tables
-- Si alguna columna ya existiera, elimina esa línea y vuelve a ejecutar.
-- ============================================================

ALTER TABLE `tours`
  ADD COLUMN `title_pt`           VARCHAR(255) NULL AFTER `title_en`,
  ADD COLUMN `subtitle_pt`        VARCHAR(255) NULL AFTER `subtitle_en`,
  ADD COLUMN `description_pt`     TEXT         NULL AFTER `description_en`,
  ADD COLUMN `itinerary_pt`       JSON         NULL AFTER `itinerary_en`,
  ADD COLUMN `includes_pt`        JSON         NULL AFTER `includes_en`,
  ADD COLUMN `excludes_pt`        JSON         NULL AFTER `excludes_en`,
  ADD COLUMN `recommendations_pt` TEXT         NULL AFTER `recommendations_en`,
  ADD COLUMN `notes_pt`           TEXT         NULL AFTER `notes_en`;

ALTER TABLE `testimonials`
  ADD COLUMN `quote_pt` TEXT NULL AFTER `quote_en`;

ALTER TABLE `regions`
  ADD COLUMN `name_pt`        VARCHAR(255) NULL AFTER `name_en`,
  ADD COLUMN `description_pt` TEXT         NULL AFTER `description_en`,
  ADD COLUMN `eyebrow_pt`     VARCHAR(255) NULL AFTER `eyebrow_en`;

ALTER TABLE `categories`
  ADD COLUMN `name_pt`        VARCHAR(255) NULL AFTER `name_en`,
  ADD COLUMN `description_pt` TEXT         NULL AFTER `description_en`;

ALTER TABLE `pages`
  ADD COLUMN `title_pt`   VARCHAR(255) NULL AFTER `title_en`,
  ADD COLUMN `content_pt` LONGTEXT     NULL AFTER `content_en`;

ALTER TABLE `offers`
  ADD COLUMN `title_pt`       VARCHAR(255) NULL AFTER `title_en`,
  ADD COLUMN `description_pt` TEXT         NULL AFTER `description_en`,
  ADD COLUMN `cta_label_pt`   VARCHAR(255) NULL AFTER `cta_label_en`;

-- Registrar la migración para que Laravel no intente re-ejecutarla.
INSERT INTO `migrations` (`migration`, `batch`)
VALUES ('2026_06_27_000001_add_pt_columns_to_all_tables', 99);
