<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Oxhq\Oxcribe\Bridge\DeadCodeAnalysisRequestFactory;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Support\RequestSerializer;

it('serializes a request into a predictable payload', function () {
    if (! class_exists(RequestSerializer::class)) {
        $this->markTestSkipped('RequestSerializer has not been created yet.');
    }

    $request = Request::create(
        '/oxcribe/requests/serialize?include=body',
        'POST',
        ['subject' => 'test', 'count' => 2],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        json_encode(['body' => 'payload', 'nested' => ['x' => 1]], JSON_THROW_ON_ERROR)
    );

    $serialized = app(RequestSerializer::class)->serialize($request);

    expect($serialized)->toBeArray()
        ->and($serialized)->toMatchArray([
            'method' => 'POST',
            'path' => 'oxcribe/requests/serialize',
            'query' => ['include' => 'body'],
        ])
        ->and($serialized['headers'])->toBeArray()
        ->and($serialized['input'])->toBeArray();
});

it('preserves richer query parameter shapes', function () {
    $request = Request::create(
        '/oxcribe/search?include=body&tags[0]=alpha&tags[1]=beta&filters[state]=open',
        'GET',
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
        ],
    );

    $serialized = app(RequestSerializer::class)->serialize($request);

    expect($serialized['query'])->toMatchArray([
        'include' => 'body',
        'tags' => ['alpha', 'beta'],
        'filters' => ['state' => 'open'],
    ]);
});

it('serializes a deadcode analysis request with runtime routes', function () {
    Route::get('/deadcode/runtime', static fn () => 'ok')
        ->name('deadcode.runtime')
        ->middleware(['api']);

    $runtime = app(RuntimeSnapshotFactory::class)->make();
    $request = app(DeadCodeAnalysisRequestFactory::class)->make($runtime);

    $wirePayload = json_decode($request->toWireJson(), true, 512, JSON_THROW_ON_ERROR);

    expect($wirePayload['contractVersion'])->toBe('deadcode.analysis.v1')
        ->and($wirePayload['runtime']['routes'])->toBeArray()
        ->and($wirePayload['runtime']['routes'])->not->toBeEmpty()
        ->and($wirePayload['manifest']['project']['root'])->toBe(base_path());
});
