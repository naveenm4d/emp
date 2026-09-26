<?php

namespace App\Domains\Template\Services;

use App\Domains\Event\Enums\EventType;
use App\Domains\Template\Contracts\TemplatePreviewServiceInterface;
use App\Domains\Template\DTOs\MediaSlot;
use App\Domains\Template\Enums\MediaType;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use App\Domains\Template\Support\PlaceholderContext;
use App\Domains\Template\Support\PlaceholderFormat;
use App\Domains\Template\Support\PlaceholderImage;
use App\Domains\Template\Support\PlaceholderSyntax;
use App\Domains\Template\Support\TemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\HtmlString;

/**
 * Renders a template version the same way an event's invitation is rendered
 * (see EventDesignService), but with sample details and placeholder images.
 */
class TemplatePreviewService implements TemplatePreviewServiceInterface
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
    ) {}

    public function render(TemplateVersion $version, array $details = []): array
    {
        $editable = $version->editable();
        $styles = $editable->colorStyles().$this->renderer->render(
            PlaceholderSyntax::parse($version->styles(), PlaceholderContext::Styles),
            assetUrl: $version->assetUrl(...),
        );

        $markup = $this->renderer->render(
            PlaceholderSyntax::parse($version->markup(), PlaceholderContext::Markup),
            [...$this->sampleValues(), ...$this->detailValues($details), ...$this->sampleMedia($version), ...$editable->values()],
            $version->assetUrl(...),
        );

        return [
            'html' => "<style>\n{$styles}\n</style>\n{$markup}",
            'fonts' => $version->fonts(),
            'scripts' => $version->scriptUrls(),
        ];
    }

    public function forTemplate(Template $template, array $details = []): array
    {
        /** @var TemplateVersion $version */
        $version = $template->latestVersion;

        return [
            'id' => $template->id,
            'name' => $template->name,
            'version' => $version->version,
            ...$this->render($version, $details),
        ];
    }

    /** @return array<string, string|HtmlString> */
    private function sampleValues(): array
    {
        return [
            'event.title' => 'Anna & Raj',
            'event.description' => 'Join us for an evening of food, music and celebration with the people we love most.',
            'event.type' => 'Wedding',
            'event.date' => PlaceholderFormat::date(now()->addDays(30)),
            'event.start_time' => '6:00 PM',
            'event.end_time' => '10:00 PM',
            'event.location_name' => 'The Grand Pavilion',
            'event.location_address' => new HtmlString('12 Lake View Road<br>Colombo'),
            'event.map_url' => 'https://maps.google.com/',
            'guest.name' => 'Priya',
        ];
    }

    /**
     * The details typed into the event form so far, formatted like a real invitation.
     *
     * @param  array<string, string>  $details
     * @return array<string, string|HtmlString>
     */
    private function detailValues(array $details): array
    {
        $type = EventType::tryFrom((string) ($details['event_type'] ?? ''));
        $date = filled($details['event_date'] ?? null) ? CarbonImmutable::createFromFormat('Y-m-d', $details['event_date']) : null;

        return array_filter([
            'event.title' => filled($details['title'] ?? null) ? trim($details['title']) : null,
            'event.description' => PlaceholderFormat::multiline($details['description'] ?? null),
            'event.type' => $type?->label(),
            'event.date' => PlaceholderFormat::date($date ?: null),
            'event.start_time' => PlaceholderFormat::time($details['start_time'] ?? null),
            'event.end_time' => PlaceholderFormat::time($details['end_time'] ?? null),
            'event.location_name' => filled($details['location_name'] ?? null) ? $details['location_name'] : null,
            'event.location_address' => PlaceholderFormat::multiline($details['location_address'] ?? null),
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, string> image slots get a placeholder picture; video and music stay empty */
    private function sampleMedia(TemplateVersion $version): array
    {
        return collect($version->slots())
            ->filter(fn (MediaSlot $slot) => $slot->type === MediaType::Image)
            ->mapWithKeys(fn (MediaSlot $slot) => [$slot->key => PlaceholderImage::dataUri($slot->displayLabel())])
            ->all();
    }
}
