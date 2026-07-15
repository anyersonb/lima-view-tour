<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Configuración';
    protected static ?string $title = 'Configuración del sitio';
    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $rows = Setting::all()->pluck('value', 'key')->toArray();
        // Pre-rellenar credenciales de PayPal desde el .env si aún no están en la BD
        $rows['paypal_client_id'] = $rows['paypal_client_id'] ?? config('services.paypal.client_id');
        $rows['paypal_secret']    = $rows['paypal_secret']    ?? config('services.paypal.secret');
        $rows['paypal_mode']      = $rows['paypal_mode']      ?? config('services.paypal.mode', 'sandbox');
        $rows['paypal_webhook_id'] = $rows['paypal_webhook_id'] ?? config('services.paypal.webhook_id');

        // Pre-rellenar keys de Google y Tripadvisor desde .env si aún no están en la BD
        $rows['google_maps_api_key']        = $rows['google_maps_api_key']        ?? config('services.google.maps_api_key');
        $rows['google_place_id']            = $rows['google_place_id']            ?? config('services.google.place_id');
        $rows['google_reviews_enabled']     = isset($rows['google_reviews_enabled'])
            ? filter_var($rows['google_reviews_enabled'], FILTER_VALIDATE_BOOLEAN)
            : false;
        $rows['tripadvisor_api_key']        = $rows['tripadvisor_api_key']        ?? config('services.tripadvisor.api_key');
        $rows['tripadvisor_location_id']    = $rows['tripadvisor_location_id']    ?? config('services.tripadvisor.location_id');
        $rows['tripadvisor_reviews_enabled'] = isset($rows['tripadvisor_reviews_enabled'])
            ? filter_var($rows['tripadvisor_reviews_enabled'], FILTER_VALIDATE_BOOLEAN)
            : false;

        // Pre-rellenar Zonas de recogida (pickup) — configuración GLOBAL para todos los tours
        $rows['pickup_enabled'] = isset($rows['pickup_enabled'])
            ? filter_var($rows['pickup_enabled'], FILTER_VALIDATE_BOOLEAN)
            : false;

        // Pre-rellenar reCAPTCHA desde .env si aún no están en la BD
        $rows['recaptcha_enabled']  = isset($rows['recaptcha_enabled'])
            ? filter_var($rows['recaptcha_enabled'], FILTER_VALIDATE_BOOLEAN)
            : (bool) config('services.recaptcha.enabled', false);
        $rows['recaptcha_version']     = $rows['recaptcha_version']     ?? config('services.recaptcha.version', 'v3');
        $rows['recaptcha_site_key']    = $rows['recaptcha_site_key']    ?? config('services.recaptcha.site_key');
        $rows['recaptcha_secret_key']  = $rows['recaptcha_secret_key']  ?? config('services.recaptcha.secret_key');
        $rows['recaptcha_v3_threshold']= $rows['recaptcha_v3_threshold']?? config('services.recaptcha.threshold', 0.5);

        // Pre-fill cookie banner settings with defaults if not yet stored
        $rows['cookie_banner_enabled'] = isset($rows['cookie_banner_enabled'])
            ? filter_var($rows['cookie_banner_enabled'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $rows['cookie_text_es'] = $rows['cookie_text_es'] ?? '';
        $rows['cookie_text_en'] = $rows['cookie_text_en'] ?? '';
        $rows['cookie_text_pt'] = $rows['cookie_text_pt'] ?? '';

        // Decode FAQs JSON for the Repeater
        if (isset($rows['faqs']) && is_string($rows['faqs'])) {
            $decoded = json_decode($rows['faqs'], true);
            $rows['faqs'] = is_array($decoded) ? $decoded : [];
        } else {
            $rows['faqs'] = [];
        }

        // Decode home Repeaters
        $repeaterKeys = [
            'home_destinos', 'home_why_items', 'home_tour_type_tabs',
            'home_footer_features', 'home_exp_tours', 'home_reco_items', 'home_faqs',
            'pickup_zones',
        ];
        foreach ($repeaterKeys as $rk) {
            if (isset($rows[$rk]) && is_string($rows[$rk])) {
                $decoded = json_decode($rows[$rk], true);
                $rows[$rk] = is_array($decoded) ? $decoded : [];
            } else {
                $rows[$rk] = $rows[$rk] ?? [];
            }
        }

        $this->form->fill($rows);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('settings')->columnSpanFull()->tabs([
                    Tabs\Tab::make('General')->icon('heroicon-o-globe-alt')->schema([
                        TextInput::make('site_name')->label('Nombre del sitio'),
                        TextInput::make('site_tagline_es')->label('Tagline (ES)'),
                        TextInput::make('site_tagline_en')->label('Tagline (EN)'),
                        Textarea::make('site_description_es')->rows(2)->label('Descripción (ES)'),
                        Textarea::make('site_description_en')->rows(2)->label('Descripción (EN)'),
                    ]),
                    Tabs\Tab::make('Contacto')->icon('heroicon-o-phone')->schema([
                        TextInput::make('contact_email')->email(),
                        TextInput::make('contact_phone')->label('Teléfono principal'),
                        TextInput::make('contact_phone_secondary')->label('Teléfono secundario'),
                        TextInput::make('whatsapp')->label('WhatsApp (sin +)')->placeholder('51935542384'),
                        TextInput::make('contact_address_es')->label('Dirección (ES)'),
                        TextInput::make('contact_address_en')->label('Dirección (EN)'),
                        TextInput::make('contact_hours_es')->label('Horarios (ES)'),
                        TextInput::make('contact_hours_en')->label('Horarios (EN)'),
                        TextInput::make('booking_notification_email')
                            ->email()
                            ->label('Email para avisos de reserva')
                            ->helperText('Cada vez que entre una nueva reserva, se enviará un aviso a esta dirección. Si se deja vacío se usará el correo remitente configurado en el servidor.'),

                        // ── Footer: descripción de la empresa ─────────────────
                        \Filament\Forms\Components\Section::make('Footer — descripción de la empresa')
                            ->description('Texto que aparece bajo el logo en el footer. Dejar vacío para usar el texto por defecto del idioma.')
                            ->collapsible()
                            ->schema([
                                Textarea::make('footer_about_es')
                                    ->label('Descripción footer (ES)')
                                    ->rows(3)
                                    ->placeholder('Nuestra empresa se distingue por ofrecer experiencias únicas y vibrantes…')
                                    ->columnSpanFull(),
                                Textarea::make('footer_about_en')
                                    ->label('Descripción footer (EN)')
                                    ->rows(3)
                                    ->placeholder('Our company distinguishes itself by offering unique and vibrant experiences…')
                                    ->columnSpanFull(),
                                Textarea::make('footer_about_pt')
                                    ->label('Descripción footer (PT)')
                                    ->rows(3)
                                    ->placeholder('Nossa empresa se distingue por oferecer experiências únicas e vibrantes…')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                    Tabs\Tab::make('Redes sociales')->icon('heroicon-o-share')->schema([
                        TextInput::make('social_instagram')->prefix('https://')->label('Instagram'),
                        TextInput::make('social_facebook')->prefix('https://')->label('Facebook'),
                        TextInput::make('social_tiktok')->prefix('https://')->label('TikTok'),
                        TextInput::make('social_youtube')->prefix('https://')->label('YouTube'),
                        TextInput::make('social_google_reviews')
                            ->label('Enlace "Ver en Google" (reseñas)')
                            ->url()
                            ->helperText('Enlace a la ficha de Google Business para dejar/ver reseñas. Usado en las tarjetas de rating de cada tour.'),
                        TextInput::make('social_tripadvisor')
                            ->label('Enlace "Ver en Tripadvisor"')
                            ->url()
                            ->helperText('Enlace al perfil de Tripadvisor. Usado en las tarjetas de rating de cada tour.'),
                    ]),
                    Tabs\Tab::make('Pagos')->icon('heroicon-o-credit-card')->schema([
                        Select::make('paypal_mode')
                            ->label('Modo de PayPal')
                            ->options(['sandbox' => 'Sandbox (pruebas)', 'live' => 'Live (producción)'])
                            ->default('sandbox')
                            ->native(false)
                            ->required()
                            ->helperText('Sandbox = pruebas sin dinero real. Live = cobros reales. Cambia a "Live" solo con credenciales de producción.'),
                        TextInput::make('paypal_client_id')
                            ->label('PayPal Client ID (público)')
                            ->columnSpanFull()
                            ->autocomplete(false),
                        TextInput::make('paypal_secret')
                            ->label('PayPal Secret (privado)')
                            ->password()
                            ->revealable()
                            ->columnSpanFull()
                            ->autocomplete('new-password')
                            ->helperText('Se guarda en la base de datos. Si lo cambias en PayPal, actualízalo aquí.'),
                        TextInput::make('paypal_webhook_id')
                            ->label('Webhook ID (opcional)'),
                    ]),
                    Tabs\Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([
                        TextInput::make('seo_default_title')->label('Title por defecto'),
                        Textarea::make('seo_default_description')->rows(2)->maxLength(160)->label('Description por defecto'),
                        TextInput::make('seo_default_keywords')->label('Keywords (separadas por coma)'),
                        FileUpload::make('seo_og_image')->image()->disk('public')->directory('seo')
                            ->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('seo', 1200, deletePrevious: true))
                            ->helperText('Se optimiza a WebP (máx. 1200px).')
                            ->label('OG Image por defecto'),
                        TextInput::make('seo_google_site_verification')->label('Google Search Console'),
                        TextInput::make('seo_bing_site_verification')->label('Bing Webmaster'),
                        TextInput::make('seo_google_analytics_id')->placeholder('G-XXXXXX')->label('Google Analytics 4'),
                        TextInput::make('seo_gtm_id')->placeholder('GTM-XXXXXX')->label('Google Tag Manager'),
                        TextInput::make('seo_facebook_pixel')->label('Facebook Pixel ID'),
                    ]),
                    Tabs\Tab::make('Home')->icon('heroicon-o-home')->schema([

                        // ── Hero ─────────────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Hero')
                            ->description('Imagen y título principal del banner superior.')
                            ->collapsible()
                            ->schema([
                                FileUpload::make('home_hero_image')
                                    ->label('Hero — imagen de fondo')
                                    ->image()
                                    ->disk('media')
                                    ->directory('home')
                                    ->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1920, disk: 'media', deletePrevious: true))
                                    ->helperText('Dejar vacío para usar la imagen por defecto (Machu Picchu). Se optimiza a WebP (máx. 1920px).')
                                    ->columnSpanFull(),
                                Textarea::make('home_hero_title_es')
                                    ->label('Hero título (ES)')
                                    ->rows(3)
                                    ->helperText('Usa saltos de línea para controlar el quiebre del texto. Ej: "Descubre\nlo que te\ntransforma."')
                                    ->placeholder("Descubre\nlo que te\ntransforma.")
                                    ->columnSpanFull(),
                                Textarea::make('home_hero_title_en')
                                    ->label('Hero título (EN)')
                                    ->rows(3)
                                    ->placeholder("Discover\nwhat\ntransforms you.")
                                    ->columnSpanFull(),
                                Textarea::make('home_hero_title_pt')
                                    ->label('Hero título (PT)')
                                    ->rows(3)
                                    ->placeholder("Descubra\no que\ntransforma você.")
                                    ->columnSpanFull(),
                            ]),

                        // ── Stats ─────────────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Stats (4 indicadores)')
                            ->description('Valores numéricos y etiquetas de los 4 indicadores. Las etiquetas son únicas (sin idioma).')
                            ->collapsible()
                            ->columns(2)
                            ->schema([
                                TextInput::make('stats_rating')
                                    ->label('Stat 1 — Valor (Valoración)')
                                    ->placeholder('4.8'),
                                TextInput::make('home_stat_rating_label')
                                    ->label('Stat 1 — Etiqueta')
                                    ->placeholder('Valoración'),
                                TextInput::make('home_stat_travelers')
                                    ->label('Stat 2 — Valor (Viajeros)')
                                    ->placeholder('+2,000'),
                                TextInput::make('home_stat_travelers_label')
                                    ->label('Stat 2 — Etiqueta')
                                    ->placeholder('Viajeros felices'),
                                TextInput::make('stats_years')
                                    ->label('Stat 3 — Valor (Años)')
                                    ->placeholder('+11'),
                                TextInput::make('home_stat_years_label')
                                    ->label('Stat 3 — Etiqueta')
                                    ->placeholder('Años de experiencia'),
                                TextInput::make('stats_tours')
                                    ->label('Stat 4 — Valor (Tours)')
                                    ->placeholder('+50'),
                                TextInput::make('home_stat_tours_label')
                                    ->label('Stat 4 — Etiqueta')
                                    ->placeholder('Tours únicos'),
                            ]),

                        // ── Sección "Más Comprados" ───────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Más Comprados"')
                            ->collapsible()
                            ->columns(2)
                            ->schema([
                                TextInput::make('home_sec_featured_eyebrow_es')->label('Eyebrow (ES)')->placeholder('NUESTROS TOURS'),
                                TextInput::make('home_sec_featured_eyebrow_en')->label('Eyebrow (EN)')->placeholder('OUR TOURS'),
                                TextInput::make('home_sec_featured_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('NOSSOS TOURS'),
                                TextInput::make('home_sec_featured_title_es')->label('Título (ES)')->placeholder('Más Comprados'),
                                TextInput::make('home_sec_featured_title_en')->label('Título (EN)')->placeholder('Best Sellers'),
                                TextInput::make('home_sec_featured_title_pt')->label('Título (PT)')->placeholder('Mais Vendidos'),
                            ]),

                        // ── Sección "Tours en Lima, Ica y Cusco" ─────────────
                        \Filament\Forms\Components\Section::make('Sección "Tours en Lima, Ica y Cusco"')
                            ->collapsible()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_cities_title_es')->label('Título (ES)')->placeholder('Tours en Lima, Ica y Cusco'),
                                TextInput::make('home_sec_cities_title_en')->label('Título (EN)')->placeholder('Tours in Lima, Ica and Cusco'),
                                TextInput::make('home_sec_cities_title_pt')->label('Título (PT)')->placeholder('Tours em Lima, Ica e Cusco'),
                            ]),

                        // ── Página Gracias ────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Página "Gracias" (post-contacto)')
                            ->collapsible()
                            ->schema([
                                FileUpload::make('gracias_banner_image')
                                    ->label('Imagen de fondo (panel derecho)')
                                    ->image()
                                    ->disk('media')
                                    ->directory('home')
                                    ->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1920, disk: 'media', deletePrevious: true))
                                    ->helperText('Dejar vacío para usar Rectangle 19218.jpg por defecto. Se optimiza a WebP (máx. 1920px).')
                                    ->columnSpanFull(),
                                FileUpload::make('gracias_logo_image')
                                    ->label('Logo sobre la imagen')
                                    ->image()
                                    ->disk('media')
                                    ->directory('home')
                                    ->helperText('Dejar vacío para usar logo.png por defecto. (El logo NO se convierte para conservar la transparencia original.)')
                                    ->columnSpanFull(),
                                TextInput::make('gracias_badge_es')->label('Badge/eyebrow (ES)')->placeholder('Tu mensaje fue enviado'),
                                TextInput::make('gracias_badge_en')->label('Badge/eyebrow (EN)')->placeholder('Your message was sent'),
                                TextInput::make('gracias_badge_pt')->label('Badge/eyebrow (PT)')->placeholder('Sua mensagem foi enviada'),
                                Textarea::make('gracias_title_es')->rows(2)->label('Título (ES)')->placeholder("Gracias por\ncontactarnos"),
                                Textarea::make('gracias_title_en')->rows(2)->label('Título (EN)')->placeholder("Thanks for\ncontacting us"),
                                Textarea::make('gracias_title_pt')->rows(2)->label('Título (PT)')->placeholder("Obrigado por\nnos contatar"),
                                Textarea::make('gracias_body_es')->rows(3)->label('Cuerpo (ES)')->placeholder('Hemos captado tus datos de forma segura, pronto nos pondremos en contacto contigo.')->columnSpanFull(),
                                Textarea::make('gracias_body_en')->rows(3)->label('Cuerpo (EN)')->placeholder('We have securely captured your information and will be in touch with you shortly.')->columnSpanFull(),
                                Textarea::make('gracias_body_pt')->rows(3)->label('Cuerpo (PT)')->placeholder('Capturamos seus dados com segurança e entraremos em contato em breve.')->columnSpanFull(),
                                TextInput::make('gracias_cta_es')->label('Botón CTA (ES)')->placeholder('Volver a inicio'),
                                TextInput::make('gracias_cta_en')->label('Botón CTA (EN)')->placeholder('Back to home'),
                                TextInput::make('gracias_cta_pt')->label('Botón CTA (PT)')->placeholder('Voltar ao início'),
                            ]),

                        // ── Sección 3: Más Visitados ────────────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Más Visitados" (Destinos)')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_destinos_eyebrow_es')->label('Eyebrow (ES)')->placeholder('MÁS VISITADOS'),
                                TextInput::make('home_sec_destinos_eyebrow_en')->label('Eyebrow (EN)')->placeholder('MOST VISITED'),
                                TextInput::make('home_sec_destinos_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('MAIS VISITADOS'),
                                TextInput::make('home_sec_destinos_title_es')->label('Título (ES)')->placeholder('Descubre las Ciudades<br>más Visitadas del Perú')->columnSpanFull(),
                                TextInput::make('home_sec_destinos_title_en')->label('Título (EN)')->placeholder('Discover the Most Visited Cities of Peru')->columnSpanFull(),
                                TextInput::make('home_sec_destinos_title_pt')->label('Título (PT)')->placeholder('Descubra as Cidades mais Visitadas do Peru')->columnSpanFull(),
                                TextInput::make('home_destinos_footer_es')->label('Cierre sección (ES)')->placeholder('Experiencias auténticas, memorias inolvidables. Viaja con')->columnSpanFull(),
                                TextInput::make('home_destinos_footer_en')->label('Cierre sección (EN)')->placeholder('Authentic experiences, unforgettable memories. Travel with')->columnSpanFull(),
                                TextInput::make('home_destinos_footer_pt')->label('Cierre sección (PT)')->placeholder('Experiências autênticas, memórias inesquecíveis. Viaje com')->columnSpanFull(),
                            ]),

                        // ── Repeater: Destinos ──────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Destinos (cards con imagen)')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_destinos')
                                    ->label('Destinos')
                                    ->helperText('Dejar vacío para usar los destinos por defecto (Cusco, Lima, Ica).')
                                    ->schema([
                                        TextInput::make('title_es')->label('Título (ES)')->required(),
                                        TextInput::make('title_en')->label('Título (EN)'),
                                        TextInput::make('title_pt')->label('Título (PT)'),
                                        TextInput::make('badge_es')->label('Badge (ES)'),
                                        TextInput::make('badge_en')->label('Badge (EN)'),
                                        TextInput::make('badge_pt')->label('Badge (PT)'),
                                        Textarea::make('desc_es')->label('Descripción (ES)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_en')->label('Descripción (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_pt')->label('Descripción (PT)')->rows(2)->columnSpanFull(),
                                        TextInput::make('img')->label('Nombre imagen (assets/banners/)')->placeholder('Rectangle 19218.jpg')->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title_es'] ?? null)
                                    ->addActionLabel('+ Agregar destino')
                                    ->columnSpanFull(),
                            ]),

                        // ── Sección 4: Por qué elegirnos ─────────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "¿Por qué elegirnos?"')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_why_eyebrow_es')->label('Eyebrow (ES)')->placeholder('VIAJA CON CONFIANZA'),
                                TextInput::make('home_sec_why_eyebrow_en')->label('Eyebrow (EN)')->placeholder('TRAVEL WITH CONFIDENCE'),
                                TextInput::make('home_sec_why_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('VIAJE COM CONFIANÇA'),
                                TextInput::make('home_sec_why_title_es')->label('Título (ES)')->placeholder('¿Por qué elegir<br>Lima View Tours?')->columnSpanFull(),
                                TextInput::make('home_sec_why_title_en')->label('Título (EN)')->placeholder('Why choose<br>Lima View Tours?')->columnSpanFull(),
                                TextInput::make('home_sec_why_title_pt')->label('Título (PT)')->placeholder('Por que escolher<br>Lima View Tours?')->columnSpanFull(),
                                TextInput::make('home_sec_why_subtitle_es')->label('Subtítulo (ES)')->placeholder('Más que un tour, te ofrecemos<br>experiencias inolvidables.')->columnSpanFull(),
                                TextInput::make('home_sec_why_subtitle_en')->label('Subtítulo (EN)')->placeholder('More than a tour, we offer you<br>unforgettable experiences.')->columnSpanFull(),
                                TextInput::make('home_sec_why_subtitle_pt')->label('Subtítulo (PT)')->placeholder('Mais que um tour, oferecemos<br>experiências inesquecíveis.')->columnSpanFull(),
                                TextInput::make('home_trust_banner_es')->label('Banner confianza (ES)')->placeholder('Reserva fácil, segura y<br><strong class="font-bold text-orange-500">100% garantizada</strong>')->columnSpanFull(),
                                TextInput::make('home_trust_banner_en')->label('Banner confianza (EN)')->placeholder('Easy, secure and<br><strong class="font-bold text-orange-500">100% guaranteed</strong> booking')->columnSpanFull(),
                                TextInput::make('home_trust_banner_pt')->label('Banner confianza (PT)')->placeholder('Reserva fácil, segura e<br><strong class="font-bold text-orange-500">100% garantida</strong>')->columnSpanFull(),
                            ]),

                        // ── Repeater: ¿Por qué elegirnos? items ─────────────────────
                        \Filament\Forms\Components\Section::make('Razones "¿Por qué elegirnos?" (lista)')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_why_items')
                                    ->label('Razones')
                                    ->helperText('Dejar vacío para usar las 5 razones por defecto.')
                                    ->schema([
                                        TextInput::make('title_es')->label('Título (ES)')->required(),
                                        TextInput::make('title_en')->label('Título (EN)'),
                                        TextInput::make('title_pt')->label('Título (PT)'),
                                        Textarea::make('desc_es')->label('Descripción (ES)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_en')->label('Descripción (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_pt')->label('Descripción (PT)')->rows(2)->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title_es'] ?? null)
                                    ->addActionLabel('+ Agregar razón')
                                    ->columnSpanFull(),
                            ]),

                        // ── Sección 5: ¿Qué tipo de tour? ────────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "¿Qué tipo de tour estás buscando?"')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_types_eyebrow_es')->label('Eyebrow (ES)')->placeholder('CATEGORÍAS'),
                                TextInput::make('home_sec_types_eyebrow_en')->label('Eyebrow (EN)')->placeholder('CATEGORIES'),
                                TextInput::make('home_sec_types_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('CATEGORIAS'),
                                TextInput::make('home_sec_types_title_es')->label('Título (ES)')->placeholder('¿Qué tipo de tour estás buscando?')->columnSpanFull(),
                                TextInput::make('home_sec_types_title_en')->label('Título (EN)')->placeholder('What type of tour are you looking for?')->columnSpanFull(),
                                TextInput::make('home_sec_types_title_pt')->label('Título (PT)')->placeholder('Que tipo de tour você está procurando?')->columnSpanFull(),
                            ]),

                        // ── Repeater: Tour Type Tabs ─────────────────────────────────
                        \Filament\Forms\Components\Section::make('Tabs de tipo de tour')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_tour_type_tabs')
                                    ->label('Tipos de tour')
                                    ->helperText('Dejar vacío para usar los 4 tipos por defecto. El campo "id" debe ser único (cult, adv, cul, oth).')
                                    ->schema([
                                        TextInput::make('id')->label('ID (único, sin espacios)')->placeholder('cult')->required(),
                                        TextInput::make('label_es')->label('Label tab (ES)')->required(),
                                        TextInput::make('label_en')->label('Label tab (EN)'),
                                        TextInput::make('label_pt')->label('Label tab (PT)'),
                                        TextInput::make('title_es')->label('Título panel (ES)'),
                                        TextInput::make('title_en')->label('Título panel (EN)'),
                                        TextInput::make('title_pt')->label('Título panel (PT)'),
                                        TextInput::make('img')->label('Nombre imagen (assets/banners/)')->placeholder('Rectangle 19215.jpg'),
                                        TextInput::make('eyebrow_es')->label('Eyebrow (ES)'),
                                        TextInput::make('eyebrow_en')->label('Eyebrow (EN)'),
                                        TextInput::make('eyebrow_pt')->label('Eyebrow (PT)'),
                                        Textarea::make('desc_es')->label('Descripción (ES)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_en')->label('Descripción (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_pt')->label('Descripción (PT)')->rows(2)->columnSpanFull(),
                                        TextInput::make('price')->label('Precio desde (USD)')->numeric(),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => ($state['label_es'] ?? null))
                                    ->addActionLabel('+ Agregar tipo')
                                    ->columnSpanFull(),
                            ]),

                        // ── Repeater: Footer features ────────────────────────────────
                        \Filament\Forms\Components\Section::make('Features del footer de sección "¿Qué tipo de tour?"')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_footer_features')
                                    ->label('Features (4 íconos en fila)')
                                    ->helperText('Dejar vacío para usar los 4 features por defecto.')
                                    ->schema([
                                        TextInput::make('label_es')->label('Etiqueta (ES)')->required(),
                                        TextInput::make('label_en')->label('Etiqueta (EN)'),
                                        TextInput::make('label_pt')->label('Etiqueta (PT)'),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['label_es'] ?? null)
                                    ->addActionLabel('+ Agregar feature')
                                    ->columnSpanFull(),
                            ]),

                        // ── Sección 6: Experiencias ───────────────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Descubre experiencias únicas"')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_exp_eyebrow_es')->label('Eyebrow (ES)')->placeholder('EXPERIENCIAS'),
                                TextInput::make('home_sec_exp_eyebrow_en')->label('Eyebrow (EN)')->placeholder('EXPERIENCES'),
                                TextInput::make('home_sec_exp_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('EXPERIÊNCIAS'),
                                TextInput::make('home_sec_exp_title_es')->label('Título (ES)')->placeholder('Descubre experiencias únicas')->columnSpanFull(),
                                TextInput::make('home_sec_exp_title_en')->label('Título (EN)')->placeholder('Discover unique experiences')->columnSpanFull(),
                                TextInput::make('home_sec_exp_title_pt')->label('Título (PT)')->placeholder('Descubra experiências únicas')->columnSpanFull(),
                                TextInput::make('home_swipe_hint_es')->label('Hint deslizar (ES)')->placeholder('Desliza para ver más tours')->columnSpanFull(),
                                TextInput::make('home_swipe_hint_en')->label('Hint deslizar (EN)')->placeholder('Swipe to see more tours')->columnSpanFull(),
                                TextInput::make('home_swipe_hint_pt')->label('Hint deslizar (PT)')->placeholder('Deslize para ver mais tours')->columnSpanFull(),
                                TextInput::make('home_swipe_sub_es')->label('Subtexto hint (ES)')->placeholder('Descubre nuestras mejores experiencias')->columnSpanFull(),
                                TextInput::make('home_swipe_sub_en')->label('Subtexto hint (EN)')->placeholder('Discover our best experiences')->columnSpanFull(),
                                TextInput::make('home_swipe_sub_pt')->label('Subtexto hint (PT)')->placeholder('Descubra nossas melhores experiências')->columnSpanFull(),
                            ]),

                        // ── Repeater: Experiencias únicas ────────────────────────────
                        \Filament\Forms\Components\Section::make('Cards de "Experiencias únicas"')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_exp_tours')
                                    ->label('Experiencias')
                                    ->helperText('Dejar vacío para usar las 4 tarjetas por defecto.')
                                    ->schema([
                                        TextInput::make('title_es')->label('Título (ES)')->required(),
                                        TextInput::make('title_en')->label('Título (EN)'),
                                        TextInput::make('title_pt')->label('Título (PT)'),
                                        TextInput::make('badge_es')->label('Badge (ES)'),
                                        TextInput::make('badge_en')->label('Badge (EN)'),
                                        TextInput::make('badge_pt')->label('Badge (PT)'),
                                        TextInput::make('img')->label('Nombre imagen (assets/banners/)')->placeholder('Rectangle 19216.jpg'),
                                        TextInput::make('badgeBg')->label('Color badge (teal-800 / orange-500)')->placeholder('teal-800'),
                                        TextInput::make('slug')->label('Slug del tour'),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title_es'] ?? null)
                                    ->addActionLabel('+ Agregar experiencia')
                                    ->columnSpanFull(),
                            ]),

                        // ── Sección 6B: Opiniones ─────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Opiniones de viajeros"')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_reviews_eyebrow_es')->label('Eyebrow (ES)')->placeholder('OPINIONES REALES'),
                                TextInput::make('home_sec_reviews_eyebrow_en')->label('Eyebrow (EN)')->placeholder('REAL REVIEWS'),
                                TextInput::make('home_sec_reviews_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('OPINIÕES REAIS'),
                                TextInput::make('home_sec_reviews_title_es')->label('Título (ES)')->placeholder('Lo que dicen nuestros viajeros')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_title_en')->label('Título (EN)')->placeholder('What our travelers say')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_title_pt')->label('Título (PT)')->placeholder('O que dizem nossos viajantes')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_subtitle_es')->label('Subtítulo (ES)')->placeholder('Miles de viajeros han vivido el Perú con nosotros.')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_subtitle_en')->label('Subtítulo (EN)')->placeholder('Thousands of travelers have experienced Peru with us.')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_subtitle_pt')->label('Subtítulo (PT)')->placeholder('Milhares de viajantes viveram o Peru conosco.')->columnSpanFull(),
                            ]),

                        // ── Sección 7: FAQs ──────────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Preguntas frecuentes" (home)')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_faq_title_es')->label('Título (ES)')->placeholder('Preguntas frecuentes')->columnSpanFull(),
                                TextInput::make('home_sec_faq_title_en')->label('Título (EN)')->placeholder('Frequently asked questions')->columnSpanFull(),
                                TextInput::make('home_sec_faq_title_pt')->label('Título (PT)')->placeholder('Perguntas frequentes')->columnSpanFull(),
                                TextInput::make('home_sec_faq_subtitle_es')->label('Subtítulo (ES)')->placeholder('Resolvemos las dudas más comunes...')->columnSpanFull(),
                                TextInput::make('home_sec_faq_subtitle_en')->label('Subtítulo (EN)')->placeholder('We resolve the most common doubts...')->columnSpanFull(),
                                TextInput::make('home_sec_faq_subtitle_pt')->label('Subtítulo (PT)')->placeholder('Resolvemos as dúvidas mais comuns...')->columnSpanFull(),
                            ]),

                        // ── Repeater: FAQs home ──────────────────────────────────────
                        \Filament\Forms\Components\Section::make('FAQs del acordeón (home) — separadas del AEO')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_faqs')
                                    ->label('Preguntas frecuentes (home)')
                                    ->helperText('Estas FAQs son para el acordeón visual de la home. Son DISTINTAS a las FAQs del tab AEO/FAQ (schema estructurado). Dejar vacío para usar las 6 preguntas por defecto.')
                                    ->schema([
                                        TextInput::make('q_es')->label('Pregunta (ES)')->required()->columnSpanFull(),
                                        TextInput::make('q_en')->label('Pregunta (EN)')->columnSpanFull(),
                                        TextInput::make('q_pt')->label('Pregunta (PT)')->columnSpanFull(),
                                        Textarea::make('a_es')->label('Respuesta (ES)')->rows(2)->required()->columnSpanFull(),
                                        Textarea::make('a_en')->label('Respuesta (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('a_pt')->label('Respuesta (PT)')->rows(2)->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['q_es'] ?? null)
                                    ->addActionLabel('+ Agregar pregunta')
                                    ->columnSpanFull(),
                            ]),

                        // ── Sección 8: Verificado y Recomendado ──────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Verificado y Recomendado"')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_reco_eyebrow_es')->label('Eyebrow pill (ES)')->placeholder('VERIFICADO Y RECOMENDADO POR')->columnSpanFull(),
                                TextInput::make('home_sec_reco_eyebrow_en')->label('Eyebrow pill (EN)')->placeholder('VERIFIED AND RECOMMENDED BY')->columnSpanFull(),
                                TextInput::make('home_sec_reco_eyebrow_pt')->label('Eyebrow pill (PT)')->placeholder('VERIFICADO E RECOMENDADO POR')->columnSpanFull(),
                                TextInput::make('home_reco_intro_es')->label('Intro "Estamos recomendados por" (ES)')->columnSpanFull(),
                                TextInput::make('home_reco_intro_en')->label('Intro "Estamos recomendados por" (EN)')->columnSpanFull(),
                                TextInput::make('home_reco_intro_pt')->label('Intro "Estamos recomendados por" (PT)')->columnSpanFull(),
                                TextInput::make('home_reco_headline_es')->label('Headline (ES)')->placeholder('Best Tours in Lima')->columnSpanFull(),
                                TextInput::make('home_reco_headline_en')->label('Headline (EN)')->placeholder('Best Tours in Lima')->columnSpanFull(),
                                TextInput::make('home_reco_headline_pt')->label('Headline (PT)')->placeholder('Best Tours in Lima')->columnSpanFull(),
                                Textarea::make('home_reco_body_es')->rows(2)->label('Cuerpo (ES)')->placeholder('Una de las mejores empresas...')->columnSpanFull(),
                                Textarea::make('home_reco_body_en')->rows(2)->label('Cuerpo (EN)')->columnSpanFull(),
                                Textarea::make('home_reco_body_pt')->rows(2)->label('Cuerpo (PT)')->columnSpanFull(),
                                TextInput::make('home_reco_award_title_es')->label('Premio título (ES)')->placeholder('GANAMOS EL PREMIO<br>DE VERIFICACIÓN')->columnSpanFull(),
                                TextInput::make('home_reco_award_title_en')->label('Premio título (EN)')->columnSpanFull(),
                                TextInput::make('home_reco_award_title_pt')->label('Premio título (PT)')->columnSpanFull(),
                                Textarea::make('home_reco_award_desc_es')->rows(2)->label('Premio descripción (ES)')->placeholder('para los mejores tours...')->columnSpanFull(),
                                Textarea::make('home_reco_award_desc_en')->rows(2)->label('Premio descripción (EN)')->columnSpanFull(),
                                Textarea::make('home_reco_award_desc_pt')->rows(2)->label('Premio descripción (PT)')->columnSpanFull(),
                                TextInput::make('home_sec_why_reco_title_es')->label('H3 "¿Por qué somos recomendados?" (ES)')->placeholder('¿Por qué somos recomendados?')->columnSpanFull(),
                                TextInput::make('home_sec_why_reco_title_en')->label('H3 "¿Por qué somos recomendados?" (EN)')->columnSpanFull(),
                                TextInput::make('home_sec_why_reco_title_pt')->label('H3 "¿Por qué somos recomendados?" (PT)')->columnSpanFull(),
                                TextInput::make('home_sec_verified_in_es')->label('H3 "Recomendado y verificado en" (ES)')->placeholder('Recomendado y verificado en')->columnSpanFull(),
                                TextInput::make('home_sec_verified_in_en')->label('H3 "Recomendado y verificado en" (EN)')->columnSpanFull(),
                                TextInput::make('home_sec_verified_in_pt')->label('H3 "Recomendado y verificado en" (PT)')->columnSpanFull(),
                                Textarea::make('home_reviews_footer_es')->rows(2)->label('Footer reseñas (ES)')->placeholder('Miles de reseñas verificadas...')->columnSpanFull(),
                                Textarea::make('home_reviews_footer_en')->rows(2)->label('Footer reseñas (EN)')->columnSpanFull(),
                                Textarea::make('home_reviews_footer_pt')->rows(2)->label('Footer reseñas (PT)')->columnSpanFull(),
                            ]),

                        // ── Repeater: ¿Por qué somos recomendados? ──────────────────
                        \Filament\Forms\Components\Section::make('Cards "¿Por qué somos recomendados?"')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_reco_items')
                                    ->label('Razones de recomendación')
                                    ->helperText('Dejar vacío para usar las 4 razones por defecto.')
                                    ->schema([
                                        TextInput::make('title_es')->label('Título (ES)')->required(),
                                        TextInput::make('title_en')->label('Título (EN)'),
                                        TextInput::make('title_pt')->label('Título (PT)'),
                                        Textarea::make('desc_es')->label('Descripción (ES)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_en')->label('Descripción (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_pt')->label('Descripción (PT)')->rows(2)->columnSpanFull(),
                                        Select::make('icon')
                                            ->label('Icono')
                                            ->options(['shield'=>'Escudo (shield)','star'=>'Estrella (star)','headset'=>'Auriculares (headset)','medal'=>'Medalla (medal)'])
                                            ->native(false),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title_es'] ?? null)
                                    ->addActionLabel('+ Agregar razón')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                    Tabs\Tab::make('GEO')->icon('heroicon-o-map-pin')->schema([
                        TextInput::make('geo_business_name')
                            ->label('Nombre del negocio')
                            ->default('Lima View Tours')
                            ->helperText('Nombre oficial que aparece en Google Maps y buscadores.'),
                        TextInput::make('geo_street')
                            ->label('Calle / Dirección exacta')
                            ->placeholder('Av. Larco 1301, Miraflores'),
                        TextInput::make('geo_city')
                            ->label('Ciudad')
                            ->default('Lima'),
                        TextInput::make('geo_region')
                            ->label('Región / Departamento')
                            ->default('Lima'),
                        TextInput::make('geo_postal_code')
                            ->label('Código postal'),
                        TextInput::make('geo_country')
                            ->label('País (código ISO 2)')
                            ->default('PE')
                            ->maxLength(2),
                        TextInput::make('geo_latitude')
                            ->label('Latitud')
                            ->placeholder('-12.1091800')
                            ->helperText('Decimal. Ej: -12.1091800 (copia de Google Maps)'),
                        TextInput::make('geo_longitude')
                            ->label('Longitud')
                            ->placeholder('-77.0365200'),
                        TextInput::make('geo_region_code')
                            ->label('Código de región (ISO 3166-2)')
                            ->default('PE-LIM')
                            ->placeholder('PE-LIM')
                            ->helperText('Formato ISO 3166-2. Ej: PE-LIM para Lima, Perú.'),
                        TextInput::make('geo_price_range')
                            ->label('Rango de precio')
                            ->default('$$')
                            ->placeholder('$$')
                            ->helperText('Símbolo para schema.org. Ej: $ = económico, $$$$ = lujo.'),
                    ]),
                    Tabs\Tab::make('AEO / FAQ')->icon('heroicon-o-question-mark-circle')->schema([
                        Repeater::make('faqs')
                            ->label('Preguntas Frecuentes (FAQs)')
                            ->helperText('Estas FAQs se muestran en el sitio como acordeón y se incluyen en el schema FAQ (AEO).')
                            ->schema([
                                TextInput::make('question_es')->label('Pregunta (ES)')->required()->columnSpanFull(),
                                TextInput::make('question_en')->label('Pregunta (EN)')->columnSpanFull(),
                                TextInput::make('question_pt')->label('Pregunta (PT)')->columnSpanFull(),
                                Textarea::make('answer_es')->label('Respuesta (ES)')->rows(3)->required()->columnSpanFull(),
                                Textarea::make('answer_en')->label('Respuesta (EN)')->rows(3)->columnSpanFull(),
                                Textarea::make('answer_pt')->label('Respuesta (PT)')->rows(3)->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question_es'] ?? null)
                            ->addActionLabel('+ Agregar pregunta')
                            ->columnSpanFull(),
                    ]),
                    Tabs\Tab::make('APIs')->icon('heroicon-o-key')->schema([
                        TextInput::make('google_maps_api_key')
                            ->label('Google Maps API Key')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->columnSpanFull()
                            ->helperText('Habilita Places API + Maps JavaScript API en Google Cloud, con billing. Activa el autocompletado de hotel en el checkout.'),
                        TextInput::make('google_place_id')
                            ->label('Google Place ID')
                            ->columnSpanFull()
                            ->helperText('ID del negocio en Google Maps, para traer reseñas. Ej: ChIJN1t_tDeuEmsRUsoyG83frY4'),
                        Toggle::make('google_reviews_enabled')
                            ->label('Activar traída de reseñas de Google')
                            ->helperText('Requiere Google Maps API Key y Place ID configurados.'),
                        TextInput::make('tripadvisor_api_key')
                            ->label('Tripadvisor API Key')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->columnSpanFull()
                            ->helperText('Clave de la Content API de Tripadvisor. Requiere aprobación previa de Tripadvisor.'),
                        TextInput::make('tripadvisor_location_id')
                            ->label('Tripadvisor Location ID')
                            ->columnSpanFull()
                            ->helperText('ID numérico del negocio en Tripadvisor. Se encuentra en la URL del perfil.'),
                        Toggle::make('tripadvisor_reviews_enabled')
                            ->label('Activar traída de reseñas de Tripadvisor')
                            ->helperText('Requiere Tripadvisor API Key y Location ID configurados.'),

                        \Filament\Forms\Components\Section::make('Tarjetas de reseñas (rating y nº)')
                            ->description('Rating y número de reseñas mostrados en las tarjetas de Google/Tripadvisor de cada tour. Los enlaces "Ver en Google/Tripadvisor" se configuran en la pestaña "Redes sociales".')
                            ->collapsible()
                            ->schema([
                                TextInput::make('reviews_google_rating')
                                    ->label('Google — Rating (ej. 4.9)')
                                    ->helperText('Se muestra en la tarjeta de Google de la página de cada tour.'),
                                TextInput::make('reviews_google_count')
                                    ->label('Google — Nº de reseñas (ej. 123)')
                                    ->numeric()
                                    ->helperText('Cantidad de reseñas mostrada junto al rating de Google.'),
                                TextInput::make('reviews_tripadvisor_rating')
                                    ->label('Tripadvisor — Rating (ej. 4.6)')
                                    ->helperText('Se muestra en la tarjeta de Tripadvisor de la página de cada tour.'),
                                TextInput::make('reviews_tripadvisor_count')
                                    ->label('Tripadvisor — Nº de reseñas (ej. 8)')
                                    ->numeric()
                                    ->helperText('Cantidad de reseñas mostrada junto al rating de Tripadvisor.'),
                            ]),
                    ]),
                    Tabs\Tab::make('Recogida')->icon('heroicon-o-map')->schema([
                        Toggle::make('pickup_enabled')
                            ->label('¿Ofrece servicio de recogida en zonas generales?')
                            ->helperText('Actívalo para mostrar la sección "¿Dónde te recogemos?" en la página de CADA tour, con el mapa y las zonas configuradas abajo. Es una configuración global: aplica igual para todos los tours.')
                            ->live(),

                        \Filament\Forms\Components\Section::make('Zonas de recogida')
                            ->description('Define las zonas donde ofreces recogida. Se muestran en todos los tours. Reutiliza la Google Maps API Key configurada en la pestaña "APIs".')
                            ->collapsible()
                            ->visible(fn (\Filament\Forms\Get $get): bool => (bool) $get('pickup_enabled'))
                            ->schema([
                                \Filament\Forms\Components\ViewField::make('pickup_zones_tools')
                                    ->view('filament.pages.partials.pickup-zones-tools')
                                    ->dehydrated(false)
                                    ->columnSpanFull(),

                                Repeater::make('pickup_zones')
                                    ->label('Zonas')
                                    ->helperText('Escribe el nombre del lugar para usar el autocompletado de Google (rellena lat/lng automáticamente). Si el autocompletado no responde, puedes ingresar lat/lng a mano: en Google Maps, clic derecho sobre el punto exacto → clic en las coordenadas para copiarlas.')
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Nombre del lugar / zona')
                                            ->placeholder('Miraflores, Lima')
                                            ->required()
                                            ->columnSpanFull()
                                            ->extraInputAttributes([
                                                'data-pickup-place-input' => 'true',
                                                'autocomplete' => 'off',
                                                'x-init' => 'window.__lvtInitPickupAutocomplete && window.__lvtInitPickupAutocomplete($el)',
                                            ]),
                                        TextInput::make('lat')
                                            ->label('Latitud')
                                            ->numeric()
                                            ->step('any')
                                            ->required()
                                            ->extraInputAttributes(['data-pickup-lat' => 'true']),
                                        TextInput::make('lng')
                                            ->label('Longitud')
                                            ->numeric()
                                            ->step('any')
                                            ->required()
                                            ->extraInputAttributes(['data-pickup-lng' => 'true']),
                                        TextInput::make('radius_km')
                                            ->label('Radio (km)')
                                            ->numeric()
                                            ->step('0.1')
                                            ->minValue(0.1)
                                            ->default(2)
                                            ->required(),
                                        Select::make('type')
                                            ->label('Tipo de recogida')
                                            ->options([
                                                'all'    => 'Todas las ubicaciones',
                                                'hotels' => 'Solo hoteles',
                                            ])
                                            ->default('all')
                                            ->native(false)
                                            ->required(),
                                    ])
                                    ->columns(4)
                                    ->reorderable()
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                    ->addActionLabel('Añadir otra zona')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                    Tabs\Tab::make('reCAPTCHA')->icon('heroicon-o-shield-check')->schema([
                        Toggle::make('recaptcha_enabled')
                            ->label('Activar reCAPTCHA')
                            ->helperText('Protege los formularios públicos contra bots. Requiere las claves de Google configuradas abajo.')
                            ->live(),
                        Select::make('recaptcha_version')
                            ->label('Versión')
                            ->options([
                                'v3' => 'v3 — Invisible (recomendado, sin interrupción al usuario)',
                                'v2' => 'v2 — Casilla "No soy un robot"',
                            ])
                            ->default('v3')
                            ->native(false)
                            ->required()
                            ->live()
                            ->helperText('v3 actúa en segundo plano. v2 muestra un widget visible al usuario.'),
                        TextInput::make('recaptcha_site_key')
                            ->label('Site Key (pública)')
                            ->columnSpanFull()
                            ->autocomplete(false)
                            ->helperText('Se obtiene en https://www.google.com/recaptcha/admin — clave pública que va en el frontend.'),
                        TextInput::make('recaptcha_secret_key')
                            ->label('Secret Key (privada)')
                            ->password()
                            ->revealable()
                            ->columnSpanFull()
                            ->autocomplete('new-password')
                            ->helperText('Clave privada que se usa en el servidor para verificar los tokens. Nunca exponerla en el frontend.'),
                        TextInput::make('recaptcha_v3_threshold')
                            ->label('Umbral de puntuación v3 (0.0 – 1.0)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.05)
                            ->default(0.5)
                            ->visible(fn (\Filament\Forms\Get $get): bool => $get('recaptcha_version') === 'v3')
                            ->helperText('Puntuaciones más cercanas a 1.0 son probablemente humanos; cerca de 0.0, bots. El valor recomendado es 0.5.'),
                    ]),
                    Tabs\Tab::make('Cookies')->icon('heroicon-o-shield-exclamation')->schema([
                        Toggle::make('cookie_banner_enabled')
                            ->label('Mostrar banner de consentimiento de cookies')
                            ->helperText(
                                'Activo: el banner se muestra a usuarios sin decisión previa y el analytics '.
                                'requiere consentimiento (Google Consent Mode v2 + FB Pixel gating). '.
                                'Inactivo: el analytics carga directamente sin pedir consentimiento.'
                            )
                            ->default(true),
                        Textarea::make('cookie_text_es')
                            ->label('Texto del banner (ES)')
                            ->rows(3)
                            ->placeholder('Usamos cookies para mejorar tu experiencia y analizar el tráfico del sitio. Puedes aceptarlas o rechazarlas.')
                            ->helperText('Deja en blanco para usar el texto por defecto en español.')
                            ->columnSpanFull(),
                        Textarea::make('cookie_text_en')
                            ->label('Texto del banner (EN)')
                            ->rows(3)
                            ->placeholder('We use cookies to improve your experience and analyze site traffic. You can accept or reject them.')
                            ->helperText('Leave blank to use the default English text.')
                            ->columnSpanFull(),
                        Textarea::make('cookie_text_pt')
                            ->label('Texto del banner (PT)')
                            ->rows(3)
                            ->placeholder('Usamos cookies para melhorar sua experiência e analisar o tráfego do site. Você pode aceitá-las ou recusá-las.')
                            ->helperText('Deixe em branco para usar o texto padrão em português.')
                            ->columnSpanFull(),
                    ]),
                ]),
            ])
            ->statePath('data');
    }

    /** Keys whose values are stored as boolean type in settings. */
    private const BOOLEAN_KEYS = [
        'google_reviews_enabled',
        'tripadvisor_reviews_enabled',
        'recaptcha_enabled',
        'cookie_banner_enabled',
        'pickup_enabled',
    ];

    public function save(): void
    {
        foreach ($this->data as $key => $value) {
            // Serialize Repeater fields as JSON string
            $jsonRepeaterKeys = ['faqs','home_destinos','home_why_items','home_tour_type_tabs',
                                 'home_footer_features','home_exp_tours','home_reco_items','home_faqs',
                                 'pickup_zones'];
            if (in_array($key, $jsonRepeaterKeys, true)) {
                Setting::set($key, json_encode(is_array($value) ? $value : []));
                continue;
            }
            // Store boolean toggle fields with the correct type so castValue works
            if (in_array($key, self::BOOLEAN_KEYS, true)) {
                Setting::set($key, $value ? '1' : '0', 'boolean');
                continue;
            }
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Configuración guardada correctamente')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Guardar cambios')
                ->submit('save'),
        ];
    }
}
