<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Data\DeadCodeAnalysisResponse;

it('parses the aligned deadcore response shape for controller reachability', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreControllerReachabilityPayload());

    expect($response->contractVersion)->toBe('deadcode.analysis.v1')
        ->and($response->requestId)->toBe('req-controller-reachability')
        ->and($response->status)->toBe('ok')
        ->and($response->meta->durationMs)->toBe(17)
        ->and($response->meta->cacheHits)->toBe(2)
        ->and($response->meta->cacheMisses)->toBe(1)
        ->and($response->entrypoints)->toHaveCount(1)
        ->and($response->entrypoints[0]->symbol)->toBe('App\\Http\\Controllers\\UserController::index')
        ->and($response->symbols)->toHaveCount(2)
        ->and($response->symbols[0]->reachableFromRuntime)->toBeTrue()
        ->and($response->symbols[1]->reachableFromRuntime)->toBeFalse()
        ->and($response->findings)->toHaveCount(1)
        ->and($response->findings[0]->symbol)->toBe('App\\Http\\Controllers\\UserController::unused')
        ->and($response->findings[0]->category)->toBe('unused_controller_method')
        ->and($response->removalPlan->changeSets)->toHaveCount(1)
        ->and($response->removalPlan->changeSets[0]->startLine)->toBe(20)
        ->and($response->removalPlan->changeSets[0]->endLine)->toBe(24);
});

it('serializes back to the raw deadcore wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreControllerReachabilityPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcoreControllerReachabilityPayload());
});

it('parses the phase 2 http-adjacent symbol kinds and finding categories', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcorePhaseTwoHttpAdjacencyPayload());

    expect($response->symbols)->toHaveCount(3)
        ->and($response->symbols[0]->kind)->toBe('controller_class')
        ->and($response->symbols[0]->symbol)->toBe('App\\Http\\Controllers\\UnusedWebhookController')
        ->and($response->symbols[1]->kind)->toBe('form_request_class')
        ->and($response->symbols[1]->symbol)->toBe('App\\Http\\Requests\\UnusedOrderRequest')
        ->and($response->symbols[2]->kind)->toBe('resource_class')
        ->and($response->symbols[2]->symbol)->toBe('App\\Http\\Resources\\UnusedOrderResource')
        ->and($response->findings)->toHaveCount(3)
        ->and($response->findings[0]->category)->toBe('unused_controller_class')
        ->and($response->findings[1]->category)->toBe('unused_form_request')
        ->and($response->findings[2]->category)->toBe('unused_resource_class')
        ->and($response->removalPlan->changeSets)->toBeEmpty();
});

it('serializes the phase 2 http-adjacent payload back to the wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcorePhaseTwoHttpAdjacencyPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcorePhaseTwoHttpAdjacencyPayload());
});
