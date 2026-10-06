# Validación del cliente — lote PayPal + tarjeta + cards (05/10/2026, reintento)

Entorno: http://lima-tour.test (local, responde). Sesión anónima, datos de prueba ficticios. No se pagó nada, no se tocó código ni datos de configuración.
Limitación de mi herramienta: no puedo medir cajas ni elementFromPoint. Juzgué visibilidad por el árbol de la página (no incluye lo oculto) y capturas de elemento.

## VEREDICTO GLOBAL: NO OK (no puedo dar el OK de negocio)
Motivo: el punto 1 cumple, pero el punto 2 (lo que más importa al cliente: pagar con tarjeta junto a PayPal, datos precargados) NO se pudo ver y el punto 3 (Reservas) no se llegó a probar. Sin evidencia no apruebo.

## Punto 1 — Cards de tours: CUMPLE
- Home 1440, "Más Comprados" (8) y "Tours en Lima, Ica y Cusco" (6): sin "BEST SELLER", "OFERTA ESPECIAL", "INCLUYE" ni subtítulos grises. Solo queda la etiqueta de descuento ("47% Descuento"), que no estaba en la lista de prohibidos.
- Carrusel "Experiencias" (1440 y 375, captura de elemento): sin insignias; título + 3 datos.
- /es/tours 1440: "16 tours encontrados", 16 cards en 3 columnas, limpias.
- /es/tours 375: 16 cards con 16 URL distintas (sin duplicados), formato horizontal, limpias (captura de la card City Tour Lima con Catacumbas).
Observaciones menores: (a) en móvil la duración sale dos veces en la misma card ("4 Horas" naranja + fila de datos); (b) en escritorio "Español/Inglés" se corta; (c) la foto de la 1ª card de Experiencias (móvil) y varias de /tours salieron en blanco en captura, sin confirmar si es carga diferida; (d) en /tours móvil la sección de opiniones repite el mismo texto 4 veces con 4 nombres.

## Punto 2 — Checkout: NO VERIFICABLE en lo principal; términos CUMPLE en ES
Recorrido real: ficha City Tour Lima con Catacumbas > fecha > Reservar ahora > carrito > Datos (llené nombre, teléfono, correo ficticios) > Pago.
- Botón de tarjeta junto a PayPal: NO VERIFICABLE. En el paso de pago, con "Pagar ahora" elegido, no aparece ningún botón de PayPal ni de tarjeta; aparece el aviso "Pasarela en configuración — El método de pago en línea estará disponible en breve." En local no hay pasarela configurada, así que no pude ver ni el botón de tarjeta ni el iframe de PayPal. Esto debe verificarse donde haya claves de PayPal (staging); no puedo darlo por bueno.
- Nombre, correo y teléfono precargados hacia PayPal/tarjeta: NO VERIFICABLE (mismo motivo: no hay botón/ventana de pago que abrir).
- Términos obligatorios: CUMPLE (ES). Con la casilla sin marcar pulsé "Reservar y pagar después": no avanza y muestra en rojo "Debes aceptar los términos y condiciones para continuar." Mensaje claro, sin códigos. Observación mayor-menor: la pantalla vuelve al paso 1 (Reservas) y arriba, sin señalar la casilla; el cliente puede no entender dónde falló. Ojo: este camino fue "pagar después", no el de pago con PayPal/tarjeta, que no pude ejercitar.
- EN y PT: NO VERIFICABLE. Entré a /en/carrito (carga, "Shopping cart") y pulsé continuar, pero me detuve por indicación antes de llegar al mensaje de términos. /en/cart da 404 (la ruta real es /en/carrito). PT no se probó.
- Otros detalles vistos en ES: "x 1 personas" (concordancia), "Se cobrará el 6 de oct.." (doble punto) en la opción pagar después, y "Cancela hasta las 9:00 del 6 de oct." que ya es hoy.
- Dónde me atasqué: el panel de pago no muestra PayPal/tarjeta en local; y los widgets de fecha duplicados (escritorio/móvil) me hicieron perder turnos.

## Punto 3 — Admin > Reservas: NO VERIFICABLE
No llegué a abrir el panel: no busqué credenciales ni entré al admin. Tab "Mañana", contador en "Todas" y horas de Lima en "Hoy"/"Semana" quedan sin ver. No se cambió ninguna contraseña ni se creó ningún usuario.
PREGUNTA ABIERTA (no bloquea): el cliente marcó "Todas" con una flecha el 29/09 y no sabemos qué quería (¿el contador?, ¿que sea la pestaña por defecto?, ¿el orden?, ¿el nombre?). Hay que preguntarle.

## Punto 4 — Defectos menores: NO VERIFICABLE (no bloquean)
- Banner de cookies tapa el botón de pagar a 375px: no probado a 375 en el paso de pago. A 1440 el banner ocupa la franja inferior de todas las páginas (visto en capturas), pero ahí no tapó el botón de la reserva.
- Teléfono de 16 dígitos con error genérico: no probado.

## Preguntas para el cliente
1. ¿Qué quería indicar con la flecha sobre "Todas" en Reservas?
2. ¿Quiere que al olvidar los términos la pantalla se quede en el paso de pago y marque la casilla, en vez de volver al inicio del carrito?

## Pendiente para poder dar el OK
- Probar en un entorno con claves de PayPal: tarjeta junto a PayPal, datos precargados, términos sin marcar en ES/EN/PT (sin completar pago).
- Entrar al admin local (credenciales de seeders/docs) y revisar tabs de Reservas y horas de Lima.
- Cookies a 375px y teléfono de 16 dígitos.

Nota de limpieza: por error dejé un archivo vacío de prueba `docs\validacion\2026-10-05-client-validator-lote-paypal-cards.md.tmp`; no tengo herramienta para borrarlo, conviene eliminarlo.
