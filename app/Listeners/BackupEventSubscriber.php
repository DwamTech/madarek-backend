<?php

namespace App\Listeners;

use App\Models\BackupHistory;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class BackupEventSubscriber
{
    /**
     * Handle backup successful event.
     */
    public function handleBackupWasSuccessful(BackupWasSuccessful $event)
    {
        BackupHistory::create([
            'type' => 'create',
            'status' => 'success',
            'file_name' => $event->backupDestination->newestBackup()->path(),
            'file_size' => $event->backupDestination->newestBackup()->size(),
            'message' => 'Backup created successfully.',
        ]);
    }

    /**
     * Handle backup failed event.
     */
    public function handleBackupHasFailed(BackupHasFailed $event)
    {
        BackupHistory::create([
            'type' => 'create',
            'status' => 'failed',
            'message' => $event->exception->getMessage(),
        ]);
    }

    /**
     * Handle cleanup successful event.
     */
    public function handleCleanupWasSuccessful(CleanupWasSuccessful $event)
    {
        BackupHistory::create([
            'type' => 'clean',
            'status' => 'success',
            'message' => 'Cleanup completed successfully.',
        ]);
    }

    /**
     * Handle cleanup failed event.
     */
    public function handleCleanupHasFailed(CleanupHasFailed $event)
    {
        BackupHistory::create([
            'type' => 'clean',
            'status' => 'failed',
            'message' => $event->exception->getMessage(),
        ]);
    }

    /**
     * Handle healthy backup found event.
     */
    public function handleHealthyBackupWasFound(HealthyBackupWasFound $event)
    {
        BackupHistory::create([
            'type' => 'monitor',
            'status' => 'success',
            'message' => 'Backup health check passed for: '.$event->backupDestination->backupName(),
        ]);
    }

    /**
     * Handle unhealthy backup found event.
     */
    public function handleUnhealthyBackupWasFound(UnhealthyBackupWasFound $event)
    {
        BackupHistory::create([
            'type' => 'monitor',
            'status' => 'failed',
            'message' => 'Unhealthy backup found: '.$event->backupDestination->backupName().'. Reason: '.$event->failReason,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     * @return void
     */
    public function subscribe($events)
    {
        $events->listen(
            BackupWasSuccessful::class,
            [BackupEventSubscriber::class, 'handleBackupWasSuccessful']
        );

        $events->listen(
            BackupHasFailed::class,
            [BackupEventSubscriber::class, 'handleBackupHasFailed']
        );

        $events->listen(
            CleanupWasSuccessful::class,
            [BackupEventSubscriber::class, 'handleCleanupWasSuccessful']
        );

        $events->listen(
            CleanupHasFailed::class,
            [BackupEventSubscriber::class, 'handleCleanupHasFailed']
        );

        $events->listen(
            HealthyBackupWasFound::class,
            [BackupEventSubscriber::class, 'handleHealthyBackupWasFound']
        );

        $events->listen(
            UnhealthyBackupWasFound::class,
            [BackupEventSubscriber::class, 'handleUnhealthyBackupWasFound']
        );
    }
}
