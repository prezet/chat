<?php

use App\Actions\RunChat;
use App\Models\Chat;
use App\Models\Message;
use EchoLabs\Prism\Enums\FinishReason;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Text\Response as TextResponse;
use EchoLabs\Prism\ValueObjects\ResponseMeta;
use EchoLabs\Prism\ValueObjects\ToolCall;
use EchoLabs\Prism\ValueObjects\ToolResult;
use EchoLabs\Prism\ValueObjects\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('processes a simple chat message and returns AI response', function () {
    $chat = Chat::create(['id' => (string) Str::uuid()]);

    // Create a test message
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => 'What is Laravel?',
        'parts' => ['text' => 'What is Laravel?'],
        'created_at' => now(),
    ]);

    // Create a fake provider response
    $fakeResponse = new TextResponse(
        steps: collect([]),
        responseMessages: collect([]),
        text: 'Laravel is a PHP web application framework.',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
        responseMeta: new ResponseMeta('fake-1', 'fake-model'),
        messages: collect([]),
        additionalContent: []
    );

    // Set up the fake
    $fake = Prism::fake([$fakeResponse]);

    $lazyResult = app(RunChat::class)->handle($chat);
    expect($lazyResult)->toBeInstanceOf(LazyCollection::class);
    $result = $lazyResult->collect(); // Force iteration to standard Collection

    expect($result)
        ->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(1)
        ->and($result->first()->content)->toBe('Laravel is a PHP web application framework.')
        ->and($result->first()->role)->toBe('assistant');

    // Verify Prism interaction
    $fake->assertCallCount(1);
});

it('handles multi-step chat with tool usage', function () {
    $chat = Chat::create(['id' => (string) Str::uuid()]);

    // Fake the weather API response
    Http::fake([
        'api.open-meteo.com/v1/forecast*' => Http::response([
            'current' => [
                'temperature_2m' => 22.5, // 72.5°F
            ],
            'daily' => [
                'sunrise' => ['2024-03-20T06:15'],
                'sunset' => ['2024-03-20T18:30'],
            ],
            'hourly' => [
                'temperature_2m' => array_fill(0, 24, 22.5),
            ],
            'timezone' => 'Europe/Paris',
        ], 200),
    ]);

    // Create initial user message
    Message::create([
        'id' => (string) Str::uuid(),
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => "What's the weather in Paris?",
        'parts' => ['text' => "What's the weather in Paris?"],
        'created_at' => now(),
    ]);

    // Define the expected tool call and response sequence
    $responses = [
        // First response: AI decides to use the weather tool
        new TextResponse(
            steps: collect([]),
            responseMessages: collect([]),
            text: '',
            finishReason: FinishReason::ToolCalls,
            toolCalls: [
                new ToolCall(
                    id: 'call_123',
                    name: 'getWeather',
                    arguments: ['latitude' => 48.8566, 'longitude' => 2.3522]
                ),
            ],
            toolResults: [
                new ToolResult(
                    toolCallId: 'call_123',
                    toolName: 'getWeather',
                    args: ['latitude' => 48.8566, 'longitude' => 2.3522],
                    result: 'It is sunny in Berlin.'
                ),
            ],
            usage: new Usage(15, 25),
            responseMeta: new ResponseMeta('fake-1', 'fake-model'),
            messages: collect([]),
            additionalContent: []
        ),
        // Second response: AI uses the tool result
        new TextResponse(
            steps: collect([]),
            responseMessages: collect([]),
            text: 'The weather in Paris is currently sunny with a temperature of 72°F.',
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(20, 30),
            responseMeta: new ResponseMeta('fake-2', 'fake-model'),
            messages: collect([]),
            additionalContent: []
        ),
    ];

    // Set up the fake
    $fake = Prism::fake($responses);

    $lazyResult = app(RunChat::class)->handle($chat);
    $result = $lazyResult->collect(); // Force iteration to standard Collection

    // Assert multiple responses were processed
    expect($result)
        ->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(2)
        ->and($result[0]->parts[0]['toolInvocation']['toolName'])->toBe('getWeather')
        ->and($result[0]->parts[0]['toolInvocation']['args'])->toBe(['latitude' => 48.8566, 'longitude' => 2.3522])
        ->and($result[1]->content)
        ->toBe('The weather in Paris is currently sunny with a temperature of 72°F.');

    // Verify tool call message

    // Verify final response

    // Verify Prism interactions
    $fake->assertCallCount(2);
});

it('handles empty chat history gracefully', function () {
    $chat = Chat::create(['id' => (string) Str::uuid()]);

    // Create a fake empty response
    $fakeResponse = new TextResponse(
        steps: collect([]),
        responseMessages: collect([]),
        text: '',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(0, 0),
        responseMeta: new ResponseMeta('fake-1', 'fake-model'),
        messages: collect([]),
        additionalContent: []
    );

    // Set up the fake
    $fake = Prism::fake([$fakeResponse]);

    $result = app(RunChat::class)->handle($chat);

    expect($result)
        ->toBeInstanceOf(LazyCollection::class)
        ->and($result)->toBeEmpty();

    // Verify no Prism calls were made
    $fake->assertCallCount(1);
});
