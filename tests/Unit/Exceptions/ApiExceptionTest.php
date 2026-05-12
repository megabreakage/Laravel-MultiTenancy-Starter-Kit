<?php

declare(strict_types=1);

use App\Exceptions\ResourceNotFoundException;

it('renders to a standardized error envelope', function () {
    $e = new ResourceNotFoundException('Widget not found');
    $response = $e->render(request());
    $data = $response->getData(true);

    expect($data)->toHaveKey('error')->toHaveKey('meta');
    expect($data['error']['code'])->toBe('RESOURCE_NOT_FOUND');
    expect($data['error']['message'])->toBe('Widget not found');
    expect($response->getStatusCode())->toBe(404);
});
