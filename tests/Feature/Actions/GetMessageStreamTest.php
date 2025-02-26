<?php

use App\Actions\GetMessageStream;
use App\Models\Message;

it('converts a simple text message without parts', function () {
    $message = Message::factory()
        ->state([
            'id' => '123',
            'content' => 'Hello World',
            'parts' => [],
        ])
        ->make();

    $stream = GetMessageStream::handle($message);

    // Expect 4 lines:
    // 1. Start step part: f:{"id": "123"}
    // 2. Text part: 0:"Hello World"
    // 3. Finish step part: e:...
    // 4. Finish message part: d:...
    expect($stream)->toHaveCount(4)
        ->and($stream[0])->toStartWith('f:')
        ->and($stream[1])->toStartWith('0:')
        ->and($stream[2])->toStartWith('e:')
        ->and($stream[3])->toStartWith('d:');
});

it('omits the finish message part when finish reason is ToolCalls', function () {
    $message = Message::factory()
        ->state([
            'id' => '123',
            'content' => 'Hello World',
            'parts' => [],
            'metadata' => ['finishReason' => 'ToolCalls'],
        ])
        ->make();

    $stream = GetMessageStream::handle($message);

    // Without finish message part, expect 3 lines: f:, 0:, e:
    expect($stream)->toHaveCount(3)
        ->and($stream[2])->toStartWith('e:');
});

it('handles tool-invocation parts correctly', function () {
    $message = Message::factory()
        ->withToolInvocation()
        ->state(['id' => 'tool-message'])
        ->make();

    $stream = GetMessageStream::handle($message);

    // Expected order:
    // 0. f: message id.
    // 1. c: for partial-call.
    // 2. 9: for call.
    // 3. a: for result.
    // 4. e: finish tool-call.
    // 5. e: finish step.
    // 6. d: finish message.
    expect($stream[0])->toStartWith('f:')
        ->and($stream[1])->toStartWith('c:')
        ->and($stream[2])->toStartWith('9:')
        ->and($stream[3])->toStartWith('a:')
        ->and($stream[4])->toStartWith('e:')
        ->and($stream[5])->toStartWith('d:');
});

it('handles data parts correctly', function () {
    $message = Message::factory()
        ->withData()
        ->state(['id' => 'data-message'])
        ->make();

    $stream = GetMessageStream::handle($message);

    // Expected:
    // 0. f: step start.
    // 1. 2: for data part.
    // 2. e: finish step.
    // 3. d: finish message.
    expect($stream[0])->toStartWith('f:')
        ->and($stream[1])->toStartWith('2:');

    // Check that the JSON-decoded data is as expected.
    $dataPart = json_decode(substr($stream[1], 2), true);
    expect($dataPart)->toBeArray()
        ->and($dataPart[0]['key'])->toEqual('value')
        ->and($stream[2])->toStartWith('e:')
        ->and($stream[3])->toStartWith('d:');
});

it('handles error parts correctly', function () {
    $message = Message::factory()
        ->withError()
        ->state(['id' => 'error-message'])
        ->make();

    $stream = GetMessageStream::handle($message);

    // Expected:
    // 0. f: step start.
    // 1. 3: for error part.
    // 2. e: finish step.
    // 3. d: finish message.
    expect($stream[0])->toStartWith('f:')
        ->and($stream[1])->toStartWith('3:');

    $errorMessage = json_decode(substr($stream[1], 2), true);
    expect($errorMessage)->toEqual('Something went wrong')
        ->and($stream[2])->toStartWith('e:')
        ->and($stream[3])->toStartWith('d:');
});

it('handles annotation parts correctly', function () {
    $message = Message::factory()
        ->withAnnotation()
        ->state(['id' => 'annotation-message'])
        ->make();

    $stream = GetMessageStream::handle($message);

    // Expected:
    // 0. f: step start.
    // 1. 8: for annotation part.
    // 2. e: finish step.
    // 3. d: finish message.
    expect($stream[0])->toStartWith('f:')
        ->and($stream[1])->toStartWith('8:');

    $annotations = json_decode(substr($stream[1], 2), true);
    expect($annotations)->toBeArray()
        ->and($annotations[0]['id'])->toEqual('note-1')
        ->and($stream[2])->toStartWith('e:')
        ->and($stream[3])->toStartWith('d:');
});

it('handles reasoning parts correctly', function () {
    $message = Message::factory()
        ->withReasoning()
        ->state(['id' => 'reasoning-message'])
        ->make();

    $stream = GetMessageStream::handle($message);

    // Expected:
    // 0. f: step start.
    // 1. g: for reasoning part.
    // 2. e: finish step.
    // 3. d: finish message.
    expect($stream[0])->toStartWith('f:')
        ->and($stream[1])->toStartWith('g:');

    $reasoning = json_decode(substr($stream[1], 2), true);
    expect($reasoning)->toEqual('I think this is the right answer.')
        ->and($stream[2])->toStartWith('e:')
        ->and($stream[3])->toStartWith('d:');
});
