<?php

namespace CalApi;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Log {

    protected static $instance;

    /**
     * Method to return the Monolog instance
     *
     * @return \Monolog\Logger
     */
    static public function getLogger()
    {
        if (! self::$instance) {
            self::configureInstance();
        }

        return self::$instance;
    }

    /**
     * Configure Monolog to use a rotating files system.
     *
     * @return Logger
     */
    protected static function configureInstance()
    {
        $logger = new Logger('logger');
        $logger->pushHandler(new StreamHandler(CAL_ROOT . '/cal.log', Level::Debug));
        self::$instance = $logger;
    }

    public static function debug($message, array $context = []){
        self::getLogger()->debug($message, $context);
    }

    public static function info($message, array $context = []){
        self::getLogger()->info($message, $context);
    }

    public static function notice($message, array $context = []){
        self::getLogger()->notice($message, $context);
    }

    public static function warning($message, array $context = []){
        self::getLogger()->warning($message, $context);
    }

    public static function error($message, array $context = []){
        self::getLogger()->error($message, $context);
    }

    public static function critical($message, array $context = []){
        self::getLogger()->critical($message, $context);
    }

    public static function alert($message, array $context = []){
        self::getLogger()->alert($message, $context);
    }

    public static function emergency($message, array $context = []){
        self::getLogger()->emergency($message, $context);
    }

}

?>