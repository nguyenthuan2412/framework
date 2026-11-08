<?php

namespace Ryra\Core\Mail\Mailables;

use Closure;
use Ryra\Core\Collection\Arr;

class Envelope
{
    /**
     * Create a new message envelope instance.
     */
    public function __construct(
        public Address|string $from = '',
        public array $to = [],
        public array $cc = [],
        public array $bcc = [],
        public array $replyTo = [],
        public string $subject = '',
        public Closure|array $using = []
    )
    {
        $this->from = !empty($from) && is_string($from) ? new Address($from) : $from;
        $this->to = $this->normalizeAddresses($to);
        $this->cc = $this->normalizeAddresses($cc);
        $this->bcc = $this->normalizeAddresses($bcc);
        $this->replyTo = $this->normalizeAddresses($replyTo);
        $this->subject = $subject;
        $this->using = Arr::wrap($using);
    }

    /**
     * Normalize the given array of addresses.
     */
    protected function normalizeAddresses(array $addresses): array
    {
        return collect($addresses)
            ->map(static fn ($address) => is_string($address) ? new Address($address) : $address)
            ->all();
    }

    /**
     * Specify who the message will be "from".
     */
    public function from(Address|string $address, ?string $name = null): static
    {
        $this->from = is_string($address) ? new Address($address, $name) : $address;

        return $this;
    }

    /**
     * Add a "to" recipient to the message envelope.
     */
    public function to(Address|array|string $address, ?string $name = null): static
    {
        $this->to = array_merge($this->to, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Add a "cc" recipient to the message envelope.
     */
    public function cc(Address|array|string $address, ?string $name = null): static
    {
        $this->cc = array_merge($this->cc, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Add a "bcc" recipient to the message envelope.
     */
    public function bcc(Address|array|string $address, ?string $name = null): static
    {
        $this->bcc = array_merge($this->bcc, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Add a "reply to" recipient to the message envelope.
     */
    public function replyTo(Address|array|string $address, ?string $name = null): static
    {
        $this->replyTo = array_merge($this->replyTo, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Set the subject of the message.
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Add a Symfony Message customization callback to the message.
     */
    public function using(Closure $callback): static
    {
        $this->using[] = $callback;

        return $this;
    }

    /**
     * Determine if the message is from the given address.
     */
    public function isFrom(string $address, ?string $name = null): bool
    {
        if (is_null($name)) {
            return $this->from->address === $address;
        }

        return $this->from->address === $address &&
               $this->from->name === $name;
    }

    /**
     * Determine if the message has the given address as a recipient.
     */
    public function hasTo(string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($this->to, $address, $name);
    }

    /**
     * Determine if the message has the given address as a "cc" recipient.
     */
    public function hasCc(string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($this->cc, $address, $name);
    }

    /**
     * Determine if the message has the given address as a "bcc" recipient.
     */
    public function hasBcc(string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($this->bcc, $address, $name);
    }

    /**
     * Determine if the message has the given address as a "reply to" recipient.
     */
    public function hasReplyTo(string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($this->replyTo, $address, $name);
    }

    /**
     * Determine if the message has the given recipient.
     */
    protected function hasRecipient(array $recipients, string $address, ?string $name = null): bool
    {
        return collect($recipients)->contains(static function ($recipient) use ($address, $name) {
            if (is_null($name)) {
                return $recipient->address === $address;
            }

            return $recipient->address === $address &&
                   $recipient->name === $name;
        });
    }

    /**
     * Determine if the message has the given subject.
     */
    public function hasSubject(string $subject): bool
    {
        return $this->subject === $subject;
    }
}