<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatBotController;
use App\Http\Controllers\WhatsAppController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Route::post('/chat-bot', [ChatBotController::class, 'listenToReplies']);

Route::post('/webhook/whatsapp', [WhatsAppController::class, 'receiveMessage']);

use App\Http\Controllers\ChatGPTController;

Route::post('/chat', [ChatGPTController::class, 'chat']);