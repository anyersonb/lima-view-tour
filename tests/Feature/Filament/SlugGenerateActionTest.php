<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\TourResource\Pages\CreateTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Incidente 30/08/2026, mismo commit 7791956 (25/08) que RecordRouteKeyNameTest:
 * HasEditableSlugField::slugGenerateAction() (línea 305) tipaba el closure de
 * la acción como `FormsSet $set, FormsGet $get` sin ningún `use` que trajera
 * esos alias. PHP los resolvía contra el namespace del archivo
 * (App\Filament\Concerns\FormsSet, inexistente) y Filament reventaba con
 * TypeError al inyectar el Filament\Forms\Set real → 500 en el botón
 * "Generar desde el título" al crear un Tour, una Página o un Artículo de blog.
 *
 * Los tests anteriores de este trait llamaban a set()/get() directamente y
 * nunca instanciaban el closure de la Action, así que el type-hint roto no se
 * evaluaba jamás. Este test pasa por el camino real del usuario: monta la
 * acción con mountFormComponentAction() y la ejecuta con
 * callMountedFormComponentAction(), igual que un clic real en el botón.
 *
 * Alcance real, verificado leyendo el formulario (no asumido): SOLO
 * TourResource y BlogPostResource usan HasEditableSlugField y por tanto solo
 * ellos tienen el botón "Generar desde el título". PageResource NO usa este
 * trait — su campo 'slug' (PageResource.php:63) es un TextInput manual propio
 * ("sección a la que pertenece"), sin suffixAction ni slugGenerateAction, así
 * que el 500 nunca pudo darse ahí. Se deja un test que documenta y blinda esa
 * ausencia en vez de forzar un caso que no existe.
 */
class SlugGenerateActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    public function test_generating_the_slug_from_the_title_works_on_tour_creation(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateTour::class)
            ->fillForm(['title_es' => 'Tour Máquina De Prueba Slug'])
            ->mountFormComponentAction('slug', 'slug_generar')
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors()
            ->assertFormSet(['slug' => 'tour-maquina-de-prueba-slug']);
    }

    /**
     * No es el mismo test que Tour/Blog "adaptado" a Página: es la
     * comprobación de que ese botón NO existe en Página, para que quede
     * blindado si algún día alguien migra PageResource a HasEditableSlugField
     * sin darse cuenta de que reintroduce el riesgo del type-hint.
     */
    public function test_the_title_derived_slug_action_does_not_exist_on_page_creation(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreatePage::class)
            ->assertFormComponentActionDoesNotExist('slug', 'slug_generar');
    }

    public function test_generating_the_slug_from_the_title_works_on_blog_post_creation(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateBlogPost::class)
            ->fillForm(['title_es' => 'Artículo Máquina De Prueba Slug'])
            ->mountFormComponentAction('slug', 'slug_generar')
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors()
            ->assertFormSet(['slug' => 'articulo-maquina-de-prueba-slug']);
    }
}
