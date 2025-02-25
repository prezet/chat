<?php

namespace App\Actions;

use App\Models\Chat;
use ArrayObject;
use EchoLabs\Prism\ValueObjects\Messages\SystemMessage;
use EchoLabs\Prism\ValueObjects\Messages\UserMessage;
use EchoLabs\Prism\ValueObjects\Messages\AssistantMessage;
use EchoLabs\Prism\ValueObjects\Messages\ToolResultMessage;
use EchoLabs\Prism\ValueObjects\ToolCall;
use EchoLabs\Prism\ValueObjects\ToolResult;

class GetPrismMessages
{
    /**
     * Convert chat messages (and any related tool invocations) into Prism message objects.
     */
    public static function handle(Chat $chat): array
    {
        // Retrieve all messages in ascending chronological order
        $messages = $chat->messages()->orderBy('created_at')->get();

        return $messages->flatMap(function ($message) {
            $results = [];

            switch ($message->role) {
                case 'system':
                    $results[] = new SystemMessage($message->content);
                    break;

                case 'user':
                    $results[] = new UserMessage($message->content);
                    break;

                case 'assistant':
                    // Always add the assistant’s answer.
                    $results[] = new AssistantMessage(
                        $message->content,
                        self::getToolCalls($message->parts)
                    );

                    // If any tool results exist, add a separate message for them.
                    $toolResults = self::getToolResults($message->parts);
                    if (!empty($toolResults)) {
                        $results[] = new ToolResultMessage($toolResults);
                    }
                    break;
            }

            return $results;
        })->toArray();
    }

    protected static function getToolCalls(array|ArrayObject $parts): array
    {
        $toolCalls = [];

        foreach ($parts as $part) {
            if($part['type'] !== 'tool-invocation') {
                continue;
            }

            $toolInvocation = $part['toolInvocation'];

            if($toolInvocation['state'] !== 'call') {
                continue;
            }

            $toolCalls[] = new ToolCall(
                id: $toolInvocation['toolCallId'],
                name: $toolInvocation['toolName'],
                arguments: $toolInvocation['args'] ?? []
            );
        }

        return $toolCalls;
    }

    protected static function getToolResults(array|ArrayObject $parts): array
    {
        $toolResults = [];

        foreach ($parts as $part) {
            if($part['type'] !== 'tool-invocation') {
                continue;
            }

            $toolInvocation = $part['toolInvocation'];

            if($toolInvocation['state'] !== 'result') {
                continue;
            }

            $toolResults[] = new ToolResult(
                toolCallId: $toolInvocation['toolCallId'],
                toolName: $toolInvocation['toolName'],
                args: $toolInvocation['args'] ?? [],
                result: json_encode($toolInvocation['result'])
            );
        }

        return $toolResults;
    }
}
