<?php

namespace App\Actions;

use App\Models\Chat;
use App\Models\Message as DbMessage;
use EchoLabs\Prism\Text\Response;
use EchoLabs\Prism\ValueObjects\ToolCall;
use EchoLabs\Prism\ValueObjects\ToolResult;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class SavePrismMessages
{
    /**
     * Save final LLM text as an `assistant` message, and store each tool result
     * as a separate `data` message.
     * Return a lazy collection that yields messages as they are saved.
     */
    public function handle(Response $response, Chat $chat): LazyCollection
    {
        return LazyCollection::make(function () use ($response, $chat) {
            // 1) Save assistant message if text is returned.
            if (! empty($response->text)) {
                $assistantMessage = new DbMessage([
                    'id' => (string) Str::uuid(),
                    'chat_id' => $chat->id,
                    'role' => 'assistant',
                    'content' => $response->text,
                    'parts' => [
                        ['type' => 'text', 'text' => $response->text],
                    ],
                    'metadata' => [
                        'usage' => $response->usage,
                        'finishReason' => $response->finishReason->name,
                        'responseMeta' => $response->responseMeta,
                    ],
                ]);
                $assistantMessage->save();
                yield $assistantMessage;
            }

            // 2) For each tool result, create a data message.
            foreach ($response->toolResults as $key => $toolResult) {
                $toolCall = $response->toolCalls[$key];
                $toolCallId = ! empty($toolResult->toolCallId)
                    ? $toolResult->toolCallId
                    : (string) Str::uuid();

                $dataMessage = new DbMessage([
                    'id' => (string) Str::uuid(),
                    'chat_id' => $chat->id,
                    'role' => 'assistant',
                    'content' => '',
                    'parts' => [
                        $this->buildToolCallPart($toolCall, $toolCallId),
                        $this->buildToolResultPart($toolResult, $toolCallId),
                    ],
                    'metadata' => [
                        'usage' => $response->usage,
                        'finishReason' => $response->finishReason->name,
                        'responseMeta' => $response->responseMeta,
                    ],
                ]);
                $dataMessage->save();
                yield $dataMessage;
            }
        });
    }

    protected function buildToolCallPart(ToolCall $toolCall, string $toolCallId): array
    {
        return [
            'type' => 'tool-invocation',
            'toolInvocation' => [
                'toolCallId' => $toolCallId,
                'toolName' => $toolCall->name,
                'args' => $toolCall->arguments(),
                'state' => 'call',
            ],
        ];
    }

    protected function buildToolResultPart(ToolResult $toolResult, string $toolCallId): array
    {
        $parsed = $this->tryJsonDecode($toolResult->result);

        return [
            'type' => 'tool-invocation',
            'toolInvocation' => [
                'toolCallId' => $toolCallId,
                'toolName' => $toolResult->toolName,
                'state' => 'result',
                'result' => $parsed,
            ],
        ];
    }

    protected function tryJsonDecode(mixed $data): mixed
    {
        if (! is_string($data)) {
            return $data;
        }

        $decoded = json_decode($data, true);

        return $decoded ?? $data;
    }
}
