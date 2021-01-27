<?php

namespace Wan\Models\OrderConfigService;

class WanArConfigOrderServerType extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['order_config_service']['config_order_server_type'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // server_status，可用状态（0不可用，1可用）
    const SERVER_STATUS_INVALID = 0;
    const SERVER_STATUS_VALID = 1;

    /**
     * 获取所有一级服务类型
     * @param $select
     * @param bool $withDeleted - 是否包括被删除的类型
     * @return mixed
     */
    public function getAllFirstLevel($select="*", $withDeleted=false)
    {
        $params =  [
            "select" => $select,
            "condition" => "parent_id = 0",
        ];
        if (!$withDeleted) {
            $params['condition'] .= " AND server_status = :statusValid";
            $params['params'][':statusValid'] = self::SERVER_STATUS_VALID;
        }
        return self::model()->findAll($params);
    }

    /**
     * 获取一个类目下的所有服务类型
     * @param $categoryId
     * @param $allFirstLevel - self::getAllFirstLevel 的输出
     * @return array
     */
    public function getByCategoryIdWithinRecords($categoryId, $allFirstLevel)
    {
        $result = [];
        foreach ($allFirstLevel as $item) {
            if ($item['server_category_id'] == $categoryId) {
                array_push($result, $item);
            }
        }
        return $result;
    }

    /**
     * 通过 id 获取一条记录
     * @param $serverTypeId
     * @param string $select
     * @return mixed
     */
    public function getById($serverTypeId, $select="*")
    {
        return self::model()->find(
            [
                'select' => $select,
                'condition' => 'id = :id',
                'params' => [
                    ':id' => $serverTypeId
                ]
            ]
        );
    }
}