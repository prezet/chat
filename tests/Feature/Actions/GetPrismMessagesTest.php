<?php

use App\Actions\GetPrismMessages;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use EchoLabs\Prism\ValueObjects\Messages\AssistantMessage;
use EchoLabs\Prism\ValueObjects\Messages\SystemMessage;
use EchoLabs\Prism\ValueObjects\Messages\ToolResultMessage;
use EchoLabs\Prism\ValueObjects\Messages\UserMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('converts a chat with system, user, and assistant messages with tool parts into Prism messages', function () {
    // Create a chat
    $chat = Chat::factory()->create();

    // Create a system message.
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'system',
        'content' => 'System content example',
        'parts' => [],
    ]);

    // Create a user message.
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => 'User question',
        'parts' => [],
    ]);

    // Create an assistant message that includes both a tool call and a tool result.
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'assistant',
        'content' => 'Assistant answer',
        'parts' => [
            [
                'type' => 'tool-invocation',
                'toolInvocation' => [
                    'state' => 'call',
                    'toolCallId' => 'call_1',
                    'toolName' => 'search',
                    'args' => ['query' => 'Latest news'],
                ],
            ],
            [
                'type' => 'tool-invocation',
                'toolInvocation' => [
                    'state' => 'result',
                    'toolCallId' => 'call_1',
                    'toolName' => 'search',
                    'args' => ['query' => 'Latest news'],
                    'result' => ['articles' => ['Article 1', 'Article 2']],
                ],
            ],
        ],
    ]);

    // Run the action.
    $messages = app(GetPrismMessages::class)->handle($chat);

    // Expect four Prism messages:
    //   1. SystemMessage
    //   2. UserMessage
    //   3. AssistantMessage
    //   4. ToolResultMessage (from the assistant message)
    expect($messages)->toHaveCount(4)
        ->and($messages[0])->toBeInstanceOf(SystemMessage::class)
        ->and($messages[0]->content)->toBe('System content example')
        ->and($messages[1])->toBeInstanceOf(UserMessage::class)
        ->and($messages[1]->text())->toBe('User question')
        ->and($messages[2])->toBeInstanceOf(AssistantMessage::class)
        ->and($messages[2]->content)->toBe('Assistant answer')
        ->and($messages[3])->toBeInstanceOf(ToolResultMessage::class)
        ->and($messages[3]->toolResults)->toHaveCount(1)
        ->and($messages[3]->toolResults[0]->toolCallId)->toBe('call_1')
        ->and($messages[3]->toolResults[0]->toolName)->toBe('search')
        ->and($messages[3]->toolResults[0]->args)->toBe(['query' => 'Latest news'])
        ->and($messages[3]->toolResults[0]->result)
        ->toBe(json_encode(['articles' => ['Article 1', 'Article 2']]));
});

it('converts an assistant message without tool invocations into a single Prism message', function () {
    $chat = Chat::create(['id' => (string) Str::uuid()]);

    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'assistant',
        'content' => 'Assistant answer without tools',
        'parts' => [], // No tool parts
    ]);

    $messages = app(GetPrismMessages::class)->handle($chat);

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toBeInstanceOf(AssistantMessage::class)
        ->and($messages[0]->content)->toBe('Assistant answer without tools');
});

it('maintains the correct order of messages', function () {
    $chat = Chat::create(['id' => (string) Str::uuid()]);

    // Create messages in a specific order.
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'system',
        'content' => 'System message',
        'parts' => [],
    ]);
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => 'User message',
        'parts' => [],
    ]);
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'assistant',
        'content' => 'Assistant message with no tools',
        'parts' => [],
    ]);

    $messages = app(GetPrismMessages::class)->handle($chat);

    // Verify the order: System, User, Assistant.
    expect($messages[0])->toBeInstanceOf(SystemMessage::class)
        ->and($messages[1])->toBeInstanceOf(UserMessage::class)
        ->and($messages[2])->toBeInstanceOf(AssistantMessage::class);
});
