<?php

namespace Wan\Models\Crms;

class WanArCrmConfig extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['crm_config'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // 职位为 部门经理 对应的配置 ID
    const POSITION_DEPARTMENT_LEADER_ID = 49;
    // 职位为 事业部经理 对应的配置ID
    const POSITION_GROUP_LEADER_ID = 48;
    // 职位：58-测试
    const POSITION_GROUP_TEST_ID = 58;

    // 获取事业部对应类目时的键
    const NAME_OF_GET_CAUSE_CAT = "cause_cat";
    // 表示事业部的记录 name 键
    const NAME_OF_CAUSE = "user_cause";

    /**
     * 获取一个部门对应的类目
     * @param $causeId - 部门在 crm_config 中记录的 id
     * @param string $select
     * @return mixed - 返回的是一个数组，一个部门可能对应多个类目
     */
    public function getCauseCategory($causeId, $select="*")
    {
        return self::model()->findAll(
            [
                "select" => $select,
                "condition" => "pid = :pid AND name = :name",
                "params" => [
                    ":pid" => $causeId,
                    ":name" => self::NAME_OF_GET_CAUSE_CAT
                ]
            ]
        );
    }

    /**
     * 获取所有事业部
     * @return mixed
     */
    public function getAllCause($select="*")
    {
        return self::model()->findAll(
            [
                "select" => $select,
                "condition" => "name = :name",
                "params" => [
                    ":name" => self::NAME_OF_CAUSE
                ]
            ]
        );
    }

    /**
     * 获取所有事业部的类目
     * @return mixed
     */
    public function getAllCauseCat($select="*")
    {
        return self::model()->findAll(
            [
                "select" => $select,
                "condition" => "name = :name",
                "params" => [
                    ":name" => self::NAME_OF_GET_CAUSE_CAT
                ]
            ]
        );
    }
}