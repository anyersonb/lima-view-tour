<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PaymentLinkResource;
use App\Filament\Resources\PaymentLinkResource\Pages\CreatePaymentLink;
use App\Filament\Resources\PaymentLinkResource\Pages\EditPaymentLink;
use App\Filament\Resources\PaymentLinkResource\Pages\ListPaymentLinks;
use App\Models\PaymentLink;
use App\Models\Tour;
use App\Models\User;
use Filament\Tables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke test del recurso Filament "Links de pago": confirma que el panel
 * arranca sin error fatal (list/create/tabla/acciones) y que crear un link
 * desde el formulario genera un código único y un estado 'pending' — más
 * allá de lo que php -l puede detectar (los errores de Filament suelen ser
 * en tiempo de ejecución: relaciones mal referenciadas, columnas
 * inexistentes, etc.).
 */
class PaymentLinkResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_list_page_renders_and_shows_records(): void
    {
        $this->actingAs($this->admin());

        $link = PaymentLink::factory()->create(['tour_id' => Tour::factory()->create()->id]);

        Livewire::test(ListPaymentLinks::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$link]);
    }

    public function test_status_filter_shows_only_selected_statuses(): void
    {
        $this->actingAs($this->admin());

        $pending = PaymentLink::factory()->create();
        $paid = PaymentLink::factory()->paid()->create();

        Livewire::test(ListPaymentLinks::class)
            ->filterTable('status', ['paid'])
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_create_form_generates_unique_code_and_pending_status(): void
    {
        $this->actingAs($this->admin());

        $tour = Tour::factory()->create(['price' => 120]);

        Livewire::test(CreatePaymentLink::class)
            ->fillForm([
                'tour_id' => $tour->id,
                'adults' => 2,
                'children' => 1,
                'amount' => 360.00,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $link = PaymentLink::firstOrFail();

        $this->assertSame('pending', $link->status);
        $this->assertSame($tour->id, $link->tour_id);
        $this->assertGreaterThanOrEqual(32, strlen($link->code));
        $this->assertNotNull($link->created_by);
    }

    public function test_cancel_action_marks_link_cancelled(): void
    {
        $this->actingAs($this->admin());

        $link = PaymentLink::factory()->create();

        Livewire::test(ListPaymentLinks::class)
            ->callTableAction('cancel', $link);

        $this->assertSame('cancelled', $link->refresh()->status);
    }

    public function test_duplicate_action_creates_a_new_pending_link(): void
    {
        $this->actingAs($this->admin());

        $tour = Tour::factory()->create();
        $original = PaymentLink::factory()->paid()->create([
            'tour_id' => $tour->id,
            'amount' => 200,
            'paypal_capture_id' => 'CAP-ORIGINAL',
            'customer_email' => 'prefill@example.com',
            'buyer_email' => 'quien-pago-de-verdad@example.com',
        ]);

        // mountTableAction(), no callTableAction(): "duplicate" no tiene
        // formulario ni requiresConfirmation(), así que Filament la ejecuta
        // directamente al montarla (sin modal) — igual que un solo clic real
        // en el navegador. callTableAction() encadena mount+call y la
        // corre DOS veces para acciones sin modal (confirmado corriendo el
        // conteo con un Log::info temporal dentro de la action).
        Livewire::test(ListPaymentLinks::class)
            ->mountTableAction('duplicate', $original);

        $this->assertSame(2, PaymentLink::count());

        $copy = PaymentLink::where('id', '!=', $original->id)->firstOrFail();
        $this->assertSame('pending', $copy->status);
        $this->assertNotSame($original->code, $copy->code);
        $this->assertNull($copy->paypal_capture_id);
        $this->assertSame((string) $original->amount, (string) $copy->amount);

        // A-1: duplicar NUNCA copia datos de contacto — ni el prefill del
        // admin ni (sobre todo) quién pagó de verdad el link original.
        $this->assertNull($copy->customer_email);
        $this->assertNull($copy->buyer_email);
    }

    // ─────────────────────────────────────────────────────────────
    //  Item 5 — badge del menú sin guarda
    // ─────────────────────────────────────────────────────────────

    public function test_navigation_badge_does_not_crash_when_table_is_missing(): void
    {
        // Prueba REAL, no simulada: se renombra la tabla, se llama al
        // método directamente (igual que lo pinta CADA página del panel vía
        // el sidebar) y se restaura — sin el guardián, esto lanza
        // QueryException y tumba TODO /admin.
        Schema::rename('payment_links', 'payment_links_test_tmp');

        try {
            $badge = PaymentLinkResource::getNavigationBadge();
            $this->assertNull($badge);
        } finally {
            Schema::rename('payment_links_test_tmp', 'payment_links');
        }
    }

    public function test_navigation_badge_counts_only_pending_links(): void
    {
        PaymentLink::factory()->count(2)->create();
        PaymentLink::factory()->paid()->create();

        $this->assertSame('2', PaymentLinkResource::getNavigationBadge());
    }

    // ─────────────────────────────────────────────────────────────
    //  Item 13 (B-5) — un link pagado no se borra
    // ─────────────────────────────────────────────────────────────

    public function test_paid_link_cannot_be_deleted_individually(): void
    {
        $this->actingAs($this->admin());

        $paid = PaymentLink::factory()->paid()->create();

        // El guardián real vive en el modelo (PaymentLink::booted()), así
        // que se prueba directo ahí: ->delete() debe ser un no-op.
        $result = $paid->delete();

        $this->assertFalse((bool) $result);
        $this->assertDatabaseHas('payment_links', ['id' => $paid->id]);
    }

    public function test_a_refunded_link_with_a_capture_id_cannot_be_deleted(): void
    {
        // B-5 PARCIAL en la re-auditoría: el guardián original solo miraba
        // status==='paid' — un link YA reembolsado (que cambió de status
        // pero SIGUE teniendo un cobro real registrado) se podía borrar sin
        // problema, perdiendo el rastro del dinero.
        $this->actingAs($this->admin());

        $refunded = PaymentLink::factory()->create([
            'status' => 'refunded',
            'paypal_capture_id' => 'CAP-REFUNDED',
        ]);

        $result = $refunded->delete();

        $this->assertFalse((bool) $result);
        $this->assertDatabaseHas('payment_links', ['id' => $refunded->id]);
    }

    public function test_paid_link_survives_a_bulk_delete_while_others_are_removed(): void
    {
        $this->actingAs($this->admin());

        // El link PAGADO va primero a propósito: Collection::each() (lo que
        // usa DeleteBulkAction por defecto) ABORTA el resto del foreach en
        // cuanto un callback devuelve `false` — como el guardián de
        // PaymentLink::booted() devuelve exactamente eso para bloquear un
        // borrado, sin el ->using() de PaymentLinkResource (foreach normal
        // en vez de ->each()) el link pendiente de ESTE MISMO lote quedaría
        // sin borrar, no por protegerlo, sino por un efecto secundario del
        // helper de colección.
        $paid = PaymentLink::factory()->paid()->create();
        $pending = PaymentLink::factory()->create();

        Livewire::test(ListPaymentLinks::class)
            ->callTableBulkAction('delete', [$paid->getKey(), $pending->getKey()]);

        $this->assertDatabaseHas('payment_links', ['id' => $paid->id]);
        $this->assertDatabaseMissing('payment_links', ['id' => $pending->id]);
    }

    // ─────────────────────────────────────────────────────────────
    //  UX colateral — la notificación del borrado en lote cuenta, no dice
    //  "Eliminado" a secas cuando el lote quedó incompleto
    // ─────────────────────────────────────────────────────────────

    public function test_bulk_delete_notification_reports_how_many_were_deleted_and_how_many_were_protected(): void
    {
        $this->actingAs($this->admin());

        $paid = PaymentLink::factory()->paid()->create();
        $pending = PaymentLink::factory()->create();

        Livewire::test(ListPaymentLinks::class)
            ->callTableBulkAction('delete', [$paid->getKey(), $pending->getKey()])
            ->assertNotified(PaymentLinkResource::bulkDeleteNotificationTitle(1, 1));
    }

    public function test_bulk_delete_notification_when_nothing_is_protected_does_not_mention_protected_links(): void
    {
        $this->actingAs($this->admin());

        $first = PaymentLink::factory()->create();
        $second = PaymentLink::factory()->create();

        Livewire::test(ListPaymentLinks::class)
            ->callTableBulkAction('delete', [$first->getKey(), $second->getKey()])
            ->assertNotified(PaymentLinkResource::bulkDeleteNotificationTitle(2, 0));

        $this->assertDatabaseMissing('payment_links', ['id' => $first->id]);
        $this->assertDatabaseMissing('payment_links', ['id' => $second->id]);
    }

    public function test_delete_action_is_hidden_on_edit_page_for_a_paid_link(): void
    {
        $this->actingAs($this->admin());

        $paid = PaymentLink::factory()->paid()->create();

        Livewire::test(EditPaymentLink::class, ['record' => $paid->getRouteKey()])
            ->assertActionHidden('delete');
    }

    // ─────────────────────────────────────────────────────────────
    //  FIX-3 (docs/payment-links/FIX-2.md) — la celda "Enlace" solo copia,
    //  nunca navega a Editar
    // ─────────────────────────────────────────────────────────────

    public function test_url_column_has_no_link_and_click_disabled(): void
    {
        // Prueba REAL sobre el objeto Column que Filament usa para decidir
        // si envuelve la celda en un <a> (ver
        // vendor/filament/tables/resources/views/components/columns/column.blade.php:51,
        // gateado por "(! $isClickDisabled)"): si alguien vuelve a agregar
        // ->url(...) a esta columna, o le quita ->disabledClick(), este
        // test falla porque el bug real era exactamente esa combinación
        // (sin url propia + recordUrl del row heredado sin bloquear).
        //
        // isClickDisabled() de una columna copyable evalúa el estado de la
        // celda (HasCellState::getState()), que exige un $record enlazado
        // — por eso se usa el helper oficial assertTableColumnExists(),
        // que hace $column->record($record) antes de invocar el callback,
        // en vez de tomar la columna directo de getTable()->getColumn().
        $this->actingAs($this->admin());

        $link = PaymentLink::factory()->create();

        Livewire::test(ListPaymentLinks::class)
            ->assertTableColumnExists(
                'url',
                fn (Tables\Columns\Column $column): bool => $column->getUrl() === null && $column->isClickDisabled(),
                $link,
            );
    }
}
