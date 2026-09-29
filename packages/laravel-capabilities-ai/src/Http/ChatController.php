<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Support\CapabilityResult;
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
 * Proposal accept/reject act as the authenticated user only (D-022): no user → 401,
 * another user's (or an ownerless) conversation's proposal → 404, before the service runs.
 *
 * Error branches reuse the core D-018 envelope (same shape as capability invoke).
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
            return $this->failure('not_found', 'Conversation not found');
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
            return $this->failure('not_found', 'Conversation not found');
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
            return $this->failure('not_found', 'Turn not found');
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
            return $this->failure('not_found', 'Turn not found');
        } catch (RuntimeException $e) {
            return $this->failure('conflict', $e->getMessage());
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
            return $this->failure('not_found', 'Turn not found');
        }
    }

    public function acceptProposal(Request $request, string $proposalUlid, ProposalService $proposals): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }
        if (! $proposals->ownedBy($proposalUlid, $userId)) {
            return $this->proposalNotFound();
        }

        try {
            $outcome = $proposals->accept($proposalUlid);

            return $this->jsonFromAcceptOutcome($outcome);
        } catch (ModelNotFoundException) {
            return $this->proposalNotFound();
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

    public function rejectProposal(Request $request, string $proposalUlid, ProposalService $proposals): JsonResponse
    {
        $userId = $this->userId($request);
        if ($userId === null) {
            return $this->unauthenticated();
        }
        if (! $proposals->ownedBy($proposalUlid, $userId)) {
            return $this->proposalNotFound();
        }

        try {
            $proposal = $proposals->reject($proposalUlid);

            return new JsonResponse(['ulid' => $proposal->ulid, 'status' => $proposal->status]);
        } catch (ModelNotFoundException) {
            return $this->proposalNotFound();
        } catch (RuntimeException $e) {
            return $this->failure('conflict', $e->getMessage());
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
            return $this->failure('not_found', 'Conversation not found');
        } catch (RuntimeException $e) {
            return $this->failure('conflict', $e->getMessage());
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

    private function failure(string $code, string $message): JsonResponse
    {
        $result = CapabilityResult::failure($code, $message);

        return new JsonResponse($result->toArray(), (int) ($result->error['http_status'] ?? 500));
    }

    private function proposalNotFound(): JsonResponse
    {
        return $this->failure('not_found', 'Proposal not found');
    }
}
