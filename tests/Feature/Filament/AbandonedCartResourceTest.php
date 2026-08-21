<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AbandonedCartResource\Pages\ListAbandonedCarts;
use App\Filament\Resources\AbandonedCartResource\Pages\ViewAbandonedCart;
use App\Models\AbandonedCart;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lote "carritos abandonados con toda la info de contacto" (2026-08-19).
 *
 * Antes de este cambio el listado solo mostraba email/items/total/estado/
 * recordatorios/idioma/fechas: `name` y `phone` se capturaban en el checkout
 * pero nunca se pintaban, y no existía página de detalle (no se podía ver
 * qué tours tenía el carrito). Estos tests ejercitan el panel Livewire real
 * (no el modelo aislado) y deben FALLAR si se revierte el cambio.
 */
class AbandonedCartResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_the_list_shows_name_and_a_working_whatsapp_link_normalized_for_peru(): void
    {
        $this->actingAs($this->admin());

        $cart = AbandonedCart::create([
            'session_id' => 'sess-1',
            'email' => 'cliente@example.com',
            'name' => 'Rosa Martínez',
            'phone' => '987 654 321', // celular peruano sin +51
            'locale' => 'es',
            'items' => [[
                'row_id' => 'r1', 'tour_id' => 1, 'title_snapshot' => 'Tour Islas Ballestas',
                'unit_price' => 50, 'adults' => 2, 'children' => 0, 'quantity' => 2, 'subtotal' => 100,
                'travel_date' => '2026-09-01',
            ]],
            'subtotal' => 100,
            'total' => 100,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);

        $html = Livewire::test(ListAbandonedCarts::class)->html();

        $this->assertStringContainsString('Rosa Martínez', $html, 'El nombre del carrito no aparece en el listado.');
        $this->assertStringContainsString(
            'https://wa.me/51987654321',
            $html,
            'El link de WhatsApp no aparece o el teléfono no se normalizó con el prefijo +51.'
        );
        $this->assertStringContainsString('tel:+51987654321', $html, 'El link tel: no aparece normalizado.');
        $this->assertStringContainsString('mailto:cliente@example.com', $html, 'El link mailto: del correo no aparece.');

        $this->assertNotNull($cart->fresh());
    }

    public function test_a_cart_without_phone_does_not_render_a_broken_whatsapp_link(): void
    {
        $this->actingAs($this->admin());

        AbandonedCart::create([
            'session_id' => 'sess-2',
            'email' => 'sinfono@example.com',
            'name' => 'Sin Teléfono',
            'phone' => null,
            'locale' => 'es',
            'items' => [['row_id' => 'r2', 'tour_id' => 2, 'title_snapshot' => 'Tour Huacachina', 'unit_price' => 30, 'adults' => 1, 'children' => 0, 'quantity' => 1, 'subtotal' => 30]],
            'subtotal' => 30,
            'total' => 30,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);

        $html = Livewire::test(ListAbandonedCarts::class)->html();

        $this->assertStringContainsString('(sin teléfono)', $html);
        $this->assertStringNotContainsString('wa.me/', $html, 'No debería ofrecerse un link de WhatsApp cuando no hay teléfono.');
    }

    public function test_the_phone_filter_isolates_carts_that_can_be_called(): void
    {
        $this->actingAs($this->admin());

        $withPhone = AbandonedCart::create([
            'session_id' => 'sess-3', 'email' => 'con@example.com', 'phone' => '987654321',
            'items' => [['row_id' => 'a', 'tour_id' => 1, 'title_snapshot' => 'x', 'unit_price' => 10, 'quantity' => 1, 'subtotal' => 10]],
            'total' => 10, 'status' => 'active', 'last_activity_at' => now(),
        ]);

        $withoutPhone = AbandonedCart::create([
            'session_id' => 'sess-4', 'email' => 'sin@example.com', 'phone' => null,
            'items' => [['row_id' => 'b', 'tour_id' => 1, 'title_snapshot' => 'x', 'unit_price' => 10, 'quantity' => 1, 'subtotal' => 10]],
            'total' => 10, 'status' => 'active', 'last_activity_at' => now(),
        ]);

        Livewire::test(ListAbandonedCarts::class)
            ->filterTable('has_phone', true)
            ->assertCanSeeTableRecords([$withPhone])
            ->assertCanNotSeeTableRecords([$withoutPhone]);
    }

    public function test_the_detail_page_shows_items_customer_fallback_phone_and_recovery_link(): void
    {
        $this->actingAs($this->admin());

        $customer = Customer::create([
            'name' => 'Cliente Registrado',
            'email' => 'registrado@example.com',
            'phone' => '912345678',
            'password' => bcrypt('secret1234'),
        ]);

        $cart = AbandonedCart::create([
            'session_id' => 'sess-5',
            'customer_id' => $customer->id,
            'email' => 'registrado@example.com',
            'name' => 'Cliente Registrado',
            'phone' => null, // el carrito NO capturó teléfono, pero el cliente sí tiene uno
            'locale' => 'en',
            'items' => [[
                'row_id' => 'r3', 'tour_id' => 3, 'title_snapshot' => 'Tour Machu Picchu',
                'unit_price' => 200, 'adults' => 2, 'children' => 1, 'quantity' => 3, 'subtotal' => 600,
                'travel_date' => '2026-10-05',
            ]],
            'subtotal' => 600,
            'total' => 600,
            'coupon_code' => 'LIMA10',
            'status' => 'active',
            'reminders_sent' => 1,
            'last_activity_at' => now(),
        ]);

        $html = Livewire::test(ViewAbandonedCart::class, ['record' => $cart->getRouteKey()])->html();

        $this->assertStringContainsString('Tour Machu Picchu', $html, 'El detalle no muestra el tour del carrito.');
        $this->assertStringContainsString('LIMA10', $html, 'El detalle no muestra el cupón usado.');
        $this->assertStringContainsString('912345678', $html, 'No se ve el teléfono del cliente registrado como respaldo.');
        $this->assertStringContainsString('wa.me/51912345678', $html, 'El WhatsApp de detalle no usa el teléfono del perfil del cliente cuando el carrito no capturó uno.');
        $this->assertStringContainsString($cart->token, $html, 'El enlace público de recuperación (token) no aparece en el detalle.');
        $this->assertStringContainsString('Inglés', $html, 'No se muestra el idioma en que navegaba el visitante.');
    }
}
