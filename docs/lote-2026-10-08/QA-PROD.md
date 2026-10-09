# QA PROD - Lima View Tours - lote 2026-10-08

Estado: COMPLETO (E parcial). Sesion admin activa. Link de pago id 2 RESTAURADO a su valor inicial (ver D).

## A - /admin/bookings (1440px): PASA
- Carga sin ?activeTab: el primer tab es "Manana" (badge 2) y esta resaltado; tabla con 2 filas, ambas con fecha de viaje oct. 9, 2026 (manana en Lima). Badge 2 = 2 filas.
- Clic real en "Todas": URL ?activeTab=all, 78 resultados (badge 78). Clic de vuelta en "Manana": URL ?activeTab=tomorrow, 2 filas.
- Orden de tabs: Manana 2, Todas 78, Pagadas 60, Pago pendiente 18, Fallidas 0, Pago en riesgo 2, Salidas de hoy 0, Salidas de esta semana 5.
- Limite: "activo" se juzgo por captura (resaltado) y por contenido filtrado, no por atributo aria-selected.

## Extra - correo consultado por el cliente (solo lectura)
- Ese email exacto no existe. La busqueda "abarys" en tab Todas da 1 reserva, con email [correo de la clienta, reserva LVT-XS1UIBHC]. Probable error de tipeo del brief.
- Referencia LVT-XS1UIBHC (id 136). Tour: FULL-DAY TOUR TO THE HUACACHINA OASIS + BALLESTAS ISLANDS IN PARACAS.
- Fecha de viaje: oct. 9, 2026. Creada: oct. 8, 2026 14:26:53 (updated_at identico). 1 adulto, 0 ninos, 80,00 US$.
- Metodo de pago: Pagar luego (pay_later). Estado de pago: Por pagar (pending). Estado: Pendiente. Locale: en.
- Link de pago asociado (columna "Link de pago"): no visible en la fila; en /admin/payment-links existe el link id 3 con prefill [correo de la clienta, reserva LVT-XS1UIBHC], 80 US$, Pendiente, creado oct. 8 22:55:17.
- payment_reminder_sent_at / "recordatorio enviado": NO se muestra en ningun lado. Columnas toggleables (P. unitario, Descuento, Metodo, Ref. pago, Link de pago, Recogida, Created at, Updated at): ninguna de recordatorio. Formulario de edicion (Estado y pago, Recojo y notas, etc.): sin campo de recordatorio. No se guardo nada. Columnas toggleables que active las desactive de nuevo.


## B - /admin/payment-links: PASA
- Columna "Idioma" presente en la tabla (3 links). Edicion del link id 2 (code R6CQ02GcY0SNEBVC0NdJisznAcVWo483nNqwdbBJ, tour City Tour Lima, 33 US$, Pendiente, sin usar): aparece el Select "Idioma del cliente" (opciones Espanol / English / Portugues) con texto de ayuda.
- VALOR INICIAL: vacio ("Automatico por navegador"), celda de la tabla vacia.
- Puesto English y guardado: la tabla muestra "English" en la columna Idioma de ese link.
- Nota: se uso el link 2 porque es el unico activo no asociado a un cliente; el link 3 (abarysev) y el 1 (pagado) no se tocaron.

## C - /pagar/{code}: PASA (con 1 observacion)
- Idioma English (sin ?lang): header y UI en ingles; H1 "Complete your payment"; "Passengers/1 Adult", "Full name *", "Email address *", "Phone"; botones PayPal "Pay with PayPal" y "Debit or Credit Card" dentro de iframe "Payment Button". SDK: paypal.com/sdk/js ...&locale=en_US (200).
- ?lang=pt: H1 "Complete seu pagamento", "Passageiros", "Nome completo *", "Endereco de e-mail *", "Telefone"; botones "PayPal" y "Cartao de Debito ou Credito"; SDK locale=pt_BR (200).
- ?lang=es: H1 "Completa tu pago"; botones "PayPal" y "Tarjeta de debito o credito"; SDK locale=es_PE (200).
- Botones PayPal renderizados en los 3 casos (iframe con los dos enlaces accesibles). NO se hizo clic.
- Consola: 0 errores en las 3 cargas; solo el warning preexistente de manifest (logo.png).
- Observacion (Baja, contenido, no del lote): el nombre del tour no esta traducido: en en aparece "CITY TOUR EN LIMA: CON VISITA A LAS CATACUMBAS" (titulo abreviado en mayusculas) y en pt/es el titulo largo en espanol. Datos del tour, no del fix.
- No verificado: medicion con getBoundingClientRect (el toolset no tiene evaluate); no se comprobo el atributo html lang (solo contenido visible y SDK); no probado con el navegador configurado en espanol para el caso automatico.

## D - Restauracion: HECHO
- Quitado "English" (Remove item), guardado. Reabierta la edicion: el select muestra "Automatico por navegador". En la tabla la fila del link 2 ya no muestra idioma (celda vacia; 0 coincidencias de English/Espanol/Portugues). Link igual que al inicio.

## E - Contacto /en/contact-us: PARCIAL (sin cambios respecto al intento anterior)
- Unico error: "We could not verify that you are not a robot. Please try again." (ingles, sin claves validation.*). Errores de campo no verificados por el captcha previo. No se repitio (throttle).

## Veredicto: APTO para A, B, C, D. E parcial (campos sin verificar).
Defectos: ninguno Alto/Medio. Baja: widget reCAPTCHA en espanol en paginas /en; nombre de tour sin traducir en /pagar.
