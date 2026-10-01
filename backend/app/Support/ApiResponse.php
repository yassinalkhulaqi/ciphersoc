<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function ok(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message, 'meta' => $meta]);
    }

    public static function paginated($paginator, ?string $message = null): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $paginator->items(), 'message' => $message,
            'meta' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()]]);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }
}
