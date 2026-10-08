<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat.index');

Route::get('/conversations', [ChatController::class, 'conversations'])->name('conversations.index');
Route::post('/conversations', [ChatController::class, 'storeConversation'])->name('conversations.store');
Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('conversations.messages');
Route::post('/conversations/{conversation}/messages', [ChatController::class, 'storeMessage'])->name('conversations.messages.store');
