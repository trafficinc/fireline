<?php

use Fireline\Engine\Decision;
use Fireline\Engine\RequestContext;
use PHPUnit\Framework\TestCase;

class DecisionExplainabilityTest extends TestCase
{
    public function testDecisionExplainsMatchedSignalsScoreAndThreshold(): void
    {
        $context = new RequestContext([]);
        $context->addResult([
            'field' => 'get.id',
            'score' => 17,
            'matches' => [
                ['id' => 'SQL_BOOLEAN_OPERATOR'],
            ],
            'breakdown' => [
                'rule:SQL_BOOLEAN_OPERATOR' => 6,
                'encoding_heuristics' => 4,
                'route_model' => 7,
            ],
        ]);

        $decision = Decision::block($context, 'field_score_threshold');
        $text = $decision->explain(15);

        $this->assertStringContainsString('Blocked:', $text);
        $this->assertStringContainsString('rule:SQL_BOOLEAN_OPERATOR (+6)', $text);
        $this->assertStringContainsString('Final Score: 17', $text);
        $this->assertStringContainsString('Threshold: 15', $text);
        $this->assertSame(['SQL_BOOLEAN_OPERATOR'], $decision->explanation(15)['matched_rules']);
    }
}
