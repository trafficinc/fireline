<?php

trait LogService {

    /**
     * Send HTTP status code 403 to client
     */
    public function block(){
        header('HTTP/1.1 403 Forbidden');
        exit;
    }

    public function handleService($value, $filter, $request){
        // log & block
        $this->log($value, $filter, $request);
        $this->block();
    }

    protected function cleanLogValue(string $value): string {
        $value = preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);
        $value = $this->redactSensitiveValue($value);
        if (strlen($value) > 1000) {
            $value = substr($value, 0, 1000) . '...';
        }

        return $value;
    }

    protected function redactSensitiveValue(string $value): string {
        return preg_replace(
            '/\b(password|passwd|pwd|token|api[_-]?key|secret|authorization)=([^&\s]+)/i',
            '$1=[redacted]',
            $value
        );
    }

    protected function cleanLogField(string $value): string {
        return preg_replace('/[\x00-\x1F\x7F]/', ' ', $value);
    }

    protected function logFilePath(): string {
        return dirname(__DIR__) . '/storage/logs/fireline.log';
    }

    protected function buildLogEvent(string $value, string $filter, string $request): array {
        return [
            'level' => 'warn',
            'event' => 'fireline.blocked_request',
            'timestamp' => date('c'),
            'unix_time' => time(),
            'remote_addr' => $this->cleanLogField($_SERVER['REMOTE_ADDR'] ?? ''),
            'method' => $this->cleanLogField($request),
            'filter' => $this->cleanLogField($filter),
            'value' => $this->cleanLogValue($value),
            'request_uri' => $this->cleanLogField($_SERVER['REQUEST_URI'] ?? ''),
            'user_agent' => $this->cleanLogField($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referer' => $this->cleanLogField($_SERVER['HTTP_REFERER'] ?? ''),
        ];
    }

    protected function ensureLogFileWritable(string $logFile): void {
        $logDir = dirname($logFile);
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            throw new Exception('Cannot create log directory, please check permissions.');
        }

        if (!file_exists($logFile) && file_put_contents($logFile, '') === false) {
            throw new Exception('Cannot create log file, please check permissions.');
        }

        if (!is_writable($logFile)) {
            throw new Exception('Cannot write to log file, please check permissions.');
        }
    }

    /**
     * Write the detected  to a log file
     *
     * @param string $value
     * @param string $filter
     * @return fireline
     * @throws Exception
     */
    public function log(string $value, string $filter, string $request){
        $logFile = $this->logFilePath();
        $this->ensureLogFileWritable($logFile);

        $encoded = json_encode($this->buildLogEvent($value, $filter, $request), JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($logFile, $encoded . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new Exception('Cannot write to log file, please check permissions.');
        }
    }
}
