<?php

namespace App\Domains\Template\Contracts;

use App\Domains\Client\Models\Client;
use App\Domains\Template\DTOs\UpdateTemplateData;
use App\Domains\Template\Exceptions\TemplateInUseException;
use App\Domains\Template\Models\Template;

interface TemplateServiceInterface
{
    /** A client bought a paid template: they can use it for all their events from now on. */
    public function grantToClient(Template $template, Client $client, ?string $paymentId): void;

    /** Change catalogue details (name, price, category, visibility, …). */
    public function update(Template $template, UpdateTemplateData $data): Template;

    /**
     * Delete the template, its versions and their files.
     *
     * @throws TemplateInUseException when an event is designed with one of its versions
     */
    public function delete(Template $template): void;
}
