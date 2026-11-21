<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasLocalizedSeoFields;
use App\Models\Setting;
use App\Support\HomeDefaults;
use App\Support\PageSeo;
use Filament\Forms\Components\DatePicker;
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
    use HasLocalizedSeoFields;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración del sitio';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    /**
     * Repeaters del home precargados con HomeDefaults. Valor = campos NO traducibles
     * (sin sufijo _es/_en/_pt) que SÍ existen como campo del Repeater. Lo puramente
     * visual (iconPath, img, deco...) no se precarga: la vista lo hereda por posición.
     */
    protected const HOME_REPEATER_FORM_FIELDS = [
        'home_destinos' => [],
        'home_why_items' => [],
        'home_tour_type_tabs' => ['id'],
        'home_footer_features' => [],
        'home_exp_tours' => ['slug'],
        'home_reco_items' => ['icon'],
        'home_faqs' => [],
        'home_choose_items' => [],
    ];

    /**
     * Defaults del home reducidos a los campos que el Repeater tiene.
     */
    protected static function homeDefaultsForForm(string $key, array $extraFields): array
    {
        $out = [];
        foreach (HomeDefaults::for($key) as $item) {
            $out[] = array_filter(
                $item,
                static fn ($v, $k) => (preg_match('/_(es|en|pt)$/', (string) $k) === 1 || in_array($k, $extraFields, true)) && $v !== null,
                ARRAY_FILTER_USE_BOTH
            );
        }

        return $out;
    }

    public function mount(): void
    {
        $rows = Setting::all()->pluck('value', 'key')->toArray();
        // Pre-rellenar credenciales de PayPal desde el .env si aún no están en la BD
        $rows['paypal_client_id'] = $rows['paypal_client_id'] ?? config('services.paypal.client_id');
        $rows['paypal_secret'] = $rows['paypal_secret'] ?? config('services.paypal.secret');
        $rows['paypal_mode'] = $rows['paypal_mode'] ?? config('services.paypal.mode', 'sandbox');
        $rows['paypal_webhook_id'] = $rows['paypal_webhook_id'] ?? config('services.paypal.webhook_id');

        // Pre-rellenar keys de Google y Tripadvisor desde .env si aún no están en la BD
        $rows['google_maps_api_key'] = $rows['google_maps_api_key'] ?? config('services.google.maps_api_key');
        $rows['google_place_id'] = $rows['google_place_id'] ?? config('services.google.place_id');
        $rows['google_reviews_enabled'] = isset($rows['google_reviews_enabled'])
            ? filter_var($rows['google_reviews_enabled'], FILTER_VALIDATE_BOOLEAN)
            : false;
        $rows['tripadvisor_api_key'] = $rows['tripadvisor_api_key'] ?? config('services.tripadvisor.api_key');
        $rows['tripadvisor_location_id'] = $rows['tripadvisor_location_id'] ?? config('services.tripadvisor.location_id');
        $rows['tripadvisor_reviews_enabled'] = isset($rows['tripadvisor_reviews_enabled'])
            ? filter_var($rows['tripadvisor_reviews_enabled'], FILTER_VALIDATE_BOOLEAN)
            : false;

        // Pre-rellenar Zonas de recogida (pickup) — configuración GLOBAL para todos los tours
        $rows['pickup_enabled'] = isset($rows['pickup_enabled'])
            ? filter_var($rows['pickup_enabled'], FILTER_VALIDATE_BOOLEAN)
            : false;

        // Pre-rellenar reCAPTCHA desde .env si aún no están en la BD
        $rows['recaptcha_enabled'] = isset($rows['recaptcha_enabled'])
            ? filter_var($rows['recaptcha_enabled'], FILTER_VALIDATE_BOOLEAN)
            : (bool) config('services.recaptcha.enabled', false);
        $rows['recaptcha_version'] = $rows['recaptcha_version'] ?? config('services.recaptcha.version', 'v3');
        $rows['recaptcha_site_key'] = $rows['recaptcha_site_key'] ?? config('services.recaptcha.site_key');
        $rows['recaptcha_secret_key'] = $rows['recaptcha_secret_key'] ?? config('services.recaptcha.secret_key');
        $rows['recaptcha_v3_threshold'] = $rows['recaptcha_v3_threshold'] ?? config('services.recaptcha.threshold', 0.5);

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
            'home_choose_items', 'pickup_zones', 'home_search_chips',
            'home_star_milestones_es', 'home_star_milestones_en', 'home_star_milestones_pt',
            'home_star_chips_es', 'home_star_chips_en', 'home_star_chips_pt',
        ];
        foreach ($repeaterKeys as $rk) {
            if (isset($rows[$rk]) && is_string($rows[$rk])) {
                $decoded = json_decode($rows[$rk], true);
                $rows[$rk] = is_array($decoded) ? $decoded : [];
            } else {
                $rows[$rk] = $rows[$rk] ?? [];
            }
        }

        // Repeaters del home: si no hay nada guardado (clave ausente, JSON roto o
        // []), precargar los textos que la web muestra hoy. Así el cliente edita
        // lo que ve en vez de un repeater vacío (que al añadir 1 item hacía que
        // la web pasara de N elementos a 1).
        foreach (self::HOME_REPEATER_FORM_FIELDS as $rk => $extraFields) {
            if (empty($rows[$rk]) || ! is_array($rows[$rk])) {
                $rows[$rk] = self::homeDefaultsForForm($rk, $extraFields);
            }
        }

        // Los Select de tours comparan contra claves int: el Setting guarda texto.
        foreach (['home_star_tour_id', 'home_compare_tour_a', 'home_compare_tour_b'] as $k) {
            $rows[$k] = (isset($rows[$k]) && is_numeric($rows[$k])) ? (int) $rows[$k] : null;
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
                            ->label('Emails para avisos de reserva')
                            ->placeholder('correo1@dominio.com, correo2@dominio.com')
                            ->helperText('Cada vez que entre una nueva reserva se enviará un aviso a estas direcciones. Puedes poner VARIOS correos separados por coma. El cliente que reserva siempre recibe su confirmación aparte. Si se deja vacío se usará el correo remitente del servidor.')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    foreach (preg_split('/[,;\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $email) {
                                        if (! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                                            $fail("«{$email}» no es un correo válido.");
                                        }
                                    }
                                };
                            }),

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
                    Tabs\Tab::make('Datos legales')->icon('heroicon-o-scale')->schema([
                        \Filament\Forms\Components\Placeholder::make('legal_help')
                            ->label('')
                            ->content('Estos datos salen en Términos, Privacidad, Política de devoluciones y Libro de Reclamaciones. Si dejas un dato vacío, esa línea no se muestra. El teléfono oficial es el "Teléfono principal" de la pestaña Contacto.')
                            ->columnSpanFull(),
                        TextInput::make('legal_business_name')
                            ->label('Razón social')
                            ->placeholder('Ej.: NOMBRE DE LA EMPRESA S.A.C.')
                            ->maxLength(200),
                        TextInput::make('legal_ruc')
                            ->label('RUC')
                            ->placeholder('11 dígitos')
                            ->maxLength(11)
                            ->rule('nullable')
                            ->rule('regex:/^[0-9]{11}$/')
                            ->validationMessages(['regex' => 'El RUC debe tener 11 dígitos.']),
                        TextInput::make('legal_address')
                            ->label('Dirección fiscal')
                            ->placeholder('Calle, número, distrito, ciudad')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('legal_refund_days')
                            ->label('Plazo de devolución (días hábiles)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->placeholder('10')
                            ->helperText('Días hábiles que tardan en procesarse los reembolsos. Se muestra en la Política de cambios y devoluciones. Vacío = 10.'),
                        \Filament\Forms\Components\Section::make('Resumen de cancelación (checkout)')
                            ->description('Texto corto que se muestra en el checkout, junto al botón de pago. Debe coincidir con tu Política de cambios y devoluciones. Si lo dejas vacío en todos los idiomas, no se muestra ningún texto de cancelación en el checkout.')
                            ->columnSpanFull()
                            ->schema([
                                Textarea::make('legal_cancellation_summary_es')
                                    ->label('Resumen (Español)')
                                    ->rows(2)->maxLength(300)
                                    ->placeholder('Ej.: Cancela gratis hasta 24 horas antes de la salida.'),
                                Textarea::make('legal_cancellation_summary_en')
                                    ->label('Summary (English)')
                                    ->rows(2)->maxLength(300)
                                    ->helperText('Vacío = se usa el de español.'),
                                Textarea::make('legal_cancellation_summary_pt')
                                    ->label('Resumo (Português)')
                                    ->rows(2)->maxLength(300)
                                    ->helperText('Vacío = se usa el de español.'),
                            ]),
                    ])->columns(2),
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
                        FileUpload::make('seo_og_image')->fetchFileInformation(false)->image()->disk('public')->directory('seo')
                            ->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('seo', 1200, deletePrevious: true))
                            ->helperText('Se optimiza a WebP (máx. 1200px).')
                            ->label('OG Image por defecto'),
                        TextInput::make('seo_google_site_verification')->label('Google Search Console'),
                        TextInput::make('seo_bing_site_verification')->label('Bing Webmaster'),
                        TextInput::make('seo_google_analytics_id')->placeholder('G-XXXXXX')->label('Google Analytics 4'),
                        TextInput::make('seo_gtm_id')->placeholder('GTM-XXXXXX')->label('Google Tag Manager'),
                        TextInput::make('seo_facebook_pixel')->label('Facebook Pixel ID'),
                        \Filament\Forms\Components\Section::make('Metas por página')
                            ->description('Title y description de las páginas que no son un contenido del CMS (no tienen ficha propia donde guardarlos). Si dejas un campo vacío se usa el texto por defecto del sitio. Si dejas vacío un idioma, se usa el español.')
                            ->icon('heroicon-o-document-text')
                            ->schema(static::seoPageFields())
                            ->collapsible()
                            ->collapsed(),
                        \Filament\Forms\Components\Section::make('Schema global del sitio')
                            ->description('Datos estructurados (JSON-LD) que se imprimen en TODAS las páginas del sitio, por idioma. Vacío por defecto: si no lo completas, no se emite nada adicional (el schema Organization/WebSite automático de siempre sigue funcionando igual, sin cambios). Aquí es donde se carga a mano el Organization/TravelAgency que hoy sale automático, cuando se decida reemplazarlo.')
                            ->icon('heroicon-o-globe-alt')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Textarea::make('schema_jsonld_global_es')
                                    ->label('JSON-LD global — Español')
                                    ->rows(6)
                                    ->columnSpanFull()
                                    ->helperText('Opcional. Se imprime en TODAS las páginas. Debe ser JSON válido — se valida antes de guardar.')
                                    ->rules([static::seoJsonLdRule()]),
                                Textarea::make('schema_jsonld_global_en')
                                    ->label('JSON-LD global — English')
                                    ->rows(6)
                                    ->columnSpanFull()
                                    ->helperText('Opcional. Si está vacío, cae al del español.')
                                    ->rules([static::seoJsonLdRule()]),
                                Textarea::make('schema_jsonld_global_pt')
                                    ->label('JSON-LD global — Português')
                                    ->rows(6)
                                    ->columnSpanFull()
                                    ->helperText('Opcional. Se vazio, usa o do espanhol.')
                                    ->rules([static::seoJsonLdRule()]),
                            ]),
                    ]),
                    Tabs\Tab::make('Home')->icon('heroicon-o-home')->schema([

                        // ── TODAS las imágenes del Home ──────────────────────
                        // Un solo lugar para cambiar cada imagen del home. Los
                        // FileUpload son de nivel superior (dentro de Repeater no
                        // persisten en esta página). Vacío = imagen por defecto.
                        // Nota: las fotos de las tarjetas de tours ("Más Comprados",
                        // "Ofertas", etc.) se editan en cada Tour → pestaña Imágenes.
                        \Filament\Forms\Components\Section::make('🖼️ Imágenes del Home')
                            ->description('Aquí cambias TODAS las imágenes editables del home: hero, destinos, tipos de tour y experiencias. Cada foto se optimiza a WebP automáticamente. Vacío = imagen por defecto.')
                            ->collapsible()
                            ->schema([
                                \Filament\Forms\Components\Fieldset::make('Hero (banner superior)')
                                    ->schema([
                                        FileUpload::make('home_hero_image')
                                            ->fetchFileInformation(false)
                                            ->label('Imagen de fondo del hero')
                                            ->image()->disk('media')->directory('home')
                                            ->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1920, disk: 'media', deletePrevious: true))
                                            ->helperText('Dejar vacío para usar la imagen por defecto (Machu Picchu). Máx. 1920px.')
                                            ->columnSpanFull(),
                                    ])->columns(1),
                                \Filament\Forms\Components\Fieldset::make('Ciudades más visitadas (Destinos)')
                                    ->schema([
                                        FileUpload::make('home_destino_img_1')->fetchFileInformation(false)->label('1 · Tours en Cusco')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_destino_img_2')->fetchFileInformation(false)->label('2 · Tours en Lima')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_destino_img_3')->fetchFileInformation(false)->label('3 · Tours en Ica')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                    ])->columns(3),
                                \Filament\Forms\Components\Fieldset::make('¿Qué tipo de tour estás buscando?')
                                    ->schema([
                                        FileUpload::make('home_tourtype_img_1')->fetchFileInformation(false)->label('1 · Tours Culturales')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_tourtype_img_2')->fetchFileInformation(false)->label('2 · Tours de Aventura')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_tourtype_img_3')->fetchFileInformation(false)->label('3 · Experiencias Culinarias')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_tourtype_img_4')->fetchFileInformation(false)->label('4 · Otras experiencias')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                    ])->columns(2),
                                \Filament\Forms\Components\Fieldset::make('Descubre experiencias únicas')
                                    ->schema([
                                        FileUpload::make('home_exp_img_1')->fetchFileInformation(false)->label('1 · Experiencia')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_exp_img_2')->fetchFileInformation(false)->label('2 · Experiencia')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_exp_img_3')->fetchFileInformation(false)->label('3 · Experiencia')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                        FileUpload::make('home_exp_img_4')->fetchFileInformation(false)->label('4 · Experiencia')->image()->disk('media')->directory('home')->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1400, disk: 'media', deletePrevious: true)),
                                    ])->columns(2),
                            ]),

                        // ── Hero (títulos) ────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Hero — títulos')
                            ->description('Título principal del banner superior (la imagen está arriba en "Imágenes del Home").')
                            ->collapsible()
                            ->schema([
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
                        \Filament\Forms\Components\Section::make('Cifras de confianza (4 indicadores)')
                            ->description('Los 4 números que salen debajo del banner del home. Escribe SOLO datos reales y verificables. Si dejas un valor VACÍO, ese indicador no se muestra en la web (no hay valores por defecto). Las etiquetas son únicas (sin idioma); si dejas una vacía se usa el texto estándar traducido.')
                            ->collapsible()
                            ->columns(2)
                            ->schema([
                                TextInput::make('stats_rating')
                                    ->label('Valoración — Valor')
                                    ->placeholder('Ej.: 4.9')
                                    ->maxLength(12)
                                    ->helperText('Nota promedio real (de Tripadvisor, Google, etc.). Vacío = no se muestra.'),
                                TextInput::make('home_stat_rating_label')
                                    ->label('Valoración — Etiqueta')
                                    ->placeholder('Valoración')
                                    ->maxLength(40),
                                TextInput::make('stats_rating_source_es')
                                    ->label('Valoración — Fuente (Español)')
                                    ->placeholder('Ej.: Tripadvisor')
                                    ->maxLength(60)
                                    ->helperText('De dónde sale la nota. Se muestra junto a la valoración ("según Tripadvisor"). Vacío = sin fuente.'),
                                TextInput::make('stats_rating_source_en')
                                    ->label('Valoración — Fuente (English)')
                                    ->maxLength(60)
                                    ->helperText('Si lo dejas vacío se usa la fuente en español.'),
                                TextInput::make('stats_rating_source_pt')
                                    ->label('Valoración — Fuente (Português)')
                                    ->maxLength(60)
                                    ->helperText('Si lo dejas vacío se usa la fuente en español.'),
                                \Filament\Forms\Components\Placeholder::make('stats_rating_source_spacer')->label('')->content(''),
                                TextInput::make('home_stat_travelers')
                                    ->label('Viajeros — Valor')
                                    ->placeholder('Ej.: +8.000')
                                    ->maxLength(20)
                                    ->helperText('Cantidad real de viajeros atendidos. Vacío = no se muestra.'),
                                TextInput::make('home_stat_travelers_label')
                                    ->label('Viajeros — Etiqueta')
                                    ->placeholder('Viajeros felices')
                                    ->maxLength(40),
                                TextInput::make('stats_years')
                                    ->label('Años de experiencia — Valor')
                                    ->placeholder('Ej.: +11')
                                    ->maxLength(20)
                                    ->helperText('Vacío = no se muestra.'),
                                TextInput::make('home_stat_years_label')
                                    ->label('Años de experiencia — Etiqueta')
                                    ->placeholder('Años de experiencia')
                                    ->maxLength(40),
                                TextInput::make('stats_tours')
                                    ->label('Tours — Valor')
                                    ->placeholder('Ej.: +50')
                                    ->maxLength(20)
                                    ->helperText('Vacío = no se muestra.'),
                                TextInput::make('home_stat_tours_label')
                                    ->label('Tours — Etiqueta')
                                    ->placeholder('Tours únicos')
                                    ->maxLength(40),
                            ]),

                        // ── Banda de confianza: cancelación ───────────────────
                        \Filament\Forms\Components\Section::make('Banda de confianza — Cancelación')
                            ->description('Primer indicador de la banda de confianza (ej. "Cancelación gratuita" / "Hasta 24 h antes"). Si lo dejas vacío en los tres idiomas se usa el "Resumen de cancelación" de Datos legales; si tampoco hay, el indicador no se muestra. Escribe SOLO lo que realmente ofreces.')
                            ->collapsible()
                            ->collapsed()
                            ->columns(2)
                            ->schema(array_merge(
                                ...array_map(static fn (string $l, string $n): array => [
                                    TextInput::make("home_trust_cancel_title_{$l}")
                                        ->label("Título corto ({$n})")
                                        ->placeholder($l === 'es' ? 'Cancelación gratuita' : null)
                                        ->maxLength(40)
                                        ->helperText($l === 'es' ? 'Vacío = se usa el resumen de Datos legales.' : 'Vacío = se usa el de español.'),
                                    TextInput::make("home_trust_cancel_text_{$l}")
                                        ->label("Detalle ({$n})")
                                        ->placeholder($l === 'es' ? 'Hasta 24 h antes' : null)
                                        ->maxLength(80)
                                        ->helperText('Opcional. Vacío = solo se muestra el título.'),
                                ], ['es', 'en', 'pt'], ['Español', 'English', 'Português'])
                            )),

                        // ── Banda de confianza: pago flexible ─────────────────
                        \Filament\Forms\Components\Section::make('Banda de confianza — Pago flexible')
                            ->description('Quinto indicador de la banda (ej. "Paga el 50% para separar tu cupo"). Sirve para avisar ANTES del pago que se puede pagar el 100%, el 50% para separar el cupo, o reservar y pagar después. Si dejas el título VACÍO, el indicador no se muestra. Activa esto solo si ofreces esas opciones de pago.')
                            ->collapsible()
                            ->collapsed()
                            ->columns(2)
                            ->schema(array_merge(
                                ...array_map(static fn (string $l, string $n): array => [
                                    TextInput::make("home_trust_pay_title_{$l}")
                                        ->label("Título corto ({$n})")
                                        ->placeholder($l === 'es' ? 'Paga el 50% para separar tu cupo' : null)
                                        ->maxLength(60)
                                        ->helperText($l === 'es' ? 'Vacío = el indicador no aparece en el Inicio.' : 'Vacío = se usa el de español.'),
                                    Textarea::make("home_trust_pay_detail_{$l}")
                                        ->label("Detalle de la ventana ({$n})")
                                        ->rows(3)
                                        ->maxLength(500)
                                        ->helperText($l === 'es' ? 'Texto que se abre al tocar el indicador. Vacío = se usa un texto estándar con las 3 opciones (pagar 100%, pagar 50% para separar, reservar y pagar después).' : 'Vacío = se usa el texto en español (o el estándar).'),
                                ], ['es', 'en', 'pt'], ['Español', 'English', 'Português'])
                            )),

                        // ── Banda de confianza: ventanas de detalle ───────────
                        \Filament\Forms\Components\Section::make('Banda de confianza — Ventanas de detalle')
                            ->description('Al tocar cada tarjeta de la banda de confianza (cancelación, años, viajeros, valoración) se abre una ventana con un texto explicativo. Escribe aquí ese texto. Si dejas un texto VACÍO, esa tarjeta no se puede tocar (no abre ventana). Escribe SOLO información real y verificable.')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Tabs::make('home_trust_detail_langs')->tabs(array_map(
                                    static fn (string $l, string $n): Tabs\Tab => Tabs\Tab::make($n)->schema(array_map(
                                        static fn (string $key, string $label): Textarea => Textarea::make("home_trust_detail_{$key}_{$l}")
                                            ->label("{$label} ({$n})")
                                            ->rows(3)
                                            ->maxLength(500)
                                            ->helperText($l === 'es'
                                                ? 'Vacío = la tarjeta de "'.$label.'" no abre ventana.'
                                                : 'Vacío = se usa el texto en español.'),
                                        ['cancel', 'years', 'travelers', 'rating'],
                                        ['Cancelación gratuita', 'Años de experiencia', 'Viajeros', 'Valoración'],
                                    )),
                                    ['es', 'en', 'pt'],
                                    ['Español', 'English', 'Português'],
                                )),
                            ]),
                        // ── Chips rápidos del buscador ────────────────────────
                        \Filament\Forms\Components\Section::make('Chips rápidos del buscador (hero)')
                            ->description('Botones bajo la caja de búsqueda del inicio (ej. "Medio día", "Historia"). Si no añades ninguno, el sitio genera solos los de duración y categorías que tengan tours. Un chip cuyo destino no tenga tours publicados no se muestra. Máximo 8.')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Repeater::make('home_search_chips')
                                    ->label('Chips del buscador')
                                    ->schema([
                                        TextInput::make('label_es')->label('Texto (Español)')->required()->maxLength(40),
                                        TextInput::make('label_en')->label('Texto (English)')->maxLength(40)->helperText('Vacío = se usa el de español.'),
                                        TextInput::make('label_pt')->label('Texto (Português)')->maxLength(40)->helperText('Vacío = se usa el de español.'),
                                        Select::make('type')
                                            ->label('Qué filtra')
                                            ->options(['duracion' => 'Duración', 'categoria' => 'Categoría', 'destino' => 'Destino (región)'])
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn (\Filament\Forms\Set $set) => $set('value', null)),
                                        Select::make('value')
                                            ->label('Valor')
                                            ->required()
                                            ->options(fn (\Filament\Forms\Get $get): array => match ($get('type')) {
                                                'duracion' => collect(\App\Support\TourSearch::DURATIONS)
                                                    ->mapWithKeys(fn (string $d) => [$d => \App\Support\TourSearch::durationLabel($d, 'es')])->all(),
                                                'categoria' => \App\Models\Category::active()->orderBy('order')->pluck('name_es', 'slug')->all(),
                                                'destino' => \App\Models\Region::active()->orderBy('order')->pluck('name_es', 'slug')->all(),
                                                default => [],
                                            }),
                                    ])
                                    ->columns(2)
                                    ->maxItems(8)
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->addActionLabel('Añadir chip')
                                    ->itemLabel(fn (array $state): ?string => $state['label_es'] ?? null)
                                    ->helperText('Para chips como "Historia" o "Arqueología" apúntalos a una categoría existente (créala antes en Categorías si no existe).'),
                            ]),

                        // ── Tour estrella ─────────────────────────────────────
                        \Filament\Forms\Components\Section::make('⭐ Tour estrella')
                            ->description('Bloque grande con foto de UN tour que quieres destacar en el home. Si no eliges ningún tour, el bloque NO se muestra. La foto, precio y duración salen del propio tour (edítalos en Tours).')
                            ->collapsible()
                            ->collapsed()
                            ->columns(1)
                            ->schema([
                                Select::make('home_star_tour_id')
                                    ->label('Tour a destacar')
                                    ->options(fn (): array => static::publishedTourOptions())
                                    ->searchable()
                                    ->placeholder('— Ninguno (el bloque no se muestra) —')
                                    ->helperText('Solo aparecen los tours publicados.'),
                                Tabs::make('home_star_langs')->tabs([
                                    Tabs\Tab::make('Español')->schema(static::starLangFields('es')),
                                    Tabs\Tab::make('English')->schema(static::starLangFields('en')),
                                    Tabs\Tab::make('Português')->schema(static::starLangFields('pt')),
                                ]),
                            ]),

                        // ── Comparador ────────────────────────────────────────
                        \Filament\Forms\Components\Section::make('⚖️ Comparador "¿X o Y?" (dentro de "Cómo elegir tu tour")')
                            ->description('Compara dos tours lado a lado (precio, duración, categoría, foto). Los datos salen de cada tour. Si no eliges los dos tours, el comparador NO se muestra.')
                            ->collapsible()
                            ->collapsed()
                            ->columns(2)
                            ->schema([
                                Select::make('home_compare_tour_a')
                                    ->label('Primer tour')
                                    ->options(fn (): array => static::publishedTourOptions())
                                    ->searchable()
                                    ->placeholder('— Ninguno —')
                                    ->different('home_compare_tour_b')
                                    ->validationMessages(['different' => 'Elige dos tours distintos.']),
                                Select::make('home_compare_tour_b')
                                    ->label('Segundo tour')
                                    ->options(fn (): array => static::publishedTourOptions())
                                    ->searchable()
                                    ->placeholder('— Ninguno —'),
                                TextInput::make('home_compare_intro_es')
                                    ->label('Frase de ayuda (Español)')
                                    ->maxLength(200)
                                    ->placeholder('Ej.: ¿No sabes cuál elegir? Compáralos.')
                                    ->helperText('Opcional. Va encima de las dos tarjetas.'),
                                TextInput::make('home_compare_intro_en')
                                    ->label('Frase de ayuda (English)')
                                    ->maxLength(200)
                                    ->helperText('Opcional. Vacío = se usa la de español.'),
                                TextInput::make('home_compare_intro_pt')
                                    ->label('Frase de ayuda (Português)')
                                    ->maxLength(200)
                                    ->helperText('Opcional. Vacío = se usa la de español.'),
                            ]),

// ── Puntuaciones por plataforma ───────────────────────
                        \Filament\Forms\Components\Section::make('Puntuación en cada plataforma')
                            ->description('Notas que salen en la franja "Recomendado y verificado en". Escribe SOLO la nota real que ves hoy en cada plataforma. Si dejas una vacía, esa plataforma muestra su logo sin nota.')
                            ->collapsible()
                            ->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_score_tripadvisor')->label('Tripadvisor')->placeholder('Ej.: 4.9')->maxLength(8)
                                    ->helperText('Vacío = sin nota.'),
                                TextInput::make('home_score_viator')->label('Viator')->placeholder('Ej.: 4.8')->maxLength(8)
                                    ->helperText('Vacío = sin nota.'),
                                TextInput::make('home_score_getyourguide')->label('GetYourGuide')->placeholder('Ej.: 4.9')->maxLength(8)
                                    ->helperText('Vacío = sin nota.'),
                            ]),

                        // ── CTA final "El Perú te espera" ─────────────────────
                        \Filament\Forms\Components\Section::make('Cierre del home: "El Perú te espera"')
                            ->description('Bloque final con el botón de reservar. Si dejas un texto vacío se usa el texto estándar (ya traducido a los 3 idiomas).')
                            ->collapsible()
                            ->collapsed()
                            ->columns(1)
                            ->schema(array_merge(
                                ...array_map(static fn (string $l, string $n): array => [
                                    TextInput::make("home_cta_eyebrow_{$l}")->label("Texto pequeño superior ({$n})")->maxLength(80)
                                        ->placeholder($l === 'es' ? 'Tu historia empieza aquí' : null)
                                        ->helperText('Frase corta encima del título.'),
                                    TextInput::make("home_cta_title_{$l}")->label("Título ({$n})")->maxLength(160)
                                        ->placeholder($l === 'es' ? 'El Perú te espera. Nosotros te guiamos.' : null)
                                        ->helperText('Para resaltar una parte en color escríbela así: <span class="lvt-accent">texto</span>. No se permite ningún otro código.')
                                        ->rules([static fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                            $rest = preg_replace('#</?span(?: class="lvt-accent")?>#i', '', (string) $value);
                                            if (str_contains((string) $rest, '<') || str_contains((string) $rest, '>')) {
                                                $fail('Solo se permite resaltar con <span class="lvt-accent">texto</span>. Quita cualquier otro código.');
                                            }
                                        }]),
                                    TextInput::make("home_cta_sub_{$l}")->label("Subtítulo ({$n})")->maxLength(200)
                                        ->helperText('Una frase que invita a reservar.'),
                                ], ['es', 'en', 'pt'], ['Español', 'English', 'Português'])
                            )),

                        // ── Sección "Más Comprados" ───────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Tours en Perú más vendidos"')
                            ->collapsible()
                            ->columns(2)
                            ->schema([
                                TextInput::make('home_sec_featured_eyebrow_es')->label('Eyebrow (ES)')->placeholder('NUESTROS TOURS'),
                                TextInput::make('home_sec_featured_eyebrow_en')->label('Eyebrow (EN)')->placeholder('OUR TOURS'),
                                TextInput::make('home_sec_featured_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('NOSSOS TOURS'),
                                TextInput::make('home_sec_featured_title_es')->label('Título (ES)')->placeholder('Tours en Perú más vendidos'),
                                TextInput::make('home_sec_featured_title_en')->label('Título (EN)')->placeholder('Best-selling tours in Peru'),
                                TextInput::make('home_sec_featured_title_pt')->label('Título (PT)')->placeholder('Tours no Peru mais vendidos'),
                            ]),

                        // ── Sección "Tours en Lima, Ica y Cusco" ─────────────
                        \Filament\Forms\Components\Section::make('Sección "Tours de un día desde Lima"')
                            ->description('Los tours se eligen en cada tour con el interruptor "Mostrar en Tours de un día desde Lima".')
                            ->collapsible()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_cities_title_es')->label('Título (ES)')->placeholder('Tours de un día desde Lima'),
                                TextInput::make('home_sec_cities_title_en')->label('Título (EN)')->placeholder('Day tours from Lima'),
                                TextInput::make('home_sec_cities_title_pt')->label('Título (PT)')->placeholder('Tours de um dia saindo de Lima'),
                            ]),

                        // ── Página Gracias ────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Página "Gracias" (post-contacto)')
                            ->collapsible()
                            ->schema([
                                FileUpload::make('gracias_banner_image')
                                    ->fetchFileInformation(false)
                                    ->label('Imagen de fondo (panel derecho)')
                                    ->image()
                                    ->disk('media')
                                    ->directory('home')
                                    ->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('home', 1920, disk: 'media', deletePrevious: true))
                                    ->helperText('Dejar vacío para usar Rectangle 19218.jpg por defecto. Se optimiza a WebP (máx. 1920px).')
                                    ->columnSpanFull(),
                                FileUpload::make('gracias_logo_image')
                                    ->fetchFileInformation(false)
                                    ->label('Logo sobre la imagen')
                                    ->image()
                                    ->disk('media')
                                    ->directory('home')
                                    // Hallazgo de seguridad #5 (2026-09-30): este campo no pasa por
                                    // ImageOptimizer::saver() (a propósito, para conservar el archivo
                                    // original), así que necesita su propio endurecimiento de nombre.
                                    ->getUploadedFileNameForStorageUsing(\App\Support\ImageOptimizer::safeImageNamer())
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
                                TextInput::make('home_sec_destinos_title_es')->label('Título (ES)')->placeholder('Tours por destino en Perú: Lima, Ica, Nazca y Cusco')->columnSpanFull(),
                                TextInput::make('home_sec_destinos_title_en')->label('Título (EN)')->placeholder('Tours by destination in Peru: Lima, Ica, Nazca and Cusco')->columnSpanFull(),
                                TextInput::make('home_sec_destinos_title_pt')->label('Título (PT)')->placeholder('Tours por destino no Peru: Lima, Ica, Nazca e Cusco')->columnSpanFull(),
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
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
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
                                        \Filament\Forms\Components\Placeholder::make('img_note')
                                            ->label('Imagen')
                                            ->content('La imagen de cada tarjeta se sube en la sección "Imágenes de las tarjetas del Home" (más abajo), por posición.')
                                            ->columnSpanFull(),
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
                                TextInput::make('home_sec_why_title_es')->label('Título (ES)')->placeholder('¿Por qué reservar tus tours en Perú con Lima View Tours?')->columnSpanFull(),
                                TextInput::make('home_sec_why_title_en')->label('Título (EN)')->placeholder('Why book your Peru tours with Lima View Tours?')->columnSpanFull(),
                                TextInput::make('home_sec_why_title_pt')->label('Título (PT)')->placeholder('Por que reservar seus tours no Peru com a Lima View Tours?')->columnSpanFull(),
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
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
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
                        \Filament\Forms\Components\Section::make('Sección "Tipos de tours en Perú"')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_types_eyebrow_es')->label('Eyebrow (ES)')->placeholder('CATEGORÍAS'),
                                TextInput::make('home_sec_types_eyebrow_en')->label('Eyebrow (EN)')->placeholder('CATEGORIES'),
                                TextInput::make('home_sec_types_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('CATEGORIAS'),
                                TextInput::make('home_sec_types_title_es')->label('Título (ES)')->placeholder('Tipos de tours en Perú')->columnSpanFull(),
                                TextInput::make('home_sec_types_title_en')->label('Título (EN)')->placeholder('Types of tours in Peru')->columnSpanFull(),
                                TextInput::make('home_sec_types_title_pt')->label('Título (PT)')->placeholder('Tipos de tours no Peru')->columnSpanFull(),
                            ]),

                        // ── Sección 5B: Cómo elegir tu tour en Perú ─────────────────
                        \Filament\Forms\Components\Section::make('Sección "Cómo elegir tu tour en Perú"')
                            ->description('Guía rápida que aparece debajo de "Tipos de tours". Dejar vacío para usar los textos por defecto.')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_choose_eyebrow_es')->label('Eyebrow (ES)')->placeholder('GUÍA RÁPIDA'),
                                TextInput::make('home_sec_choose_eyebrow_en')->label('Eyebrow (EN)')->placeholder('QUICK GUIDE'),
                                TextInput::make('home_sec_choose_eyebrow_pt')->label('Eyebrow (PT)')->placeholder('GUIA RÁPIDO'),
                                TextInput::make('home_sec_choose_title_es')->label('Título (ES)')->placeholder('Cómo elegir tu tour en Perú')->columnSpanFull(),
                                TextInput::make('home_sec_choose_title_en')->label('Título (EN)')->placeholder('How to choose your tour in Peru')->columnSpanFull(),
                                TextInput::make('home_sec_choose_title_pt')->label('Título (PT)')->placeholder('Como escolher seu tour no Peru')->columnSpanFull(),
                                Textarea::make('home_sec_choose_intro_es')->rows(2)->label('Introducción (ES)')->columnSpanFull(),
                                Textarea::make('home_sec_choose_intro_en')->rows(2)->label('Introducción (EN)')->columnSpanFull(),
                                Textarea::make('home_sec_choose_intro_pt')->rows(2)->label('Introducción (PT)')->columnSpanFull(),
                                Repeater::make('home_choose_items')
                                    ->label('Consejos (tarjetas)')
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
                                    ->schema([
                                        TextInput::make('title_es')->label('Título (ES)')->required(),
                                        TextInput::make('title_en')->label('Título (EN)'),
                                        TextInput::make('title_pt')->label('Título (PT)'),
                                        Textarea::make('desc_es')->label('Texto (ES)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_en')->label('Texto (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_pt')->label('Texto (PT)')->rows(2)->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title_es'] ?? null)
                                    ->addActionLabel('+ Agregar consejo')
                                    ->columnSpanFull(),
                            ]),

                        // ── Repeater: Tour Type Tabs ─────────────────────────────────
                        \Filament\Forms\Components\Section::make('Tabs de tipo de tour')
                            ->collapsible()->collapsed()
                            ->schema([
                                Repeater::make('home_tour_type_tabs')
                                    ->label('Tipos de tour')
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web. El ID de cada tipo no se debe cambiar.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
                                    ->schema([
                                        TextInput::make('id')->label('ID (único, sin espacios)')->placeholder('cult')->required(),
                                        TextInput::make('label_es')->label('Label tab (ES)')->required(),
                                        TextInput::make('label_en')->label('Label tab (EN)'),
                                        TextInput::make('label_pt')->label('Label tab (PT)'),
                                        TextInput::make('title_es')->label('Título panel (ES)'),
                                        TextInput::make('title_en')->label('Título panel (EN)'),
                                        TextInput::make('title_pt')->label('Título panel (PT)'),
                                        \Filament\Forms\Components\Placeholder::make('img_note')
                                            ->label('Imagen')
                                            ->content('La imagen se sube en la sección "Imágenes de las tarjetas del Home" (más abajo), por posición.')
                                            ->columnSpanFull(),
                                        TextInput::make('eyebrow_es')->label('Eyebrow (ES)'),
                                        TextInput::make('eyebrow_en')->label('Eyebrow (EN)'),
                                        TextInput::make('eyebrow_pt')->label('Eyebrow (PT)'),
                                        Textarea::make('desc_es')->label('Descripción (ES)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_en')->label('Descripción (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_pt')->label('Descripción (PT)')->rows(2)->columnSpanFull(),
                                        TextInput::make('price')->label('Precio desde (USD)')->numeric()->helperText('Opcional. Si lo dejas vacío no se muestra ningún precio en esa pestaña.'),
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
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
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
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
                                    ->schema([
                                        TextInput::make('title_es')->label('Título (ES)')->required(),
                                        TextInput::make('title_en')->label('Título (EN)'),
                                        TextInput::make('title_pt')->label('Título (PT)'),
                                        \Filament\Forms\Components\Placeholder::make('img_note')
                                            ->label('Imagen')
                                            ->content('La imagen se sube en la sección "Imágenes de las tarjetas del Home" (más abajo), por posición.')
                                            ->columnSpanFull(),
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
                                TextInput::make('home_sec_reviews_title_es')->label('Título (ES)')->placeholder('Opiniones de nuestros viajeros')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_title_en')->label('Título (EN)')->placeholder('Reviews from our travelers')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_title_pt')->label('Título (PT)')->placeholder('Opiniões dos nossos viajantes')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_subtitle_es')->label('Subtítulo (ES)')->placeholder('Miles de viajeros han vivido el Perú con nosotros.')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_subtitle_en')->label('Subtítulo (EN)')->placeholder('Thousands of travelers have experienced Peru with us.')->columnSpanFull(),
                                TextInput::make('home_sec_reviews_subtitle_pt')->label('Subtítulo (PT)')->placeholder('Milhares de viajantes viveram o Peru conosco.')->columnSpanFull(),
                            ]),

                        // ── Sección 7: FAQs ──────────────────────────────────────────
                        \Filament\Forms\Components\Section::make('Sección "Preguntas frecuentes" (home)')
                            ->collapsible()->collapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('home_sec_faq_title_es')->label('Título (ES)')->placeholder('Preguntas frecuentes sobre tours en Perú')->columnSpanFull(),
                                TextInput::make('home_sec_faq_title_en')->label('Título (EN)')->placeholder('Frequently asked questions about tours in Peru')->columnSpanFull(),
                                TextInput::make('home_sec_faq_title_pt')->label('Título (PT)')->placeholder('Perguntas frequentes sobre tours no Peru')->columnSpanFull(),
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
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web. Son distintas a las FAQs del tab AEO/FAQ.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
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
                                TextInput::make('home_reco_headline_es')->label('Frase destacada (ES) — ya no es encabezado H2')->placeholder('Best Tours in Lima')->columnSpanFull(),
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
                                    ->helperText('Estos son los textos que se ven hoy en la web. Edítalos aquí; si borras un elemento, desaparece de la web.')
                                    ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->requiresConfirmation()->modalHeading('¿Borrar este elemento?')->modalDescription('Desaparecerá de la web cuando pulses «Guardar».')->modalSubmitActionLabel('Sí, borrar'))
                                    ->schema([
                                        TextInput::make('title_es')->label('Título (ES)')->required(),
                                        TextInput::make('title_en')->label('Título (EN)'),
                                        TextInput::make('title_pt')->label('Título (PT)'),
                                        Textarea::make('desc_es')->label('Descripción (ES)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_en')->label('Descripción (EN)')->rows(2)->columnSpanFull(),
                                        Textarea::make('desc_pt')->label('Descripción (PT)')->rows(2)->columnSpanFull(),
                                        Select::make('icon')
                                            ->label('Icono')
                                            ->options(['shield' => 'Escudo (shield)', 'star' => 'Estrella (star)', 'headset' => 'Auriculares (headset)', 'medal' => 'Medalla (medal)'])
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

                        \Filament\Forms\Components\Section::make('Sello "Recomendado en Tripadvisor"')
                            ->description('Dato manual mientras la API Terra de Tripadvisor no tiene key aprobada. Si dejas el % vacío, el sello NO se muestra en el sitio — no hay ningún valor por defecto.')
                            ->collapsible()
                            ->schema([
                                TextInput::make('tripadvisor_recommend_percent')
                                    ->label('% que recomienda (0-100)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->helperText('Vacío = el sello no se pinta. Ej: 96.'),
                                TextInput::make('tripadvisor_reviews_count')
                                    ->label('Nº de reseñas consideradas')
                                    ->numeric()
                                    ->minValue(0),
                                DatePicker::make('tripadvisor_stats_updated_at')
                                    ->label('Fecha del dato')
                                    ->native(false)
                                    ->helperText('Para poder mostrar "actualizado en <fecha>" junto al sello.'),
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
                                                'all' => 'Todas las ubicaciones',
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
                ]),
            ])
            ->statePath('data');
    }

    /** Keys whose values are stored as boolean type in settings. */
    private const BOOLEAN_KEYS = [
        'google_reviews_enabled',
        'tripadvisor_reviews_enabled',
        'recaptcha_enabled',
        'pickup_enabled',
    ];

    public function save(): void
    {
        // getState() deshidrata el formulario: procesa los FileUpload (mueve los
        // archivos temporales al disco configurado y devuelve la RUTA), también
        // los anidados dentro de Repeaters. Iterar $this->data crudo dejaba el
        // estado de FilePond sin resolver ({uuid:{}}) y no guardaba la imagen.
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            // Serialize Repeater fields as JSON string
            $jsonRepeaterKeys = ['faqs', 'home_destinos', 'home_why_items', 'home_tour_type_tabs',
                'home_footer_features', 'home_exp_tours', 'home_reco_items', 'home_faqs',
                'home_choose_items', 'pickup_zones', 'home_search_chips',
                'home_star_milestones_es', 'home_star_milestones_en', 'home_star_milestones_pt',
                'home_star_chips_es', 'home_star_chips_en', 'home_star_chips_pt'];
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

    /**
     * Tours publicados para los Select del home (id => título). Se evalúa al
     * abrir el formulario, no al cargar la clase.
     *
     * @return array<int, string>
     */
    protected static function publishedTourOptions(): array
    {
        return \App\Models\Tour::published()->orderBy('title_es')->pluck('title_es', 'id')->all();
    }

    /** Campos por idioma del bloque "Tour estrella". */
    protected static function starLangFields(string $l): array
    {
        $name = ['es' => 'Español', 'en' => 'English', 'pt' => 'Português'][$l];

        return [
            TextInput::make("home_star_badge_{$l}")
                ->label("Etiqueta sobre la foto ({$name})")
                ->maxLength(60)
                ->placeholder($l === 'es' ? 'EL TOUR MÁS VENDIDO EN LIMA' : null)
                ->helperText($l === 'es'
                    ? 'Píldora que aparece encima de la foto. Escribe SOLO lo que sea verdad. Vacío = no se muestra.'
                    : 'Vacío = se usa la de español.'),
            TextInput::make("home_star_headline_{$l}")
                ->label("Frase grande sobre la foto ({$name})")
                ->maxLength(80)
                ->placeholder($l === 'es' ? 'Aventura en un solo día.' : null)
                ->helperText($l === 'es'
                    ? 'La frase grande que va escrita sobre la foto. Vacío = no se muestra.'
                    : 'Vacío = se usa la de español.'),
            TextInput::make("home_star_subtitle_{$l}")
                ->label("Línea bajo la frase ({$name})")
                ->maxLength(90)
                ->placeholder($l === 'es' ? 'Océano · dunas · buggy · oasis' : null)
                ->helperText($l === 'es'
                    ? 'Línea corta debajo de la frase grande. Vacío = no se muestra.'
                    : 'Vacío = se usa la de español.'),
            Repeater::make("home_star_chips_{$l}")
                ->label("Etiquetas sobre la foto ({$name}) — máximo 4")
                ->schema([
                    TextInput::make('text')->label('Etiqueta')->required()->maxLength(30)
                        ->placeholder('Ej.: BUGGY'),
                ])
                ->maxItems(4)
                ->defaultItems(0)
                ->reorderable()
                ->addActionLabel('Añadir etiqueta')
                ->helperText($l === 'es'
                    ? 'Palabras cortas que salen como botones blancos sobre la foto (BUGGY, MIRADOR, OASIS, MAR). Sin etiquetas = no se muestran.'
                    : 'Si no escribes etiquetas en este idioma se usan las de español.'),
            TextInput::make("home_star_question_{$l}")
                ->label("Pregunta del texto ({$name})")
                ->maxLength(120)
                ->placeholder($l === 'es' ? '¿Por qué es el tour más vendido en Lima?' : null)
                ->helperText($l === 'es'
                    ? 'Título pequeño encima del párrafo (el párrafo es la "Bajada"). Vacío = no se muestra.'
                    : 'Vacío = se usa la de español.'),
            TextInput::make("home_star_title_{$l}")
                ->label("Titular corto ({$name})")
                ->maxLength(90)
                ->helperText($l === 'es'
                    ? 'Ej.: "Un día entre el desierto y el mar". Si lo dejas vacío se usa el nombre del tour.'
                    : 'Vacío = se usa el titular en español.'),
            Textarea::make("home_star_lead_{$l}")
                ->label("Bajada ({$name})")
                ->rows(2)
                ->maxLength(220)
                ->helperText($l === 'es'
                    ? 'Una o dos frases que venden la experiencia. Vacío = no se muestra.'
                    : 'Vacío = se usa la bajada en español.'),
            Repeater::make("home_star_milestones_{$l}")
                ->label("Hitos con título y detalle ({$name}) — máximo 4")
                ->schema([
                    TextInput::make('text')->label('Título del hito')->required()->maxLength(80)
                        ->placeholder('Ej.: Buggies'),
                    TextInput::make('detail')->label('Detalle (opcional)')->maxLength(120)
                        ->placeholder('Ej.: Adrenalina en dunas')
                        ->helperText('Frase corta bajo el título. Vacío = el hito muestra solo el título.'),
                ])
                ->maxItems(4)
                ->defaultItems(0)
                ->reorderable()
                ->addActionLabel('Añadir hito')
                ->helperText($l === 'es'
                    ? 'Lo más atractivo del tour en frases cortas. Sin hitos = no se muestra la lista.'
                    : 'Si no escribes hitos en este idioma se usan los de español.'),
        ];
    }
    /**
     * Bloques "Metas por página" del tab SEO: una sección plegable por página
     * de sistema (home, catálogo, catálogo por región, listado del blog) con
     * meta title y meta description en los tres idiomas.
     *
     * Las claves las arma \App\Support\PageSeo para que el admin y el front
     * lean exactamente el mismo nombre de setting; no escribir el patrón
     * "seo_page_..." a mano en ningún otro lado.
     */
    protected static function seoPageFields(): array
    {
        $sections = [];

        foreach (PageSeo::PAGES as $page => $pageLabel) {
            $fields = [];

            foreach (PageSeo::LOCALES as $locale => $localeLabel) {
                $titleKey = PageSeo::settingKey($page, 'title', $locale);
                $descKey = PageSeo::settingKey($page, 'description', $locale);

                $fields[] = TextInput::make($titleKey)
                    ->label("Meta title — {$localeLabel}")
                    ->maxLength(70)
                    ->live(onBlur: true)
                    ->helperText(fn (\Filament\Forms\Get $get): string => static::seoCharHelper($get($titleKey), 50, 60));

                $fields[] = Textarea::make($descKey)
                    ->label("Meta description — {$localeLabel}")
                    ->rows(2)
                    ->maxLength(160)
                    ->live(onBlur: true)
                    ->helperText(fn (\Filament\Forms\Get $get): string => static::seoCharHelper($get($descKey), 150, 160));

                $schemaKey = PageSeo::settingKey($page, 'schema', $locale);

                $fields[] = Textarea::make($schemaKey)
                    ->label("Datos estructurados (JSON-LD) — {$localeLabel}")
                    ->rows(6)
                    ->columnSpanFull()
                    ->helperText('Opcional. Si lo completas, se imprime en el <head> de esta página en este idioma. Si el idioma actual está vacío, cae al español. Debe ser JSON válido — se valida antes de guardar.')
                    ->rules([static::seoJsonLdRule()]);
            }

            $sections[] = \Filament\Forms\Components\Section::make($pageLabel)
                ->schema($fields)
                ->columns(2)
                ->collapsible()
                ->collapsed();
        }

        return $sections;
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
