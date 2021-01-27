<?php

namespace Wan\Services\Ali;

use Wan\Models\Crms\WanArCrmUser;
use Wan\Models\Crms\WanArFollowAlert;
use Wan\Models\UserService\WanArUserInfo;

/**
 * Class DingTalkService
 * @package Wan\Services\Ali
 */
class DingTalkService
{
    const ROBOT_WEBHOOK_TEST = 'https://oapi.dingtalk.com/robot/send?access_token=7dc4a023c8ccfc2d809fc5b9c04e3b769fd8153edfee234edc894ed5a1b4734c';
    const ROBOT_WEBHOOK_PROD = 'https://oapi.dingtalk.com/robot/send?access_token=5caaf0be6355b82231fa1b6efeadcba96a88ec130f17f1480b1ec672a50c5716';

    /**
     * 发送机器人消息
     * @param $post_string
     * @return bool|string
     */
    function request_by_curl($post_string) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::getWebHook());
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json;charset=utf-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /**
     * 发送机器人消息，跟进提醒的消息
     * @param $followAlertRcd - 一条 crms.follow_alert 记录
     */
    public function robotSendFollowNotice($followAlertRcd)
    {
        $wanArCrmUser = new WanArCrmUser();
        $wanArUserInfo = new WanArUserInfo();
        $wanArFollowAlert = new WanArFollowAlert();

        $title = $followAlertRcd["title"] ?? "--";
        $toCustomerIds = $followAlertRcd["to_customer_ids"] ?? "";
        $toUserIds = $followAlertRcd["to_user_ids"] ?? "";
        $followTime = $followAlertRcd["follow_time"] ?? "";
        $content = $followAlertRcd["content"] ?? "";
        if (empty($toCustomerIds)) {
            return resultError(2003, "没有需要通知的对象");
        }
        $toCustomers = $wanArCrmUser->getByIds(explode(",", $toCustomerIds), "phone, uname");
        $toUsers = $wanArUserInfo->getByUserIds($toUserIds, "account");
        $toCustomerPhones = [];
        $toCustomerAtPhones = [];
        $toCustomerNames = [];
        $toUserNames = [];
        foreach ($toCustomers as $toCustomer) {
            array_push($toCustomerNames, $toCustomer["uname"]);
            if (!empty($toCustomer["phone"])) {
                array_push($toCustomerPhones, $toCustomer["phone"]);
                // []() 表示链接，markdown 的语法，实现文字变蓝
                array_push($toCustomerAtPhones, '[@'.$toCustomer["phone"].']()');
            }
        }
        foreach ($toUsers as $toUser) {
            array_push($toUserNames, $toUser["account"]);
        }
        // 组装消息体
        $data = [
            'msgtype' => 'markdown',
            "markdown" => [
                'title' => '跟进提醒',
                'text' => "### 【跟进提醒】 \r\n >**标题**：".$title
                    ." \r\n  "
                    ." \r\n >**跟进用户**：".implode(", ", $toUserNames)
                    ." \r\n  "
                    ." \r\n >**负责人**：".implode(", ", $toCustomerNames)
                    ." \r\n  "
                    ." \r\n >**跟进时间**：".$followTime
                    ." \r\n  "
                    ." \r\n >**内容**：".$content
                    ." \r\n  "
                    ." \r\n ".implode(" ", $toCustomerAtPhones)
            ],
            'at' => [
                'atMobiles' => $toCustomerPhones
            ],
            'isAtAll' => false
        ];
        $data_string = json_encode($data);
        // 失败：返回 false
        // 成功：返回 {"errcode":0,"errmsg":"ok"}
        $result = self::request_by_curl($data_string);
        if (!empty($result)) {
            $wanArFollowAlert->setToSend($followAlertRcd["id"]);
        }
    }

    /**
     * 根据所在环境获取对应的机器人消息触发地址
     * @return string
     */
    public function getWebHook()
    {
        if (DEV_ENV == 'prod') {
            return self::ROBOT_WEBHOOK_PROD;
        }
        else {
            return self::ROBOT_WEBHOOK_TEST;
        }
    }
}