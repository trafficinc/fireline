<?php

namespace Fireline\Engine;

use Fireline\Cache\FingerprintCache;
use Fireline\Cache\SafeCache;
use Fireline\Cache\ThreatCache;
use Fireline\Extract\RequestField;
use Fireline\Heuristics\EncodingHeuristics;
use Fireline\Heuristics\EntropyHeuristics;
use Fireline\Heuristics\ShellHeuristics;
use Fireline\Heuristics\SqlHeuristics;
use Fireline\Heuristics\UploadHeuristics;
use Fireline\Heuristics\XssHeuristics;
use Fireline\Learning\RouteLearner;
use Fireline\Scan\AhoCorasick;
use Fireline\Scan\Prefilter;
use Fireline\Scan\RegexScanner;
use Fireline\Scoring\ScoreAccumulator;
use Fireline\Scoring\Thresholds;

class FieldInspector
{
    protected $thresholds;

    public function __construct(Thresholds $thresholds)
    {
        $this->thresholds = $thresholds;
    }

    public function inspect(array $request, RequestField $field, string $normalized, bool $useSafeCache): ?ScanResult
    {
        $fingerprint = FingerprintCache::build($request, $field, $normalized);

        if ($useSafeCache && ThreatCache::isKnownThreat($fingerprint)) {
            return new ScanResult(
                $field->name(),
                $field->source(),
                $this->thresholds->blockThreshold(),
                [[
                    'id' => 'THREAT_CACHE_HIT',
                    'type' => 'cache',
                    'score' => $this->thresholds->blockThreshold(),
                    'category' => 'cache',
                ]],
                ['threat_cache' => $this->thresholds->blockThreshold()],
                $fingerprint,
                $field->value(),
                $normalized
            );
        }

        if ($useSafeCache && SafeCache::isKnownSafe($fingerprint)) {
            return null;
        }

        $score = new ScoreAccumulator();
        $score->add('prefilter', Prefilter::analyze($normalized));

        $matches = AhoCorasick::scan($normalized, $this->thresholds->paranoiaLevel());
        foreach ($matches as $match) {
            $score->addRule($match);
        }

        $score->add('sql_heuristics', SqlHeuristics::analyze($normalized));
        $score->add('xss_heuristics', XssHeuristics::analyze($normalized));
        $score->add('shell_heuristics', ShellHeuristics::analyze($normalized));
        $score->add('encoding_heuristics', EncodingHeuristics::analyze($normalized));
        $score->add('entropy_heuristics', EntropyHeuristics::analyze($normalized));
        $score->add('upload_heuristics', UploadHeuristics::analyze($field, $normalized));

        if ($score->total() >= $this->thresholds->regexThreshold()) {
            $regex = RegexScanner::scanDetailed($normalized, $matches, $this->thresholds->paranoiaLevel());
            foreach ($regex['matches'] as $match) {
                $score->addRule($match);
                $matches[] = $match;
            }
        }

        $score->add('route_model', RouteLearner::compare((string) ($request['route'] ?? ''), $field, $normalized));

        return new ScanResult(
            $field->name(),
            $field->source(),
            $score->total(),
            $matches,
            $score->breakdown(),
            $fingerprint,
            $field->value(),
            $normalized
        );
    }
}
