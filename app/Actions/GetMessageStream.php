<?php

namespace App\Actions;

use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class GetMessageStream
{
    /*
     * Protocol part prefixes.
     */
    private const PREFIX_START_STEP      = 'f:';
    private const PREFIX_TEXT            = '0:';
    private const PREFIX_TOOL_START      = 'b:';
    private const PREFIX_TOOL_DELTA      = 'c:';
    private const PREFIX_TOOL_CALL       = '9:';
    private const PREFIX_TOOL_RESULT     = 'a:';
    private const PREFIX_DATA            = '2:';
    private const PREFIX_ERROR           = '3:';
    private const PREFIX_ANNOTATION      = '8:';
    private const PREFIX_REASONING       = 'g:';
    private const PREFIX_FINISH_STEP     = 'e:';
    private const PREFIX_FINISH_MESSAGE  = 'd:';

    /*
     * Vercel AI SDK finish reasons
     */
    private const FINISH_REASON_STOP           = 'stop';
    private const FINISH_REASON_LENGTH         = 'length';
    private const FINISH_REASON_CONTENT_FILTER = 'content-filter';
    private const FINISH_REASON_TOOL_CALLS     = 'tool-calls';
    private const FINISH_REASON_ERROR          = 'error';
    private const FINISH_REASON_OTHER          = 'other';
    private const FINISH_REASON_UNKNOWN        = 'unknown';

    /**
     * Map Prism finish reasons to Vercel AI SDK finish reasons
     */
    private static function mapFinishReason(string $prismFinishReason): string
    {
        return match ($prismFinishReason) {
            'Stop' => self::FINISH_REASON_STOP,
            'Length' => self::FINISH_REASON_LENGTH,
            'ContentFilter' => self::FINISH_REASON_CONTENT_FILTER,
            'ToolCalls' => self::FINISH_REASON_TOOL_CALLS,
            'Error' => self::FINISH_REASON_ERROR,
            default => self::FINISH_REASON_UNKNOWN,
        };
    }

    /**
     * Convert a Eloquent Message into an array of lines
     * following the Vercel AI SDK stream protocol.
     *
     * Each message is processed in three phases:
     *   1. Start Step Part (f:): Signifies the start of processing a message.
     *   2. Content Parts: These can be text parts, tool invocations, data parts, etc.
     *   3. Finish Step Part (e:): Marks the end of the step.
     *   4. If finishReason is not tool-calls, add Finish Message Part (d:) to signal the end.
     *
     * @param Message $message
     * @return array<string>
     */
    public static function handle(Message $message): array
    {
        $lines = [];
        
        // 1. Start Step Part: Notify the beginning of processing for this message.
        $lines[] = self::formatStartStep($message->id);

        // 2. Process the message content:
        // If no structured parts exist, treat the entire content as a single text part.
        $parts = $message->parts ?? [];
        if (empty($parts)) {
            $lines[] = self::formatTextPart($message->content);
        } else {
            foreach ($parts as $part) {
                $lines = array_merge($lines, self::processPart($part));
            }
        }

        // Get metadata for usage and finish reason
        $metadata = $message->metadata;
        $finishReason = self::mapFinishReason($metadata['finishReason'] ?? 'Stop');
        $usage = $metadata['usage'] ?? ['promptTokens' => 0, 'completionTokens' => 0];
        $isContinued = $finishReason === self::FINISH_REASON_TOOL_CALLS;

        // 3. Finish Step Part: Mark the end of the step.
        $lines[] = self::formatFinishStep($finishReason, $usage, $isContinued);

        // 4. Add Finish Message Part if this is the final message (not a tool call)
        if (!$isContinued) {
            $lines[] = self::formatFinishMessage($finishReason, $usage);
        }

        return $lines;
    }

    /**
     * Format the start step part.
     */
    private static function formatStartStep(string $messageId): string
    {
        return self::PREFIX_START_STEP . json_encode(['messageId' => $messageId]);
    }

    /**
     * Format a text part.
     */
    private static function formatTextPart(string $text): string
    {
        return self::PREFIX_TEXT . json_encode($text);
    }

    /**
     * Format a data part.
     */
    private static function formatDataPart(array $data): string
    {
        return self::PREFIX_DATA . json_encode($data);
    }

    /**
     * Format an error part.
     */
    private static function formatErrorPart(string $errorMessage): string
    {
        return self::PREFIX_ERROR . json_encode($errorMessage);
    }

    /**
     * Format an annotation part.
     */
    private static function formatAnnotationPart(array $annotations): string
    {
        return self::PREFIX_ANNOTATION . json_encode($annotations);
    }

    /**
     * Format a reasoning part.
     */
    private static function formatReasoningPart(string $reasoning): string
    {
        return self::PREFIX_REASONING . json_encode($reasoning);
    }

    /**
     * Format the finish step part.
     *
     * @param string $finishReason The finish reason from metadata
     * @param array $usage The usage metrics from metadata
     * @param bool $isContinued Whether this step continues to the next one
     */
    private static function formatFinishStep(string $finishReason, array $usage, bool $isContinued): string
    {
        return self::PREFIX_FINISH_STEP . json_encode([
            'finishReason' => $finishReason,
            'usage' => [
                'promptTokens'     => $usage['promptTokens'] ?? 0,
                'completionTokens' => $usage['completionTokens'] ?? 0,
            ],
            'isContinued' => $isContinued,
        ]);
    }

    /**
     * Format the finish message part.
     */
    private static function formatFinishMessage(string $finishReason, array $usage): string
    {
        return self::PREFIX_FINISH_MESSAGE . json_encode([
            'finishReason' => $finishReason,
            'usage' => [
                'promptTokens'     => $usage['promptTokens'] ?? 0,
                'completionTokens' => $usage['completionTokens'] ?? 0,
            ],
        ]);
    }

    /**
     * Process an individual part based on its type.
     *
     * @param array $part
     * @return array<string> The stream lines for this part.
     */
    private static function processPart(array $part): array
    {
        $lines = [];
        $type = $part['type'] ?? '';

        switch ($type) {
            case 'text':
                $lines[] = self::formatTextPart($part['text'] ?? '');
                break;

            case 'tool-invocation':
                $lines = array_merge($lines, self::processToolInvocation($part['toolInvocation'] ?? []));
                break;

            case 'data':
                $lines[] = self::formatDataPart($part['data'] ?? []);
                break;

            case 'error':
                $lines[] = self::formatErrorPart($part['message'] ?? 'Unknown error');
                break;

            case 'annotation':
                $lines[] = self::formatAnnotationPart($part['annotations'] ?? []);
                break;

            case 'reasoning':
                $lines[] = self::formatReasoningPart($part['reasoning'] ?? '');
                break;

            default:
                // Fallback: if the part type is unrecognized but contains text, use it as a text part.
                if (!empty($part['text'])) {
                    $lines[] = self::formatTextPart($part['text']);
                }
                break;
        }

        return $lines;
    }

    /**
     * Process a tool invocation part.
     *
     * Depending on the 'state', the tool invocation may result in one or more stream lines.
     *
     * @param array $invocation
     * @return array<string>
     */
    private static function processToolInvocation(array $invocation): array
    {
        $lines = [];
        $state = $invocation['state'] ?? '';

        switch ($state) {
            case 'streamStart':
                $lines[] = self::PREFIX_TOOL_START . json_encode([
                        'toolCallId' => $invocation['toolCallId'] ?? 'call_' . uniqid(),
                        'toolName'   => $invocation['toolName'] ?? 'unknownTool',
                    ]);
                break;

            case 'partial-call':
                $lines[] = self::PREFIX_TOOL_DELTA . json_encode([
                        'toolCallId'    => $invocation['toolCallId'] ?? 'call_' . uniqid(),
                        'argsTextDelta' => $invocation['argsTextDelta'] ?? '',
                    ]);
                break;

            case 'call':
                $lines[] = self::PREFIX_TOOL_CALL . json_encode([
                        'toolCallId' => $invocation['toolCallId'],
                        'toolName'   => $invocation['toolName'] ?? 'unknownTool',
                        'args'       => $invocation['args'] ?? (object) [],
                    ]);
                break;

            case 'result':
                $lines[] = self::PREFIX_TOOL_RESULT . json_encode([
                        'toolCallId' => $invocation['toolCallId'],
                        'result'     => $invocation['result'] ?? (object) [],
                    ]);
                break;
        }

        return $lines;
    }
}
