<?php

namespace App\Services\AI\Router;

use App\Services\AI\AIProviderManager;

/**
 * AIProviderRouter provides intelligent multi-provider routing and quota failover.
 * Inherits full provider orchestration from AIProviderManager.
 */
class AIProviderRouter extends AIProviderManager
{
    // Inherits resolveCandidateProviders, route, routeStream, registerProvider, getProviders, etc.
}
