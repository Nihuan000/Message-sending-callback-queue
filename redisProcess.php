<?php

/**
 * Created by PhpStorm.
 * User: nihuan
 * Date: 17-11-9
 * Time: 下午5:54
 */
date_default_timezone_set('Asia/Shanghai');
require_once dirname(__FILE__) . '/config/config.php';
class redisProcess
{
    private $redis;
    private $conf;

    public function __construct(){
        $this->conf = json_decode(CONFIG);
        if (!$this->redis) {
            $this->redisConn();
        }
    }

    private function redisConn(){
        $r = $this->conf->redis;
        $this->redis = new Redis();
        $this->redis->connect($r->host,$r->port);
        $this->redis->auth($r->pass);
        $this->redis->select($r->db);
    }


    /**
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-11-09
     * @param $key
     * @param $message
     */
    public function cacheData($key,$message){
        if(!empty($key) && !empty($message)){
           $this->redis->rPush($key,$message);
        }
    }
}
