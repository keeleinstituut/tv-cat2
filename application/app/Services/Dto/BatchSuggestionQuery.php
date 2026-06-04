<?php

namespace App\Services\Dto;

class BatchSuggestionQuery
{
    public string $q;
    public ?string $contextBefore;
    public ?string $contextAfter;

    public function __construct(string $q, ?string $contextBefore = null, ?string $contextAfter = null)
    {
        $this->q = $q;
        $this->contextBefore = $contextBefore;
        $this->contextAfter = $contextAfter;
    }

    public static function make(string $q, ?string $contextBefore = null, ?string $contextAfter = null): self
    {
        return new self($q, $contextBefore, $contextAfter);
    }
}
