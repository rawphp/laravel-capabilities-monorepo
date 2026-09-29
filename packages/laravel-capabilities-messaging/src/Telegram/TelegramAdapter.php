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
    /** @var callable|null agent turn: (message) => array{text: string, tool_calls?: list} */
    private $ingressHandler;

    public function __construct(
        private readonly ?TelegramBotClient $bot = null,
        ?callable $ingressHandler = null,
    ) {
        $this->ingressHandler = $ingressHandler;
    }

    /**
     * @param  array<string, mixed>|object  $message
     * @return array<string, mixed>
     */
    public function handle(array|object $message): array|object
    {
        $data = is_array($message) ? $message : (array) $message;

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
        $data = is_array($message) ? $message : (array) $message;

        $chatId = (string) ($data['chat_id'] ?? '');
        $text = (string) ($data['text'] ?? '');

        if ($this->bot !== null && $chatId !== '') {
            $this->bot->sendMessage($chatId, $text, $data);
        }
    }

    /**
     * Structural guarantee: this class has no domain run() method.
     */
    public function ownsDomainRunPath(): bool
    {
        return false;
    }
}
