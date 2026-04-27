<?php

namespace App\Services\Dto;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;


class GetSuggestionsOptions
{
  public string $q;
  public ?string $sourceLocale = null;
  public ?string $targetLocale = null;
  public ?array $translationMemoryIds = null;
  public ?array $providers = null;
  public ?string $contextBefore = null;
  public ?string $contextAfter = null;
  public ?int $limit = null;


  public static function make(): self
  {
    return new self();
  }

  public function setQ(string $q): self
  {
    $this->q = $q;
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

  public function setContextBefore(?string $contextBefore): self
  {
    $this->contextBefore = $contextBefore;
    return $this;
  }

  public function setContextAfter(?string $contextAfter): self
  {
    $this->contextAfter = $contextAfter;
    return $this;
  }

  public function setLimit(?int $limit): self
  {
    $this->limit = $limit;
    return $this;
  }
}
