<?php

namespace App\Services\Dto;


class GetSuggestionsOptions
{
    public ?string $sourceLocale = null;
    public ?string $targetLocale = null;
    public ?array $translationMemoryIds = [];
    public ?array $providers = null;
    public ?int $limit = null;

    /** @var BatchSuggestionQuery[] */
    public array $queries = [];

    public static function make(): self
    {
        return new self();
    }

    public function getQ(): string
    {
        return $this->queries[0]->q ?? '';
    }

    // Backward-compat single-query setters — manage $queries[0]

    public function setQ(string $q): self
    {
        if (empty($this->queries)) {
            $this->queries[] = new BatchSuggestionQuery($q);
        } else {
            $this->queries[0]->q = $q;
        }
        return $this;
    }

    public function setContextBefore(?string $contextBefore): self
    {
        if (empty($this->queries)) {
            $this->queries[] = new BatchSuggestionQuery('');
        }
        $this->queries[0]->contextBefore = $contextBefore;
        return $this;
    }

    public function setContextAfter(?string $contextAfter): self
    {
        if (empty($this->queries)) {
            $this->queries[] = new BatchSuggestionQuery('');
        }
        $this->queries[0]->contextAfter = $contextAfter;
        return $this;
    }

    // Multi-query methods

    public function addQuery(string $q, ?string $contextBefore = null, ?string $contextAfter = null): self
    {
        $this->queries[] = new BatchSuggestionQuery($q, $contextBefore, $contextAfter);
        return $this;
    }

    public function setQueries(array $queries): self
    {
        $this->queries = $queries;
        return $this;
    }

    public function setSourceLocale(?string $sourceLocale): self
    {
        $this->sourceLocale = $sourceLocale;
        return $this;
    }

    public function setTargetLocale(?string $targetLocale): self
    {
        $this->targetLocale = $targetLocale;
        return $this;
    }

    public function setTranslationMemoryIds(?array $translationMemoryIds): self
    {
        $this->translationMemoryIds = $translationMemoryIds;
        return $this;
    }

    public function setProviders(?array $providers): self
    {
        $this->providers = $providers;
        return $this;
    }

    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
}
