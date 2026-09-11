# Pase 2026-08-31 — mensajes de pago (tarjeta rechazada + traducciones)

Segundo pase del incidente de pagos. El primero (404 del panel + 500 de la varita)
ya está en producción desde el 30/08.

## Qué arregla

| # | Defecto | Afecta a |
|---|---|---|
| 1 | Una tarjeta rechazada por el banco daba **HTTP 500** y el mensaje *"no pudo completarse, contáctanos"* — el mismo que una caída nuestra. El cliente no sabía que debía probar otra tarjeta | Todos los que pagan con tarjeta |
| 2 | El mensaje genérico de error estaba **cableado en español** en 3 sitios del controlador y 1 del Blade. Un cliente de EE.UU. o Brasil veía español en pleno checkout | Clientes EN / PT |

El defecto 2 lo encontró `client-validator` (el dueño del negocio), no un test:
técnicamente no había nada roto.

## Archivos a subir (7)

Ruta remota base: `/public_html/limaprogramacion/`
(⚠️ NO `/limaprogramacion/`, que es una copia stale a nivel home)

```
app/Exceptions/PayPalCardDeclinedException.php   ← NUEVO
app/Services/PayPalService.php
app/Http/Controllers/CheckoutController.php
lang/es/booking.php
lang/en/booking.php
lang/pt/booking.php
resources/views/checkout.blade.php
```

Los `tests/**` y `database/factories/PageFactory.php` NO van a producción.

## Lo que este pase NO lleva

- **Sin `npm run build`**: verificado que el diff del Blade no añade ni una clase
  de Tailwind (`git diff | grep class=` vacío).
- **Sin migración**: no hay cambios de esquema.
- **Sin `filament:clear-cached-components`**: `bootstrap/cache/` en producción solo
  tiene `packages.php` y `services.php`.

## Orden

1. Subir los 7 archivos (`curl -T`, **un comando por archivo, empezando por `curl`** —
   si se envuelve en un script, el clasificador lo bloquea).
2. Cotejar md5 remoto contra local, uno a uno. Si uno no coincide, resubir ese;
   si falla dos veces, parar.
3. 🚨 **Borrar vistas compiladas**: `storage/framework/views/*.php`.
   Este pase toca Blade — sin esto, `checkout.blade.php` sigue sirviéndose viejo.
   (El pase del 30/08 no lo necesitaba; este sí.)
4. Reset de opcache: `https://limaviewtours.com/opcache-reset.php?key=lvt-mail-diag-2026`
   → debe responder `{"opcache":true}`.
5. Humo sin login: `/es` y una ficha de tour responden 200.

## Verificación post-deploy

En producción, con el carrito cargado (⚠️ **sin completar ningún pago real**):

1. El checkout carga y los botones de PayPal aparecen.
2. El sitio en `/en/` y `/pt/` sigue funcionando.
3. Que NO aparezca por ningún lado el texto crudo `booking.payment_failed`
   ni `booking.card_declined` — eso indicaría una traducción ausente.

El mensaje de tarjeta rechazada NO se puede provocar en producción sin una tarjeta
que el banco rechace de verdad. Queda verificado en local con clic real, en español
y en inglés. En producción se comprueba por ausencia: que no salga la clave cruda.

## Rollback

Los 6 `*.prod-antes` de esta carpeta son los archivos **vivos antes del pase**.
`PayPalCardDeclinedException.php` es nuevo: puede quedarse en el servidor, sin el
`use` en `PayPalService` es inerte.

Restaurar = subir cada `.prod-antes` a su ruta + borrar vistas compiladas + reset
de opcache.

⚠️ Revertir devuelve el 500 y el mensaje en español. Solo si el pase rompe algo peor.
