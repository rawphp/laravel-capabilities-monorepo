<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Rawphp\CapabilitiesAi\Http\ChatController;

/*
| Proposal accept/reject — loaded only when capabilities-ai.proposals.enabled is true.
*/

Route::post('proposals/{proposalUlid}/accept', [ChatController::class, 'acceptProposal'])
    ->name('capabilities-ai.proposals.accept');
Route::post('proposals/{proposalUlid}/reject', [ChatController::class, 'rejectProposal'])
    ->name('capabilities-ai.proposals.reject');
