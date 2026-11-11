<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

test('the assistant eval refuses to run anywhere but staging', function (string $url) {
    config(['app.url' => $url, 'ai.platform_keys.anthropic' => 'sk-test']);
    Http::fake();

    $this->artisan('app:assistant-eval')->expectsOutputToContain('staging (.local) only')->assertFailed();

    Http::assertNothingSent();
})->with(['https://routepilot.pro', 'http://localhost']);
