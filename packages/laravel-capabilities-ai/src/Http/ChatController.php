<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\CapabilitiesAi\Domain\AcceptOutcome;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnCapacityExceededException;
use Rawphp\CapabilitiesAi\Domain\TurnService;
use RuntimeException;

/**
 * Thin HTTP adapters — domain logic lives in services.
 *
 * Conversation/turn routes act as the authenticated user only (D-022): no user → 401,
 * another user's conversation or turn → 404. Body `user_id` is ignored.
 */
final class ChatController
{
    /** Crockford base32; ConversationService mints uppercase hex, a subset. */
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    public function history(Request $request, string $conversationUlid, ConversationService $conversations): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }

        try {
            return new JsonResponse($conversations->history($conversationUlid, $userId));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Conversation not found'], 404);
        }
    }

    /**
     * Conversation owner is the authenticated user — never a body `user_id` (D-022).
     * The owner becomes the bus actor for every turn and proposal accept.
     */
    public function storeMessage(Request $request, ConversationService $conversations): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }

        $errors = $this->storeMessageErrors($request);
        if ($errors !== []) {
            return new JsonResponse(['message' => 'The given data was invalid.', 'errors' => $errors], 422);
        }

        try {
            $ids = $conversations->createUserMessage(
                content: $request->input('content'),
                conversationUlid: $request->input('conversation_ulid'),
                userId: $userId,
                appId: $request->input('app_id'),
            );
        } catch (TurnCapacityExceededException $e) {
            return new JsonResponse(['message' => $e->getMessage(), 'outcome' => AcceptOutcome::KIND_RETRYABLE], 429);
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Conversation not found'], 404);
        }

        return new JsonResponse($ids, 201);
    }

    /**
     * @return array<string, list<string>>
     */
    private function storeMessageErrors(Request $request): array
    {
        $errors = [];

        $content = $request->input('content');
        if (! is_string($content) || trim($content) === '') {
            $errors['content'] = ['The content field must be a non-empty string.'];
        }

        $conversationUlid = $request->input('conversation_ulid');
        if ($conversationUlid !== null
            && (! is_string($conversationUlid) || preg_match(self::ULID_PATTERN, $conversationUlid) !== 1)) {
            $errors['conversation_ulid'] = ['The conversation_ulid field must be a 26-character uppercase ULID.'];
        }

        return $errors;
    }

    public function showTurn(Request $request, string $turnUlid, TurnService $turns): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }

        try {
            return new JsonResponse($turns->show($turnUlid, $userId));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Turn not found'], 404);
        }
    }

    public function cancelTurn(Request $request, string $turnUlid, TurnService $turns): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }

        try {
            return new JsonResponse($turns->cancel($turnUlid, $userId));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Turn not found'], 404);
        } catch (RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }
    }

    public function turnEvents(Request $request, string $turnUlid, TurnService $turns): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }

        try {
            $cursor = (int) $request->query('cursor', 0);
            $events = $turns->events($turnUlid, $userId, $cursor);

            return new JsonResponse(['turn_ulid' => $turnUlid, 'events' => $events]);
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Turn not found'], 404);
        }
    }

    public function acceptProposal(string $proposalUlid, ProposalService $proposals): JsonResponse
    {
        try {
            $outcome = $proposals->accept($proposalUlid);

            return $this->jsonFromAcceptOutcome($outcome);
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Proposal not found'], 404);
        }
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
        try {
            $proposal = $proposals->reject($proposalUlid);

            return new JsonResponse(['ulid' => $proposal->ulid, 'status' => $proposal->status]);
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Proposal not found'], 404);
        } catch (RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }
    }

    public function destroyConversation(Request $request, string $conversationUlid, ConversationService $conversations): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }

        try {
            return new JsonResponse($conversations->destroy($conversationUlid, $userId));
        } catch (ModelNotFoundException) {
            return new JsonResponse(['message' => 'Conversation not found'], 404);
        } catch (RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 409);
        }
    }

    private function userId(Request $request): ?string
    {
        $user = $request->user();
        $id = $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;
        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        return $id === '' ? null : (string) $id;
    }

    private function unauthenticated(): JsonResponse
    {
        return new JsonResponse(['message' => 'Unauthenticated'], 401);
    }
}
