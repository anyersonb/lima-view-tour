# Lima View Tours

Sitio web de reservaciones de tours en Lima, Ica y Cusco — Perú.

## Stack

- **Laravel 10** (PHP 8.1) con **Culqi PHP SDK** para pagos
- **Vite + Tailwind CSS 3 + SCSS** (estructura 7-1 simplificada)
- **Alpine.js** para interactividad ligera
- **Blade** con componentes reutilizables (`x-header`, `x-footer`, `x-jsonld`, `x-lang-switcher`)
- Multilingüe **ES/EN** vía prefijo de URL `/{locale}/...` y middleware `SetLocale`
- SEO: meta head completo (Open Graph, Twitter Cards, hreflang), JSON-LD `TravelAgency`

## Estructura

```
resources/
├─ scss/
│  ├─ abstracts/     variables y mixins
│  ├─ base/          reset y typography
│  ├─ components/    buttons, cards, ...
│  ├─ layouts/       header, footer
│  ├─ pages/         estilos por página
│  └─ app.scss       entrada (importa Tailwind + parciales)
├─ js/app.js         Alpine.js
└─ views/
   ├─ layouts/app.blade.php   layout maestro con SEO completo
   ├─ components/             Blade components
   └─ home.blade.php, tours/, checkout, contact, ...

lang/
├─ es/, en/   nav, seo, common, footer
└─ es.json, en.json   strings inline

routes/web.php   redirige `/` a locale detectado, prefija rutas con `{locale}`
```

## Setup local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run dev          # o `npm run build` para producción
php artisan serve
```

Acceso por **Laragon**: `http://lima-tour.test/`

## Design system

Tokens en `tailwind.config.js` y `resources/scss/abstracts/_variables.scss`:

| Token | Hex | Uso |
|---|---|---|
| `teal-700` | `#15474B` | Color principal de marca |
| `orange-500` | `#E29347` | Acento / CTAs |
| `cream-100` | `#F5F0ED` | Fondos suaves |
| `cream-200` | `#ECE6E2` | Fondos alternos |
| `state-error` | `#C81F21` | Estados de error |
| `state-success` | `#145212` | Estados de éxito |

**Tipografía:** Hedvig Letters Serif (titulares), Albert Sans (cuerpo y botones), Instrument Serif (precios).

## Pasarela de pago

Culqi PHP SDK instalado vía Composer. Integración pendiente en `/checkout`.
