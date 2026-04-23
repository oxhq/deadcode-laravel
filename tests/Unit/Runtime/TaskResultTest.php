<?php

declare(strict_types=1);

use Deadcode\Runtime\TaskResult;

it('stores structured task result data', function (): void {
    $result = new TaskResult(
        status: 'ok',
        data: ['findingCount' => 12],
        meta: ['durationMs' => 87],
    );

    expect($result->status)->toBe('ok');
    expect($result->data['findingCount'])->toBe(12);
    expect($result->meta['durationMs'])->toBe(87);
});
