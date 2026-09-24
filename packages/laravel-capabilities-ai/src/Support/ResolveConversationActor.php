<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use RuntimeException;

/**
 * Resolve conversation.user_id → product actor for bus invokes (ORI-775).
 *
 * Fail closed: never fall through to ResolveActor::defaultUser() / silent id=1.
 * Hosts set {@see capabilities-ai.user_model} or auth.providers.users.model.
 */
final class ResolveConversationActor
{
    /** In-process coach / job surface (legacy RunCoachCommandHandler shape). */
    public const CALLER_JOB = 'job';

    /**
     * @param  class-string|null  $userModel  Explicit model override (tests / hosts)
     */
    public function __construct(
        private readonly ?string $userModel = null,
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

        $modelClass = self::assertQueryableModel($this->userModelClass());

        $user = $modelClass::query()->find($id);
        if ($user === null && ctype_digit($id)) {
            $user = $modelClass::query()->find((int) $id);
        }

        if ($user === null || ! is_object($user)) {
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
     * Bus invoke options matching legacy coach job principal.
     *
     * @param  array<string, mixed>  $extra  Merged after caller/actor (e.g. idempotency_key)
     * @return array<string, mixed>
     */
    public function invokeOptions(object $actor, array $extra = []): array
    {
        return array_merge([
            'caller' => self::CALLER_JOB,
            'actor' => $actor,
        ], $extra);
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
