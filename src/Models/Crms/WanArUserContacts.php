<?php

namespace Wan\Models\Crms;

class WanArUserContacts extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['user_contacts'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // 状态，0-被删除，10-有效，20-主要联系人
    const STATUS_DELETED = 0;
    const STATUS_VALID = 10;
    const STATUS_MAIN = 20;

    // 性别：0：其他，1：男，2：女
    const GENDER_OTHER = 0;
    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;

    // 1.总负责人，2.老板，3.经理，4.业务，5.客服，6.客服主管，7.财务人员
    // 默认：业务
    const POSITION_CEO = 1;
    const POSITION_BOSS = 2;
    const POSITION_MANAGER = 3;
    const POSITION_BUSINESS = 4;
    const POSITION_CUSTOMER = 5;
    const POSITION_CUS_MANAGER = 6;
    const POSITION_FINANCIAL = 7;

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
        $wanArUserContacts = new self();
        $table = $wanArUserContacts->tableName();

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
            $sqlAddition .= " LIMIT $limit".
                " OFFSET $offset";
        }
        $sql = $sqlSelect . $sqlFrom . $sqlJoin . $sqlWhere . $sqlAddition;
        $records = $wanArUserContacts->getDbConnection()->createCommand($sql)->queryAll();
        if ($isCount) {
            return $records[0]['theCount'];
        } else {
            return $records;
        }
    }

    /**
     * 获取一个用户的主要联系人
     * @param $userId - 用户ID
     * @param string $select
     * @return mixed
     */
    public function getUserMainContact($userId, $select="*")
    {
        return self::model()->find(
            [
                "select" => $select,
                "condition" => "user_id = :userId AND status = :statusMain",
                "params" => [
                    ":userId" => $userId,
                    ":statusMain" => self::STATUS_MAIN
                ]
            ]
        );
    }

    /**
     * 获取指定条件下的查询语句
     * @param  $search
     * @return  mixed
     */
    public function getWhere($search)
    {
        $userId = $search["userId"] ?? "";

        $tableUserContacts = self::tableName();
        $where = " WHERE $tableUserContacts.status != ".self::STATUS_DELETED;
        if (!empty($userId)) {
            $where .= " AND $tableUserContacts.user_id = $userId";
        }
        return $where;
    }

    /**
     * 获取性别选择时的下拉项
     * @return array[]
     */
    public function getGenderSelection()
    {
        return [
            ["value"=>self::GENDER_MALE, "text"=>"男"],
            ["value"=>self::GENDER_FEMALE, "text"=>"女"],
            ["value"=>self::GENDER_OTHER, "text"=>"其他"],
        ];
    }

    /**
     * 获取 gender（性别） 字段对应的文本
     * @param $gender
     * @return string
     */
    public function getGenderText($gender)
    {
        $text = "";
        foreach (self::getGenderSelection() as $selection) {
            if ($selection["value"] == $gender) {
                return $selection["text"];
            }
        }
        return $text;
    }

    /**
     * 获取职位选择的下拉项
     * @return array[]
     */
    public function getPositionSelection()
    {
        return [
            ["value"=>self::POSITION_CEO, "text"=>"总负责人"],
            ["value"=>self::POSITION_BOSS, "text"=>"老板"],
            ["value"=>self::POSITION_MANAGER, "text"=>"经理"],
            ["value"=>self::POSITION_BUSINESS, "text"=>"业务"],
            ["value"=>self::POSITION_CUSTOMER, "text"=>"客服"],
            ["value"=>self::POSITION_CUS_MANAGER, "text"=>"客服主管"],
            ["value"=>self::POSITION_FINANCIAL, "text"=>"财务人员"],
        ];
    }

    /**
     * 获取职位（position）对应的文本
     * @param $position
     * @return mixed|string
     */
    public function getPositionText($position)
    {
        $text = "";
        foreach (self::getPositionSelection() as $selection) {
            if ($selection["value"] == $position) {
                return $selection["text"];
            }
        }
        return $text;
    }

    /**
     * 一个联系人是否是主要联系人
     * @param $status - status 字段的值
     * @return bool
     */
    public function isMain($status)
    {
        return $status == self::STATUS_MAIN;
    }

    /**
     * 讲一个用户的所有联系人都设为不是主要联系人
     * @param $userId
     * @return mixed
     */
    public function setAllToNormal($userId)
    {
        return self::model()->updateAll(
            [
                "status" => self::STATUS_VALID,
            ],
            "user_id = :userId AND status != :deleted",
            [
                ":userId" => $userId,
                ":deleted" => self::STATUS_DELETED
            ]
        );
    }

    /**
     * 将一条记录设置为主要联系人
     * @param $userContactId - usre_contacts.id
     * @return mixed
     */
    public function setOneToMain($userContactId)
    {
        return self::model()->updateAll(
            [
                "status" => self::STATUS_MAIN
            ],
            "id = :id",
            [
                ":id" => $userContactId
            ]
        );
    }

    /**
     * 添加或修改一个联系人
     * @param $params
     * @return mixed
     */
    public function addOrEdit($params)
    {
        $userContactId = $params["userContactId"] ?? "";
        $userId = $params['userId'] ?? "";
        $gender = $params['gender'] ?? "";
        $name = $params['name'] ?? "";
        $phone = $params['phone'] ?? "";
        $position = $params['position'] ?? "";
        $qq = $params['qq'] ?? "";
        $wx = $params['wx'] ?? "";
        $dateNow = date("Y-m-d H:i:s");
        if (empty($userContactId)) {
            $rcd = new self();
            $rcd->create_time = $dateNow;
            $rcd->status = self::STATUS_VALID;
        }
        else {
            $rcd = self::model()->findByPk($userContactId);
        }
        $rcd->user_id = $userId;
        $rcd->name = $name;
        $rcd->gender = $gender;
        $rcd->position = $position;
        $rcd->phone = $phone;
        $rcd->qq = $qq;
        $rcd->wx = $wx;
        $rcd->update_time = $dateNow;
        return $rcd->save();
    }

    /**
     * 删除一个联系人
     * @param $userContactId - user_contacts.id
     * @return mixed
     */
    public function deleteOne($userContactId)
    {
        return self::model()->updateAll(
            [
                "status" => self::STATUS_DELETED
            ],
            "id = :id",
            [
                ":id" => $userContactId
            ]
        );
    }
}