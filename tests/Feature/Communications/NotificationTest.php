<?php

namespace Tests\Feature\Communications;

use App\Modules\Communications\Models\CommNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_lists_and_marks_notification_read(): void
    {
        $user = auth()->user();
        $this->assertNotNull($user);

        $n = CommNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'TEST',
            'title' => 'Hola',
            'body' => 'Cuerpo',
        ]);

        $this->getJson('/api/communications/notifications')->assertOk()->assertJsonPath('data.0.title', 'Hola');

        $this->patchJson('/api/communications/notifications/'.$n->id)->assertOk()->assertJsonPath('id', $n->id);

        $n->refresh();
        $this->assertNotNull($n->read_at);
    }
}
