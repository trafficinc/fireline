<?php

use Fireline\Engine\Decision;
use Fireline\Engine\RequestContext;
use Fireline\Engine\ScanResult;
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

    public function testBlockAcceptsScanResultAsMatchedResult(): void
    {
        $context = new RequestContext([]);
        $result = new ScanResult(
            'get.id',
            'get',
            25,
            [['id' => 'SQL_UNION_SELECT']],
            ['rule:SQL_UNION_SELECT' => 12],
            'fp',
            '1 union select',
            '1 union select'
        );
        $context->addResult($result);

        $decision = Decision::block($context, 'field_score_threshold', $result);

        $this->assertSame('get.id', $decision->matchedResult()['field']);
        $this->assertSame(['SQL_UNION_SELECT'], $decision->explanation()['matched_rules']);
    }
}
