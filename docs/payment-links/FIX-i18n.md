# FIX i18n — links de pago (Estado 2, backend-laravel) — 2026-10-08

Informe de origen: `docs/payment-links/CRO-i18n.md`. SIN desplegar, SIN commit.

## Defecto -> archivo -> cambio

| # | Defecto | Archivo | Cambio |
|---|---|---|---|
| 1 CRÍTICO | Admin no fija idioma | `database/migrations/2026_10_08_000001_add_locale_to_payment_links_table.php` (nuevo) | columna `locale` VARCHAR(5) NULL tras `travel_date`; `down()` la elimina |
| 1 | idem | `app/Models/PaymentLink.php` | SIN cambio: usa `$guarded = ['id']`, `locale` ya es asignable; no requiere cast |
| 1 | idem | `app/Filament/Resources/PaymentLinkResource.php` | const `LOCALE_OPTIONS`; Select "Idioma del cliente" (vacío = "Automático por navegador") en sección Configuración; columna "Idioma" (badge) en tabla. La acción copiar link no se tocó. "Duplicar" conserva el idioma (no es PII) |
| 1 | `resolveLocale()` solo Accept-Language | `app/Http/Controllers/PaymentLinkController.php` (`resolveLocale`, ahora con `?PaymentLink $link`; `show()` carga el link antes) | Prioridad: `?lang=` (solo es/en/pt, `in_array` estricto) > `$link->locale` (validado contra supported) > Accept-Language > fallback |
| 1 | POST recalculaba por navegador | `resources/views/payment-links/show.blade.php` | `createUrl`/`captureUrl` llevan `lang=<locale mostrado>`; el POST toma el mismo idioma. Valor fuera de es/en/pt se ignora y cae al idioma del link. Ese locale va a `bookings.locale` y al correo (sin cambios en BookingCreationService) |
| 2 ALTO | thanks en español | `resources/views/checkout/thanks.blade.php` (description, "Detalle de tu reserva", personas/adultos/niños con `trans_choice`, Referencia, Estado, Confirmada/Pendiente, Volver al inicio, sin detalles, Ver tours) + claves nuevas en `lang/{es,en,pt}/checkout.php` | Flujo del checkout normal comparte la vista: claves en los 3 idiomas, es conserva los textos originales |
| 3 ALTO | SDK `locale=es_PE` fijo | `show.blade.php` | `$paypalLocale` es->es_PE, en->en_US, pt->pt_BR (fallback en_US) |
| 4 ALTO | Mensajes JS en español | `show.blade.php` + `lang/*/payment_links.php` (`js_create_failed`, `js_capture_failed`, `js_paypal_error`) | `@json(__())` |
| 5 MEDIO | "Culqi" en página PayPal | `show.blade.php` + `lang/*/payment_links.php` (`secure_payment`) | `checkout.secure_payment` NO se tocó: también lo usa `checkout/payment.blade.php:280`, que es la página con Culqi (ahí es cierto). La página de links usa clave propia "con PayPal" |
| 6 MEDIO | Sin validation en/es/pt | `lang/{en,es,pt}/validation.php` (nuevos) | en = copia del framework + `attributes`; es/pt = subconjunto de reglas comunes (required, email, max, min, between, regex, unique...), el resto cae al fallback `en` por clave (no se instaló laravel-lang). `attributes`: customer_name/email/phone/orderID legibles en los 3 |
| 7 MEDIO | Fechas `d M Y` | `show.blade.php`, `thanks.blade.php` | `->locale($locale)->translatedFormat('d M Y')` |
| 9 BAJO | "1 Adults" | `show.blade.php` + `payment_links.adults_count/children_count` (trans_choice) | singular/plural |
| 10 BAJO | "Phone (Peru)" | `show.blade.php` + `payment_links.phone_label` | "Phone"/"Teléfono"/"Telefone" solo en esta página; `checkout.customer_phone` intacta |

No tocado (según instrucciones): Kernel.php, SendBookingPaymentReminders.php, ListBookings.php. Bajo 11 (reenvío de correo desde admin usa locale de la app) no se abordó: fuera del alcance pedido.

## SQL para producción (phpMyAdmin, sin artisan)

```sql
ALTER TABLE `payment_links` ADD COLUMN `locale` VARCHAR(5) NULL AFTER `travel_date`;

INSERT INTO `migrations` (`migration`, `batch`)
VALUES ('2026_10_08_000001_add_locale_to_payment_links_table', (SELECT COALESCE(MAX(m.batch), 0) + 1 FROM (SELECT batch FROM `migrations`) AS m));
```
(Si phpMyAdmin rechaza la subconsulta, usar `batch` = último batch visible + 1; en local el último era 24.) Orden de deploy: SQL primero, luego los archivos. Sin la columna el código no falla (lee `locale` como null), pero guardar el Select en el admin sí daría error. Tras subir `lang/`: `config:cache` no aplica a lang; si hay opcache, usar `opcache-reset.php`. Si hay vistas cacheadas, `view:clear` equivalente (borrar `storage/framework/views/*`).

Efecto colateral a avisar: al existir `lang/es|en|pt/validation.php`, TODOS los formularios del sitio pasan a mostrar errores de validación en el idioma activo (antes salían en inglés crudo). Es mejora, pero es cambio global.

## Verificación

PHPUnit (SQLite en memoria, `phpunit.xml`; `lima_tours` intacta): `tests/Feature/PaymentLinkLocaleTest.php` nuevo, 6 tests / 23 aserciones verdes. Falsación: con el controller original (stash selectivo) 4 de 6 fallan. Corrida `--filter "PaymentLink|Checkout|Paypal"`: 109 tests, 438 aserciones, OK (4 skipped preexistentes).

curl contra `artisan serve` :8791, link id 4 (valor inicial `locale` = NULL, ahora devuelto a NULL; migración local aplicada, batch 24):

| Caso | html lang | SDK | JS | Otros |
|---|---|---|---|---|
| link NULL, Accept-Language es | es | `locale=es_PE` | "No se pudo iniciar el pago." | "Pago 100% seguro con PayPal", POST `?lang=es` |
| link NULL, en | en | `locale=en_US` | inglés | "1 Adult" |
| link locale=en, navegador es | en | `locale=en_US` | "There was an error with PayPal. Please reload..." | "100% secure payment with PayPal", POST `?lang=en` |
| link locale=en, es, `?lang=pt` | pt | `locale=pt_BR` | "Ocorreu um erro com o PayPal..." | "1 Adulto", "Pagamento 100% seguro com PayPal", POST `?lang=pt` |
| link locale=en, es, `?lang=xx` | en | `locale=en_US` | inglés | valor inválido ignorado |

## No verificado
- Render real del SDK/botones PayPal en `en_US`/`pt_BR` (sin navegador, sin credenciales reales): solo se verificó el parámetro en el HTML.
- Pago real de punta a punta y correo en pt/en (la captura y `bookings.locale=pt` y el redirect a thanks/pt sí están en test con PayPal fakeado; el contenido del correo no se re-verificó, ya estaba OK según el CRO).
- Select/columna de Filament renderizados en el panel (no hay test de UI nuevo; `PaymentLinkResourceTest` existente en la corrida).
- Traducción es/pt de validation es un subconjunto escrito a mano; revisar con hablante nativo si se quiere pulir.
