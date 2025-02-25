<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Example 'chats' table
        Schema::create('chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampsTz();
        });

        // Example 'messages' table
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('chat_id');
            $table->enum('role', ['system', 'user', 'assistant', 'data']);
            $table->text('content');
            // Each part can be something like { "type": "text", "text": "..."}
            $table->jsonb('parts')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->foreign('chat_id')
                ->references('id')->on('chats')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('chats');
    }
};
