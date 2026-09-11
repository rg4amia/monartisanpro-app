<?php

namespace Tests\Unit;

use App\Services\RealtimeEventService;
use Tests\TestCase;

class RealtimeEventServiceTest extends TestCase
{
    private RealtimeEventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RealtimeEventService();
    }

    public function test_it_creates_and_formats_realtime_event(): void
    {
        $payload = [
            'status' => 'in_progress',
            'action' => 'test',
        ];

        // L'appel crée l'événement ou le fallback simulé si la DB n'est pas active
        $event = $this->service->broadcast(999, 'mission_status', $payload);

        $this->assertEquals(999, $event->mission_id);
        $this->assertEquals('mission_status', $event->event_type);
        $this->assertEquals('in_progress', $event->payload_json['status']);
    }
}
