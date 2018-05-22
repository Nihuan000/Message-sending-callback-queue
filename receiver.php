<?php
/**
 * Created by PhpStorm.
 * User: nihuan
 * Date: 17-7-7
 * Time: 下午3:43
 * Desc: 外部调用文件
 */
date_default_timezone_set('Asia/Shanghai');
require dirname(__FILE__) . '/httpProducer.php';
require dirname(__FILE__) . '/redisProcess.php';

$msg = file_get_contents("php://input");
$msgArr = json_decode($msg,true);
if(!empty($msgArr)){
    $body = '';
    $is_valid = 0;
    $limit_time = 0;
    if(isset($msgArr['CallbackCommand'])){
        if($msgArr['CallbackCommand'] == 'C2C.CallbackAfterSendMsg') {
            $body = 'MsgCallback#####' . $msg;
            $is_valid = 1;
       }
    }elseif (isset($msgArr['message'])){
        switch($msgArr['message']['step']){
            case 3:
                $body = 'sendImSms#####' . $msg;
                $is_valid = 1;
                break;

            case 4:
                $body = 'sendAllImSms#####' . $msg;
                $is_valid = 1;
                break;
        }
        $limit_time = 1;
    }elseif (isset($msgArr['SMS'])){
        $body = 'SMS#####' . $msg;
        $is_valid = 1;
        $limit_time = 1;
    }
   if($is_valid == 1 && !empty($body)){
       $hours = date('H');
       if(($hours >= 8 && $hours <= 22) || $limit_time == 0){
           $p = new HttpProducer();
           $p->process($body);
       }else{
           if($hours < 8){
               $sendDay = date('Ymd') . '_process';
           }else{
               $sendDay = date('Ymd',strtotime('+1')) . '_process';
           }
           //限时消息,写入待发送
           $c = new redisProcess();
           $c->cacheData($sendDay,$msg);
       }
   }
}
