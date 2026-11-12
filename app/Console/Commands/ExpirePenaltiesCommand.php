<?php

namespace App\Console\Commands;

use App\Services\PenaltyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('penalties:expire')]
#[Description('Expire old penalty points past 90 days and recalculate user restrictions')]
class ExpirePenaltiesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PenaltyService $penaltyService): int
    {
        $this->info('Checking for expired penalty points...');

        $count = $penaltyService->expirePenalties();

        $this->info("Successfully expired {$count} penalty points.");

        return Command::SUCCESS;
    }
}

