<?php

namespace App\Console\Commands;

use App\Services\OrderExpiryService;
use Illuminate\Console\Command;

class ExpireUnpaidOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:expire-unpaid {--limit=100 : Maximum number of overdue orders to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire unpaid pending_payment orders past their deadline and release reserved stock';

    /**
     * Execute the console command.
     */
    public function handle(OrderExpiryService $orderExpiryService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $expiredCount = $orderExpiryService->expireAllPastDue($limit);

        $this->info("Expired {$expiredCount} overdue unpaid order(s).");

        return self::SUCCESS;
    }
}

