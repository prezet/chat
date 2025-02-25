<?php

namespace App\Actions;

use App\Models\Chat;
use App\Tools\WeatherTool;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Enums\ToolChoice;
use EchoLabs\Prism\Enums\FinishReason;
use App\Tools\FlightSearchTool;
use App\Tools\FlightBookingTool;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use App\Models\Message;

class RunChat
{
    /**
     * Process the chat in multiple steps.
     * Returns a lazy collection that yields messages as they are saved.
     * The final message will have a special 'is_final' property set to true.
     *
     * @param Chat $chat
     * @return LazyCollection
     */
    public static function handle(Chat $chat): LazyCollection
    {
//        $searchTool = new FlightSearchTool();
//        $bookTool   = new FlightBookingTool();
        return LazyCollection::make(function () use ($chat) {
            $weatherTool = new WeatherTool();
            $maxSteps = 5;

            for ($step = 1; $step <= $maxSteps; $step++) {
                // Refresh the message list each iteration so the LLM sees the latest conversation.
                $prismMessages = GetPrismMessages::handle($chat);

                try {
                    $response = Prism::text()
                        ->using(config('prism.provider'), config('prism.model'))
                        ->withMessages($prismMessages)
                        ->withTools([$weatherTool])
                        ->withToolChoice(ToolChoice::Auto)
                        ->generate();

                    // Yield each message as it's saved
                    foreach ((new SavePrismMessages)->handle($response, $chat) as $message) {
                        yield $message;
                    }

                    // Exit loop if last message was not a tool call
                    if ($response->finishReason !== FinishReason::ToolCalls) {
                        break;
                    }
                } catch (\Exception $e) {
                    // Yield the error message
                    yield static::makeErrorMessage($chat, $e);
                    break;
                }
            }
        });
    }

    /**
     * Create an error message instance.
     *
     * @param Chat $chat
     * @param \Exception $e
     * @return Message
     */
    protected static function makeErrorMessage(Chat $chat, \Exception $e): Message
    {
        return Message::make([
            'id' => (string) Str::uuid(),
            'chat_id' => $chat->id,
            'role' => 'assistant',
            'content' => $e->getMessage(),
            'parts' => [
                [
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]
            ],
            'metadata' => [
                'finishReason' => 'Error',
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0],
            ]
        ]);
    }
}
