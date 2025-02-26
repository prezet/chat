<?php

use App\Actions\HydrateChat;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns an empty array when no messages exist for the chat', function () {
    $chat = Chat::factory()->create();

    $result = HydrateChat::handle($chat->id);

    expect($result)->toBeEmpty();
});

it('returns messages in the correct shape and order', function () {
    // Create a chat.
    $chat = Chat::factory()->create();

    // Create messages with increasing creation times.
    $message1 = Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => 'Hello',
        'parts' => ['text' => 'Hello'],
        'created_at' => Carbon::now()->subMinutes(2),
    ]);

    $message2 = Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'assistant',
        'content' => 'Hi there!',
        'parts' => ['text' => 'Hi there!'],
        'created_at' => Carbon::now()->subMinute(),
    ]);

    // Invoke the HydrateChat action.
    $result = HydrateChat::handle($chat->id);

    // Verify the count.
    expect($result)->toHaveCount(2);

    // Assert that each message has the expected structure and order.
    expect($result[0]['id'])->toBe($message1->id)
        ->and($result[0]['role'])->toBe($message1->role)
        ->and($result[0]['content'])->toBe($message1->content)
        ->and($result[0]['parts'])->toBe($message1->parts)
        ->and($result[0]['createdAt'])->toBe($message1->created_at->toISOString());

    expect($result[1]['id'])->toBe($message2->id)
        ->and($result[1]['role'])->toBe($message2->role)
        ->and($result[1]['content'])->toBe($message2->content)
        ->and($result[1]['parts'])->toBe($message2->parts)
        ->and($result[1]['createdAt'])->toBe($message2->created_at->toISOString());
});
