<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ProviderHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderHealthController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        // Protected for admins/internal status
        $health = ProviderHealth::latest('last_checked_at')->get();

        return $this->successResponse($health);
    }
}
