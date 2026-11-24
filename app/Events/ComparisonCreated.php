<?php

namespace App\Events;

use App\Models\ComparisonJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComparisonCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ComparisonJob $job
    ) {}
}
