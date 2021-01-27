<?php

namespace Wan\Models\Crms;

class WanArBigDataUserStat extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['bigdata_user_stat'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    /**
     * 获取一个用户的所属记录
     * @param $userId
     * @param string $select
     * @return mixed
     */
    public function getByUserId($userId, $select="*")
    {
        return self::model()->find([
            "select" => $select,
            "condition" => "user_id = :userId",
            "params" => [
                ":userId" => $userId
            ]
        ]);
    }
    
}