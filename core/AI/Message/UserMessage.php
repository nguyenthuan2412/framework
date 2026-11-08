<?php

namespace Ryra\Core\AI\Message;

class UserMessage extends Message
{
    /**
     * @param array<int, Media> $media Ảnh/tài liệu đính kèm theo lượt nói này.
     */
    public function __construct(
        string $content = '',
        public readonly array $media = [],
    ) {
        parent::__construct($content);
    }

    public function role(): Role
    {
        return Role::User;
    }

    public function hasMedia(): bool
    {
        return $this->media !== [];
    }

    public function withMedia(Media ...$media): static
    {
        return new static($this->content, [...$this->media, ...$media]);
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'media' => array_map(static fn (Media $item): array => $item->toArray(), $this->media),
        ];
    }
}
