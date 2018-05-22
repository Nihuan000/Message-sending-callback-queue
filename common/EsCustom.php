<?php

/**
 * Created by PhpStorm.
 * User: nihuan
 * Date: 17-8-23
 * Time: 下午6:05
 */
class EsCustom
{
    private $client;
    private $index = 'chat_record';

    public function __construct()
    {
        $params['hosts'] = array(
            '127.0.0.1:8300'
        );
        $this->client = new Elasticsearch\Client($params);
    }


    /**
     * 索引数据公共方法
     * @Author Nihuan
     * @Version 1.0
     * @Date 17-08-23
     * @param $chat_id
     * @param $type
     * @param array $body
     * @return mixed
     */
    public function chat_index($chat_id,$type,$body = []){
        return $this->client->index(['id' => $chat_id, 'index' => $this->index, 'type' => $type, 'body' => $body]);
    }

}
