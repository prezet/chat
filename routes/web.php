<?php

use App\Actions\HydrateChat;
use App\Http\Controllers\ChatController;
use App\Models\Chat;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(callback: function () {
    Route::get('dashboard', function () {
        $chat = auth()->user()->chats()->create();

        return redirect()->route('dashboard.show', ['chat' => $chat->id]);
    })->name('dashboard');

    Route::get('/dashboard/{chat}', function (Chat $chat) {
        $user = auth()->user();
        $initialMessages = HydrateChat::handle($chat->id);

        return Inertia::render('dashboard', [
            'chatUrl' => route('chat'),
            'chatId' => $chat->id,
            'initialMessages' => $initialMessages,
            'chats' => $user->chats()->orderBy('created_at', 'desc')->get(),
            '_token' => csrf_token(),
        ]);
    })->name('dashboard.show')->middleware();

    Route::post('/chat', [ChatController::class, 'store'])->name('chat');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
