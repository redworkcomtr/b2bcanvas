<?php

namespace App\Jobs;

use App\Mail\B2BNotificationMail;
use App\Models\NotificationMailLog;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNotificationMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $logId,
        public string $recipient,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $log = NotificationMailLog::query()->findOrFail($this->logId);

        if ($log->status === 'sent') {
            return;
        }

        $log->increment('attempts');

        if ($log->status === 'failed' && $log->attempts >= $log->max_attempts) {
            throw new Exception('Notification mail has exceeded retry limit.');
        }

        try {
            Mail::to($this->recipient)->send(new B2BNotificationMail($log));
            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Exception $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            if ($log->attempts >= $log->max_attempts) {
                throw $exception;
            }

            throw $exception;
        }
    }
}
