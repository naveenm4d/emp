<?php

use App\Domains\Event\Models\Event;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Admin)->create(), 'staff');

    /** A zip package built from $files (relative path => contents). */
    $this->zip = function (array $files): UploadedFile {
        $path = storage_path('framework/testing/upload-'.uniqid().'.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return new UploadedFile($path, 'package.zip', 'application/zip', null, true);
    };

    $this->package = fn (array $manifest = []) => [
        'template.json' => json_encode([
            'key' => 'aurora',
            'name' => 'Aurora',
            'version' => '1.0.0',
            'author' => 'EMP Studio',
            'category' => 'party',
            ...$manifest,
        ]),
        'index.html' => '<h1 data-title>{{ event.title }}</h1>{{ rsvp }}',
        'css/style.css' => 'h1 { color: teal }',
        'js/main.js' => 'window.empInvitation.root.querySelector("[data-title]");',
    ];
});

it('lists templates filtered by category and status', function () {
    Template::factory()->published()->create(['category' => TemplateCategory::Party, 'name' => 'Party On']);
    Template::factory()->published()->inactive()->create(['category' => TemplateCategory::Party]);
    Template::factory()->published()->create(['category' => TemplateCategory::Wedding]);

    $this->get('/admin/templates?category=party&status=active')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/templates/index')
            ->has('templates.data', 1)
            ->where('templates.data.0.name', 'Party On'));
});

it('imports an uploaded zip and opens the new template', function () {
    $response = $this->post('/admin/templates', ['package' => ($this->zip)(($this->package)())]);

    $template = Template::sole();
    $response->assertRedirect("/admin/templates/{$template->id}")
        ->assertSessionHas('success', 'Imported Aurora v1.0.0.');

    expect($template->latestVersion->scripts())->toBe(['js/main.js']);
    Storage::disk(config('emp.template_disk'))->assertExists("{$template->latestVersion->path}/js/main.js");
});

it('shows the importer\'s error on the upload field', function () {
    $files = ($this->package)();
    unset($files['index.html']);

    $this->post('/admin/templates', ['package' => ($this->zip)($files)])
        ->assertSessionHasErrors(['package' => 'The package has no index.html.']);

    expect(Template::count())->toBe(0);
});

it('updates catalogue details', function () {
    $template = Template::factory()->published()->create();

    $this->patch("/admin/templates/{$template->id}", [
        'name' => 'Renamed',
        'description' => null,
        'category' => 'birthday',
        'tags' => [' Fun ', 'fun', 'Kids'],
        'price' => 29900,
        'currency' => 'lkr',
        'display_price' => 'Rs. 990',
        'sort_order' => 3,
        'is_active' => false,
    ])->assertSessionHas('success', 'Template updated.');

    expect($template->fresh())
        ->name->toBe('Renamed')
        ->category->toBe(TemplateCategory::Birthday)
        ->tags->toBe(['fun', 'kids'])
        ->price->toBe(29900)
        ->currency->toBe('LKR')
        ->is_active->toBeFalse();
});

it('refuses to delete a template that events use', function () {
    $template = Template::factory()->published()->create();
    Event::factory()->create(['template_version_id' => $template->latest_version_id]);

    $this->from("/admin/templates/{$template->id}")
        ->delete("/admin/templates/{$template->id}")
        ->assertRedirect("/admin/templates/{$template->id}")
        ->assertSessionHas('error', 'Events use this template, so it cannot be deleted. Deactivate it instead.');

    expect($template->fresh())->not->toBeNull();
});

it('deletes an unused template with its versions and files', function () {
    $template = Template::factory()->published()->create();
    $path = $template->fresh()->latestVersion->path;

    $this->delete("/admin/templates/{$template->id}")->assertRedirect('/admin/templates');

    expect(Template::count())->toBe(0)->and(TemplateVersion::count())->toBe(0);
    Storage::disk(config('emp.template_disk'))->assertMissing("{$path}/index.html");
});

it('previews a version with sample details and its scripts', function () {
    $template = Template::factory()->create();
    $version = TemplateVersion::factory()->for($template)->withScript()->create();

    $this->get("/admin/templates/{$template->id}/versions/{$version->id}/preview")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/templates/preview')
            ->where('design.html', fn (string $html) => str_contains($html, 'Anna &amp; Raj') && str_contains($html, 'data:image/svg+xml;base64,'))
            ->has('design.scripts', 1));
});

it('returns 404 when previewing another template\'s version', function () {
    $template = Template::factory()->published()->create();
    $other = Template::factory()->published()->create();

    $this->get("/admin/templates/{$template->id}/versions/{$other->latest_version_id}/preview")->assertNotFound();
});

it('lets viewers browse templates but not change them', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');
    $template = Template::factory()->published()->create();

    $this->get("/admin/templates/{$template->id}")->assertOk();
    $this->post('/admin/templates', ['package' => ($this->zip)(($this->package)())])->assertForbidden();
    $this->patch("/admin/templates/{$template->id}", ['name' => 'X'])->assertForbidden();
    $this->delete("/admin/templates/{$template->id}")->assertForbidden();

    expect(Template::count())->toBe(1);
});
