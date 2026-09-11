-- ============================================================
-- Auditoría retroactiva: ¿hay ya algún JSON-LD envenenado?
-- Lima View Tours · 2026-08-31
--
-- Correr en phpMyAdmin (producción NO acepta conexión MySQL externa).
-- SOLO LEE. No borra ni modifica nada.
--
-- Qué busca: valores de JSON-LD que contengan </script, <!-- u onerror.
-- Un JSON-LD legítimo NUNCA necesita ninguno de los tres.
--
-- Resultado esperado: 0 filas. Si sale alguna, NO la borres todavía:
-- cópiala y avísame; hay que ver si es un ataque o un copia-pega torpe.
-- ============================================================

SELECT 'settings' AS origen, `key` AS donde, LEFT(`value`, 200) AS muestra
FROM settings
WHERE `key` LIKE '%schema%'
  AND (`value` LIKE '%</script%' OR `value` LIKE '%<!--%' OR `value` LIKE '%onerror%')

UNION ALL

SELECT 'tours', CONCAT('id=', id, ' / ', slug), LEFT(CONCAT_WS(' || ', schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt), 200)
FROM tours
WHERE CONCAT_WS(' ', schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt)
      REGEXP '</script|<!--|onerror'

UNION ALL

SELECT 'pages', CONCAT('id=', id, ' / ', slug), LEFT(CONCAT_WS(' || ', schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt), 200)
FROM pages
WHERE CONCAT_WS(' ', schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt)
      REGEXP '</script|<!--|onerror'

UNION ALL

SELECT 'blog_posts', CONCAT('id=', id, ' / ', slug), LEFT(CONCAT_WS(' || ', schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt), 200)
FROM blog_posts
WHERE CONCAT_WS(' ', schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt)
      REGEXP '</script|<!--|onerror';


-- ------------------------------------------------------------
-- Complemento: cuántos JSON-LD hay guardados hoy en total.
-- Sirve para saber cuánto trabajo tiene por delante el SEO
-- y para confirmar que la consulta de arriba miró algo real
-- (si esto da 0, el 0 de arriba no prueba nada).
-- ------------------------------------------------------------

SELECT 'settings con schema' AS que, COUNT(*) AS cuantos
FROM settings WHERE `key` LIKE '%schema%' AND `value` IS NOT NULL AND `value` <> ''
UNION ALL
SELECT 'tours con schema', COUNT(*) FROM tours
WHERE COALESCE(schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt) IS NOT NULL
UNION ALL
SELECT 'pages con schema', COUNT(*) FROM pages
WHERE COALESCE(schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt) IS NOT NULL
UNION ALL
SELECT 'blog_posts con schema', COUNT(*) FROM blog_posts
WHERE COALESCE(schema_jsonld_es, schema_jsonld_en, schema_jsonld_pt) IS NOT NULL;
