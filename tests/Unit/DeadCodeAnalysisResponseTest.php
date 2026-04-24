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
        ->and($response->symbols[0]->reasonSummary)->toBe('Reachable through Laravel runtime routing or supported controller call expansion.')
        ->and($response->symbols[0]->reachabilityReasons)->toHaveCount(1)
        ->and($response->symbols[0]->reachabilityReasons[0]->code)->toBe('supported_controller_reachability')
        ->and($response->symbols[1]->reachableFromRuntime)->toBeFalse()
        ->and($response->findings)->toHaveCount(1)
        ->and($response->findings[0]->symbol)->toBe('App\\Http\\Controllers\\UserController::unused')
        ->and($response->findings[0]->category)->toBe('unused_controller_method')
        ->and($response->findings[0]->reasonSummary)->toBe('No runtime route or supported controller call keeps this method alive.')
        ->and($response->findings[0]->evidence)->toHaveCount(1)
        ->and($response->findings[0]->evidence[0]->code)->toBe('no_supported_controller_reachability')
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

it('parses the policy reachability symbol kind and finding category', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcorePolicyReachabilityPayload());

    expect($response->entrypoints)->toHaveCount(1)
        ->and($response->entrypoints[0]->kind)->toBe('runtime_policy')
        ->and($response->entrypoints[0]->symbol)->toBe('App\\Policies\\OrderPolicy')
        ->and($response->entrypoints[0]->source)->toBe('App\\Models\\Order')
        ->and($response->symbols)->toHaveCount(2)
        ->and($response->symbols[0]->kind)->toBe('policy_class')
        ->and($response->symbols[0]->reachableFromRuntime)->toBeTrue()
        ->and($response->symbols[1]->kind)->toBe('policy_class')
        ->and($response->symbols[1]->reachableFromRuntime)->toBeFalse()
        ->and($response->findings)->toHaveCount(1)
        ->and($response->findings[0]->category)->toBe('unused_policy_class')
        ->and($response->removalPlan->changeSets)->toHaveCount(1);
});

it('serializes the policy reachability payload back to the wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcorePolicyReachabilityPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcorePolicyReachabilityPayload());
});

it('parses the command reachability symbol kind and finding category', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreCommandReachabilityPayload());

    expect($response->entrypoints)->toHaveCount(1)
        ->and($response->entrypoints[0]->kind)->toBe('runtime_command')
        ->and($response->entrypoints[0]->source)->toBe('maintenance:reachable')
        ->and($response->symbols)->toHaveCount(2)
        ->and($response->symbols[0]->kind)->toBe('command_class')
        ->and($response->symbols[0]->reachableFromRuntime)->toBeTrue()
        ->and($response->symbols[1]->kind)->toBe('command_class')
        ->and($response->symbols[1]->reachableFromRuntime)->toBeFalse()
        ->and($response->findings)->toHaveCount(1)
        ->and($response->findings[0]->category)->toBe('unused_command_class')
        ->and($response->removalPlan->changeSets)->toHaveCount(1);
});

it('serializes the command reachability payload back to the wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreCommandReachabilityPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcoreCommandReachabilityPayload());
});

it('parses the listener reachability symbol kind and finding category', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreListenerReachabilityPayload());

    expect($response->entrypoints)->toHaveCount(1)
        ->and($response->entrypoints[0]->kind)->toBe('runtime_listener')
        ->and($response->entrypoints[0]->source)->toBe('App\\Events\\OrderShipped')
        ->and($response->symbols)->toHaveCount(2)
        ->and($response->symbols[0]->kind)->toBe('listener_class')
        ->and($response->symbols[0]->reachableFromRuntime)->toBeTrue()
        ->and($response->symbols[1]->kind)->toBe('listener_class')
        ->and($response->symbols[1]->reachableFromRuntime)->toBeFalse()
        ->and($response->findings)->toHaveCount(1)
        ->and($response->findings[0]->category)->toBe('unused_listener_class')
        ->and($response->removalPlan->changeSets)->toHaveCount(1);
});

it('serializes the listener reachability payload back to the wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreListenerReachabilityPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcoreListenerReachabilityPayload());
});

it('parses the subscriber reachability symbol kind and finding category', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreSubscriberReachabilityPayload());

    expect($response->entrypoints)->toHaveCount(1)
        ->and($response->entrypoints[0]->kind)->toBe('runtime_subscriber')
        ->and($response->entrypoints[0]->source)->toBe('App\\Events\\OrderShipped')
        ->and($response->symbols)->toHaveCount(2)
        ->and($response->symbols[0]->kind)->toBe('subscriber_class')
        ->and($response->symbols[0]->reachableFromRuntime)->toBeTrue()
        ->and($response->symbols[1]->kind)->toBe('subscriber_class')
        ->and($response->symbols[1]->reachableFromRuntime)->toBeFalse()
        ->and($response->findings)->toHaveCount(1)
        ->and($response->findings[0]->category)->toBe('unused_subscriber_class')
        ->and($response->removalPlan->changeSets)->toHaveCount(1);
});

it('serializes the subscriber reachability payload back to the wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreSubscriberReachabilityPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcoreSubscriberReachabilityPayload());
});

it('parses the job reachability symbol kind and finding category', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreJobReachabilityPayload());

    expect($response->entrypoints)->toHaveCount(1)
        ->and($response->entrypoints[0]->kind)->toBe('runtime_job')
        ->and($response->entrypoints[0]->source)->toBe('redis:emails')
        ->and($response->symbols)->toHaveCount(2)
        ->and($response->symbols[0]->kind)->toBe('job_class')
        ->and($response->symbols[0]->reachableFromRuntime)->toBeTrue()
        ->and($response->symbols[1]->kind)->toBe('job_class')
        ->and($response->symbols[1]->reachableFromRuntime)->toBeFalse()
        ->and($response->findings)->toHaveCount(1)
        ->and($response->findings[0]->category)->toBe('unused_job_class')
        ->and($response->removalPlan->changeSets)->toHaveCount(1);
});

it('serializes the job reachability payload back to the wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcoreJobReachabilityPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcoreJobReachabilityPayload());
});

it('parses the phase 4 model-heavy symbol kinds and finding categories', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcorePhaseFourModelPayload());

    expect($response->entrypoints)->toHaveCount(1)
        ->and($response->entrypoints[0]->kind)->toBe('runtime_route')
        ->and($response->symbols)->toHaveCount(5)
        ->and(array_map(
            static fn ($symbol) => $symbol->kind,
            $response->symbols,
        ))->toBe([
            'model_method',
            'model_scope',
            'model_relationship',
            'model_accessor',
            'model_mutator',
        ])
        ->and(array_map(
            static fn ($finding) => $finding->category,
            $response->findings,
        ))->toBe([
            'unused_model_method',
            'unused_model_scope',
            'unused_model_relationship',
            'unused_model_accessor',
            'unused_model_mutator',
        ])
        ->and($response->findings[0]->reasonSummary)->toBe('No supported explicit model call from already-reachable code reaches this method.')
        ->and($response->findings[0]->evidence)->toHaveCount(1)
        ->and($response->findings[1]->reasonSummary)->toBe('No supported explicit scope-call pattern reaches this local scope.')
        ->and($response->removalPlan->changeSets)->toHaveCount(5);
});

it('serializes the phase 4 model-heavy payload back to the wire shape', function () {
    $response = DeadCodeAnalysisResponse::fromArray(deadcorePhaseFourModelPayload());

    expect(json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcorePhaseFourModelPayload());
});
