<?php

/**
 * Created by PhpStorm.
 * User: nihuan
 * Date: 17-11-9
 * Time: 下午7:20
 */
date_default_timezone_set('Asia/Shanghai');
require_once dirname(__FILE__) . '/config/config.php';
require dirname(__FILE__) . '/httpProducer.php';
class resendMsg
{
    private $redis;
    private $conf;
    private $index;
    private $limit = 100;
    public function __construct(){
        $this->conf = json_decode(CONFIG);
        if (!$this->redis) {
            $this->redisConn();
        }
        $this->index = date('Ymd') . '_process';
    }

    private function redisConn(){
        $r = $this->conf->redis;
        $this->redis = new Redis();
        $this->redis->connect($r->host,$r->port);
        $this->redis->auth($r->pass);
        $this->redis->select($r->db);
    }


    /**
     * 重发缓存消息
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-11-09
     */
    public function send(){
        if($this->redis->exists($this->index)){
            $len = $this->redis->lLen($this->index);
            $pages = ceil($len/$this->limit);
            for ($i=1;$i<=$pages;$i++){
                $list = $this->redis->lRange($this->index,0,$this->limit);
                if(!empty($list)){
                    foreach ($list as $item) {
                        $body = '';
                        $is_valid = 0;
                        $msgArr = json_decode($item,true);
                        if (isset($msgArr['message'])){
                            switch($msgArr['message']['step']){
                                case 3:
                                    $body = 'sendImSms#####' . $item;
                                    $is_valid = 1;
                                    break;

                                case 4:
                                    $body = 'sendAllImSms#####' . $item;
                                    $is_valid = 1;
                                    break;
                            }
                        }elseif (isset($msgArr['SMS'])){
                            $body = 'SMS#####' . $item;
                            $is_valid = 1;
                        }
                        if($is_valid == 1 && !empty($body)){
                            $p = new HttpProducer();
                            $p->process($body);
                        }
                        echo $item . PHP_EOL;
                        $this->redis->lPop($this->index);
                    }
                }
            }
        }
    }
}
$resend = new resendMsg();
$resend->send();
