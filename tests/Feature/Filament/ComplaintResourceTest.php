<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ComplaintResource;
use App\Filament\Resources\ComplaintResource\Pages\EditComplaint;
use App\Filament\Resources\ComplaintResource\Pages\ListComplaints;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComplaintResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeComplaint(): Complaint
    {
        return Complaint::register([
            'locale' => 'es', 'first_name' => 'Luis', 'last_name' => 'Soto', 'doc_type' => 'dni', 'doc_number' => '87654321',
            'address' => 'Calle 1', 'phone' => '999999999', 'email' => 'luis@example.com', 'is_minor' => false,
            'good_type' => 'service', 'currency' => 'PEN', 'good_description' => 'Tour', 'type' => 'complaint',
            'detail' => 'Mala atención en la oficina.', 'consumer_request' => 'Una disculpa.', 'accepted_declaration' => true,
        ]);
    }

    public function test_list_and_edit_render_and_response_can_be_saved(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));
        $c = $this->makeComplaint();

        Livewire::test(ListComplaints::class)->assertSuccessful()->assertSee($c->code);

        Livewire::test(EditComplaint::class, ['record' => $c->getRouteKey()])
            ->assertSuccessful()
            ->fillForm(['status' => 'answered', 'response' => 'Lamentamos lo ocurrido; le ofrecimos disculpas.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $c->refresh();
        $this->assertSame('answered', $c->status);
        $this->assertNotNull($c->responded_at);
        $this->assertSame('Mala atención en la oficina.', $c->detail); // solo lectura: intacto
    }

    public function test_registry_cannot_be_created_or_deleted_from_panel(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'qa@webtilia.com']));
        $c = $this->makeComplaint();

        $this->assertFalse(ComplaintResource::canCreate());
        $this->assertFalse(ComplaintResource::canDelete($c));
        $this->assertFalse(ComplaintResource::canDeleteAny());
    }
}
