<?php

namespace Wan\Models\Crms;

/**
 * 用户类目，按服务类别计算的统计表
 * Class WanArBigDataUserStatSubServer
 * @package Wan\Models\Crms
 */
class WanArBigDataUserStatSubGoods extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['bigdata_user_stat_sub_goods'];
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
     * 获取一个用户在某个类目下的数据
     * @param $userId
     * @param $categoryId
     * @return mixed
     */
    public function getUserCategoryData($userId, $categoryId)
    {
        return self::model()->findAll(
            [
                "condition" => "user_id = :userId AND category_id = :categoryId",
                "params" => [
                    ":userId" => $userId,
                    ":categoryId" => $categoryId
                ]
            ]
        );
    }
}