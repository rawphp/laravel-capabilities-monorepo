<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Http;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\CapabilitiesAi\Domain\AcceptOutcome;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use RuntimeException;

/**
 * Thin HTTP adapters — domain logic lives in services.
 */
final class ChatController
{
    public function history(string $conversationUlid, ConversationService $conversations): JsonResponse
    {
        try {
            return new JsonResponse($conversations->history($conversationUlid));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Conversation not found'], 404);
        }
    }

    public function storeMessage(Request $request, ConversationService $conversations): JsonResponse
    {
        $ids = $conversations->createUserMessage(
            content: (string) $request->input('content', ''),
            conversationUlid: $request->input('conversation_ulid'),
            userId: $request->input('user_id'),
            appId: $request->input('app_id'),
        );

        return new JsonResponse($ids, 201);
    }

    public function showTurn(string $turnUlid, TurnService $turns): JsonResponse
    {
        try {
            return new JsonResponse($turns->show($turnUlid));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Turn not found'], 404);
        }
    }

    public function cancelTurn(string $turnUlid, TurnService $turns): JsonResponse
    {
        try {
            return new JsonResponse($turns->cancel($turnUlid));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Turn not found'], 404);
        } catch (RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }
    }

    public function turnEvents(Request $request, string $turnUlid, TurnService $turns): JsonResponse
    {
        try {
            $cursor = (int) $request->query('cursor', 0);
            $events = $turns->events($turnUlid, $cursor);

            return new JsonResponse(['turn_ulid' => $turnUlid, 'events' => $events]);
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Turn not found'], 404);
        }
    }

    public function acceptProposal(string $proposalUlid, ProposalService $proposals): JsonResponse
    {
        $outcome = $proposals->accept($proposalUlid);

        return $this->jsonFromAcceptOutcome($outcome);
    }

    private function jsonFromAcceptOutcome(AcceptOutcome $outcome): JsonResponse
    {
        $proposal = $outcome->proposal;
        $body = [
            'ulid' => $proposal->ulid,
            'status' => $proposal->status,
            'outcome' => $outcome->kind,
        ];

        if ($outcome->message !== null) {
            $body['message'] = $outcome->message;
        }
        if ($outcome->approvalId !== null) {
            $body['approval_id'] = $outcome->approvalId;
        }
        if ($outcome->error !== null) {
            $body['error'] = $outcome->error;
        }

        $status = $outcome->httpStatus ?? match ($outcome->kind) {
            AcceptOutcome::KIND_ACCEPTED => 200,
            AcceptOutcome::KIND_APPROVAL_REQUIRED => 202,
            AcceptOutcome::KIND_RETRYABLE => 409,
            AcceptOutcome::KIND_REFUSE => 403,
            default => 422,
        };

        return new JsonResponse($body, $status);
    }

    public function rejectProposal(string $proposalUlid, ProposalService $proposals): JsonResponse
    {
        $proposal = $proposals->reject($proposalUlid);

        return new JsonResponse(['ulid' => $proposal->ulid, 'status' => $proposal->status]);
    }

    public function destroyConversation(string $conversationUlid, ConversationService $conversations): JsonResponse
    {
        try {
            return new JsonResponse($conversations->destroy($conversationUlid));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Conversation not found'], 404);
        } catch (RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }
    }
}
