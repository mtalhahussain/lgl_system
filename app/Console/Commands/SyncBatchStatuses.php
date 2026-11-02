<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBatchStatuses extends Command
{
    protected $signature = 'batches:sync-status';

    protected $description = 'Advance upcoming/ongoing batches by their dates and complete enrollments of completed batches';

    private const ORDER = ['upcoming' => 0, 'ongoing' => 1, 'completed' => 2];

    public function handle(): int
    {
        $today = today();
        $changed = 0;
        $enrollments = 0;
        Batch::whereIn('status', ['upcoming', 'ongoing'])
            ->where(fn ($query) => $query->whereDate('start_date', '<=', $today)->orWhereDate('end_date', '<', $today))
            ->select('id')->chunkById(200, function ($batches) use ($today, &$changed, &$enrollments) {
                foreach ($batches as $row) {
                    [$batchChanged, $enrollmentsChanged] = DB::transaction(function () use ($row, $today) {
                        $batch = Batch::lockForUpdate()->find($row->id);
                        if (! $batch || ! isset(self::ORDER[$batch->status])) {
                            return [0, 0];
                        }
                        $target = Batch::statusForDates($batch->start_date, $batch->end_date, $today);
                        // Forward only: never move a batch back or touch cancelled ones.
                        if (self::ORDER[$target] <= self::ORDER[$batch->status]) {
                            return [0, 0];
                        }
                        $before = $batch->status;
                        $batch->update(['status' => $target]);
                        AuditLog::create(['actor_id' => null, 'action' => 'batch.status_synced',
                            'subject_type' => 'batch', 'subject_id' => $batch->id,
                            'changes' => ['before' => ['status' => $before], 'after' => ['status' => $target]]]);

                        return [1, $batch->syncEnrollmentStatuses()];
                    }, 3);
                    $changed += $batchChanged;
                    $enrollments += $enrollmentsChanged;
                }
            });
        // Catch-up for batches completed earlier (manually or before this rule existed).
        Batch::where('status', 'completed')->whereHas('enrollments', fn ($query) => $query->where('status', 'active'))
            ->select('id')->chunkById(200, function ($batches) use (&$enrollments) {
                foreach ($batches as $row) {
                    $enrollments += DB::transaction(fn () => Batch::lockForUpdate()->find($row->id)?->syncEnrollmentStatuses() ?? 0, 3);
                }
            });
        $this->info("Batch statuses updated: {$changed}; enrollments completed: {$enrollments}");

        return self::SUCCESS;
    }
}
