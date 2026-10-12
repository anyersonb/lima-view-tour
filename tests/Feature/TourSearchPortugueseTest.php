<?php

namespace Tests\Feature;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourSearchPortugueseTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_a_tour_by_its_portuguese_title(): void
    {
        Tour::factory()->create(['title_es' => 'Laguna Humantay', 'title_en' => 'Humantay Lake', 'title_pt' => 'Lagoa Encantada', 'is_published' => true]);

        $this->get('/pt/buscar?q=Encantada')->assertOk()->assertSee('Lagoa Encantada');
    }
}
