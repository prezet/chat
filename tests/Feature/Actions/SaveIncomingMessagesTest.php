<?php

use App\Actions\SaveIncomingMessages;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('saves new incoming messages to the database', function () {
    $chat = Chat::factory()->create();

    $incomingMessages = [
        [
            'id' => (string) Str::uuid(),
            'role' => 'user',
            'content' => 'Test message',
            'createdAt' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'role' => 'assistant',
            'content' => 'Response message',
            'createdAt' => now()->addMinute(),
        ],
    ];

    SaveIncomingMessages::handle($chat->id, $incomingMessages);

    // Verify that two messages exist in the database.
    expect(Message::where('chat_id', $chat->id)->count())->toBe(2);

    // Confirm each message is saved correctly.
    foreach ($incomingMessages as $incoming) {
        $message = Message::where('id', $incoming['id'])->first();
        expect($message)->not->toBeNull()
            ->and($message->chat_id)->toBe($chat->id)
            ->and($message->role)->toBe($incoming['role'])
            ->and($message->content)->toBe($incoming['content'])
            ->and($message->parts)->toBe([['text' => $incoming['content']]])
            ->and($message->created_at->timestamp)->toBe($incoming['createdAt']->timestamp);
    }
});

it('does not duplicate messages that already exist', function () {
    $chat = Chat::factory()->create();

    $existingMessageId = (string) Str::uuid();

    // Pre-create an existing message.
    Message::create([
        'id' => $existingMessageId,
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => 'Existing message',
        'parts' => ['text' => 'Existing message'],
        'created_at' => now(),
    ]);

    $incomingMessages = [
        [
            'id' => $existingMessageId, // duplicate entry
            'role' => 'user',
            'content' => 'Existing message',
            'createdAt' => now()->toISOString(),
        ],
        [
            'id' => (string) Str::uuid(), // new message
            'role' => 'assistant',
            'content' => 'New message',
            'createdAt' => now()->addMinute()->toISOString(),
        ],
    ];

    SaveIncomingMessages::handle($chat->id, $incomingMessages);

    // Verify that only one new message was added (total count becomes 2).
    expect($chat->messages)->count()->toBe(2);

    // Confirm that the new message has the correct details.
    $newMessage = $chat->messages()->where('id', '!=', $existingMessageId)->first();

    expect($newMessage)->not->toBeNull()
        ->and($newMessage->role)->toBe('assistant')
        ->and($newMessage->content)->toBe('New message')
        ->and($newMessage->parts)->toBe([['text' => 'New message']]);
});
