<?php

namespace Wan\Models\Crms;

class WanArFollowAlert extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['follow_alert'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // 提醒方式：1-提前15分钟，2-提前1小时，3-提前1天'
    const REMINDER_15MIN = 1;
    const REMINDER_1HOUR = 2;
    const REMINDER_1DAY = 3;

    // 发送状态，0-未发送，10-已发送
    const STATUS_WAIT = 0;
    const STATUS_SEND = 10;

    /**
     * 获取指定条件下的记录
     * @param  $search
     * @param  $isCount - 是否是数量查询
     * @return  mixed
     */
    public function getRecordsOrCount($search, $isCount=false)
    {
        $page = $search["page"] ?? 1;
        $limit = $search['limit'] ?? 15;
        $offset = ($page - 1) * $limit;

        // 一系列的 Model 初始化
        $arFollowAlert = new self();
        $table = $arFollowAlert->tableName();

        $sqlSelect = "SELECT *";
        if ($isCount) {
            $sqlSelect = "SELECT COUNT(*) AS theCount";
        }
        $sqlFrom = " FROM $table";
        // 有些搜索会涉及到关联查询，在这里就要关联好
        $sqlJoin = "";
        $sqlWhere = $this->getWhere($search);
        // 排序规则写到 addition 里面
        $sqlAddition = "";
        if (!$isCount) {
            $sqlAddition .= " ORDER BY $table.id DESC LIMIT $limit".
                " OFFSET $offset";
        }
        $sql = $sqlSelect . $sqlFrom . $sqlJoin . $sqlWhere . $sqlAddition;
        $records = $arFollowAlert->getDbConnection()->createCommand($sql)->queryAll();
        if ($isCount) {
            return $records[0]['theCount'];
        } else {
            return $records;
        }
    }

    /**
     * 获取指定条件下的查询语句
     * @param  $search
     * @return  mixed
     */
    public function getWhere($search)
    {
        $tableFollowAlert = self::tableName();

        $searchCreateTime = $search['searchCreateTime'] ?? '';    # 创建日期
        $searchFollowTime = $search['searchFollowTime'] ?? '';    # 跟进日期
        $searchCreateCus = $search['searchCreateCus'] ?? '';      # 创建人
        $searchResponseCus = $search['searchResponseCus'] ?? '';  # 负责人
        $where = " WHERE 1=1";
        if(!empty($searchCreateTime)) {
            $searchCreateTime = explode(" - ", $searchCreateTime);
            $startTime = $searchCreateTime[0]." 00:00:00";
            $endTime = $searchCreateTime[1]." 23:59:59";
            // 填写了 创建日期
            $where .= (" AND $tableFollowAlert.create_time >= '$startTime'"
                ." AND $tableFollowAlert.create_time <= '$endTime'");
        }
        if(!empty($searchFollowTime)) {
            // 填写了 跟进日期
            $searchFollowTime = explode(" - ", $searchFollowTime);
            $startTime = $searchFollowTime[0]." 00:00:00";
            $endTime = $searchFollowTime[1]." 23:59:59";
            $where .= (" AND $tableFollowAlert.follow_time >= '$startTime'"
                ." AND $tableFollowAlert.follow_time <= '$endTime'");
        }
        if(!empty($searchCreateCus)) {
            // 创建人 没有选择全部
            $where .= " AND $tableFollowAlert.create_id = $searchCreateCus";
        }
        if(!empty($searchResponseCus)) {
            // 负责人 没有选择全部
            $where .= " AND FIND_IN_SET($searchResponseCus, $tableFollowAlert.to_customer_ids)";
        }
        return $where;
    }

    /**
     * 获取提前时间的下拉选搜索
     * @return array[]
     */
    public function getReminderSelection()
    {
        return [
            ["value"=>self::REMINDER_15MIN, "text"=>"15分钟", "phpBeforeTime"=>"-15 minutes", 'seconds'=>900],
            ["value"=>self::REMINDER_1HOUR, "text"=>"1小时", "phpBeforeTime"=>"-1 hour", 'seconds'=>3600],
            ["value"=>self::REMINDER_1DAY, "text"=>"1天", "phpBeforeTime"=>"-1 day", 'seconds'=>86400],
        ];
    }

    /**
     * 添加一个跟进提醒
     * @param $params - 新纪录需要的参数
     * @return mixed
     */
    public function addOneAlert($params)
    {
        $wanArCrmUser = new WanArCrmUser();
        $newRcd = new self();
        $timeNow = date("Y-m-d H:i:s");
        $planFollowTime = $params["planFollowTime"] ?? '';
        $reminder = $params["reminder"] ?? '';
        $newRcd->title = $params["title"] ?? '';
        $newRcd->status = self::STATUS_WAIT;
        $newRcd->to_user_ids = $params["userIds"] ?? '';
        $newRcd->to_customer_ids = $params["responseCus"] ?? '';
        $newRcd->follow_time = $planFollowTime;
        $newRcd->reminder = $reminder;
        $newRcd->send_time = self::getAlertTime(strtotime($planFollowTime), $reminder);
        $newRcd->content = $params["content"] ?? '';
        $newRcd->create_id = $wanArCrmUser->getUserId();
        $newRcd->create_time = $timeNow;
        $newRcd->update_time = $timeNow;
        return $newRcd->save();
    }

    /**
     * 获取一个提醒发送通知的时间
     * @param int $followTime - unix 时间戳
     * @param $reminder - 对应到 follow_alert.reminder
     * @return string - Y-m-d H:i:s 格式的日期
     */
    public function getAlertTime(int $followTime, $reminder)
    {
        // 这个发送通知的时间应该在 $followTime 的 $reminder 之前
        $phpBeforeTime = "";
        foreach (self::getReminderSelection() as $item) {
            if ($item["value"] == $reminder) {
                $phpBeforeTime = $item["phpBeforeTime"];
                break;
            }
        }
        // 这里假设 reminder 全是合法的
        return date("Y-m-d H:i:s", strtotime($phpBeforeTime, $followTime));
    }

    /**
     * 获取一个提醒设置对应的文字说明
     * @param $reminder
     * @return mixed|string
     */
    public function getReminderText($reminder)
    {
        $text = "";
        foreach (self::getReminderSelection() as $item) {
            if ($item["value"] == $reminder) {
                $text = $item["text"];
                break;
            }
        }
        return $text;
    }

    /**
     * 获取 $timeStart 时间往后一分钟内需要发送的提醒
     * @param $timeStart - 开始计算一分钟的时间
     * @return mixed
     */
    public function getWaitSendWithinOneMinute($timeStart)
    {
        if (DEV_ENV == "local") {
            $dateOneMinuteLater = date("Y-m-d H:i:s", $timeStart + 3600);
        }
        else {
            $dateOneMinuteLater = date("Y-m-d H:i:s", $timeStart + 60);
        }
        return self::model()->findAll([
            // 更精确的应该是筛选在这 1 分钟内的记录
            // 但之前的记录可能没有发送成功，这里就没有加上 send_time >= $timeNow
            "condition" => "status = :waitSend AND send_time < :dateOneMinuteLater",
            "params" => [
                ":waitSend" => self::STATUS_WAIT,
                ":dateOneMinuteLater" => $dateOneMinuteLater
            ]
        ]);
    }

    /**
     * 将一个跟进提醒设置为已发送
     * @param $followAlertId - follow_alert.id
     * @return mixed
     */
    public function setToSend($followAlertId)
    {
        return self::model()->updateAll(
            [
                "status" => self::STATUS_SEND
            ],
            "id = :id",
            [
                ":id" => $followAlertId
            ]
        );
    }
}