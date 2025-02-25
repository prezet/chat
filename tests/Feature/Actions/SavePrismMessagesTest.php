<?php

namespace Tests\Feature\Actions;

use App\Actions\SavePrismMessages;
use App\Models\Chat;
use App\Models\Message;
use EchoLabs\Prism\Enums\FinishReason;
use EchoLabs\Prism\ValueObjects\ProviderResponse;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Text\Response;
use EchoLabs\Prism\ValueObjects\ResponseMeta;
use EchoLabs\Prism\ValueObjects\ToolCall;
use EchoLabs\Prism\ValueObjects\ToolResult;
use EchoLabs\Prism\ValueObjects\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * Helpers to create a Chat and a fresh instance of SavePrismMessages.
 */
function createChat(): Chat
{
    return Chat::create(['id' => (string) Str::uuid()]);
}

function createSaveAction(): SavePrismMessages
{
    return new SavePrismMessages();
}

it('stores final LLM text as an assistant message when no tool results exist', function () {
    $chat = createChat();

    // Simulate a completed response with final text but no tool calls/results.
    $prismResponse = new Response(
        steps: collect([]),
        responseMessages: collect([]),
        text: 'Final answer from the AI assistant',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 25),
        responseMeta: new ResponseMeta('fake-1', 'fake-model'),
        messages: collect([])
    );

    $lazyMessages = createSaveAction()->handle($prismResponse, $chat);
    $messages = $lazyMessages->collect(); // Force lazy iteration to standard Collection

    // Assertions
    // Ensure one database record was created, with role = 'assistant' and correct text
    expect($chat->messages->count())->toBe(1);
    $msg = $chat->messages->first();
    expect($msg->role)->toBe('assistant')
        ->and($msg->content)->toBe('Final answer from the AI assistant')
        ->and($msg->parts)->toBeArray()->toHaveCount(1)
        ->and($messages)->toBeInstanceOf(Collection::class)
        ->and($messages)->toHaveCount(1)
        ->and($messages->first()->id)->toBe($msg->id);

    // The handle() method should return the newly created messages
});

it('stores each tool result as a separate assistant message with tool call/result parts', function () {
    $chat = createChat();

    // Create 2 pairs of tool calls/results in the response
    $toolCalls = [
        new ToolCall(
            id: 'call_1',
            name: 'search',
            arguments: ['query' => 'Latest news']
        ),
        new ToolCall(
            id: 'call_2',
            name: 'weather',
            arguments: ['city' => 'Berlin']
        ),
    ];

    $toolResults = [
        new ToolResult(
            toolCallId: 'call_1',
            toolName: 'search',
            args: ['query' => 'Latest news'],
            result: 'Found 2 articles about your query.'
        ),
        new ToolResult(
            toolCallId: 'call_2',
            toolName: 'weather',
            args: ['city' => 'Berlin'],
            result: 'It is sunny in Berlin.'
        ),
    ];

    // Response text is left empty to illustrate a scenario where the AI uses only tools
    // but does not produce immediate final text.
    $prismResponse = new Response(
        steps: collect([]),
        responseMessages: collect([]),
        text: '',
        finishReason: FinishReason::Stop,
        toolCalls: $toolCalls,
        toolResults: $toolResults,
        usage: new Usage(5, 10),
        responseMeta: new ResponseMeta('fake-1', 'fake-model'),
        messages: collect([]),
    );

    $lazyMessages = createSaveAction()->handle($prismResponse, $chat);
    $messages = $lazyMessages->collect(); // Force lazy iteration to standard Collection

    // The result should be two messages in the DB (one for each tool call + tool result).
    expect($chat->messages->count())->toBe(2);

    /** @var Message[] $stored */
    $stored = $chat->messages()->orderBy('created_at')->get()->all();

    // First data message
    expect($stored[0]->role)->toBe('assistant')
        ->and($stored[0]->content)->toBe('')
        ->and($stored[0]->parts)->toHaveCount(2);

    $callPart1 = $stored[0]->parts[0]['toolInvocation'];
    $resultPart1 = $stored[0]->parts[1]['toolInvocation'];

    expect($callPart1['state'])->toBe('call')
        ->and($callPart1['toolCallId'])->toBe('call_1')
        ->and($callPart1['toolName'])->toBe('search')
        ->and($callPart1['args'])->toBe(['query' => 'Latest news'])
        ->and($resultPart1['state'])->toBe('result')
        ->and($resultPart1['toolCallId'])->toBe('call_1')
        ->and($resultPart1['toolName'])->toBe('search')
        ->and($resultPart1['result'])->toBe('Found 2 articles about your query.');

    // Second data message
    $callPart2 = $stored[1]->parts[0]['toolInvocation'];
    $resultPart2 = $stored[1]->parts[1]['toolInvocation'];

    expect($callPart2['state'])->toBe('call')
        ->and($callPart2['toolCallId'])->toBe('call_2')
        ->and($callPart2['toolName'])->toBe('weather')
        ->and($resultPart2['state'])->toBe('result')
        ->and($resultPart2['toolCallId'])->toBe('call_2')
        ->and($resultPart2['toolName'])->toBe('weather')
        ->and($resultPart2['result'])->toBe('It is sunny in Berlin.')
        ->and($messages)->toBeInstanceOf(Collection::class)
        ->and($messages)->toHaveCount(2);

    // The handle() method should return these two new messages
});

it('stores both final text and multiple tool results', function () {
    $chat = createChat();

    // Mix: final text from AI, plus one tool result
    $toolCalls = [
        new ToolCall(
            id: 'call_3',
            name: 'mathSolver',
            arguments: ['equation' => 'x^2 + 2x - 8 = 0']
        ),
    ];

    $toolResults = [
        new ToolResult(
            toolCallId: 'call_3',
            toolName: 'mathSolver',
            args: ['equation' => 'x^2 + 2x - 8 = 0'],
            result: 'x=2 or x=-4'
        ),
    ];

    $prismResponse = new Response(
        steps: collect([]),
        responseMessages: collect([]),
        text: 'The quadratic equation is solved. The roots are 2 and -4.',
        finishReason: FinishReason::Stop,
        toolCalls: $toolCalls,
        toolResults: $toolResults,
        usage: new Usage(10, 20),
        responseMeta: new ResponseMeta('fake-1', 'fake-model'),
        messages: collect([]),
    );

    $lazyMessages = createSaveAction()->handle($prismResponse, $chat);
    $messages = $lazyMessages->collect(); // Force lazy iteration to standard Collection

    // Expect 2 messages in DB: the final LLM text as an assistant message, plus a data message for the tool result
    expect($chat->messages->count())->toBe(2);

    /** @var Message[] $stored */
    $stored = $chat->messages()->orderBy('created_at')->get()->all();

    // 1) Assistant message with final LLM text
    expect($stored[0]->role)->toBe('assistant')
        ->and($stored[0]->content)->toBe('The quadratic equation is solved. The roots are 2 and -4.')
        ->and($stored[0]->parts)->toHaveCount(1)
        ->and($stored[0]->parts[0]['text'])->toBe('The quadratic equation is solved. The roots are 2 and -4.')
        ->and($stored[1]->role)->toBe('assistant')
        ->and($stored[1]->content)->toBe('')
        ->and($stored[1]->parts)->toHaveCount(2);

    // 2) Data message for the tool call + result

    $callPart = $stored[1]->parts[0]['toolInvocation'];
    $resultPart = $stored[1]->parts[1]['toolInvocation'];

    expect($callPart['state'])->toBe('call')
        ->and($callPart['toolCallId'])->toBe('call_3')
        ->and($callPart['toolName'])->toBe('mathSolver')
        ->and($resultPart['state'])->toBe('result')
        ->and($resultPart['toolCallId'])->toBe('call_3')
        ->and($resultPart['result'])->toBe('x=2 or x=-4')
        ->and($messages)->toBeInstanceOf(Collection::class)
        ->and($messages)->toHaveCount(2);

    // Confirm handle() returns both newly created messages
});

it('does not create an assistant message if the LLM text is empty and there are no tool results', function () {
    $chat = createChat();

    $prismResponse = new Response(
        steps: collect([]),
        responseMessages: collect([]),
        text: '',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(0, 0),
        responseMeta: new ResponseMeta('fake-1', 'fake-model'),
        messages: collect([]),
    );

    $lazyMessages = createSaveAction()->handle($prismResponse, $chat);
    $messages = $lazyMessages->collect(); // Force lazy iteration to standard Collection

    // No DB entries expected
    expect($chat->messages->count())->toBe(0);
    // handle() should return empty array
    expect($messages)->toBeInstanceOf(Collection::class)
        ->and($messages)->toBeEmpty();
});

it('can integrate with Prism::fake to simulate a response and then store it', function () {
    $chat = createChat();

    // 1) Build a fake provider response to simulate an LLM answer
    $fakeResponse = new ProviderResponse(
        text: 'Fake AI text from the LLM',
        toolCalls: [
            new ToolCall('call_42', 'googleSearch', ['query' => 'Eiffel Tower hours']),
        ],
        usage: new Usage(10, 20),
        finishReason: FinishReason::Stop,
        responseMeta: new ResponseMeta('fake-1', 'fake-model')
    );

    // 2) Set up the fake
    $fake = Prism::fake([$fakeResponse]);

    // 3) Generate the response
    $response = Prism::text()
        ->using('anthropic', 'claude-3-sonnet')
        ->withPrompt('Where can I find Eiffel Tower hours?')
        ->generate();

    // Confirm the text and tool calls
    expect($response->text)->toBe('Fake AI text from the LLM');
    expect($response->toolCalls)->toHaveCount(1);

    // 4) Pass the response to SavePrismMessages
    $lazyMessages = createSaveAction()->handle($response, $chat);
    $created = $lazyMessages->collect(); // Force lazy iteration to standard Collection

    // 5) Assertions
    //   - One assistant message with text
    //   - One data message for the tool call (but in this example the fake ProviderResponse
    //     has the tool call, not a tool result. So no separate "result" data message is created.)
    expect($chat->messages->count())->toBe(1)
        ->and($created)->toBeInstanceOf(Collection::class)
        ->and($created)->toHaveCount(1);

    $assistant = $chat->messages->first();
    expect($assistant->role)->toBe('assistant')
        ->and($assistant->content)->toBe('Fake AI text from the LLM')
        ->and($assistant->parts[0]['text'])->toBe('Fake AI text from the LLM');
});
