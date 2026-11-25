<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\PushSubscription;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PapFixtures;
use Tests\TestCase;

/** Push notifikace technikům */
class PushTest extends TestCase
{
    use PapFixtures, RefreshDatabase;

    private array $sub = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        'keys' => [
            'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
            'auth' => 'tBHItJI5svbpez7KI4CCXg',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPap();
    }

    public function test_vapid_keys_are_generated_once_and_reused(): void
    {
        Sanctum::actingAs($this->tech1);
        $k1 = $this->getJson('/api/v1/push/key')->assertOk()->json('publicKey');
        $k2 = $this->getJson('/api/v1/push/key')->json('publicKey');

        $this->assertNotEmpty($k1);
        $this->assertSame($k1, $k2);
        Storage::disk('local')->assertExists('vapid.json');
    }

    public function test_subscribe_is_idempotent_and_unsubscribe_removes(): void
    {
        Sanctum::actingAs($this->tech1);
        $this->postJson('/api/v1/push/subscribe', $this->sub)->assertOk();
        $this->postJson('/api/v1/push/subscribe', $this->sub)->assertOk();
        $this->assertSame(1, PushSubscription::where('user_id', $this->tech1->id)->count());

        $this->postJson('/api/v1/push/unsubscribe', ['endpoint' => $this->sub['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_assignment_notifies_technician_once(): void
    {
        Queue::fake();
        $ticket = $this->makeTicket($this->locA);

        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['technician_id' => $this->tech1->id])->assertOk();
        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['technician_id' => $this->tech1->id])->assertOk();

        Queue::assertPushed(SendPushNotification::class, 1);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->tech1->id
            && str_contains($job->payload['title'], $ticket->number)
            && $job->payload['url'] === '/technik/#/t/'.$ticket->id);

        // přeřazení na jiného technika = nová notifikace pro něj
        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", ['technician_id' => $this->tech2->id])->assertOk();
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->tech2->id);
    }

    public function test_returned_visit_and_cancel_notify_technician(): void
    {
        Queue::fake();
        $ticket = $this->makeTicket($this->locA, $this->tech1);
        $uuid = $this->uuid();

        Sanctum::actingAs($this->tech1);
        $this->putJson("/api/v1/tickets/{$ticket->id}/visits/$uuid", [
            'arrived_at' => now()->subHour()->toIso8601String(), 'km' => 3, 'work_description' => 'X', 'test_result' => 'ok',
        ]);
        $this->postJson("/api/v1/visits/$uuid/submit")->assertOk();

        Sanctum::actingAs($this->dispecer);
        $this->postJson("/api/v1/visits/$uuid/return", ['note' => 'Chybí fotka zámku'])->assertOk();
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->tech1->id
            && $job->payload['body'] === 'Chybí fotka zámku');

        $this->postJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'cancelled'])->assertOk();
        Queue::assertPushed(SendPushNotification::class, 2);
    }

    public function test_job_skips_inactive_user(): void
    {
        $this->tech1->update(['is_active' => false]);
        $mock = $this->mock(PushService::class);
        $mock->shouldNotReceive('sendToUser');

        (new SendPushNotification($this->tech1->id, ['title' => 'x', 'body' => 'y']))->handle($mock);
    }
}
