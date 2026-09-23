<?php

declare(strict_types=1);

use Illuminate\Http\Response;

it('reports the enabled Gemini models', function (): void {
    $response = $this->getJson(route('health.ai'));

    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.gemini.enabled', true)
        ->assertJsonStructure(['data' => ['gemini' => ['models']]]);

    expect(json_decode((string) $response->getContent(), true)['data']['gemini']['models'])->not->toBeEmpty();
});

it('reports OpenRouter details when enabled', function (): void {
    config()->set('openrouter.enabled', true);
    config()->set('openrouter.api_key', 'test-key');
    config()->set('openrouter.models', ['model-a', 'model-b']);

    $response = $this->getJson(route('health.ai'));

    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.openrouter.enabled', true)
        ->assertJsonPath('data.openrouter.models', ['model-a', 'model-b']);
});

it('reports OpenRouter as disabled when not configured', function (): void {
    config()->set('openrouter.enabled', false);

    $response = $this->getJson(route('health.ai'));

    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.openrouter.enabled', false)
        ->assertJsonPath('data.openrouter.models', []);
});

it('reports the jina reader configuration status', function (): void {
    config()->set('services.jina.api_key', '');

    $response = $this->getJson(route('health.ai'));

    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonPath('data.jina.configured', false);
});

it('is accessible without authentication', function (): void {
    $this->getJson(route('health.ai'))->assertStatus(Response::HTTP_OK);
});
