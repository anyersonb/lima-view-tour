<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory mínima: solo los dos campos NOT NULL sin default de la tabla
 * `pages` (slug, title_es). El resto es nullable o tiene default — ver
 * database/migrations/2026_05_05_021150_create_pages_table.php.
 *
 * Existe para que PageResource (declara $recordRouteKeyName, ver incidente
 * 30/08/2026) quede cubierto también por
 * ResourceEditUrlRouteKeyConsistencyTest, no solo por RecordRouteKeyNameTest.
 *
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(5),
            'title_es' => $title,
        ];
    }
}
