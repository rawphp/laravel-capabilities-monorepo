<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\CapabilitiesMessaging\Contracts\AgentTurn;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use RuntimeException;

/**
 * Telegram conversation adapter — feeds the agent, not a parallel run() path.
 *
 * Implements core ConversationIngress + ConversationReply (D-007).
 * Ingress delegates to the agent turn handler (the provider wires the host {@see AgentTurn});
 * with none it throws `agent_turn_unbound`. Never calls Eloquent domain services; never owns a second run().
 */
final class TelegramAdapter implements ConversationIngress, ConversationReply
{
    /** @var list<array<string, mixed>> */
    private array $handled = [];

    /** @var list<array<string, mixed>> */
    private array $replies = [];

    /** @var callable|null agent turn: (message) => array{text: string, tool_calls?: list} */
    private $ingressHandler;

    private bool $failIngress = false;

    private bool $failReply = false;

    public function __construct(
        private readonly ?TelegramBotClient $bot = null,
        ?callable $ingressHandler = null,
    ) {
        $this->ingressHandler = $ingressHandler;
    }

    public function failIngress(bool $fail = true): self
    {
        $this->failIngress = $fail;

        return $this;
    }

    public function failReply(bool $fail = true): self
    {
        $this->failReply = $fail;

        return $this;
    }

    /**
     * @param  array<string, mixed>|object  $message
     * @return array<string, mixed>
     */
    public function handle(array|object $message): array|object
    {
        if ($this->failIngress) {
            throw new RuntimeException('ingress_failure');
        }

        $data = is_array($message) ? $message : (array) $message;
        $this->handled[] = $data;

        // Fail closed: without an agent there is no answer (never echo the user's text back).
        if ($this->ingressHandler === null) {
            throw new RuntimeException(
                'agent_turn_unbound: bind '.AgentTurn::class.' to answer chat messages (D-007).'
            );
        }

        return ($this->ingressHandler)($data);
    }

    /**
     * @param  array<string, mixed>|object  $message
     */
    public function reply(array|object $message): void
    {
        if ($this->failReply) {
            throw new RuntimeException('reply_failure');
        }

        $data = is_array($message) ? $message : (array) $message;
        $this->replies[] = $data;

        $chatId = (string) ($data['chat_id'] ?? '');
        $text = (string) ($data['text'] ?? '');

        if ($this->bot !== null && $chatId !== '') {
            $this->bot->sendMessage($chatId, $text, $data);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handled(): array
    {
        return $this->handled;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function replies(): array
    {
        return $this->replies;
    }

    /**
     * Structural guarantee: this class has no domain run() method.
     */
    public function ownsDomainRunPath(): bool
    {
        return false;
    }
}
