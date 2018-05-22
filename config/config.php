<?php
/**
 * Created by PhpStorm.
 * User: nihuan
 * Date: 17-7-7
 * Time: 下午3:43
 */

date_default_timezone_set('Asia/Shanghai');
@ini_set('default_socket_timeout', -1);
defined('__URL__') or define('__URL__',dirname(__DIR__).DIRECTORY_SEPARATOR);

$config = array(
    //队列使用的redis
    'redis'=>array(
        'host' => '192.168.1.222',
        'port'=>6379,
        'pass' => 'abc123456',
        'db'=>12
    ),
    'mysql' => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'password' => 'root',
        'dbname' => 'sb',
        'port' => '3306',
        'charset' => 'utf8',
    ]
);
defined('CONFIG') or define('CONFIG',json_encode($config));
