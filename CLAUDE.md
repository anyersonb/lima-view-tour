# Lima View Tours — Contexto para Claude

## Rol
Actúa como **maquetador full-stack** del proyecto. Combina:
- Diseño/maquetado (HTML, CSS, Tailwind, Blade, componentes UI)
- Backend Laravel (rutas, controladores, modelos, migraciones, Eloquent)
- Integración con MCP de **Chrome** (inspección visual, screenshots, DOM, validación de UI en navegador real)
- Integración con MCP de **Figma** (extraer specs, tokens, componentes y layouts del diseño)

Flujo típico esperado: leer Figma → maquetar en Blade + Tailwind → verificar en Chrome → ajustar → integrar con backend Laravel.

## Stack
- **Laravel 10.50.2** (PHP)
- **Vite 5.4.21** + **Tailwind CSS** + **PostCSS**
- **MySQL 8.0.30** (Laragon)
- **Blade** para vistas
- Frontend assets en `resources/`

## Entorno local (Windows + Laragon)
- Working dir: `G:\laragon\www\lima-tour`
- Laragon en `G:\laragon\` (MySQL en `G:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\`)
- Shell: PowerShell (default) o Bash via Git Bash

## Base de datos
- Conexión: `mysql` en `127.0.0.1:3306`
- DB: **`lima_tours`** (¡con `s` final!)
- Usuario: `root`
- Password: *(vacío)*
- Charset: `utf8mb4_unicode_ci`

Crear DB manualmente:
```bash
/g/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS lima_tours CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## URLs
- **Laragon auto-host**: http://lima-tour.test (preferido)
- **Artisan serve**: http://127.0.0.1:8000
- **Vite dev**: http://localhost:5173 (HMR de assets)

## Setup desde cero
```bash
cd /g/laragon/www/lima-tour
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
```

## Levantar dev servers
En dos terminales (o background):
```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

## .env actual (valores no-secretos)
- `APP_NAME="Lima View Tours"`
- `APP_ENV=local`
- `APP_DEBUG=true`
- `APP_URL=http://lima-tour.test`
- `DB_DATABASE=lima_tours`
- `DB_USERNAME=root`
- `DB_PASSWORD=` (vacío)

## Migraciones aplicadas
- Laravel base: `users`, `password_reset_tokens`, `failed_jobs`, `personal_access_tokens`
- CMS: `categories`, `regions`, `tours`, `offers`, `testimonials`, `pages`, `contact_leads`, `settings`, `bookings`, `newsletter_subscribers`

## CMS — Filament 3.2
- URL admin: `/admin`
- Login admin: `admin@limaviewtours.com` / `LimaTours2026!`
- Resources: Tours (con tabs ES/EN/Imágenes/SEO), Regiones, Categorías, Testimonios, Ofertas, Páginas, Mensajes (badge unread), Reservas (badge pending), Newsletter
- Página custom: `/admin/settings` (Configuración del sitio con tabs General, Contacto, Redes, SEO, Home)
- `App\Models\User` implementa `FilamentUser` y autoriza emails `@webtilia.com` o `@limaviewtours.com`
- `App\Models\Setting::get('key')` para leer settings con cache; `Setting::set()` para escribir
- `AppViewServiceProvider` inyecta `$siteSettings` en todas las views

## SEO automático
- `/sitemap.xml` — generado por `SitemapController@index` con hreflang ES/EN, image:image, prioridades por tipo
- `/robots.txt` — generado por `SitemapController@robots`, distinto en local vs producción
- Layout `layouts/app.blade.php` inyecta GA4, GTM, Facebook Pixel, Google/Bing Site Verification desde Settings
- JSON-LD: `TravelAgency` + `WebSite` con SearchAction, sameAs desde redes sociales en Settings
- Cada Tour individual emite `@push('schema')` con JSON-LD `TouristTrip` + `AggregateRating` + `Offer`
- hreflang `es`, `en`, `x-default` en `<head>` de todas las páginas

## Vistas (todas pixel-perfect Figma)
- `home` — Hero con buscador, Stats, Tabs tours, Experiencias, Testimonios, TripAdvisor, Ofertas, ¿Por qué nosotros?, Destinos
- `about` (Nosotros) — Hero, Por qué reservar, Stats Misión/Visión, Banner, Vive cultura local con tabs, TripAdvisor, Testimonios
- `tours/index` — Hero por categoría, filtros pill, grid de cards, TripAdvisor, Testimonios, ¿Por qué reservar?
- `tours/show` — Galería con thumbnails, sidebar reserva sticky, Descripción/Itinerario/Recomendaciones/Incluye, sidebar info, Testimonios, Tours relacionados
- `checkout` — Hero, items con qty, cupón, resumen, tours recomendados
- `contact` — Hero, formulario + collage, 3 métodos de contacto
- `tours/results` — Búsqueda
- `errors/404` y `errors/maintenance`
- `gracias`, `popup`

## Repositorio Git
- Branch principal: `main` (sincronizada con `origin/main`)
- Commits iniciales:
  - `c1bab82` Initial commit
  - `daa33dd` chore: scaffold Lima View Tours Laravel 10 project
  - `3a15fa7` merge: resolve README conflict
  - `6573425` fix: replace conflicted README with project description

## Sistema de diseño (extraído de la guía de marca)

### Logotipo
- Versión clara (sobre teal): "LIMA VIEW TOURS" + ícono espiral en blanco/cream
- Versión oscura (sobre cream): "LIMA VIEW TOURS" + ícono espiral en teal
- Archivo: `public/assets/logos/` (a copiar desde `C:\Users\USUARIO WEBTILIA\Downloads\lima\LOGO.png`)

### Paleta (Tailwind tokens en `tailwind.config.js`)
| Token | Hex | Uso |
|---|---|---|
| `cream-100` | `#F5F0ED` | PRIMARIO — fondo claro |
| `cream-200` | `#ECE6E2` | PRIMARIO OSCURO — fondo alterno |
| `teal-700` | `#15474B` | SECUNDARIO — header, footer, headings |
| `orange-500` | `#E29347` | ACENTO — CTAs, carrito |
| `white` | `#FFFFFF` | FONDO |
| `state.error` | `#C81F21` | mensajes de error |
| `state.success` | `#145212` | mensajes de éxito |

### Tipografía
- **Hedvig Letters Serif** → titulares (`font-display` en Tailwind). Cargada vía Google Fonts.
- **Albert Sans** → párrafos y botones (`font-sans` por defecto).
- **Instrument Serif** → precios (`font-price`).

Carga en `resources/views/layouts/app.blade.php` con `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@300;400;500;600;700&family=Hedvig+Letters+Serif:opsz@12..24&family=Instrument+Serif:ital@0;1&display=swap">`.

### Container y breakpoints
- `tailwind.config.js` define container con padding responsivo y `screens` hasta `3xl: 1716px`
- Custom screen `3xl: 1800px` para utilidades en pantallas grandes
- `max-w-container: 1716px` disponible como utility

### Sliders — Owl Carousel 2.3.4
- Cargado vía CDN en `layouts/app.blade.php` (jQuery 3.7.1 + Owl Carousel CSS/JS)
- Inicialización inline al final del body con `data-owl-*` attributes
- Selectores activos:
    - `[data-owl-tours]` — 1/2/3/4 items por breakpoint, autoplay
    - `[data-owl-experiences]` — sección "Descubre experiencias únicas" en home
    - `[data-owl-testimonials]` — testimonios en home, about, tours/show
    - `[data-owl-offers]` — "Ofertas Especiales" en home
    - `[data-owl-related]` — tours relacionados en tours/show
    - `[data-owl-gallery]` — galería en tours/show
- Estilos custom en `resources/scss/components/_carousels.scss`: navegación naranja redonda, dots con active expandido

### Iconografía de marca
- `<x-icon-compass />` — componente Blade con SVG oficial (`public/assets/icons/title-vector.svg`) para títulos de sección "Nuestros Tours más Comprados", "Nuestros Destinos…"
- Acepta props `class` y `stroke`

### Semántica HTML / SEO
- `<header>` reservado solo para `x-header` global del sitio
- Títulos de sección usan `<h2>` con eyebrow en `<p>` (no `<header>` interno)
- `<h1>` único por página, jerarquía h1 → h2 → h3 sin saltos
- Imágenes con `alt` descriptivo (no vacío) cuando aportan información
- `aria-labelledby`/`aria-label` en cada `<section>` relevante
- `aria-hidden` en SVG decorativos y emoticones

### Botones (4 estados × 2 variantes — ver `Button.png`)
- **Primario**: relleno naranja con texto blanco / outline naranja con texto naranja / fondo naranja oscuro / disabled gris
- **Secundario**: outline naranja / relleno naranja / fondo cream con texto naranja / disabled gris
- Forma: `rounded-pill` (full radius), padding generoso, flecha `>` a la derecha
- Estados: default, hover, active (más oscuro), disabled (gris/cream con texto opaco)

### Header (ver `MENU A COLOR.png`)
- Fondo: `teal-700` (versión sólida) o transparente (sobre hero)
- Logo izquierda + nav centrado (INICIO, TOURS EN LIMA▼, TOURS EN ICA▼, TOURS EN CUSCO▼, NOSOTROS, CONTACTO)
- Derecha: selector idioma con bandera 🇵🇪, soporte tel `190010088 / Centro de Soporte`, **bloque sólido naranja a borde derecho con icono carrito blanco**
- Variante transparente: mismo layout pero sobre imagen hero, sin fondo sólido

### Footer (ver `Footer.png`)
- Fondo `teal-700`
- Banda superior: newsletter (subtítulo + título "Sign up for our newsletter…" + form Nombre/Correo + botón naranja ENVIAR)
- Grid 4 columnas: brand description / Links / Locate us at (dirección, tel, horario) / Follow us (redes) + Methods of payment (VISA, PayPal, Mastercard, AmEx)
- Copyright centrado abajo

### Páginas a maquetar (mockups en `Downloads/lima/`)
- `INICIO.png` — Home
- `NOSOTROS-1.png` — About
- `TOURS EN LIMA.png` / `TOURS EN ICA-1.png` / `TOURS EN CUSCO-1.png` — listados por destino
- `TOUR PÀGINA.png` — Detalle de tour
- `CARRITO-1.png` / `CARRITO 2.png` — Carrito
- `RESULTADOS-1.png` — Búsqueda
- `CONTACTO-1.png` — Contacto
- `GRACIAS.png` — Confirmación
- `ERROR.png` — 404
- `MANTENIMIENTO-1.png`
- `POUP.png` — Popup
- `RESPONSIVE-1.png` — referencia mobile

## Convenciones del proyecto
- Idioma de comunicación con el usuario: **español**
- Mensajes de commit en inglés con prefijo (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`)
- Vistas Blade en `resources/views/`
- Componentes Blade en `resources/views/components/`
- Tailwind config en `tailwind.config.js`
- Assets JS/CSS entry: `resources/js/app.js`, `resources/css/app.css`

## Recursos del cliente
- Imágenes y mockups: **`C:\Users\USUARIO WEBTILIA\Downloads\lima\`** (50+ PNGs)
- Logo extra (otra carpeta): `C:\Users\USUARIO WEBTILIA\Downloads\limatour\` (LOGO.png, banner.jpg, brujula.png/svg, personas.png)
- Imágenes de banners temáticos: `C:\Users\USUARIO WEBTILIA\Downloads\limaoctubre\` (Rectangle 19210-19219.jpg)
- Fuentes: **Hedvig Letters Serif** (Google Fonts), **Albert Sans** (Google Fonts), **Instrument Serif** (Google Fonts) — todas vía CDN.

## Trabajo con MCP de Chrome
- Usar para verificar maquetas en navegador real (no solo type-check)
- Screenshots para comparar contra mocks de Figma
- Inspeccionar DOM/CSS computado cuando algo no se ve igual
- Probar responsive en distintos viewports

## Trabajo con MCP de Figma
- Extraer tokens (colores, tipografía, spacing) y mapearlos a `tailwind.config.js`
- Bajar specs de componentes antes de maquetar
- Verificar nombres de capas/componentes para mantener consistencia con clases CSS
