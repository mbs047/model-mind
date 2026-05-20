<?php

use Illuminate\Support\Facades\Route;
use Mbs\LaravelAiChat\Http\Controllers\MbsAiChatController;

Route::post('/chat', [MbsAiChatController::class, 'chat'])->name('chat');
Route::get('/session', [MbsAiChatController::class, 'session'])->name('session');
Route::post('/messages/{message}/feedback', [MbsAiChatController::class, 'feedback'])->name('messages.feedback');
