<?php

declare(strict_types=1);

use App\Support\Concerns\RespondsWithJson;
use Symfony\Component\HttpFoundation\Response;

class StubController
{
    use RespondsWithJson;

    public function successPublic(mixed $data = null, int $status = Response::HTTP_OK): \Illuminate\Http\JsonResponse
    {
        return $this->success($data, $status);
    }

    public function errorPublic(string $code, string $message, int $status, array $details = []): \Illuminate\Http\JsonResponse
    {
        return $this->error($code, $message, $status, $details);
    }
}

it('produces a standardized success envelope', function () {
    $controller = new StubController();
    $response = $controller->successPublic(['hello' => 'world']);
    $data = $response->getData(true);

    expect($data)->toBeStandardSuccessEnvelope();
    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('produces a standardized error envelope', function () {
    $controller = new StubController();
    $response = $controller->errorPublic('FOO_ERROR', 'Bad foo', 400);
    $data = $response->getData(true);

    expect($data)->toBeStandardErrorEnvelope('FOO_ERROR');
});
