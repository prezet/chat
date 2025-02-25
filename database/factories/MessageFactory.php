<?php

namespace Database\Factories;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'chat_id' => (string) Str::uuid(),
            'role' => 'assistant',
            'content' => $this->faker->sentence(),
            'parts' => [],
            'metadata' => [
                'finishReason' => 'Stop',
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0],
            ],
        ];
    }

    /**
     * Indicate that the message has tool invocation parts.
     */
    public function withToolInvocation(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => '',
                'parts' => [
                    [
                        'type' => 'tool-invocation',
                        'toolInvocation' => [
                            'state' => 'partial-call',
                            'toolCallId' => 'call-1',
                            'toolName' => 'my-tool',
                        ],
                    ],
                    [
                        'type' => 'tool-invocation',
                        'toolInvocation' => [
                            'state' => 'call',
                            'toolCallId' => 'call-1',
                            'argsTextDelta' => 'partial arg',
                        ],
                    ],
                    [
                        'type' => 'tool-invocation',
                        'toolInvocation' => [
                            'state' => 'result',
                            'toolCallId' => 'call-1',
                            'result' => ['output' => 'result text'],
                        ],
                    ],
                ],
            ];
        });
    }

    /**
     * Indicate that the message has data parts.
     */
    public function withData(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => '',
                'parts' => [
                    [
                        'type' => 'data',
                        'data' => [
                            ['key' => 'value']
                        ],
                    ],
                ],
            ];
        });
    }

    /**
     * Indicate that the message has error parts.
     */
    public function withError(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => '',
                'parts' => [
                    [
                        'type' => 'error',
                        'message' => 'Something went wrong',
                    ],
                ],
            ];
        });
    }

    /**
     * Indicate that the message has annotation parts.
     */
    public function withAnnotation(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => '',
                'parts' => [
                    [
                        'type' => 'annotation',
                        'annotations' => [
                            ['id' => 'note-1', 'value' => 'Important'],
                        ],
                    ],
                ],
            ];
        });
    }

    /**
     * Indicate that the message has reasoning parts.
     */
    public function withReasoning(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => '',
                'parts' => [
                    [
                        'type' => 'reasoning',
                        'reasoning' => 'I think this is the right answer.',
                    ],
                ],
            ];
        });
    }
}
