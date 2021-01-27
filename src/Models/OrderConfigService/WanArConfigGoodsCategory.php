<?php

namespace Wan\Models\OrderConfigService;

class WanArConfigGoodsCategory extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['order_config_service']['config_goods_category'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // goods_status，可用状态（0不可用，1可用）
    const GOODS_STATUS_INVALID = 0;
    const GOODS_STATUS_VALID = 1;

    /**
     * 获取所有一级商品
     * @param $select
     * @return mixed
     */
    public function getAllFirstLevel($select="*")
    {
        return self::model()->findAll(
            [
                "select" => $select,
                "condition" => "goods_status = :goodsStatus AND parent_id = 0",
                "params" => [
                    ":goodsStatus" => self::GOODS_STATUS_VALID
                ]
            ]
        );
    }

    /**
     * 获取所有类别
     * @param string $select
     * @param bool $widthDeleted - 是否包含已经失效的记录
     * @return mixed
     */
    public function getAll($select="*", $widthDeleted=false)
    {
        $params = [
            "select" => $select,
            "condition" => "1=1",
        ];
        if (!$widthDeleted) {
            $params['condition'] .= " AND goods_status = :goodsStatus";
            $params['params'][':goodsStatus'] = self::GOODS_STATUS_VALID;
        }
        return self::model()->findAll($params);
    }

    /**
     * 获取一个类目下的所有服务类别
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
}