<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/**
 * Queued through Mail::queue(), which wraps it in SendQueuedMailable.
 */
class QueuedMailable extends Mailable implements ShouldQueue
{
    use Queueable;

    public function build(): self
    {
        return $this->subject('Queued mailable')->html('<p>Queued mailable</p>');
    }
}
