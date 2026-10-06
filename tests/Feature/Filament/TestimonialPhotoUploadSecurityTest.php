<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TestimonialResource\Pages\CreateTestimonial;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hallazgo de seguridad ALTO (2026-09-30,
 * docs/security/2026-09-30-lote-paypal-resenas.md #5): la regla de extensión
 * de `photos` en TestimonialResource era código muerto. Filament valida
 * ->rules() POR ARCHIVO (BaseFileUpload::getValidationRules(), "{$name}.*"),
 * así que $value YA es un único TemporaryUploadedFile — el `(array) $value`
 * de antes casteaba ese OBJETO a un array de sus propiedades internas
 * (nunca "un array con el archivo adentro"), el foreach nunca encontraba
 * nada con getClientOriginalExtension() y la regla pasaba SIEMPRE, con
 * cualquier extensión. El nombre en disco tampoco protegía nada: salía de
 * getClientOriginalExtension() (comportamiento por defecto de Filament),
 * así que un archivo llamado "shell.php" se guardaba tal cual con esa
 * extensión en /storage/reviews (disco público).
 *
 * Fix: la regla valida $value directamente
 * (TestimonialResource::photoExtensionRule()) y el nombre en disco sale del
 * MIME DETECTADO del archivo — nunca del cliente — restringido a
 * jpg/png/webp (ImageOptimizer::safeImageNamer()).
 */
class TestimonialPhotoUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'qa@webtilia.com']);
    }

    private function baseFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Pérez',
            'quote_es' => 'Excelente experiencia, lo recomiendo totalmente.',
            'rating' => 5,
            'source' => 'Web',
            'status' => 'pending',
            'is_featured' => false,
            'is_active' => false,
            'order' => 0,
        ], $overrides);
    }

    /**
     * Antes del fix, este mismo archivo pasaba la regla porque `(array)
     * $value` nunca encontraba el TemporaryUploadedFile (verificado con un
     * PoC directo por security-engineer). Acá se prueba el camino REAL, a
     * través del formulario completo de Filament — no la regla en
     * aislamiento.
     *
     * El MIME se fuerza a 'image/jpeg' a propósito: así se prueba
     * específicamente el control de EXTENSIÓN del cliente (lo que arregla
     * este hallazgo), aislado del control de mimetype de Filament
     * (->image()/->acceptedFileTypes()), que es una capa distinta y ya
     * existía antes.
     */
    public function test_a_php_file_disguised_as_an_image_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $shell = UploadedFile::fake()->create('shell.php', 10, 'image/jpeg');

        Livewire::test(CreateTestimonial::class)
            ->fillForm(array_merge($this->baseFormData(), [
                'photos' => [$shell],
            ]))
            ->call('create')
            ->assertHasFormErrors(['photos']);

        $this->assertSame(0, Testimonial::count(), 'El archivo rechazado no debió crear la reseña.');
        $this->assertEmpty(
            Storage::disk('public')->allFiles('reviews'),
            'Nada debió llegar al disco: el rechazo es en validación, antes de guardar.'
        );
    }

    /**
     * Defensa en profundidad, aislada de la regla de arriba: aunque la
     * validación de extensión se saltara, el NOMBRE EN DISCO nunca sale de
     * la extensión que reporta el cliente, sino del MIME detectado del
     * archivo. Se simula un archivo con extensión de cliente ".jpg" pero
     * MIME detectado que NO es una imagen real (en test,
     * Illuminate\Http\Testing\File::getMimeType() devuelve el mimeType
     * forzado en UploadedFile::fake()->create(), que hace las veces del
     * finfo real de producción) — el resultado debe caer al extension por
     * defecto ('bin'), nunca a ".php" ni a lo que diga el cliente.
     */
    public function test_stored_extension_never_comes_from_the_client_name_or_extension(): void
    {
        $fakePhpDisguisedAsJpg = UploadedFile::fake()->create('evil.jpg', 5, 'application/x-httpd-php');

        $extension = ImageOptimizer::safeExtensionFromDetectedMime($fakePhpDisguisedAsJpg, [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ]);

        $this->assertNotSame('php', $extension);
        $this->assertNotSame('jpg', $extension);
        $this->assertSame(
            'bin',
            $extension,
            'Un MIME detectado que no es jpg/png/webp debe caer al default seguro, nunca a la extensión del cliente.'
        );
    }

    public function test_a_legitimate_jpeg_is_accepted_and_stored_with_a_generated_safe_name(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $photo = UploadedFile::fake()->image('foto-viaje.jpg', 800, 600);

        Livewire::test(CreateTestimonial::class)
            ->fillForm(array_merge($this->baseFormData(), [
                'photos' => [$photo],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $testimonial = Testimonial::firstOrFail();
        $paths = $testimonial->photos ?? [];

        $this->assertNotEmpty($paths, 'La reseña se creó pero no guardó ninguna foto.');

        $storedPath = $paths[0];

        $this->assertStringEndsWith('.jpg', $storedPath);
        $this->assertStringNotContainsString(
            'foto-viaje',
            $storedPath,
            'El nombre en disco no debe conservar el nombre original del cliente.'
        );
        Storage::disk('public')->assertExists($storedPath);
    }
}
