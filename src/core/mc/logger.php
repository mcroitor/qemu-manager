<?php

namespace mc;

/**
 * Lightweight logger with level markers and optional formatter.
 */
class logger {

    public const INFO = 1;  // standard color
    public const PASS = 2;  // green color
    public const WARN = 4;  // yellow color
    public const ERROR = 8; // red color
    public const FAIL = 16; // red color
    public const DEBUG = self::INFO | self::PASS;
    
    private const LOG_TYPE = [
        self::INFO => "INFO",
        self::DEBUG => "DEBUG",
        self::PASS => "PASS",
        self::WARN => "WARN",
        self::ERROR => "ERROR",
        self::FAIL => "FAIL"
    ];

    private $logfile;
    private $pretifier = null;
    private $debug = false;

    /**
     * Creates logger instance.
     *
     * @param string $logfile Output stream or file path.
     */
    public function __construct(string $logfile = "php://stdout") {
        $this->logfile = $logfile;
    }

    /**
     * Sets output formatter callback.
     *
     * @param callable $pretifier Formatter callback.
     * @return void
     */
    public function setPretifier(callable $pretifier) {
        $this->pretifier = $pretifier;
    }

    /**
     * Enables or disables debug logging.
     *
     * @param bool $enable True to enable debug messages.
     * @return void
     */
    public function enableDebug(bool $enable = true){
        $this->debug = $enable;
    }

    /**
     * Writes a log line with timestamp and level marker.
     *
     * @param string $data Log message.
     * @param string $logType Log level constant.
     * @return void
     */
    private function write(string $data,string  $logType) {
        if (isset($_SESSION["timezone"])) {
            date_default_timezone_set($_SESSION["timezone"]);
        }
        $type = self::LOG_TYPE[$logType];
        $text = date("Y-m-d H:i:s") . "\t{$type}: {$data}" . PHP_EOL;
        if ($this->pretifier) {
            $text = call_user_func($this->pretifier, $text);
        }
        file_put_contents($this->logfile, $text, FILE_APPEND);
    }

    /**
     * Writes INFO message.
     *
     * @param string $data Log message.
     * @return void
     */
    public function info(string $data) {
        $this->write($data, self::INFO);
    }

    /**
     * Writes WARN message.
     *
     * @param string $data Log message.
     * @return void
     */
    public function warn(string $data) {
        $this->write($data, self::WARN);
    }

    /**
     * Writes PASS message.
     *
     * @param string $data Log message.
     * @return void
     */
    public function pass(string $data) {
        $this->write($data, self::PASS);
    }

    /**
     * Writes ERROR message.
     *
     * @param string $data Log message.
     * @return void
     */
    public function error(string $data) {
        $this->write($data, self::ERROR);
    }

    /**
     * Writes FAIL message.
     *
     * @param string $data Log message.
     * @return void
     */
    public function fail(string $data) {
        $this->write($data, self::FAIL);
    }

    /**
     * Writes DEBUG message when debug mode is enabled.
     *
     * @param string $data Log message.
     * @param bool $debug Force debug output for this call.
     * @return void
     */
    public function debug(string $data, bool $debug = false) {
        if($this->debug || $debug) {
            $this->write($data, self::DEBUG);
        }
    }

    /**
     * Creates stdout logger.
     *
     * @return \mc\logger Logger instance.
     */
    public static function stdout(){
        return new logger();
    }

    /**
     * Creates stderr logger.
     *
     * @return \mc\logger Logger instance.
     */
    public static function stderr(){
        return new logger("php://stderr");
    }
}
