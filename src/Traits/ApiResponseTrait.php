<?php

namespace LaraUtilX\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait ApiResponseTrait
{
    /**
     * Send a standardized success response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Request successful.',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $statusCode);
    }

    /**
     * Send a standardized error response with optional debug data.
     */
    protected function errorResponse(
        string $message = 'Something went wrong.',
        int $statusCode = 500,
        array $errors = [],
        mixed $debug = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        if (config('app.debug') && $debug !== null) {
            $response['debug'] = $debug;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Handle exception responses (useful for centralized error handling).
     */
    protected function exceptionResponse(\Throwable $e, int $statusCode = 500): JsonResponse
    {
        Log::error($e->getMessage(), [
            'exception' => $e,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return $this->errorResponse(
            'Internal server error.',
            $statusCode,
            [],
            [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]
        );
    }

    /**
     * Send a paginated response with meta info.
     */
    protected function paginatedResponse($paginator, string $message = 'Data fetched successfully.'): JsonResponse
    {
        return $this->successResponse(
            $paginator->items(),
            $message,
            200,
            [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                ]
            ]
        );
    }
}
