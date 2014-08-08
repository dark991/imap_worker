<?php
/**
 * Created by PhpStorm.
 * User: LifarAV
 * Date: 17.07.14
 * Time: 12:12
 */
namespace Application;

class Connect {

    public $connectionHandler = false;

    function __construct($config = [])
    {
        if (empty($config['host']) || empty($config['login']) || empty($config['password']))
        {
            echo 'Error! Can\'t connect to IMAP server!'
                . PHP_EOL
                . ' >>> Check the host, port, login and password array keys in your config path'
                . PHP_EOL;
            return false;
        }
        $this->imap_connect($config);
    }

    private function imap_connect(array $config)
    {
        $this->connectionHandler = @\imap_open($config['host'], $config['login'], $config['password']);
    }

    function __destruct()
    {
        @\imap_close($this->connectionHandler);
        $this->connectionHandler = null;
        unset($this->connectionHandler);
    }
}