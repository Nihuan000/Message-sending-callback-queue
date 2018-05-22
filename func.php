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
class msgExcuting{
    /**
     * 在线状态修改方法
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-07-07
     * @param int $process 进程id
     * @param array $state 在线状态
     * @return bool
     */
    private $db;
    private $mysqli;
    private $VENDOR_PATH = '/srv/sendIMCode/IM';
    private $Config = [
        /*腾讯IM聊天设置*/
        'IM_USER' => 'admin',
        'IM_APPID' => 'appid',
        'IM_YUN_URL' => 'https://console.tim.qq.com',
        'IM_YUN_VERSION' => 'v4',
        'IM_CONTENT_TYPE' => 'json',
        'IM_METHOD' => 'post',
        'IM_APN' => '0',
    ];

    public function __construct()
    {
        $this->mysqli = new mysqli('127.0.0.1','root','root','db','3306');
        if(mysqli_connect_errno())
        {
            echo "Filed to connect to MySQL: (".mysqli_connect_errno()."),".mysqli_connect_error();
        }
        else
        {
            if(!$this->mysqli->query('SET NAMES utf8'))
            {
                echo "Filed to set charset: (".mysqli_connect_errno()."),".mysqli_connect_error(); die;
            }
        }
        $this->db = $this->mysqli;
    }


    public function Msg_distribution($body){
        $msg = explode('#####',$body);
        $func = $msg[0];
        if($func != false){
            $message = json_decode($msg[1],2);
            if(!empty($func) && !empty($message)){
                return self::$func($message);
            }else{
                echo $body . PHP_EOL;
                return null;
            }
        }
    }


    protected function record($body){

    }


    /**
     * 发送短信队列
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-11-09
     * @param array $msg
     * @return bool
     */
    protected function SMS($msg = []){
        $sms = $msg['SMS'];
        $content = urldecode($sms['msg']);
        echo $content . PHP_EOL;
        $postFields = json_encode($sms);
        $ch = curl_init ();
        curl_setopt( $ch, CURLOPT_URL, "http://sms-server-url" );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8'
            )
        );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt( $ch, CURLOPT_TIMEOUT,1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
        $ret = curl_exec ( $ch );
        if (false == $ret) {
            //$result = curl_error(  $ch);
            $result =  false;
        } else {
            $rsp = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                //$result = "请求状态 ". $rsp . " " . curl_error($ch);
                $result =  false;
            } else {
                //$result = $ret;
                $result = true ;
            }
        }
        curl_close ( $ch );
        self::writeLog($content . '=>' . $ret,1);
        return $result;
    }


    /**
     * 系统消息发送
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-11-09
     * @param array $msg
     * @return bool
     */
    protected function sendImSms($msg = []){
        $message = $msg['message'];
        if (!empty($message)) {
            $im_version = self::config('IM_YUN_VERSION');
            $content_arr = json_decode($message['content'], true);
            $offline_template = self::custom_offline($message['fromId'], $content_arr);
            $params = [
                'SyncOtherMachine' => 2,
                'MsgRandom' => rand(1, 65535),
                'MsgTimeStamp' => time(),
                'From_Account' => (string)$message['fromId'],
                'To_Account' => (string)$message['toId'],
                'MsgBody' => [['MsgType' => 'TIMCustomElem', 'MsgContent' => ['Data' => $message['content'], 'Desc' => is_null($content_arr['msgContent']) ? '' : $content_arr['msgContent']]]],
                'OfflinePushInfo' => ['PushFlag' => 0, 'Ext' => is_null($offline_template) ? '' : $offline_template]
            ];
            $paramsString = json_encode($params, JSON_UNESCAPED_UNICODE);
            $parameter = self::IMService();

            $curl_params = ['url' => 'https://console.tim.qq.com/' . $im_version . '/openim/sendmsg?' . $parameter, 'timeout' => 15];
            $curl_params['post_params'] = $paramsString;
            $curl_result = self::publicCURL($curl_params, 'post');

            self::writeLog($message['content'] . '=>' . $curl_result,2);
            $reStatus = json_decode($curl_result);
            if ($reStatus->ErrorCode == 0) {
                return true;
            } else {
                return false;
            }
        }
    }


    /**
     * 消息回调写入聊天记录
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-07-07
     * @param array $callback 回调内容
     * @return bool
     */
    public function MsgCallback($callback = []){
        echo date('Y-m-d H:i:s') . PHP_EOL;
        echo '进程: 聊天记录导入...' . PHP_EOL;
        $result = false;
        $message = $callback;
        if(!empty($message)){
            $val = $message;
            if($val['From_Account'] != false && $val['From_Account'] > 600){
                $data['type_name'] = '';
                $data['content'] = $data['img_url'] = $data['audio_url'] = $msgContent = '';
                switch ($val['MsgBody'][0]['MsgType']) {
                    case 'TIMTextElem':
                        $data['msg_type'] = 1;
                        $data['type_name'] = '文本消息';
                        $data['content'] =  $val['MsgBody'][0]['MsgContent']['Text'];
                        $msgContent = $data['content'];
                        break;
                    case 'TIMCustomElem':
                        $data['msg_type'] = 2;
                        $data['type_name'] = '自定义消息';
                        $data['content'] = $val['MsgBody'][0]['MsgContent']['Data'];
                        $msgContent = '图文消息';
                        break;
                    case 'TIMImageElem':
                        $data['msg_type'] = 3;
                        $data['type_name'] = '图片消息';
                        $data['img_url'] = $val['MsgBody'][0]['MsgContent']['ImageInfoArray'][0]['URL'];
                        $msgContent = '图片消息';
                        break;
                    case 'TIMSoundElem':
                        $data['msg_type'] = 4;
                        $data['type_name'] = '语音消息';
                        $data['audio_url'] = $val['MsgBody'][0]['MsgContent']['UUID'];
                        $msgContent = '语音消息';
                        break;

                    default:
                        $data['msg_type'] = 2;
                        $data['type_name'] = '自定义消息';
                        $msgContent = '图文消息';
                }

                $get_from_user = 'select name from sb_user where user_id=' . $val['From_Account'];
                $query = mysqli_query($this->db,$get_from_user);
                $from_user = mysqli_fetch_assoc($query);
                if (isset($from_user)) {
                    $data['from_user_name'] = $from_user['name'];
                }
                unset($from_user);
                //target name
                $get_target_user = 'select name from sb_user where user_id=' . $val['To_Account'];
                $query1 = mysqli_query($this->db,$get_target_user);
                $target_user = mysqli_fetch_assoc($query1);
                if (isset($target_user)) {
                    $data['target_user_name'] = $target_user['name'];
                }
                unset($target_user);
                echo '|----发送者:' . $val['From_Account'] . PHP_EOL;
                echo '|----接收者:' . $val['To_Account'] . PHP_EOL;
                echo '|----消息类型:' . $data['type_name'] .PHP_EOL;
                echo '|----发送时间:' . date('Y-m-d H:i:s',$val['MsgTime']) . PHP_EOL;
                echo '|----消息内容:' . $data['content'] .PHP_EOL;
                echo '--------------------------------------------------------------------' . PHP_EOL;
                $data['re_time'] = $val['MsgTime'];
                unset($data['type_name']);
                $body = [
                    'from_user_name' => isset($data['from_user_name']) ? $data['from_user_name'] : '',
                    'target_user_name' => isset($data['target_user_name']) ? $data['target_user_name'] : '',
                    'from_user_id' => $val['From_Account'],
                    'target_user_id' => $val['To_Account'],
                    'img_url' => isset($data['img_url']) ? $data['img_url'] : '',
                    'audio_url' => isset($data['audio_url']) ? $data['audio_url'] : '',
                    'msg_type' => $data['msg_type'],
                    'content' => isset($data['content']) ? $data['content'] : '',
                    'record_time' => $data['re_time'],
                    'record_date' => date('Y-m-d H:i:s',$data['re_time'])
                ];
                $chat_id = md5(json_encode($body['from_user_id'] . $body['target_user_id'] . $body['content'] . $body['record_time']));
                echo $chat_id . PHP_EOL;
                require_once dirname(__FILE__) . '/common/EsCustom.php';
                $client = new EsCustom();
                $result = $client->chat_index($chat_id,'chat_new',$body);
                echo '聊天记录导入结果, 状态:' . $result['_shards']['successful'] . ', 条数:' . $result['_shards']['total'] . PHP_EOL;
                //写入对话响应时间
                require_once dirname(__FILE__) . '/common/ChatUserIndex.php';
                $chatIndex = new ChatUserIndex();
                $chatIndex->cacheUserTimes($body['from_user_id'],$body['from_user_name'],$body['target_user_id'],$body['target_user_name'],$data['re_time'],$msgContent);
                echo '聊天内容过滤' . PHP_EOL;
                $chatIndex->userChatFilter($body);
            }else{
                echo '系统消息，忽略之...' . PHP_EOL;
            }
        }
        echo '索引写入结果:' . $result['_shards']['successful'] . PHP_EOL;
        if($result['_shards']['successful'] > 0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 日志写入
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-11-09
     * @param string $content
     * @param int $type 1:短信 2:消息
     */
    protected function writeLog($content = '',$type = 1 ){
        $date = date('Y-m-d');
        $time = date('H:i:s');
        switch ($type){
            case 1:
                $filename = '_sms.log';
                break;

            case 2:
                $filename = '_sms.log';
                break;

            default:
                $filename = '';
        }
        $file = $date . $filename;
        $path = dirname(__FILE__) . '/logs/';
        $msg = '[' . $time . ']:' . $content . PHP_EOL;
        file_put_contents($path . $file,$msg,FILE_APPEND);
    }


    /**
     * 离线消息体
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-11-09
     * @param $type
     * @param $content
     * @return string
     */
    protected function custom_offline($type,$content){
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
                $data['content'] = $content['user_type'];
                $data['type'] = $content['content'];
                break;
        }

        return json_encode($data);
    }


    /**
     * 腾讯云通讯参数生成
     * @Author Nihuan
     * @Version 1.0
     * @Date 16-11-03
     * @return string
     */
    protected function IMService(){
        $user = self::config('IM_USER');
        $usersig = self::getIMtoken($user);
        $sdkappid = self::config('IM_APPID');
        $content_type = self::config('IM_CONTENT_TYPE');
        $parameter =  "usersig=" . $usersig
            . "&identifier=" . $user
            . "&sdkappid=" . $sdkappid
            . "&contenttype=" . $content_type;
        return $parameter;
    }

    /**
     * 腾讯云通讯token生成
     * @Author Nihuan
     * @Version 1.0
     * @Date 16-10-28
     * @param $uid
     * @return bool
     */
    protected function getIMtoken($uid){
        $token = '';
        if($uid == false){
            return false;
        }else{
            $private_key = $this->VENDOR_PATH . 'IMcloud/private_key';
            $bin_path = $this->VENDOR_PATH . 'IMcloud/bin/signature';
            $log = $this->VENDOR_PATH . 'im_log/im_log_' . date('y-m') . '.log';
            $appid = self::config('IM_APPID');
            $command = $bin_path
                . ' ' . escapeshellarg($private_key)
                . ' ' . escapeshellarg($appid)
                . ' ' . escapeshellarg($uid);
            exec($command, $out, $status);
            if ($status != 0)
            {
                $info = date('Y-m-d H:i:s') . ', user_id: ' . $uid . ', msg:' . json_encode($out);
                file_put_contents($log,$info);
                return null;
            }
            if(!empty($out)){
                $token = $out[0];
            }
        }
        return $token;
    }


    /**
     * 配置信息返回
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-11-09
     * @param $key
     * @return bool|mixed
     */
    protected function config($key){
        $config = $this->Config;
        if(isset($config[$key]))
            return $config[$key];
        else
            return false;
    }


    /**
     * 公共curl方法
     * @Author Gemini
     * @Version 1.0
     * @Date 2016-06-21
     * @param array $params
     * @param string $request_type
     * @return bool | string
     */
    protected function publicCURL($params, $request_type = 'get') {
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
}
