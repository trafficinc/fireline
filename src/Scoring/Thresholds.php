<?php

namespace Fireline\Scoring;

class Thresholds
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function blockThreshold(): int
    {
        return (int) ($this->config['score_threshold'] ?? 25);
    }

    public function regexThreshold(): int
    {
        return (int) ($this->config['regex_threshold'] ?? 10);
    }

    public function safeCacheThreshold(): int
    {
        return (int) ($this->config['safe_cache_threshold'] ?? 3);
    }
}
