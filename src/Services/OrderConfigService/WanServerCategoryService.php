<?php

namespace Wan\Services\OrderConfigService;

use Wan\Models\OrderConfigService\WanArConfigOrderServerType;
use Wan\Models\OrderConfigService\WanArConfigServerCategory;

class WanServerCategoryService
{
    /**
     * 获取类目、类型联动需要的页面数据
     * @return array
     */
    public function getCategoryServerTypeSelection()
    {
        $warConfigServerCategory = new WanArConfigServerCategory();
        $tableCategory = $warConfigServerCategory->tableName();
        $tableType = (new WanArConfigOrderServerType())->tableName();
        $result = [];

        $sql = "SELECT"
            ." $tableCategory.id AS catId, $tableCategory.cn_name AS catName"
            .", $tableType.id AS typeId, $tableType.cn_name AS typeName"
            ." FROM $tableCategory"
            ." LEFT JOIN $tableType ON $tableCategory.id = $tableType.server_category_id"
            ." WHERE $tableCategory.category_status = ".WanArConfigServerCategory::CATEGORY_STATUS_ON
            ." AND $tableType.server_status = ".WanArConfigOrderServerType::SERVER_STATUS_VALID
            ." AND $tableType.parent_id = 0";
        $records = $warConfigServerCategory->getDbConnection()->createCommand($sql)
            ->queryAll($sql);
        foreach ($records as $record) {
            $catId = $record['catId'];
            if (!isset($result[$catId])) {
                $result[$catId] = [
                    'value' => $catId,
                    'text' => $record['catName'],
                    'children' => []
                ];
            }
            if (!empty($record['typeId'])) {
                array_push(
                    $result[$catId]['children'],
                    [
                        'value' => $record['typeId'],
                        'text' => $record['typeName'],
                        'children' => []
                    ]
                );
            }
        }
        return array_values($result);
    }
}