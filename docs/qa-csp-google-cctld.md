# QA post-fix: CSP connect-src con dominios de pais de Google

Rama fix/csp-google-ads-cctld-2026-10-26, commit d063392 (base baaa98e). Fecha 2026-10-26.

## Estado: PASS (con 1 hallazgo Bajo y la limitacion de no poder reproducir la redireccion real)

## Fix verificado
1. Diff: solo SecurityHeaders.php (+32) y su test. 47 sufijos unicos (el brief decia 43). Todos generan `https://www.google.<sufijo>` bien formado: 0 dobles espacios, 0 comas, 0 hosts vacios. Quitando el bloque generado, la CSP publica queda identica a la de baaa98e (comparado con reflexion sobre buildCsp de ambos archivos).
2. curl (Host lima-tour.test via 127.0.0.1; el .test no resuelve desde curl):
   - /es, /en, ficha city-tour-cusco, /es/carrito: 200, 1 solo header CSP, incluye www.google.com.pe, .de y .co.uk. Tamano 3416 bytes (baseline local 2260 de CSP + los 47 hosts, 1130 bytes mas; prod hoy 2114 bytes). Margen de sobra frente a limites practicos de header (8 KB).
   - /admin/login: 200, 1633 bytes, sin com.pe/de/co.uk. CSP admin identica a baaa98e (1607 vs 1607 bytes de buildCsp, identical=true).
   - Resto de directivas (todas menos connect-src): md5 igual al baseline, publica 59f1ce7dfa2b y admin b68e2cfa8d8a, coinciden con baaa98e. Solo connect-src cambia.
3. Navegador propio (playwright-core + Chrome, perfil temporal, no se uso el MCP): /es, ficha, /es/carrito, /admin/login:
   - 0 violaciones CSP al cargar, 0 errores JS en las 3 publicas.
   - Simulacion `fetch(..., {mode:'no-cors'})`: google.com.pe, .de y .co.uk en las 3 publicas, sin violacion. Control negativo: google.com.xx (no listado) da violacion en las publicas, y en /admin/login las 3 dan violacion. El check puede fallar y falla donde debe.
   - Un 404 en /es: /storage/tours/Green-gardens...jpeg, imagen de datos locales ausente, ajeno al fix.
4. Tests: SecurityHeadersCspTest + ConsentModeTest + ConversionEventsTest: OK, 17 tests, 182 aserciones (view:clear previo).

## Gate de regresion SEO
- noindex / X-Robots-Tag: PASS. Ninguna URL con X-Robots-Tag. Unico noindex,nofollow meta en /es/carrito, igual que prod hoy (curl a prod).
- robots.txt: no verificado en local (el diff no lo toca).
- Canonical: PASS, presente y correcto en /es, /en, ficha y carrito (host local, esperable).
- 301 de slugs: N/A, el diff no cambia slugs.
- H1: PASS, 1 por pagina en las 4 URLs.
- Datos estructurados: no tocados (el diff no toca HTML); no se parsearon.
- Fix no global de CSS/functions, pero es un middleware que afecta todo el sitio: se comprobo en 4 URLs publicas, no en el inventario completo.

## Defectos
- Bajo: el brief y el comentario del codigo hablan de 43 dominios; son 47. Solo documentacion.
- Bajo: la constante no deja linea en blanco antes del docblock siguiente, y el test termina sin salto de linea final (estilo).
- Alto/Critico: ninguno.

## No verificado
- La redireccion real a www.google.com.pe/pagead/1p-conversion/... (sin IDs de Ads en local). Solo se simulo el connect-src con fetch. Hay que confirmarlo en prod tras el deploy: al anadir al carrito, 0 violaciones en consola.
- Paises fuera de la lista (47 dominios, no la lista oficial de Google): ese visitante sigue perdiendo la conversion. Es limitacion documentada del fix.
- Tamano del header en prod (el comparado fue local; en prod se suman los origenes de Vite? No: solo non-production los agrega, asi que prod sera ~1100 bytes menos que el local con los 4 origenes de Vite).
- robots.txt, JSON-LD, hreflang.

Veredicto: APROBADO

## e59fc63 (sobre d063392)

- Diff: solo SecurityHeaders.php (+5/-3: com.ve a co.ve, anade com.ni/com.sv/hn, comentario con "50", linea en blanco tras la constante) y un test nuevo contra hosts vacios. Nada mas cambia. Esto cierra los dos hallazgos Bajos anteriores (conteo y formato).
- Constante: 50 sufijos; quitando el bloque generado, la CSP publica es identica a baaa98e; admin identica (1607 = 1607).
- curl (/es, /en, /es/carrito): 200, incluyen co.ve, com.ni, com.sv, hn; 0 apariciones de com.ve; md5 del resto de directivas 59f1ce7dfa2b (igual a baaa98e). /admin/login: ninguno de los cuatro, md5 b68e2cfa8d8a (igual). Header publico 3489 bytes (local con origenes Vite), admin 1633.
- Navegador: fetch no-cors a www.google.co.ve y www.google.hn sin violacion en /es, ficha y /es/carrito; control google.com.xx da violacion en publicas; en /admin/login los tres dan violacion. Sin violaciones ni errores JS previos a la sonda (salvo el 404 de imagen local ya conocido en /es).
- Tests (3 archivos): OK, 18 tests, 353 aserciones.
- No verificado: lo mismo que arriba (redireccion real de Ads solo comprobable en prod).

Veredicto: APROBADO (e59fc63)
