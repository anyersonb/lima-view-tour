-- ─────────────────────────────────────────────────────────────
-- Deploy 2026-07-19 — Bloque comparativo (convencional VS premium)
-- Ejecutar en phpMyAdmin sobre la DB de producción: limaview_limaprogramacion
-- ─────────────────────────────────────────────────────────────

-- 1) Columna JSON para el bloque comparativo
ALTER TABLE `tours` ADD COLUMN `comparison` JSON NULL AFTER `gallery`;

-- 2) Registrar la migración para que `php artisan migrate` no la reintente
INSERT INTO `migrations` (`migration`, `batch`)
VALUES ('2026_07_18_100000_add_comparison_to_tours_table',
        (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM (SELECT * FROM `migrations`) AS m));

-- 3) Activar y llenar el bloque en el tour Huacachina (atardecer + picnic + buggy)
--    ⚠️ Verifica el slug real en producción. Si difiere, ajústalo aquí.
UPDATE `tours`
SET `comparison` = '{"color":"teal","enabled":true,"badge_es":"EXPERIENCIA EXCLUSIVA","badge_en":"EXCLUSIVE EXPERIENCE","badge_pt":"EXPERIÊNCIA EXCLUSIVA","title_es":"La única experiencia que incluye","title_en":"The only experience that includes","title_pt":"A única experiência que inclui","title_hl_es":"atardecer y picnic en el desierto","title_hl_en":"sunset and picnic in the desert","title_hl_pt":"pôr do sol e piquenique no deserto","intro_es":"No todos los tours se quedan para vivir el momento más mágico del día. Esta experiencia está diseñada para que disfrutes Huacachina al máximo.","intro_en":"Not every tour stays to live the most magical moment of the day. This experience is designed for you to enjoy Huacachina to the fullest.","intro_pt":"Nem todos os tours ficam para viver o momento mais mágico do dia. Esta experiência foi desenhada para você aproveitar Huacachina ao máximo.","conv_title_es":"TOUR CONVENCIONAL","conv_title_en":"CONVENTIONAL TOUR","conv_title_pt":"TOUR CONVENCIONAL","prem_title_es":"NUESTRA EXPERIENCIA PREMIUM","prem_title_en":"OUR PREMIUM EXPERIENCE","prem_title_pt":"NOSSA EXPERIÊNCIA PREMIUM","conv_es":["Islas Ballestas","Paracas","Degustación de Pisco","Oasis Huacachina","Buggy","Sandboarding","Regreso a Lima al finalizar la actividad"],"conv_en":["Ballestas Islands","Paracas","Pisco tasting","Huacachina Oasis","Buggy","Sandboarding","Return to Lima right after the activity"],"conv_pt":["Ilhas Ballestas","Paracas","Degustação de Pisco","Oásis de Huacachina","Buggy","Sandboarding","Retorno a Lima ao terminar a atividade"],"prem_es":["Todo lo anterior","Disfruta el atardecer desde las dunas","Picnic privado preparado en el desierto","Snacks y bebidas","Tiempo extra para fotografías increíbles","Un cierre mucho más relajado y exclusivo antes de regresar a Lima"],"prem_en":["Everything above","Enjoy the sunset from the dunes","Private picnic set up in the desert","Snacks and drinks","Extra time for incredible photos","A much more relaxed, exclusive closing before heading back to Lima"],"prem_pt":["Tudo o anterior","Aproveite o pôr do sol nas dunas","Piquenique privado preparado no deserto","Snacks e bebidas","Tempo extra para fotos incríveis","Um encerramento muito mais relaxado e exclusivo antes de voltar a Lima"],"footer_es":"El 95% de los viajeros se pierde el atardecer en Huacachina. Tú no seas uno de ellos.","footer_en":"95% of travelers miss the sunset in Huacachina. Don''t be one of them.","footer_pt":"95% dos viajantes perdem o pôr do sol em Huacachina. Não seja um deles."}'
WHERE `slug` = 'tour-oasis-huacachina-picnic-atardecer-buggy';

-- (Opcional) Ver el resultado
-- SELECT id, slug, JSON_EXTRACT(comparison, '$.enabled') AS enabled FROM tours WHERE slug = 'tour-oasis-huacachina-picnic-atardecer-buggy';
