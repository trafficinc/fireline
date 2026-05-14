<?php

use Fireline\Engine\RequestContext;
use Fireline\Engine\ScanResult;
use PHPUnit\Framework\TestCase;

class ScanResultTest extends TestCase
{
    public function testConvertsToPublicResultArrayShape(): void
    {
        $result = new ScanResult(
            'get.q',
            'get',
            12,
            [['id' => 'SQL_UNION_SELECT']],
            ['rule:SQL_UNION_SELECT' => 12],
            'abc123',
            'UNION SELECT',
            'union select'
        );

        $this->assertSame([
            'field' => 'get.q',
            'source' => 'get',
            'score' => 12,
            'matches' => [['id' => 'SQL_UNION_SELECT']],
            'breakdown' => ['rule:SQL_UNION_SELECT' => 12],
            'fingerprint' => 'abc123',
            'value' => 'UNION SELECT',
            'normalized' => 'union select',
        ], $result->toArray());
    }

    public function testRequestContextStoresScanResultsAsArrays(): void
    {
        $context = new RequestContext([]);
        $context->addResult(ScanResult::fromArray([
            'field' => 'get.q',
            'source' => 'get',
            'score' => 4,
            'fingerprint' => 'fp',
        ]));

        $this->assertSame('get.q', $context->results()[0]['field']);
        $this->assertSame(4, $context->highestScore());
    }

    public function testRequestContextNormalizesPartialArrays(): void
    {
        $context = new RequestContext([]);
        $context->addResult([
            'field' => 'post.comment',
            'score' => 9,
        ]);

        $result = $context->results()[0];

        $this->assertSame('post.comment', $result['field']);
        $this->assertSame('', $result['source']);
        $this->assertSame([], $result['matches']);
        $this->assertSame('', $result['normalized']);
    }
}
