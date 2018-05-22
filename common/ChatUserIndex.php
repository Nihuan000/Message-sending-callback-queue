<?php

/**
 * Created by PhpStorm.
 * User: nihuan
 * Date: 17-8-23
 * Time: 下午5:12
 */
date_default_timezone_set('Asia/Shanghai');
require_once dirname(__FILE__) . '/../config/config.php';
class ChatUserIndex
{
    private $redis;
    private $conf;
    private $today;
    private $mysqli;
    private $filter_string = [
        '不支持在线','不在线下单','微信下单','现结','微信付款','支付宝','服务费','手续费'
    ];
    public function __construct(){
        $this->conf = json_decode(CONFIG);
        if (!$this->redis) {
            $this->redisConn();
        }
        if (!$this->mysqli) {
            $this->mysqlConn();
        }
        $this->today = date('Y-m-d');
    }

    private function redisConn(){
        $r = $this->conf->redis;
        $this->redis = new Redis();
        $this->redis->connect($r->host,$r->port);
        $this->redis->auth($r->pass);
        $this->redis->select($r->db);
    }


    private function mysqlConn(){
        $m = $this->conf->mysql;
        $this->mysqli = new mysqli($m->host,$m->user,$m->password,$m->dbname,$m->port);
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
    }

    /**
     * 缓存会话响应时间
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-08-23
     * @param $from_id
     * @param $from_name
     * @param $target_name
     * @param $target_id
     * @param $send_time
     * @param $msg
     */
    public function cacheUserTimes($from_id,$from_name,$target_id,$target_name,$send_time,$msg){
        if(!empty($from_id) && !empty($target_id) && !empty($send_time)){
            $TimesCode = md5($this->today . 'Msg' . md5($from_id) . md5($target_id));//我发起的会话
            $checkCode = md5($this->today . 'Msg' .  md5($target_id). md5($from_id));//待我响应会话
            if(!$this->redis->sIsMember($this->today,$checkCode) && !$this->redis->sIsMember($this->today,$TimesCode)){
                if($this->redis->exists($checkCode)){
                    $this->redis->hmset($checkCode,['response_time' => $send_time]);
                    //写入会话时间到ES
                    require_once dirname(__FILE__) . '/EsCustom.php';
                    $client = new EsCustom();
                    $chatData = $this->redis->hMget($checkCode,['request_time','from_id','target_id','from_name','target_name','response_time']);
                    $body = [];
                    if(!empty($chatData)){
                        foreach ($chatData as $key => $item) {
                            $body[$key] = $item;
                        }
                        $body['answer_times'] = $send_time - $body['request_time'] > 0 ? $send_time - $body['request_time'] : 0;
                    }
                    $indexRes = $client->chat_index($checkCode,'chat_response',$body);
                    if($indexRes){
						echo '会话时长导入成功' .PHP_EOL;
                        $this->redis->lpush($this->today,$checkCode);//写入到已应答列表
                        $this->redis->lRem('chat_watting:' . $this->today,$checkCode,0);//删除待回复记录
                    }
                }elseif(!$this->redis->exists($checkCode) && !$this->redis->exists($TimesCode)){
                    $msg_info = ['request_time' => $send_time,'from_id' => $from_id, 'target_id' => $target_id, 'from_name' => $from_name, 'target_name' => $target_name, 'message' => $msg];
                    $this->redis->hmset($TimesCode,$msg_info);
                    $this->redis->lpush('chat_watting:' . $this->today,$TimesCode);
                    $remind_has_set = $this->redis->exists('wait_remind:' . $this->today);
                    $this->redis->ZADD('wait_remind:' . $this->today,$msg_info['request_time'],json_encode($msg_info));
                    if($remind_has_set == false){
                        $expire_time = strtotime('+1 day') - $msg_info['request_time'];
                        $this->redis->expire('wait_remind:' . $this->today,$expire_time);
                    }
                }
            }
        }
    }


    /**
     * 关键词过滤记录处理
     * @Author Nihuan
     * @Version 1.0
     * @Date 18-3-21
     * @param array $msg
     */
    public function userChatFilter($msg = []){
        foreach ($this->filter_string as $item) {
            if(strpos($msg['content'],$item) !== false){
                echo '消息内容:' . $msg['content'] . PHP_EOL;
                echo '匹配关键词:' . $item . PHP_EOL;
                $data = [
                    'from_id' => (int)$msg['from_user_id'],
                    'from_user' => (string)$msg['from_user_name'],
                    'target_id' => (int)$msg['target_user_id'],
                    'target_name' => (string)$msg['target_user_name'],
                    'msgContent' => (string)$msg['content'],
                    'match_string' => (string)$item,
                    'record_date' => (int)$msg['record_time'],
                    'add_time' => time()
                ];
                $this->insertOne('sb_chat_user_filter',$data);
            }
        }
    } 

    /**
     * 写入单条数据
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-10-30
     * @param $table
     * @param array $value
     * @return bool
     */
    protected function insertOne($table,$value = []){
        if(!empty($value)){
            $sql = "INSERT INTO {$table} (";
            $key = array_keys($value);
            $value = array_values($value);
            foreach ($key as $k) {
                $sql .= "{$k},";
            }
            $sql = substr($sql,0,strlen($sql) -1);
            $sql .= ') VALUES (';
            foreach ($value as $item) {
                $sql .= "'{$item}',";
            }
            $sql = substr($sql,0,strlen($sql)-1) . ');';
            $this->mysqli->query($sql);
            $last_id = mysqli_insert_id($this->mysqli);
            return $last_id;
        }else{
            return false;
        }
    }
}

