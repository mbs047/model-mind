<?php

use Illuminate\Support\Facades\Route;
use Mbs\ModelMind\Http\Controllers\ModelMindController;

Route::post('/chat', [ModelMindController::class, 'chat'])->name('chat');
Route::post('/stream', [ModelMindController::class, 'stream'])->name('stream');
Route::get('/session', [ModelMindController::class, 'session'])->name('session');
Route::post('/messages/{message}/feedback', [ModelMindController::class, 'feedback'])->name('messages.feedback');
