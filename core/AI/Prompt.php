<?php

namespace Ryra\Core\AI;

use Countable;
use Ryra\Core\AI\Message\AssistantMessage;
use Ryra\Core\AI\Message\Media;
use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\Role;
use Ryra\Core\AI\Message\SystemMessage;
use Ryra\Core\AI\Message\ToolResultMessage;
use Ryra\Core\AI\Message\UserMessage;
use Ryra\Core\Support\Arrayable;

class Prompt implements Arrayable, Countable
{
    /**
     * @var array<int, Message>
     */
    protected array $messages = [];

    /**
     * @param array<int, Message> $messages
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $message) {
            $this->add($message);
        }
    }

    public static function make(array $messages = []): static
    {
        return new static($messages);
    }

    /**
     * Tạo nhanh prompt một lượt: system (tuỳ chọn) + user.
     */
    public static function of(string $user, ?string $system = null): static
    {
        $prompt = new static();

        if ($system !== null) {
            $prompt->system($system);
        }

        return $prompt->user($user);
    }

    public function add(Message ...$messages): static
    {
        foreach ($messages as $message) {
            $this->messages[] = $message;
        }

        return $this;
    }

    public function system(string $content, bool $cacheable = false): static
    {
        return $this->add(new SystemMessage($content, $cacheable));
    }

    /**
     * @param array<int, Media> $media
     */
    public function user(string $content, array $media = []): static
    {
        return $this->add(new UserMessage($content, $media));
    }

    /**
     * @param array<int, \Ryra\Core\AI\Response\ToolCall> $toolCalls
     */
    public function assistant(string $content, array $toolCalls = []): static
    {
        return $this->add(new AssistantMessage($content, $toolCalls));
    }

    public function toolResult(string $toolCallId, string $name, string $content, bool $isError = false): static
    {
        return $this->add(new ToolResultMessage($toolCallId, $name, $content, $isError));
    }

    /**
     * @return array<int, Message>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Các message system - nhiều nhà cung cấp tách riêng khỏi luồng hội thoại.
     *
     * @return array<int, SystemMessage>
     */
    public function systemMessages(): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (Message $message): bool => $message instanceof SystemMessage,
        ));
    }

    /**
     * Luồng hội thoại đã bỏ system message.
     *
     * @return array<int, Message>
     */
    public function conversation(): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (Message $message): bool => !$message instanceof SystemMessage,
        ));
    }

    public function lastMessage(): ?Message
    {
        return $this->messages === [] ? null : $this->messages[array_key_last($this->messages)];
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    public function hasRole(Role $role): bool
    {
        foreach ($this->messages as $message) {
            if ($message->role() === $role) {
                return true;
            }
        }

        return false;
    }

    public function clone(): static
    {
        return new static($this->messages);
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function toArray(): array
    {
        return array_map(static fn (Message $message): array => $message->toArray(), $this->messages);
    }
}
