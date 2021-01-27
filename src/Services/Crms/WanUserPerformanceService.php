<?php

namespace Wan\Services\Crms;

use Wan\Models\Crms\WanArBigDataCatDaily;
use Wan\Models\Crms\WanArBigDataUserDaily;
use Wan\Models\OrderConfigService\WanArConfigOrderServerType;
use Wan\Models\Crms\WanArBigDataUserStatSubServer;
use Wan\Models\Crms\WanArBigDataUserStatSubGoods;
use Wan\Models\OrderConfigService\WanArConfigGoodsCategory;

/**
 * 用户统计服务类，这里的用户不指的是产品用户，不是后台用户，后台用户是 customer
 * Class WanUserPerformanceService
 * @package Wan\Services\Crms
 */
class WanUserPerformanceService
{
    // 类目ID
    // 1:家具, 2:灯具, 4:卫浴, 8:墙纸, 9:地毯, 10:健身器材, 12:浴霸, 13:净水器, 14:家电
    // 15:智能锁, 16:门, 17:晾衣架, 18:窗帘
    const CAT_ID_JIAJU = 1;
    const CAT_ID_DENGJU = 2;
    const CAT_ID_WEIYU = 4;
    const CAT_ID_QIANGZHI = 8;
    const CAT_ID_DITAN = 9;
    const CAT_ID_JIANSHENQICAI = 10;
    const CAT_ID_YUBA = 12;
    const CAT_ID_JINGSHUIQI = 13;
    const CAT_ID_JIADIAN = 14;
    const CAT_ID_ZHINENGSUO = 15;
    const CAT_ID_MEN = 16;
    const CAT_ID_LIANGYIJIA = 17;
    const CAT_ID_CHUANLIANG = 18;

    /**
     * 获取用户信息页饼图和柱状图的信息，组装出来的数据主要是为页面的数据服务，格式可以参考下面的 demo
     *      柱状图：https://echarts.apache.org/examples/zh/editor.html?c=bar-background
     *      饼图：https://echarts.apache.org/examples/zh/editor.html?c=pie-nest
     * @param $userId
     * @param $categoryAll - 所有的类目
     * @return array
     */
    public function getUserDataOfBarAndPie($userId, $categoryAll)
    {
        $wanArServerType = new WanArConfigOrderServerType();
        $wanArBigDataUserStatSubServer = new WanArBigDataUserStatSubServer();
        $wanArBigDataUserStatSubGoods = new WanArBigDataUserStatSubGoods();
        $wanArGoodsCategory = new WanArConfigGoodsCategory();
        // 饼图数据的初始化
        $pieLegendData = [];
        $pieSeriesData = [
            "firstLevel" => [],                         # 服务类目的数据
            "secondLevel" => [],                        # 服务类型的数据
        ];
        // 柱状图数据的初始化
        $barData = [];
        // [
        //      '家具类目ID' => [
        //          'names' => [家具下商品类别 1，家具下商品类别 2],
        //          'values' => [家具下商品类别 1 的成交量，家具下商品类别 2 的成交量]
        //      ]
        // ]
        $firstServerTypeAll = $wanArServerType->getAllFirstLevel("id, server_category_id, cn_name", true);
        $goodsAll = $wanArGoodsCategory->getAll("id, server_category_id, cn_name", true);
        $goodsAll = \ArrayHelper::index($goodsAll, 'id');
        
        foreach ($categoryAll as $item) {
            // 类型的数据
            $categoryNum = 0;               # 这个类目下所有的成交量
            $categoryId = $item['id'];      # 当前类目ID
            $categoryName = $item['cn_name'];
            $categoryPerformances = $wanArBigDataUserStatSubServer->getUserCategoryData($userId, $categoryId);
            if (empty($categoryPerformances)) {
                // 类目没有数据，这个类型肯定就没有数据，就不用继续了
                continue;
            }
            $goodsPerformaces = $wanArBigDataUserStatSubGoods->getUserCategoryData($userId, $categoryId);
            // ZoeHere
            $serverTypeAll = $wanArServerType->getByCategoryIdWithinRecords($categoryId, $firstServerTypeAll);
            $serverTypeAll = \ArrayHelper::index($serverTypeAll, 'id');
            $serverTypeData = [];
            $goodsData = [];
            // 统计类型的分布
            foreach ($categoryPerformances as $categoryPerformance) {
                if (!in_array($categoryName, $pieLegendData)) {
                    array_push($pieLegendData, $categoryName);
                }
                $serverTypeId = $categoryPerformance['server_id'];
                $serverDealCount = $categoryPerformance['total_deal_cnt'];
                if (empty($serverDealCount)) {
                    continue;
                }
                $categoryNum += $serverDealCount;
                if (isset($serverTypeData[$serverTypeId])) {
                    $serverTypeData[$serverTypeId]['value'] += $serverDealCount;
                }
                else {
                    $serverTypeData[$serverTypeId] = [
                        'name' => $serverTypeAll[$serverTypeId]['cn_name'],
                        'value' => $serverDealCount,
                        'categoryName' => $categoryName,
                    ];
                }
            }
            // 统计商品类别的分布
            foreach ($goodsPerformaces as $goodsPerformace) {
                $goodsId = $goodsPerformace['goods_id'];
                $goodsDealCount = $goodsPerformace['total_deal_cnt'];
                if (empty($goodsDealCount)) {
                    continue;
                }
                if (isset($goodsData[$goodsId])) {
                    $goodsData[$goodsId]['value'] += $goodsDealCount;
                }
                else {
                    if (isset($goodsAll[$goodsId])) {
                        // 统计数据中有脏数据，goods_id 在配置中找不到类别
                        $goodsData[$goodsId] = [
                            'name' => $goodsAll[$goodsId]['cn_name'],
                            'value' => $goodsDealCount
                        ];
                    }
                }
            }
            // 筛选出需要展示的类型数据，为 0 的数据不需要展示
            if ($categoryNum != 0) {
                array_push(
                    $pieSeriesData['firstLevel'],
                    ['value' => $categoryNum, 'name' => $categoryName]
                );
                foreach ($serverTypeData as $serverTypeDatum) {
                    array_push($pieSeriesData['secondLevel'], $serverTypeDatum);
                }
                // 商品类型数据的组装
                $barData[$categoryId] = ['names' => [], 'values' => [], 'catName' => $categoryName];
                foreach ($goodsData as $goodsDatum) {
                    array_push($barData[$categoryId]['names'], $goodsDatum['name']);
                    array_push($barData[$categoryId]['values'], $goodsDatum['value']);
                }
            }
        }
        return compact('pieLegendData', 'pieSeriesData', 'barData');
    }

    /**
     * 获取一个用户在一定筛选条件下的统计数据，结果以天为键进行输出
     * @param $userId
     * @param $searchParams - 限定条件
     * @return int[]
     */
    public function getUserDailyRecords($userId, $searchParams)
    {
        $startDay = $searchParams['timeStart'];
        $endDay = $searchParams['timeEnd'];
        $searchServeCat = $searchParams['searchServeCat'];
        $searchServeType = $searchParams['searchServeType'];
        $warBigDataUserDaily = new WanArBigDataUserDaily();
        $result = [];
        $tableName = $warBigDataUserDaily->tableName();
        $sql = "SELECT stat_day"
            .", daily_master_order_cnt AS masterOrderCnt"                                        # 下单量，平台
            .", daily_inner_enterprise_order_cnt AS innerOrderCnt"                               # 下单量，闪装
            .", daily_master_ordered_amount AS masterOrderAmount"                                # 下单金额，平台
            .", daily_total_order_amount - daily_master_ordered_amount AS innerOrderAmount"      # 下单金额，闪装
            .", daily_master_order_on_cnt AS masterDealCnt"                                      # 成交量，平台
            .", daily_inner_enterprise_order_on_cnt AS innerDealCnt"                             # 成交量，闪装
            .", daily_master_order_amount AS masterDealAmount"                                   # 成交金额，平台
            .", daily_innerenterprise_order_amount AS innerDealAmount"                           # 成交金额，闪装
            .", daily_complaint_cnt AS masterComplaintCnt"                                       # 投诉次数，平台
            .", daily_complaint_cancel_cnt AS masterComplaintCancelCnt"                          # 取消投诉次数，平台
            .", daily_complaint_on_cnt AS masterComplaintOnCnt"                                  # 投诉成立次数，平台
            .", daily_arbitration_cnt AS masterArbitrationCnt"                                   # 仲裁次数，平台
            .", daily_inner_enterprise_arbitration_cnt AS innerArbitrationCnt"                   # 仲裁次数，闪装
            .", daily_arbitration_cancel_cnt AS masterArbitrationCancelCnt"                      # 取消仲裁次数，平台
            .", daily_inner_enterprise_arbitration_cancel_cnt AS innerArbitrationCancelCnt"      # 取消仲裁次数，闪装
            .", daily_arbitration_on_cnt AS masterArbitrationOnCnt"                              # 仲裁成立次数，平台
            .", daily_inner_enterprise_arbitration_on_cnt AS innerArbitrationOnCnt"              # 仲裁成立次数，闪装
            .', master_paid_cnt, inner_enterprise_paid_cnt'
            .', master_paid_amount, inner_enterprise_paid_amount'
            ." FROM ".$tableName
            ." WHERE user_id = $userId";
        if (!empty($startDay)) {
            $sql .= " AND $tableName.stat_day >= '$startDay'";
        }
        if (!empty($endDay)) {
            $sql .= " AND $tableName.stat_day <= '$endDay'";
        }
        if (!empty($searchServeCat)) {
            if (!empty($searchServeType)) {
                $sql .= " AND $tableName.server_type_id = $searchServeType";
            }
            else {
                $sql .= " AND $tableName.category_id = $searchServeCat";
            }
        }
        $records = $warBigDataUserDaily->getDbConnection()->createCommand($sql)->queryAll();
        $recordKeys = [
            "masterOrderCnt", "innerOrderCnt", "masterOrderAmount", "innerOrderAmount"
            , "masterDealCnt", "innerDealCnt", "masterDealAmount", "innerDealAmount", "masterComplaintCnt"
            , "masterComplaintCancelCnt", "masterComplaintOnCnt", "masterArbitrationCnt", "innerArbitrationCnt"
            , "masterArbitrationCancelCnt", "innerArbitrationCancelCnt", "masterArbitrationOnCnt"
            , "innerArbitrationOnCnt", 'master_paid_cnt', 'inner_enterprise_paid_cnt', 'master_paid_amount'
            , 'inner_enterprise_paid_amount'
        ];
        // 对记录以日期为键，进行拼装
        foreach ($records as $record) {
            $statDay = $record['stat_day'];
            if (!isset($result[$statDay])) {
                foreach ($recordKeys as $recordKey) {
                    $result[$statDay][$recordKey] = $record[$recordKey];
                }
            }
            else {
                foreach ($recordKeys as $recordKey) {
                    $result[$statDay][$recordKey] += $record[$recordKey];
                }
            }
        }
        return $result;
    }

    /**
     * 获取某个类目统计表的表名
     * @param $catId - 类目ID
     * @return mixed
     */
    public function tableNameByCatId($catId)
    {
        return \Yii::app()->params['table_alias']['crms']['bigdata_daily_user_stat_'.$catId];
    }

    /**
     * 获取所有类目统计表的名称
     * @return string[]
     */
    public function getAllCatTableName()
    {
        return [
            self::CAT_ID_JIAJU => self::tableNameByCatId(self::CAT_ID_JIAJU),
            self::CAT_ID_DENGJU => self::tableNameByCatId(self::CAT_ID_DENGJU),
            self::CAT_ID_WEIYU => self::tableNameByCatId(self::CAT_ID_WEIYU),
            self::CAT_ID_QIANGZHI => self::tableNameByCatId(self::CAT_ID_QIANGZHI),
            self::CAT_ID_DITAN => self::tableNameByCatId(self::CAT_ID_DITAN),
            self::CAT_ID_JIANSHENQICAI => self::tableNameByCatId(self::CAT_ID_JIANSHENQICAI),
            self::CAT_ID_YUBA => self::tableNameByCatId(self::CAT_ID_YUBA),
            self::CAT_ID_JINGSHUIQI => self::tableNameByCatId(self::CAT_ID_JINGSHUIQI),
            self::CAT_ID_JIADIAN => self::tableNameByCatId(self::CAT_ID_JIADIAN),
            self::CAT_ID_ZHINENGSUO => self::tableNameByCatId(self::CAT_ID_ZHINENGSUO),
            self::CAT_ID_MEN => self::tableNameByCatId(self::CAT_ID_MEN),
            self::CAT_ID_LIANGYIJIA => self::tableNameByCatId(self::CAT_ID_LIANGYIJIA),
            self::CAT_ID_CHUANLIANG => self::tableNameByCatId(self::CAT_ID_CHUANLIANG),
        ];
    }
}