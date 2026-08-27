<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use OwenIt\Auditing\Models\Audit;
use Carbon\Carbon;

#[Signature('audit:cleanup')]
#[Description('Delete audit logs older than 90 days')]
class CleanupOldAuditLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysToKeep = 90;
        $cutoffDate = Carbon::now()->subDays($daysToKeep);

        $this->info("Deleting audit logs older than {$daysToKeep} days (before {$cutoffDate->toDateString()})...");

        $deletedCount = Audit::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$deletedCount} audit log(s).");

        return Command::SUCCESS;
    }
}
