<?php

namespace Fireline\Engine;

class BotGuard
{
    protected $rules;
    protected $found = '';

    public function __construct(?array $rules = null)
    {
        $file = dirname(__DIR__) . '/Compares/bots.php';
        $loaded = $rules ?? (is_readable($file) ? require $file : []);
        $this->rules = is_array($loaded) ? $loaded : [];
    }

    public function safe(string $userAgent): bool
    {
        foreach ($this->rules as $rule) {
            $rule = trim((string) $rule);
            if ($rule === '' || strpos($rule, '#') === 0) {
                continue;
            }

            $matched = @preg_match('/' . $rule . '/i', $userAgent);
            if ($matched === 1 && preg_last_error() === PREG_NO_ERROR) {
                $this->found = $userAgent === '' ? '[SystemProduced] Empty User-Agent' : $userAgent;
                return false;
            }

            if ($matched === false || preg_last_error() !== PREG_NO_ERROR) {
                $this->found = $userAgent;
                return false;
            }
        }

        return true;
    }

    public function found(): string
    {
        return $this->found;
    }
}
