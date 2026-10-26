# Auditoría de seguridad: CSP con dominios de país de Google (connect-src)

- Fecha: 2026-10-26
- Rama: fix/csp-google-ads-cctld-2026-10-26
- Rango auditado: `git diff baaa98e d063392` (commit, no el árbol de trabajo). Los cambios sin commitear en app/Support/* no se tocaron ni forman parte de esta revisión.
- Archivos: app/Http/Middleware/SecurityHeaders.php, tests/Feature/SecurityHeadersCspTest.php

## Resultado: APROBADO CON OBSERVACIONES

### Hallazgos críticos
Ninguno.

### Hallazgos altos
Ninguno.

### Hallazgos medios
Ninguno.

### Hallazgos bajos
1. **[BAJO] `com.ve` no es el dominio oficial de Google para Venezuela y falta `co.ve`.**
   - Ubicación: app/Http/Middleware/SecurityHeaders.php:94
   - Evidencia: comparé los 47 sufijos con https://www.google.com/supported_domains (187 entradas). Coinciden 46. `com.ve` no está; el oficial es `.google.co.ve` (línea 178 de esa lista). `www.google.com.ve` resuelve hoy a IP de Google (172.217.192.94) y sirve la portada de Google, así que no apunta a un tercero. Es un registro defensivo, no el destino de las redirecciones.
   - Impacto: casi nulo en seguridad. El impacto es funcional: las conversiones de visitantes de Venezuela redirigidas a www.google.co.ve se siguen bloqueando. Además, si Google dejara de renovar `com.ve`, quedaría en connect-src un host que podría registrar otra persona.
   - Remedio: reemplazar `'com.ve'` por `'co.ve'`. Lo arregla backend-laravel.
2. **[BAJO, documentación] El encargo y el commit hablan de 43 sufijos, pero hay 47.**
   - Ubicación: app/Http/Middleware/SecurityHeaders.php:91-104
   - Evidencia: hay 47 entradas y las 47 son únicas. En la CSP de /es, connect-src tiene 47 hosts `www.google.<cc>`.
   - Remedio: corregir la cifra donde se cite. El código no cambia.

### Respuesta a los 5 puntos

1. **Superficie que se abre: no hay vector nuevo de exfiltración.** La CSP pública ya permitía `script-src 'unsafe-inline' 'unsafe-eval'` (SecurityHeaders.php:20) e `img-src https:` (:34). Con un XSS, el atacante ya puede sacar datos a un host suyo con `new Image().src`, sin pasar por connect-src. También podía hacerlo por connect-src hacia `*.google-analytics.com`, `www.google.com` y `www.googleadservices.com`, enviándolos a una propiedad GA o a una cuenta de Ads propias. Los 47 hosts nuevos son de Google y aceptan el mismo tipo de endpoint que `www.google.com`, así que no permiten nada que antes no se pudiera hacer. Diferencia neta: cero.
2. **/admin no recibe esos hosts.** Lo comprobé con una sonda in-process (Kernel, APP_ENV=production, SESSION_DRIVER=array). `/admin/login` y `/%61dmin/login` dan 200 con una CSP de 1435 caracteres y sin ningún `www.google.<cc>`. `/es`, `/en` y `/` muestran `www.google.com.pe` solo en connect-src y sirven de control positivo. `/admin` da 302 sin CSP; es el comportamiento ya conocido del redirect de Filament Authenticate. `/ADMIN/login`, `/administrador`, `/admin-x` y `/es/admin` dan 404 con la CSP pública, pero no sirven el panel. La condición está en SecurityHeaders.php:118 y :157.
3. **No hay typosquatting ni dominios de terceros.** De 47 sufijos, 46 están en la lista oficial de Google. El restante es `com.ve`, que es de Google aunque no sea el canónico (ver hallazgo 1). Hice un control negativo con `gogle.de` y `com.xx` como entradas falsas, y el comparador los marcó como no oficiales. Ninguna entrada está mal formada: todas cumplen `^[a-z]{2,3}(\.[a-z]{2})?$`.
4. **No se tocaron script-src, frame-src ni default-src.** El diff solo añade la constante (:91-104) y el bucle de connect-src (:125-128). En la CSP de /es cuenté 0 hosts `www.google.<cc>` en todas las directivas salvo connect-src, que tiene 47. default-src sigue en `'self'`.
5. **La construcción del header no se rompe.** Los sufijos son literales de una constante privada, sin entrada del usuario ni configuración. En la salida no aparece `https://www.google.` vacío y el detector tiene control negativo: detecta la cadena vacía y no da falso positivo con `.de`. Tampoco hay espacios dobles ni `; ;`, y en producción no aparece `5173` (Vite). El `.=` de la línea 125 siempre opera sobre una clave que existe (`connect-src` está en CSP_BASE:39).

### Verificaciones realizadas
- [✓] A05 Misconfiguration (CSP): comparé las directivas en la sonda in-process.
- [✓] A01 Separación entre público y admin: probé 10 rutas, incluidas variantes codificadas, con mayúsculas y con prefijo.
- [✓] Integridad de la lista: la comparé con la lista oficial de Google, con DNS y con HEAD sobre com.ve, co.ve y com.pe.
- [ ] No verificado: la CSP servida en producción después del deploy (le corresponde al deployer y a anyerson-qa). No ejecuté la suite de tests para no tocar la base local; la sonda in-process cubre lo mismo que los dos tests nuevos.

### Recomendaciones de hardening
- Añadir al test un caso que falle si alguna entrada genera `https://www.google.` seguido de espacio o `;`, y otro que compruebe el patrón de cada sufijo. Hoy nada protege contra un `''` añadido a mano en el futuro.
- La lista es parcial a propósito, y los países que faltan (p. ej. com.ni, com.sv, hn) pierden conversiones. Es un tema funcional y de medición, no de seguridad: se deriva a backend-laravel o al responsable de analítica.

Veredicto: APTO
