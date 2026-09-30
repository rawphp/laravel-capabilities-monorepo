<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\CapabilitiesMessaging\Contracts\AgentTurn;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\TelegramText;
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
    /**
     * Agent turn: (message) => array{text: string, tool_calls?: list}. A follow-up message carrying
     * `tool_results` asks the agent to answer the results of its tool calls.
     *
     * @var callable|null
     */
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
     * Send `text` to `chat_id`, into forum topic `topic_id` when set. Only Bot API fields are
     * built from the message: internal keys (thread ids, metadata) never leave the process.
     * Text over Telegram's 4096 limit goes out as consecutive messages; blank text sends nothing.
     *
     * @param  array<string, mixed>|object  $message
     */
    public function reply(array|object $message): void
    {
        $data = is_array($message) ? $message : (array) $message;

        $chatId = (string) ($data['chat_id'] ?? '');
        $text = (string) ($data['text'] ?? '');

        if ($this->bot === null || $chatId === '') {
            return;
        }

        $params = [];
        if (isset($data['topic_id']) && is_numeric($data['topic_id'])) {
            $params['message_thread_id'] = (int) $data['topic_id'];
        }

        foreach (TelegramText::split($text) as $part) {
            $this->bot->sendMessage($chatId, $part, $params);
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
