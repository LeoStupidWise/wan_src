<?php

namespace Wan\Models\Crms;

/**
 * 子账号在 CRM 中的信息
 * Class WanArCrmUserInfoSub
 * @package Wan\Models\Crms
 */
class WanArCrmUserInfoSub extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['crm_user_info_sub'];
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
     * 获取一个子账号的记录
     * @param $subUserId
     * @param string $select
     * @return mixed
     */
    public function getBySubUserId($subUserId, $select="*")
    {
        return self::model()->find(
            [
                "select" => $select,
                "condition" => "sub_user_id = :subUserId",
                "params" => [
                    ":subUserId" => $subUserId
                ]
            ]
        );
    }

    /**
     * 更新一个子账号的备注
     * @param $subUserId
     * @param $remark
     * @return mixed
     */
    public function updateRemark($subUserId, $remark)
    {
        $record = self::model()->find(
            [
                "condition" => "sub_user_id = :subUserId",
                "params" => [
                    ":subUserId" => $subUserId
                ]
            ]
        );
        if (empty($record)) {
            $record = new self();
            $record->sub_user_id = $subUserId;
            $record->create_time = date("Y-m-d H:i:s");
        }
        $record->remark = $remark;
        return $record->save();
    }
}