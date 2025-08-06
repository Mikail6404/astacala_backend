<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisasterReportSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $report;

    /**
     * Create a new event instance.
     */
    public function __construct($report)
    {
        $this->report = $report;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('admin-dashboard'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'disaster-report-submitted';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'report_id' => $this->report->id,
            'title' => $this->report->title,
            'location' => $this->report->location_name ?? 'Unknown Location',
            'severity' => $this->report->severity_level,
            'disaster_type' => $this->report->disaster_type,
            'submitted_at' => $this->report->created_at->toISOString(),
            'reporter_name' => $this->report->reporter->name ?? 'Anonymous',
            'coordinates' => [
                'latitude' => $this->report->latitude,
                'longitude' => $this->report->longitude,
            ],
        ];
    }
}
