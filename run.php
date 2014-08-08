<?php
/**
 * Created by PhpStorm.
 * User: LifarAV
 * Date: 17.07.14
 * Time: 12:12
 *
 * Instruction:
 * Need a PHP >= 5.3 version
 * Need an imap extenstion included in php.ini
 * Check config file
 * Run with "php run.php"
 */

include_once('autoloader.php');

$app = new \Application\Scanner;
$app->setDebugLevel(1);
$app->run('./config/config.php');