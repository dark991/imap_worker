<?php
/**
 * Created by PhpStorm.
 * User: LifarAV
 * Date: 17.07.14
 * Time: 12:11
 */
namespace Application;

class Scanner
{
    private $config = [];
    private $debugLevel = 1;

    function run($path = './config/config.php')
    {
        $modules = [
            'connect',
            'runner'
        ];
        $this->loadModules($modules);
        $this->configure($path);

        $imap = new Connect($this->config);
        $runnerHandler = new Runner($imap->connectionHandler, $this->config);
        $runnerHandler->execute();

        unset($runnerHandler);
        unset($imap);
    }

    private function configure($path = './config/config.php')
    {
        $config = null;
        include_once $path;
        $this->config = $config;
        return true;
    }

    private function loadModules($modules)
    {
        foreach ($modules as $key => $value)
        {
            include_once 'classes/' . $value . '.php';
        }
    }

    public function setDebugLevel($level = 1)
    {
        $this->debugLevel = $level;
        return true;
    }

    protected function getDebugLevel()
    {
        return $this->debugLevel;
    }
}