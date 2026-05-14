<?php

trait LogService {

    /**
     * Log format
     *
     * %f - filter
     * %v - value
     * %i - ip address
     * %d - date
     * %t - time
     * %m - date & time
     * %u - unix time
     *
     * @var string
     */
    protected $log_format = '%m (%i) [%f] [%s] - %v';

    /**
     * Send HTTP status code 400 to client
     */
    public function block(){
        header('HTTP/1.1 400 Bad Request');
        exit;
    }

    public function handleService($value, $filter, $request){
        // log & block
        $this->log($value, $filter, $request);
        $this->block();
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
        $logFile = dirname(__DIR__) . '/storage/logs/fireline.log';
        if (!empty($logFile) && !empty($this->log_format)) {
            $data = str_replace(
                array('%f', '%v', '%i', '%s', '%d', '%t', '%m', '%u'),
                array($filter, $value, $_SERVER['REMOTE_ADDR'], $request, date('Y-m-d'), date('H:i:s'),
                    date('Y-m-d H:i:s'), time()),
                $this->log_format);
            if (is_writable($logFile)){
                file_put_contents($logFile, "\nwarn $data", FILE_APPEND);
            } else {
                throw new Exception('Cannot write to log file, please check permissions.');
            }
        }
    }
}