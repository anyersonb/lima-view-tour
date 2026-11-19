<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplaintResource\Pages;
use App\Models\Complaint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Libro de Reclamaciones virtual (INDECOPI). Es un REGISTRO LEGAL: no se crea
 * ni se borra desde el panel; solo se revisa la hoja y se registra la
 * respuesta del proveedor (plazo legal: 15 días hábiles).
 */
class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Reservas';

    protected static ?string $navigationLabel = 'Libro de Reclamaciones';

    protected static ?string $modelLabel = 'Reclamación';

    protected static ?string $pluralModelLabel = 'Libro de Reclamaciones';

    protected static ?int $navigationSort = 20;

    protected const STATUSES = [
        Complaint::STATUS_PENDING => 'Pendiente',
        Complaint::STATUS_IN_PROGRESS => 'En proceso',
        Complaint::STATUS_ANSWERED => 'Respondido',
    ];

    protected const TYPES = [
        Complaint::TYPE_CLAIM => 'Reclamo',
        Complaint::TYPE_COMPLAINT => 'Queja',
    ];

    protected const DOCS = ['dni' => 'DNI', 'ce' => 'Carné de extranjería', 'passport' => 'Pasaporte'];

    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::where('status', Complaint::STATUS_PENDING)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    // Registro legal: sin alta manual ni borrado.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        // Los datos de la hoja son de solo lectura (disabled + no se guardan).
        $ro = fn (Forms\Components\Field $f) => $f->disabled()->dehydrated(false);

        return $form->schema([
            Forms\Components\Section::make('Hoja de reclamación')
                ->description('Datos enviados por el consumidor. No se pueden modificar: es un registro legal.')
                ->schema([
                    $ro(Forms\Components\TextInput::make('code')->label('N.° de hoja')),
                    $ro(Forms\Components\TextInput::make('created_at')->label('Fecha de registro')),
                    $ro(Forms\Components\Select::make('type')->label('Tipo')->options(self::TYPES)),
                    $ro(Forms\Components\TextInput::make('response_due_at')->label('Fecha límite de respuesta (15 días hábiles)')),
                    $ro(Forms\Components\TextInput::make('first_name')->label('Nombres')),
                    $ro(Forms\Components\TextInput::make('last_name')->label('Apellidos')),
                    $ro(Forms\Components\Select::make('doc_type')->label('Tipo de documento')->options(self::DOCS)),
                    $ro(Forms\Components\TextInput::make('doc_number')->label('N.° de documento')),
                    $ro(Forms\Components\TextInput::make('address')->label('Domicilio')),
                    $ro(Forms\Components\TextInput::make('phone')->label('Teléfono')),
                    $ro(Forms\Components\TextInput::make('email')->label('Correo electrónico')),
                    $ro(Forms\Components\Toggle::make('is_minor')->label('Es menor de edad')),
                    $ro(Forms\Components\TextInput::make('guardian_name')->label('Padre, madre o apoderado')),
                    $ro(Forms\Components\Select::make('good_type')->label('Bien contratado')->options(['product' => 'Producto', 'service' => 'Servicio'])),
                    $ro(Forms\Components\TextInput::make('booking_reference')->label('Código de reserva')),
                    $ro(Forms\Components\TextInput::make('claimed_amount')->label('Monto reclamado')),
                    $ro(Forms\Components\TextInput::make('currency')->label('Moneda')),
                    $ro(Forms\Components\TextInput::make('locale')->label('Idioma')),
                    $ro(Forms\Components\Textarea::make('good_description')->label('Descripción del producto o servicio')->rows(2)->columnSpanFull()),
                    $ro(Forms\Components\Textarea::make('detail')->label('Detalle')->rows(5)->columnSpanFull()),
                    $ro(Forms\Components\Textarea::make('consumer_request')->label('Pedido del consumidor')->rows(3)->columnSpanFull()),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Respuesta del proveedor')
                ->description('Escribe la respuesta y cambia el estado a "Respondido" cuando la envíes al consumidor (por correo o teléfono). Plazo legal: 15 días hábiles.')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options(self::STATUSES)
                        ->required()
                        ->native(false),
                    Forms\Components\DateTimePicker::make('responded_at')
                        ->label('Fecha de respuesta')
                        ->helperText('Si lo dejas vacío y marcas "Respondido", se pone la fecha de hoy.'),
                    Forms\Components\Textarea::make('response')
                        ->label('Respuesta al consumidor')
                        ->rows(6)
                        ->maxLength(10000)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('N.° de hoja')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Tipo')->badge()
                    ->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === Complaint::TYPE_COMPLAINT ? 'warning' : 'danger'),
                Tables\Columns\TextColumn::make('first_name')->label('Consumidor')
                    ->formatStateUsing(fn ($state, Complaint $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')->label('Correo')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('booking_reference')->label('Reserva')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Complaint::STATUS_ANSWERED => 'success',
                        Complaint::STATUS_IN_PROGRESS => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('response_due_at')->label('Responder antes del')->date('d/m/Y')->sortable()
                    ->color(fn (Complaint $record) => $record->status !== Complaint::STATUS_ANSWERED && $record->response_due_at?->isPast() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Estado')->options(self::STATUSES),
                Tables\Filters\SelectFilter::make('type')->label('Tipo')->options(self::TYPES),
                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidos sin responder')
                    ->query(fn (Builder $q) => $q->where('status', '!=', Complaint::STATUS_ANSWERED)->whereDate('response_due_at', '<', now())),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Ver y responder'),
            ])
            // Sin acciones masivas: nada se borra.
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplaints::route('/'),
            'edit' => Pages\EditComplaint::route('/{record}/edit'),
        ];
    }
}
