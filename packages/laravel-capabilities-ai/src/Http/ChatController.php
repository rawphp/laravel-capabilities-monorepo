<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;

/**
 * Thin HTTP adapters — domain logic lives in services.
 */
final class ChatController
{
    public function history(string $conversationUlid): JsonResponse
    {
        return response()->json(['conversation_ulid' => $conversationUlid, 'messages' => []]);
    }

    public function storeMessage(Request $request, ConversationService $conversations): JsonResponse
    {
        $ids = $conversations->createUserMessage(
            content: (string) $request->input('content', ''),
            conversationUlid: $request->input('conversation_ulid'),
            userId: $request->input('user_id'),
            appId: $request->input('app_id'),
        );

        return response()->json($ids, 201);
    }

    public function showTurn(string $turnUlid): JsonResponse
    {
        return response()->json(['turn_ulid' => $turnUlid]);
    }

    public function cancelTurn(string $turnUlid): JsonResponse
    {
        return response()->json(['turn_ulid' => $turnUlid, 'status' => 'cancelled']);
    }

    public function turnEvents(string $turnUlid): JsonResponse
    {
        return response()->json(['turn_ulid' => $turnUlid, 'events' => []]);
    }

    public function acceptProposal(string $proposalUlid, ProposalService $proposals): JsonResponse
    {
        $proposal = $proposals->accept($proposalUlid);

        return response()->json(['ulid' => $proposal->ulid, 'status' => $proposal->status]);
    }

    public function rejectProposal(string $proposalUlid, ProposalService $proposals): JsonResponse
    {
        $proposal = $proposals->reject($proposalUlid);

        return response()->json(['ulid' => $proposal->ulid, 'status' => $proposal->status]);
    }

    public function destroyConversation(string $conversationUlid): JsonResponse
    {
        return response()->json(['conversation_ulid' => $conversationUlid, 'deleted' => true]);
    }
}
