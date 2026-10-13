<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['emp.deploy_token' => 'deploy-secret']);
});

afterEach(function () {
    app()->maintenanceMode()->deactivate();
    File::delete(storage_path('framework/maintenance.php'));
});

function deployRequest(string $step, ?string $token = 'deploy-secret')
{
    return test()->postJson("/webhooks/deploy/{$step}", [], $token === null ? [] : ['Authorization' => "Bearer {$token}"]);
}

it('hides the deploy endpoints when no token is configured', function () {
    config(['emp.deploy_token' => null]);

    deployRequest('down')->assertNotFound();
    expect(app()->maintenanceMode()->active())->toBeFalse();
});

it('refuses a missing or wrong token', function (?string $token) {
    deployRequest('down', $token)->assertForbidden();
    expect(app()->maintenanceMode()->active())->toBeFalse();
})->with(['missing' => null, 'wrong' => 'not-the-secret']);

it('puts the site into maintenance mode before the upload', function () {
    deployRequest('down')->assertOk()->assertJson(['status' => 'down']);

    expect(app()->maintenanceMode()->active())->toBeTrue();
    $this->get(route('client.login'))->assertServiceUnavailable();
});

it('finishes the deployment while the site is in maintenance mode', function () {
    app()->maintenanceMode()->activate([]);
    Artisan::shouldReceive('call')->once()->with('app:finish-deployment')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('done');

    deployRequest('finish')->assertOk()->assertExactJson(['status' => 'up', 'output' => 'done']);
});

it('reports a failed deployment', function () {
    Artisan::shouldReceive('call')->once()->with('app:finish-deployment')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('migrate failed');

    deployRequest('finish')->assertServerError()->assertJson(['status' => 'failed']);
});
