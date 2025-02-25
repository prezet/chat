<?php

namespace App\Http\Controllers;

use App\Actions\GetMessageStream;
use App\Actions\RunChat;
use App\Actions\SaveIncomingMessages;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\LazyCollection;

class ChatController extends Controller
{
    public function store(Request $request)
    {
        // Validate the incoming request.
        $payload = $request->validate([
            'id' => ['required', 'uuid', 'exists:chats,id'],
            'messages' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['nullable', 'string'],
            'messages.*.id' => ['required', 'uuid'],
            'messages.*.createdAt' => ['required', 'date'],
        ]);

        $chat = Chat::findOrFail($payload['id']);

        // Merge new messages from the client.
        SaveIncomingMessages::handle($chat->id, $payload['messages']);

        return response()->stream(function () use ($chat) {
            // Get the lazy collection of messages
            $messages = RunChat::handle($chat);

            foreach ($messages as $message) {
                $stream = GetMessageStream::handle($message);
                $this->sendStreamLines($stream);
            }

            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'x-vercel-ai-data-stream' => 'v1',
        ]);
    }

    /**
     * Send stream lines with proper SSE formatting
     */
    protected function sendStreamLines(array $stream)
    {
        foreach ($stream as $line) {
            echo $line . "\n";
            ob_flush();
            flush();
        }
    }
}
