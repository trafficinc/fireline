<?php

namespace Fireline\Engine;

class DecisionEngine
{
    protected $threshold;

    public function __construct(int $threshold)
    {
        $this->threshold = $threshold;
    }

    public function finalize(RequestContext $context): Decision
    {
        if ($context->highestScore() >= $this->threshold) {
            return Decision::block($context, 'score_threshold');
        }

        return Decision::allow($context);
    }
}
