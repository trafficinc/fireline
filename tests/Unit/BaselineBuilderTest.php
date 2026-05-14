<?php

use Fireline\Learning\BaselineBuilder;
use PHPUnit\Framework\TestCase;

class BaselineBuilderTest extends TestCase
{
    public function testBuildsRouteModelsFromAllowedReplayEvents(): void
    {
        $events = [
            $this->event('/login', 'post.username', 'alice', false),
            $this->event('/login', 'post.username', 'bob22', false),
            $this->event('/login', 'post.username', 'carol7', false),
            $this->event('/login', 'post.username', 'union select', true),
        ];

        $model = BaselineBuilder::build($events, 3);

        $this->assertSame('alnum', $model['/login']['fields']['post.username']['type']);
        $this->assertSame('alnum', $model['/login']['fields']['post.username']['allowed_chars']);
        $this->assertSame(6, $model['/login']['fields']['post.username']['max_length']);
    }

    public function testSkipsFieldsBelowMinimumSampleCount(): void
    {
        $events = [
            $this->event('/search', 'get.q', 'washers', false),
            $this->event('/search', 'get.q', 'bolts', false),
        ];

        $this->assertSame([], BaselineBuilder::build($events, 3));
    }

    public function testBuildsFromReplayFile(): void
    {
        $path = sys_get_temp_dir() . '/fireline-baseline-' . uniqid('', true) . '.ndjson';
        file_put_contents($path, json_encode($this->event('/orders', 'get.id', '100', false)) . PHP_EOL);
        file_put_contents($path, json_encode($this->event('/orders', 'get.id', '101', false)) . PHP_EOL, FILE_APPEND);
        file_put_contents($path, json_encode($this->event('/orders', 'get.id', '102', false)) . PHP_EOL, FILE_APPEND);

        $model = BaselineBuilder::buildFromReplayFile($path, 3);
        unlink($path);

        $this->assertSame('int', $model['/orders']['fields']['get.id']['type']);
        $this->assertSame('alnum', $model['/orders']['fields']['get.id']['allowed_chars']);
        $this->assertSame('N', $model['/orders']['fields']['get.id']['shape']);
    }

    public function testShapeModelKeepsNumericRunsDistinctFromLetters(): void
    {
        $events = [
            $this->event('/invite', 'post.code', 'abc-100', false),
            $this->event('/invite', 'post.code', 'def-101', false),
            $this->event('/invite', 'post.code', 'ghi-102', false),
        ];

        $model = BaselineBuilder::build($events, 3);

        $this->assertSame('A-N', $model['/invite']['fields']['post.code']['shape']);
    }

    protected function event(string $route, string $field, string $value, bool $blocked): array
    {
        return [
            'request' => [
                'route' => $route,
            ],
            'results' => [
                [
                    'field' => $field,
                    'normalized' => $value,
                ],
            ],
            'decision' => [
                'blocked' => $blocked,
                'score' => $blocked ? 30 : 0,
            ],
        ];
    }
}
