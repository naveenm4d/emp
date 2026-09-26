<?php

namespace Database\Factories;

use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        return [
            'key' => 'tpl-'.Str::lower(Str::random(10)),
            'name' => Str::title(fake()->word().' '.fake()->word()),
            'description' => fake()->sentence(),
            'author' => 'EMP Studio',
            'category' => fake()->randomElement(TemplateCategory::cases()),
            'tags' => [],
            'price' => 0,
            'currency' => 'LKR',
            'type' => TemplateType::Predefined,
            'is_active' => true,
        ];
    }

    /** With a published version whose code is $markup (see TemplateVersionFactory). */
    public function published(?string $markup = null, string $styles = ''): static
    {
        return $this->afterCreating(function (Template $template) use ($markup, $styles) {
            $factory = TemplateVersion::factory()->for($template);

            $version = ($markup === null ? $factory : $factory->withCode($markup, $styles))->create();

            $template->latest_version_id ??= $version->id;
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
