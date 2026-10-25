<?php

declare(strict_types=1);

namespace App\Services\Slash;

final class ParseResult
{
    /**
     * @param  array<string, string>  $fields  canonical key => raw value (last one wins)
     * @param  list<ParsedItem>  $items
     * @param  list<string>  $errors  human-readable problems ("Couldn't read the price in ...")
     * @param  list<string>  $unknownKeys
     */
    public function __construct(
        public array $fields = [],
        public array $items = [],
        public ?string $command = null,
        public ?string $commandArgument = null,
        public array $errors = [],
        public array $unknownKeys = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->fields === [] && $this->items === [] && $this->command === null;
    }

    public function hasPayload(): bool
    {
        return $this->fields !== [] || $this->items !== [];
    }
}
