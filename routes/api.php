<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatBotController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Route::post('/chat-bot', 'ChatBotController@listenToReplies');

Route::post('/chat-bot', [ChatBotController::class, 'listenToReplies']);
