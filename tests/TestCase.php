<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Template packages, uploaded media and generated invitations.
        foreach (array_unique([config('emp.template_disk'), config('emp.media_disk'), config('emp.render_disk')]) as $disk) {
            Storage::fake($disk);
        }
    }
}
