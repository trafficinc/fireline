<?php

use Fireline\Engine\Decision;
use Fireline\Engine\RequestContext;
use Fireline\Logging\EventFormatter;
use PHPUnit\Framework\TestCase;

class EventFormatterTest extends TestCase
{
    public function testBlockedDecisionUsesMatchedResultNotLastResult(): void
    {
        $context = new RequestContext([
            'ip' => '203.0.113.10',
            'method' => 'POST',
            'route' => '/login',
            'uri' => '/login',
            'user_agent' => 'Mozilla',
            'referer' => '',
        ]);
        $context->addResult([
            'field' => 'post.username',
            'source' => 'post',
            'score' => 30,
            'value' => 'union select',
            'normalized' => 'union select',
        ]);
        $context->addResult([
            'field' => 'post.note',
            'source' => 'post',
            'score' => 1,
            'value' => 'hello',
            'normalized' => 'hello',
        ]);

        $decision = Decision::block($context, 'field_score_threshold', $context->results()[0]);
        $event = EventFormatter::blockedDecision($decision);

        $this->assertSame('post.username', $event['field']);
        $this->assertSame('union select', $event['value']);
        $this->assertSame(30, $event['matched_score']);
        $this->assertSame(31, $event['score']);
    }
}
