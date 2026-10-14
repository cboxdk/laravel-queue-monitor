<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Queued through Notification::send(), which wraps it in SendQueuedNotifications.
 */
class QueuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['monitor-test'];
    }
}
