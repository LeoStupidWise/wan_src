<?php

namespace Wan\Models\OrderConfigService;

class WanArConfigServerCategory extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['order_config_service']['config_server_category'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // category_status: 可用状态（0不可用，1可用）
    const CATEGORY_STATUS_OFF=0;
    const CATEGORY_STATUS_ON=1;

    // 家具类目的 ID
    const RECORD_JIAJU_ID=1;

    /**
     * 通过类目 ID 获取一个类目记录
     * @param $id
     * @param string $select
     * @return mixed
     */
    public function getById($id, $select="*")
    {
        return self::model()->find([
            "select"=>$select,
            "condition"=>"id = :id",
            "params"=>[
                ":id"=>$id
            ]
        ]);
    }

    /**
     * 获取所有的类目
     * @param string $select
     * @return array
     */
    public function getAll($select="id, cn_name"): array
    {
        return self::model()->findAll([
            'select'=>$select,
            'condition'=>'category_status = :statusOn',
            'params'=>[
                ':statusOn'=>self::CATEGORY_STATUS_ON
            ]
        ]);
    }

    /**
     * 获取所有的类目，包括停用的在内
     * @param string $select
     * @return array
     */
    public function getAllIncludeStoped($select="id, cn_name"): array
    {
        return self::model()->findAll([
            'select'=>$select,
        ]);
    }
}