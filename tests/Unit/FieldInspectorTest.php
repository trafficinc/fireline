<?php

use Fireline\Engine\FieldInspector;
use Fireline\Extract\RequestField;
use Fireline\Scoring\Thresholds;
use PHPUnit\Framework\TestCase;

class FieldInspectorTest extends TestCase
{
    public function testInspectsFieldAndReturnsScanResult(): void
    {
        $inspector = new FieldInspector(new Thresholds([
            'paranoia_level' => 'medium',
            'regex_threshold' => 10,
        ]));

        $result = $inspector->inspect(
            ['method' => 'GET', 'route' => '/search'],
            new RequestField('get.q', '1 union select password from users', 'get'),
            '1 union select password from users',
            false
        );

        $this->assertSame('get.q', $result->toArray()['field']);
        $this->assertGreaterThan(0, $result->score());
        $this->assertNotSame('', $result->fingerprint());
        $this->assertContains('SQL_UNION_FROM', array_column($result->toArray()['matches'], 'id'));
        $this->assertArrayHasKey('rule:SQL_UNION_FROM', $result->toArray()['breakdown']);
    }
}
