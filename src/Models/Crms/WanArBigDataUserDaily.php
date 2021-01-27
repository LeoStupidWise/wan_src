<?php

namespace Wan\Models\Crms;

use Wan\Models\OrderConfigService\WanArConfigOrderServerType;
use Wan\Models\OrderConfigService\WanArConfigServerCategory;

class WanArBigDataUserDaily extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['bigdata_daily_user_stat_d'];
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
     * 跟进提醒列表历史下载记录的输出格式化
     * @param $historyRecords
     * @return array
     */
    public function exportHistoryFormat($historyRecords)
    {
        $result = [];
        $warConfigCategory = new WanArConfigServerCategory();
        $warServerType = new WanArConfigOrderServerType();
        foreach ($historyRecords as $historyRecord) {
            $temp = [
                'addedAt' => '',
                'conditions' => []
            ];
            $tempCondition = [
                'desc' => '',
                'value' => ''
            ];
            $condition = json_decode(base64_decode($historyRecord['con']), true)['params'] ?? [];

            $searchTimeRange = $condition['searchTimeRange'] ?? '';              # 日期
            $searchServeCat = $condition['searchServeCat'] ?? '';                # 服务类目
            $searchServeType = $condition['searchServeType'] ?? '';              # 服务类型
            if (empty($searchTimeRange)) {
                $searchTimeRange = date("Y-m-d", strtotime("-1 month")) . " - " . date("Y-m-d");
            }

            $temp['addedAt'] = date('Y-m-d H:i:s', $historyRecord['add_time']);

            // 日期
            $tempCondition['desc'] = '日期';
            if (!empty($searchTimeRange)) {
                $tempCondition['value'] = $searchTimeRange;
            } else {
                $tempCondition['value'] = '--';
            }
            $temp['conditions'][] = $tempCondition;

            // 服务类目
            $tempCondition['desc'] = '服务类目';
            if (!empty($searchServeCat)) {
                $tempCondition['value'] = $warConfigCategory->getById($searchServeCat, 'cn_name')['cn_name'];
            } else {
                $tempCondition['value'] = '全部';
            }
            $temp['conditions'][] = $tempCondition;

            // 服务类型
            $tempCondition['desc'] = '服务类型';
            if (!empty($searchServeType)) {
                $tempCondition['value'] = $warServerType->getById($searchServeType, 'cn_name')['cn_name'];
            } else {
                $tempCondition['value'] = '全部';
            }
            $temp['conditions'][] = $tempCondition;

            if (count($temp['conditions']) > 0) {
                foreach ($historyRecord as $key=>$value) {
                    if (!in_array($key, ['add_time', 'con'])) {
                        $temp[$key] = $value;
                    }
                }
                $result[] = $temp;
            }
        }
        return $result;
    }
}