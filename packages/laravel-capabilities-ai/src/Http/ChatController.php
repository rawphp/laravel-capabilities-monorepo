<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Domain\AcceptOutcome;
use Rawphp\CapabilitiesAi\Domain\ClientMessageConflictException;
use Rawphp\CapabilitiesAi\Domain\ClientMessageMismatchException;
use Rawphp\CapabilitiesAi\Domain\ConversationClosedException;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\ProposalService;
use Rawphp\CapabilitiesAi\Domain\TurnCapacityExceededException;
use Rawphp\CapabilitiesAi\Domain\TurnRateLimitedException;
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
 * Every error branch (401, 404, 409, 422, 429) uses the core D-018 envelope (same shape as
 * capability invoke). Proposal accept results keep their own `outcome` body at any status.
 */
final class ChatController
{
    /** Crockford base32; ConversationService mints uppercase hex, a subset. */
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    /** capabilities-ai.max_message_chars default: longest accepted user message, in characters. */
    public const DEFAULT_MAX_MESSAGE_CHARS = 32000;

    /** Longest accepted `client_message_id`, in characters (the column width). */
    private const MAX_CLIENT_MESSAGE_ID_CHARS = 191;

    /**
     * @param  int  $maxMessageChars  longest accepted `content` in characters; 0 = no cap
     */
    public function __construct(
        private readonly int $maxMessageChars = self::DEFAULT_MAX_MESSAGE_CHARS,
    ) {}

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

        $violations = $this->storeMessageViolations($request);
        if ($violations !== []) {
            return $this->failure('validation_failed', 'The given data was invalid.', ['violations' => $violations]);
        }

        /** @var string $content */
        $content = $request->input('content');
        /** @var string|null $clientMessageId */
        $clientMessageId = $request->input('client_message_id');

        try {
            $sent = $conversations->sendUserMessage(
                content: $content,
                conversationUlid: $request->input('conversation_ulid'),
                userId: $userId,
                appId: $request->input('app_id'),
                clientMessageId: $clientMessageId,
            );
        } catch (ClientMessageMismatchException $e) {
            return $this->failure('validation_failed', 'The given data was invalid.', [
                'violations' => [['field' => 'client_message_id', 'message' => $e->getMessage()]],
            ]);
        } catch (TurnCapacityExceededException|TurnRateLimitedException $e) {
            return $this->failure('rate_limited', $e->getMessage());
        } catch (ModelNotFoundException) {
            return $this->failure('not_found', 'Conversation not found');
        } catch (ConversationClosedException|ClientMessageConflictException $e) {
            return $this->failure('conflict', $e->getMessage());
        }

        // A replayed client_message_id answers 200 with the first send's ids; a new message 201.
        return new JsonResponse($sent->ids, $sent->created ? 201 : 200);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function storeMessageViolations(Request $request): array
    {
        $violations = [];

        $content = $request->input('content');
        if (! is_string($content) || trim($content) === '') {
            $violations[] = ['field' => 'content', 'message' => 'The content field must be a non-empty string.'];
        } elseif ($this->maxMessageChars > 0 && mb_strlen($content) > $this->maxMessageChars) {
            $violations[] = ['field' => 'content', 'message' => "The content field must not be longer than {$this->maxMessageChars} characters."];
        }

        $conversationUlid = $request->input('conversation_ulid');
        if ($conversationUlid !== null
            && (! is_string($conversationUlid) || preg_match(self::ULID_PATTERN, $conversationUlid) !== 1)) {
            $violations[] = ['field' => 'conversation_ulid', 'message' => 'The conversation_ulid field must be a 26-character uppercase ULID.'];
        }

        $clientMessageId = $request->input('client_message_id');
        if ($clientMessageId !== null
            && (! is_string($clientMessageId) || $clientMessageId === '' || mb_strlen($clientMessageId) > self::MAX_CLIENT_MESSAGE_ID_CHARS)) {
            $violations[] = ['field' => 'client_message_id', 'message' => 'The client_message_id field must be a string of 1 to '.self::MAX_CLIENT_MESSAGE_ID_CHARS.' characters.'];
        }

        return $violations;
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
        return $this->failure('unauthenticated', 'Unauthenticated');
    }

    /**
     * @param  array<string, mixed>  $extra  Merged into the error envelope (e.g. violations)
     */
    private function failure(string $code, string $message, array $extra = []): JsonResponse
    {
        $result = CapabilityResult::failure($code, $message, $extra);

        return new JsonResponse($result->toArray(), (int) ($result->error['http_status'] ?? 500));
    }

    private function proposalNotFound(): JsonResponse
    {
        return $this->failure('not_found', 'Proposal not found');
    }
}
