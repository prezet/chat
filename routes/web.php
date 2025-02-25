<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});


Route::get('/demo', function () {
    $chat = new Chat();
    $chat->save();

    return redirect()->route('demo', ['id' => $chat->id]);
});

Route::get('/demo/{id}', function ($id) {
    $chat = Chat::findOrFail($id);

    $initialMessages = HydrateChat::handle($id);

    return Inertia::render('Welcome', [
        '_token' => csrf_token(),
        'chatUrl' => route('chat'),
        'chatId' => $chat->id,
        'initialMessages' => $initialMessages,
    ]);
})->name('demo');


Route::get('/stream/{id}', function ($id) {
    $chat = Chat::findOrFail($id);

    $initialMessages = HydrateChat::handle($id);

    return Inertia::render('Welcome', [
        '_token' => csrf_token(),
        'chatUrl' => route('chat-stream'),
        'chatId' => $chat->id,
        'initialMessages' => $initialMessages,
    ]);
})->name('stream');

Route::post('/chat-stream', [ChatStreamController::class, 'handleChat'])->name('chat-stream');
Route::post('/chat', [ChatController::class, 'handleChat'])->name('chat');



require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
