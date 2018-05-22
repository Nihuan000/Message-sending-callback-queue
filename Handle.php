<?php
/**
 * Created by PhpStorm.
 * User: nihuan
 * Date: 17-7-7
 * Time: 下午3:43
 * Desc 队列消息执行类
 */
date_default_timezone_set('Asia/Shanghai');
require_once dirname(__FILE__) . '/../../ThinkPHP/Library/Vendor/autoload.php';
ini_set('memory_limit','512M');
/**
 * 消息队列执行类
 */
class Handle{
    public function Msg_Anasysis($body){
        $msg = explode('#####',$body);
        $func = $msg[0];
        if($func != 'MsgCallback'){
            $message = json_decode($msg[1],2);
            if(!empty($func) && !empty($message)){
                return self::$func($message);
            }else{
                echo $body . PHP_EOL;
                return false;
            }
        }else{
            return false;
        }
    }


    /**
     * 腾讯云配置
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-06-14
     * @return array
     */
    private function imConfig(){
        $im = [
            /*腾讯IM聊天设置*/
            'IM_USER' => 'soubu_admin',
            'IM_APPID' => '1400017906',
            'IM_YUN_URL' => 'https://console.tim.qq.com',
            'IM_YUN_VERSION' => 'v4',
            'IM_CONTENT_TYPE' => 'json',
            'IM_METHOD' => 'post',
            'IM_APN' => '0',
            'IM_PRIVATE_KEY' => '/var/www/html/isoubu/sendIMCode/IM/private_key',
            'IM_SIGNATURE' => '/var/www/html/isoubu/sendIMCode/IM/bin/signature',
        ];
        return $im;
    }
    /**
     * 腾讯云通讯参数生成
     * @Author Nihuan
     * @Version 1.0
     * @Date 16-11-03
     * @return string
     */
    private function IMService(){
        $im = self::imConfig();
        $usersig = self::getIMtoken('soubu_admin');
        $sdkappid = $im['IM_APPID'];
        $content_type = $im['IM_CONTENT_TYPE'];
        $parameter =  "usersig=" . $usersig
            . "&identifier=soubu_admin"
            . "&sdkappid=" . $sdkappid
            . "&contenttype=" . $content_type;
        return $parameter;
    }


    /**
     * 导入新用户到腾讯云通讯
     * @Author Nihuan
     * @Version 1.0
     * @Date 16-11-03
     * @param $body
     * @return bool
     */
    public function genIMKey($body){
        $post_params = json_decode($body,true);
        $uid = $post_params['uid'];
        $parameter = self::IMService();
        $params = [
            'Identifier' => (string)$uid
        ];
        $im = self::imConfig();
        $im_version = $im['IM_YUN_VERSION'];
        $paramsString = json_encode($params,JSON_UNESCAPED_UNICODE);
        $curl_params = ['url'=>'https://console.tim.qq.com/' . $im_version . '/im_open_login_svc/account_import?' . $parameter, 'timeout'=>15];
        $curl_params['post_params'] = $paramsString;
        $curl_result = self::publicCURL($curl_params, 'post');

        $reStatus = json_decode($curl_result);
        if($reStatus->ErrorCode == 0) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * 云通讯在线状态获取
     * @Author Nihuan
     * @Version 1.0
     * @Date 16-12-12
     * @param $body
     * @return bool|string
     */
    public function getIMstate($body){
        $post_params = json_decode($body,true);
        $uid = $post_params['uid'];
        $State = 'Online';
        $parameter = self::IMService();
        $params = [
            'To_Account' => [(string)$uid]
        ];
        $im = self::imConfig();
        $im_version = $im['IM_YUN_VERSION'];
        $paramsString = json_encode($params,JSON_UNESCAPED_UNICODE);
        $curl_params = ['url'=>'https://console.tim.qq.com/' . $im_version . '/openim/querystate?' . $parameter, 'timeout'=>15];
        $curl_params['post_params'] = $paramsString;
        $curl_result = self::publicCURL($curl_params, 'post');

        $reStatus = json_decode($curl_result);
        if($reStatus->ErrorCode == 0) {
            $result = current($reStatus->QueryResult);
            if(!is_null($result->State)){
                $State = $result->State;
            }
        }
        else {
            return false;
        }
        if($State == 'PushOnline'){
            $State = 'Online';
        }
        echo $State;
    }


    /**
     * 腾讯云通讯token生成
     * @Author Nihuan
     * @Version 1.0
     * @Date 16-10-28
     * @param $body
     * @return bool
     */
    private function getIMtoken($body){
        $post_params = json_decode($body,true);
        $uid = $post_params['uid'];
        $token = '';
        if($uid == false){
            return false;
        }else{
            $im = self::imConfig();
            $private_key = $im['IM_PRIVATE_KEY'];
            $bin_path = $im['IM_SIGNATURE'];
            $appid = $im['IM_APPID'];
            $command = $bin_path
                . ' ' . escapeshellarg($private_key)
                . ' ' . escapeshellarg($appid)
                . ' ' . escapeshellarg($uid);
            exec($command, $out, $status);
            if ($status != 0)
            {
                echo 'msg:' . json_encode($out);
            }
            if(!empty($out)){
                $token = $out[0];
            }
        }
        return $token;
    }

    /**
     * sendImSms constructor.
     * @param $body
     * @return bool
     */
    public function sendImSms($body){
        $post_params = json_decode($body,true);
        $fromId = $post_params['fromId'];
        $uid = $post_params['toId'];
        $content = $post_params['content'];
        if( !is_numeric($fromId) || !is_numeric($uid) || empty($fromId) || empty($uid) || empty($content) ){
            return false;
        }else {
            $im = self::imConfig();
            $im_version = $im['IM_YUN_VERSION'];
            $content_arr = json_decode($content,true);
            $offline_template = self::custom_offline($fromId,$content_arr);
            $params = [
                'SyncOtherMachine' => 2,
                'MsgRandom' => rand(1, 65535),
                'MsgTimeStamp' => time(),
                'From_Account'=> (string)$fromId,
                'To_Account' => (string)$uid,
                'MsgBody' => [['MsgType'=>'TIMCustomElem','MsgContent'=> ['Data' => $content , 'Desc' => is_null($content_arr['msgContent']) ? '' : $content_arr['msgContent']]]],
                'OfflinePushInfo' => ['PushFlag' => 0, 'Ext' => is_null($offline_template) ? '' : $offline_template]
            ];
            $paramsString = json_encode($params,JSON_UNESCAPED_UNICODE);
            $parameter = self::IMService();

            $curl_params = ['url'=>'https://console.tim.qq.com/' . $im_version . '/openim/sendmsg?' . $parameter, 'timeout'=>15];
            $curl_params['post_params'] = $paramsString;
            $curl_result = self::publicCURL($curl_params, 'post');

            $reStatus = json_decode($curl_result);
            if($reStatus->ErrorCode == 0) {
                return true;
            }
            else {
                return false;
            }
        }
    }


    /**
     * sendImSms constructor.
     * @param $body
     * @return bool
     */
    public function sendAllImSms($body){
        $post_params = json_decode($body,true);
        $fromId = $post_params['fromId'];
        $uid = $post_params['toId'];
        $content = $post_params['content'];
        if( !is_numeric($fromId)  || empty($fromId) || empty($uid) || empty($content) ){
            return false;
        }else {
            $im = self::imConfig();
            $im_version = $im['IM_YUN_VERSION'];
            $content_arr = json_decode($content,true);
            $offline_template = self::custom_offline($fromId,$content_arr);
            $params = [
                'SyncOtherMachine' => 2,
                'MsgRandom' => rand(1, 65535),
                'MsgTimeStamp' => time(),
                'From_Account'=> (string)$fromId,
                'To_Account' => $uid,
                'MsgBody' => [['MsgType'=>'TIMCustomElem','MsgContent'=> ['Data' => $content , 'Desc' => is_null($content_arr['msgContent']) ? '' : $content_arr['msgContent']]]],
                'OfflinePushInfo' => ['PushFlag' => 0, 'Ext' => is_null($offline_template) ? '' : $offline_template]
            ];
            $paramsString = json_encode($params,JSON_UNESCAPED_UNICODE);
            $parameter = self::IMService();

            $curl_params = ['url'=>'https://console.tim.qq.com/' . $im_version . '/openim/batchsendmsg?' . $parameter, 'timeout'=>15];
            $curl_params['post_params'] = $paramsString;
            $curl_result = self::publicCURL($curl_params, 'post');

            $reStatus = json_decode($curl_result);
            if($reStatus->ErrorCode == 0) {
                return true;
            }
            else {
                return false;
            }
        }
    }


    /**
     * 公共curl方法
     * @Author Gemini
     * @Version 1.0
     * @Date 2016-06-21
     * @param $params
     * @param $request_type
     * @return string
     */
    private function publicCURL($params, $request_type = 'get') {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $params['url']);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $params['timeout']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        if( isset($params['other_options']) ) {
            curl_setopt_array($ch, $params['other_options']);
        }

        if($request_type === 'post') {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            if (isset($params['post_params'])) curl_setopt($ch,CURLOPT_POSTFIELDS,$params['post_params']);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * 离线消息模板
     * @Author Nihuan
     * @Version 1.0
     * @Date 16-11-01
     * @param $type
     * @param $content
     * @return string
     */
    private function custom_offline($type,$content){
        $data = [];
        $data['id'] = $type;
        switch($type){
            case 1:
                $data['type'] = 1;
                break;

            case 2:
                $data['offer_id'] = $content['offer_id'];
                $data['buy_id'] = $content['id'];
                $data['type'] = $content['type'];
                break;

            case 6:
                $data['type'] = $content['type'];
                $data['message_type'] = $content['message_type'];
                $data['order_num'] = $content['order_num'];
                $data['pre_id'] = $content['pre_id'];
                $data['order_sub_num'] = $content['order_sub_num'];
                break;

            case 7:
                $data['sco_id'] = $content['sco_id'];
                $data['user_type'] = $content['user_type'];
                $data['type'] = $content['type'];
                break;
            case 8:
                $data['id'] = "8";
                $data['content'] = $content['msgContent'];
                break;
        }

        return json_encode($data);
    }
}
