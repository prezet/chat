<?php

namespace App\Actions;

use App\Models\Message;

class HydrateChat
{
    /**
     * Return all messages in the shape required by the AI SDK.
     *
     * @param  string  $chatId
     * @return array   - final collection of messages with tool invocations merged
     */
    public static function handle(string $chatId): array
    {
        $messages = Message::where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Transform each message to match the AI SDK shape
        return $messages->map(function (Message $msg) {
            return [
                'id'        => $msg->id,
                'role'      => $msg->role,
                'content'   => $msg->content,
                'createdAt' => $msg->created_at->toISOString(),
                'parts'     => $msg->parts,
            ];
        })->toArray();
    }
}
