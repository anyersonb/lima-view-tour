<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Mail\AbandonedCartReminder;
use App\Models\AbandonedCart;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AbandonedCartResource extends Resource
{
    protected static ?string $model = AbandonedCart::class;

    protected static ?string $navigationIcon  = 'heroicon-o-archive-box-x-mark';
    protected static ?string $navigationGroup  = 'Marketing';
    protected static ?string $navigationLabel  = 'Carritos abandonados';
    protected static ?string $modelLabel       = 'Carrito abandonado';
    protected static ?string $pluralModelLabel = 'Carritos abandonados';
    protected static ?int $navigationSort       = 9;

    public static function getNavigationBadge(): ?string
    {
        // Defensa: si la tabla aún no existe (migración pendiente en prod)
        // no romper el panel entero con la consulta del badge.
        if (! Schema::hasTable('abandoned_carts')) {
            return null;
        }

        return static::getModel()::where('status', 'active')
            ->whereNotNull('email')
            ->count() ?: null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Schema::hasTable('abandoned_carts');
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->description(fn (AbandonedCart $r) => $r->name),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Ítems')
                    ->badge(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active'    => 'Activo',
                        'converted' => 'Convertido',
                        'expired'   => 'Caducado',
                        default     => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'converted' => 'success',
                        'expired'   => 'gray',
                        default     => 'warning',
                    }),
                Tables\Columns\TextColumn::make('reminders_sent')
                    ->label('Recordatorios')
                    ->badge(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Idioma')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Última actividad')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_reminder_at')
                    ->label('Último recordatorio')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active'    => 'Activo',
                        'converted' => 'Convertido',
                        'expired'   => 'Caducado',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label('Reenviar recordatorio')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AbandonedCart $r) => $r->isRecoverable())
                    ->action(function (AbandonedCart $r) {
                        $next = min($r->reminders_sent + 1, 2);
                        Mail::to($r->email)->send(new AbandonedCartReminder($r, $next));
                        $r->forceFill([
                            'reminders_sent'   => $next,
                            'last_reminder_at' => now(),
                        ])->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Recordatorio enviado a ' . $r->email)
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbandonedCarts::route('/'),
        ];
    }
}
