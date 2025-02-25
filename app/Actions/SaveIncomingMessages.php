<?php

namespace App\Actions;

use App\Models\Message;

class SaveIncomingMessages
{
    /**
     * Merge new messages from the client with the database
     *
     * @param  string  $chatId
     * @param  array   $incomingMessages  - array of messages from the client
     * @return void
     */
    public static function handle(string $chatId, array $incomingMessages): void
    {
        // 1. Load existing messages from the database (asc by creation time).
        $existingMessages = Message::where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->keyBy('id'); // for quick lookup by message ID

        // 2. Insert any new messages
        foreach ($incomingMessages as $msg) {
            if($existingMessages->has($msg['id'])){
                continue;
            }

            // Create a new record
            $newMessage = new Message([
                'id'        => $msg['id'],
                'chat_id'   => $chatId,
                'role'      => $msg['role'],
                'content'   => $msg['content'],
                // new client messages only have text parts
                'parts'     => [
                    ['text' => $msg['content']]
                ],
                'created_at'=> $msg['createdAt'],
            ]);
            $newMessage->save();
        }
    }
}
