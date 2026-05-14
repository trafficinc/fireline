<?php

use Fireline\Scoring\Thresholds;
use PHPUnit\Framework\TestCase;

class ThresholdsTest extends TestCase
{
    public function testParanoiaLevelsProvideDefaultThresholds(): void
    {
        $low = new Thresholds(['paranoia_level' => 'low']);
        $strict = new Thresholds(['paranoia_level' => 'strict']);

        $this->assertSame(35, $low->blockThreshold());
        $this->assertSame(12, $strict->blockThreshold());
        $this->assertLessThan($low->regexThreshold(), $strict->regexThreshold());
    }

    public function testExplicitThresholdsOverrideParanoiaDefaults(): void
    {
        $thresholds = new Thresholds([
            'paranoia_level' => 'strict',
            'score_threshold' => 20,
        ]);

        $this->assertSame(20, $thresholds->blockThreshold());
    }
}
