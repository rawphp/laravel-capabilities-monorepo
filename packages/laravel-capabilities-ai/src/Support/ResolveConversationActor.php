<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\ActorLookup;
use RuntimeException;

/**
 * Resolve conversation.user_id → product actor for bus invokes (ORI-775).
 *
 * Fail closed: never fall through to ResolveActor::defaultUser() / silent id=1.
 * Hosts set {@see capabilities-ai.user_model} or auth.providers.users.model.
 */
final class ResolveConversationActor
{
    /** LLM-chosen tool calls in a turn: the agent surface (D-022 in-process AI adapter). */
    public const CALLER_AGENT = 'agent';

    /** Proposal accept: legacy job shape until the spec decides the accept surface. */
    public const CALLER_JOB = 'job';

    /**
     * @param  class-string|null  $userModel  Explicit model override (hosts)
     * @param  ActorLookup|null  $lookup  User lookup; null = {@see EloquentActorLookup} over the configured model
     */
    public function __construct(
        private readonly ?string $userModel = null,
        private readonly ?ActorLookup $lookup = null,
    ) {}

    /**
     * Resolve a real user principal for the conversation owner.
     *
     * @throws UnresolvedConversationActorException when user_id is missing/invalid or the user cannot be loaded
     * @throws RuntimeException when no usable user model is configured
     */
    public function resolve(mixed $userId): object
    {
        if ($userId === null) {
            throw new UnresolvedConversationActorException(
                'Conversation user_id is required for capability bus invokes; refusing silent default principal'
            );
        }

        $id = is_string($userId) || is_int($userId) || is_float($userId)
            ? trim((string) $userId)
            : '';

        if ($id === '') {
            throw new UnresolvedConversationActorException(
                'Conversation user_id is required for capability bus invokes; refusing silent default principal'
            );
        }

        $lookup = $this->lookup ?? new EloquentActorLookup(self::assertQueryableModel($this->userModelClass()));

        $user = $lookup->find($id);
        if ($user === null) {
            throw new UnresolvedConversationActorException(
                "Conversation user_id [{$id}] does not resolve to a user; refusing bus invoke"
            );
        }

        return $user;
    }

    /**
     * Fail closed on a missing, unknown, or non-queryable user model. Never queries.
     * Also run at provider boot so misconfiguration surfaces before the first turn.
     *
     * @return class-string
     *
     * @throws RuntimeException
     */
    public static function assertQueryableModel(?string $modelClass): string
    {
        if ($modelClass === null || $modelClass === '') {
            throw new RuntimeException(
                'No user model configured for conversation actor resolution (set capabilities-ai.user_model or auth.providers.users.model)'
            );
        }

        if (! class_exists($modelClass)) {
            throw new RuntimeException(
                "Configured user model [{$modelClass}] does not exist; refusing bus invoke"
            );
        }

        if (! method_exists($modelClass, 'query')) {
            throw new RuntimeException(
                "Configured user model [{$modelClass}] is not queryable; refusing bus invoke"
            );
        }

        return $modelClass;
    }

    /**
     * Bus invoke options: server-chosen caller + resolved actor (never overridable by $extra).
     *
     * @param  self::CALLER_*  $caller
     * @param  array<string, mixed>  $extra  Additional options (e.g. idempotency_key)
     * @return array<string, mixed>
     */
    public function invokeOptions(object $actor, string $caller, array $extra = []): array
    {
        return [
            'caller' => $caller,
            'actor' => $actor,
        ] + $extra;
    }

    /**
     * @return class-string|null
     */
    private function userModelClass(): ?string
    {
        if (is_string($this->userModel) && $this->userModel !== '') {
            return $this->userModel;
        }

        if (! function_exists('config')) {
            return null;
        }

        $fromPackage = config('capabilities-ai.user_model');
        if (is_string($fromPackage) && $fromPackage !== '') {
            return $fromPackage;
        }

        $fromAuth = config('auth.providers.users.model');
        if (is_string($fromAuth) && $fromAuth !== '') {
            return $fromAuth;
        }

        return null;
    }
}
