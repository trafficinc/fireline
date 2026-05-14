<?php

namespace Fireline\Scoring;

class Thresholds
{
    protected $config;
    protected $levels = [
        'low' => [
            'score_threshold' => 35,
            'regex_threshold' => 15,
            'safe_cache_threshold' => 2,
        ],
        'medium' => [
            'score_threshold' => 25,
            'regex_threshold' => 10,
            'safe_cache_threshold' => 3,
        ],
        'high' => [
            'score_threshold' => 18,
            'regex_threshold' => 8,
            'safe_cache_threshold' => 2,
        ],
        'strict' => [
            'score_threshold' => 12,
            'regex_threshold' => 5,
            'safe_cache_threshold' => 1,
        ],
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function blockThreshold(): int
    {
        return $this->threshold('score_threshold');
    }

    public function regexThreshold(): int
    {
        return $this->threshold('regex_threshold');
    }

    public function safeCacheThreshold(): int
    {
        return $this->threshold('safe_cache_threshold');
    }

    public function paranoiaLevel(): string
    {
        $level = strtolower((string) ($this->config['paranoia_level'] ?? 'medium'));

        return isset($this->levels[$level]) ? $level : 'medium';
    }

    protected function threshold(string $key): int
    {
        if (isset($this->config[$key]) && $this->config[$key] !== null) {
            return max(1, (int) $this->config[$key]);
        }

        return $this->levels[$this->paranoiaLevel()][$key];
    }
}
