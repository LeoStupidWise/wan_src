<?php

namespace Wan\Models\Crms;

class WanArFollowUserSub extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['follow_user_sub'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }
    // 状态，0：待审核，9：审核不通过，10：审核通过
    const STATUS_PASS = 10;

    /**
     * 获取一个用户的次要跟进人（多个）
     * @param $userId
     * @param string $select
     * @return mixed
     */
    public function getByUserId($userId, $select="*")
    {
        return self::model()->findAll([
            "select" => $select,
            "condition" => "user_id = :userId AND `status` = :status",
            "params" => [
                ":userId" => $userId,
                ":status" => self::STATUS_PASS
            ]
        ]);
    }
}