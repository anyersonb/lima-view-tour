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

        // Pre-rellenar reCAPTCHA desde .env si aún no están en la BD
        $rows['recaptcha_enabled']  = isset($rows['recaptcha_enabled'])
            ? filter_var($rows['recaptcha_enabled'], FILTER_VALIDATE_BOOLEAN)
            : (bool) config('services.recaptcha.enabled', false);
        $rows['recaptcha_version']     = $rows['recaptcha_version']     ?? config('services.recaptcha.version', 'v3');
        $rows['recaptcha_site_key']    = $rows['recaptcha_site_key']    ?? config('services.recaptcha.site_key');
        $rows['recaptcha_secret_key']  = $rows['recaptcha_secret_key']  ?? config('services.recaptcha.secret_key');
        $rows['recaptcha_v3_threshold']= $rows['recaptcha_v3_threshold']?? config('services.recaptcha.threshold', 0.5);

        // Decode FAQs JSON for the Repeater
        if (isset($rows['faqs']) && is_string($rows['faqs'])) {
            $decoded = json_decode($rows['faqs'], true);
            $rows['faqs'] = is_array($decoded) ? $decoded : [];
        } else {
            $rows['faqs'] = [];
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
                    ]),
                    Tabs\Tab::make('Redes sociales')->icon('heroicon-o-share')->schema([
                        TextInput::make('social_instagram')->prefix('https://')->label('Instagram'),
                        TextInput::make('social_facebook')->prefix('https://')->label('Facebook'),
                        TextInput::make('social_tiktok')->prefix('https://')->label('TikTok'),
                        TextInput::make('social_youtube')->prefix('https://')->label('YouTube'),
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
                        FileUpload::make('seo_og_image')->image()->disk('public')->directory('seo')->label('OG Image por defecto'),
                        TextInput::make('seo_google_site_verification')->label('Google Search Console'),
                        TextInput::make('seo_bing_site_verification')->label('Bing Webmaster'),
                        TextInput::make('seo_google_analytics_id')->placeholder('G-XXXXXX')->label('Google Analytics 4'),
                        TextInput::make('seo_gtm_id')->placeholder('GTM-XXXXXX')->label('Google Tag Manager'),
                        TextInput::make('seo_facebook_pixel')->label('Facebook Pixel ID'),
                    ]),
                    Tabs\Tab::make('Home')->icon('heroicon-o-home')->schema([
                        TextInput::make('hero_title_es')->label('Hero título (ES)'),
                        TextInput::make('hero_title_en')->label('Hero título (EN)'),
                        Textarea::make('hero_subtitle_es')->rows(2)->label('Hero subtítulo (ES)'),
                        FileUpload::make('hero_image')->image()->disk('public')->directory('home')->label('Hero imagen'),
                        TextInput::make('stats_travelers')->label('Stat: Viajeros'),
                        TextInput::make('stats_years')->label('Stat: Años'),
                        TextInput::make('stats_rating')->label('Stat: Rating'),
                        TextInput::make('stats_tours')->label('Stat: Tours'),
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
    ];

    public function save(): void
    {
        foreach ($this->data as $key => $value) {
            // Serialize FAQs repeater as JSON string
            if ($key === 'faqs') {
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
