<?php

namespace App\Jobs;

use App\Events\AlertCreated;
use App\Models\Event;
use App\Services\Detection\DetectionEngine;
use App\Services\Detection\EventIocMatcher;
use App\Support\Broadcasts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIngestedEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(DetectionEngine $engine, EventIocMatcher $matcher): void
    {
        $event = Event::find($this->eventId);
        if (! $event) {
            return;
        }
        try {
            $results = array_merge(
                $engine->evaluateEvent($event),
                $matcher->match($event, $engine)
            );
            $event->update(['processing_status' => 'processed']);
            foreach ($results as $res) {
                if ($res['notify']) {
                    Broadcasts::fire(new AlertCreated($res['alert']->fresh()));
                }
                if ($res['created']) {
                    EnrichAlertIndicators::dispatch($res['alert']->id);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('detection failed: '.$e->getMessage(), ['event' => $this->eventId]);
            $event->update(['processing_status' => 'detection_failed']);
        }
    }
}
