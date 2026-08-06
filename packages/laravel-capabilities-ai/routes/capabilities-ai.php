<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rawphp\CapabilitiesAi\Http\ChatController;

/*
| Optional package routes (enabled via config capabilities-ai.routes.enabled).
| Prefix default: capabilities-ai/chat
*/

Route::get('conversations/{conversationUlid}', [ChatController::class, 'history'])
    ->name('capabilities-ai.history');
Route::post('messages', [ChatController::class, 'storeMessage'])
    ->name('capabilities-ai.messages.store');
Route::get('turns/{turnUlid}', [ChatController::class, 'showTurn'])
    ->name('capabilities-ai.turns.show');
Route::post('turns/{turnUlid}/cancel', [ChatController::class, 'cancelTurn'])
    ->name('capabilities-ai.turns.cancel');
Route::get('turns/{turnUlid}/events', [ChatController::class, 'turnEvents'])
    ->name('capabilities-ai.turns.events');
Route::delete('conversations/{conversationUlid}', [ChatController::class, 'destroyConversation'])
    ->name('capabilities-ai.conversations.destroy');
