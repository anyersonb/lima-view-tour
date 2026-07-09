-- ============================================================
-- Lima View Tours — Carrito abandonado (PRODUCCIÓN)
-- Ejecutar en phpMyAdmin sobre la BD `limaview_limaprogramacion`.
-- Crea la tabla `abandoned_carts` y registra la migración para que
-- `php artisan migrate` NO intente volver a crearla.
-- Es idempotente: se puede ejecutar varias veces sin romper nada.
-- ============================================================

CREATE TABLE IF NOT EXISTS `abandoned_carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es',
  `items` json NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `coupon_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `reminders_sent` tinyint unsigned NOT NULL DEFAULT '0',
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `last_reminder_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `abandoned_carts_token_unique` (`token`),
  KEY `abandoned_carts_customer_id_foreign` (`customer_id`),
  KEY `abandoned_carts_session_id_index` (`session_id`),
  KEY `abandoned_carts_email_index` (`email`),
  KEY `abandoned_carts_status_index` (`status`),
  KEY `abandoned_carts_last_activity_at_index` (`last_activity_at`),
  CONSTRAINT `abandoned_carts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marca la migración como ejecutada (evita que artisan la reejecute)
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_07_08_120000_create_abandoned_carts_table', COALESCE(MAX(`batch`),0) + 1
FROM `migrations`
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `migration` = '2026_07_08_120000_create_abandoned_carts_table'
);
