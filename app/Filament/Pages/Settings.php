<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->data as $key => $value) {
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
