<?php

namespace App\Events;

use App\Enums\EscrowStatus;
use App\Models\Offer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EscrowStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Offer $offer,
        public ?EscrowStatus $previousStatus,
        public EscrowStatus $newStatus
    ) {}
}
