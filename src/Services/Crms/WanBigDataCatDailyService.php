<?php

namespace Wan\Services\Crms;

use Wan\Models\Crms\WanArBigDataCatDaily;
use Wan\Models\Crms\WanArCrmConfig;

/**
 * 类目统计表
 * Class WanBigDataCatDailyService
 * @package Wan\Services\Crms
 */
class WanBigDataCatDailyService
{
    /**
     * 列表的格式化
     * @param $records - 从数据库获取的数据
     * @param $requestParams - 搜索参数
     * @return  array
     */
    public function indexDecorator($records, $requestParams)
    {
        $searchCheckTime = $requestParams['searchCheckTime'] ?? '';                            # 考核时间
        $wanArBigDataCatDailt = new WanArBigDataCatDaily();
        $wanArCrmConfig = new WanArCrmConfig();
        $startTime = '';
        $endTime = '';
        if(!empty($searchCheckTime)) {
            $searchCheckTime = explode(" - ", $searchCheckTime);
            $startTime = $searchCheckTime[0]." 00:00:00";
            $endTime = $searchCheckTime[1]." 23:59:59";
        }
        $result = [];
        // 每行数据的返回格式
        $tableModel = [
            'dptName' => '',                        # 部门&事业部
            'dptId' => '',                          # 部门&事业部的Id，部门 Id 为 0
            'masterDealCnt' => 0,                   # 平台成交单量
            'masterDealAmount' => 0,                # 平台成交金额
            'enterpriseDealCnt' => 0,               # 总包成交单量
            'enterpriseDealAmount' => 0,            # 总包成交金额
            'totalDealCnt' => 0,                    # 成交量总计
            'totalDealAmount' => 0,                 # 成交金额总计
            'children' => [],                       # 包含的子列
        ];
        // 先求出整个市场部的业绩
        $departmenrPerformance = $wanArBigDataCatDailt->getDepartmentPerformance($startTime, $endTime);
        $departmenrRecord = $tableModel;
        $departmenrRecord['dptName'] = "市场部";
        $departmenrRecord['dptId'] = "0";
        $departmenrRecord['masterDealCnt'] = $departmenrPerformance['masterDealCnt'];
        $departmenrRecord['masterDealAmount'] = $departmenrPerformance['masterDealAmount'];
        $departmenrRecord['enterpriseDealCnt'] = $departmenrPerformance['innerDealCnt'];
        $departmenrRecord['enterpriseDealAmount'] = $departmenrPerformance['innerDealAmount'];
        $departmenrRecord['totalDealCnt'] = $departmenrRecord['masterDealCnt'] + $departmenrRecord['enterpriseDealCnt'];
        $departmenrRecord['totalDealAmount'] =
            $departmenrRecord['masterDealAmount'] +  $departmenrRecord['enterpriseDealAmount'];
        array_push($result, $departmenrRecord);

        // records 是和类目相关的，先找到所有的部门，再找对应类目的数据
        $records = \ArrayHelper::index($records, "category_id");
        foreach ($wanArCrmConfig->getAllCause("id ,value") as $cause) {
            $causePerformance = $tableModel;
            $causePerformance['dptName'] = $cause['value']."事业部";
            $causePerformance['dptId'] = $cause['id'];
            // 事业部下面有已分配、未分配的内容
            $causePerformance["children"] = [
                "arranged" => $tableModel,
                "disarranged" => $tableModel
            ];
            $causePerformance["children"]["arranged"]["dptName"] = "已分配";
            $causePerformance["children"]["disarranged"]["dptName"] = "未分配";
            foreach ($wanArCrmConfig->getCauseCategory($cause['id'], "value") as $causeCat) {
                $catId = $causeCat['value'];
                if (isset($records[$catId])) {
                    $record = $records[$catId];
                    // 总计
                    $causePerformance['masterDealCnt'] += $record['masterDealCnt'];
                    $causePerformance['masterDealAmount'] += $record['masterDealAmount'];
                    $causePerformance['enterpriseDealCnt'] += $record['innerDealCnt'];
                    $causePerformance['enterpriseDealAmount'] += $record['innerDealAmount'];
                    $causePerformance['totalDealCnt'] += ($record['masterDealCnt'] + $record['innerDealCnt']);
                    $causePerformance['totalDealAmount'] += ($record['masterDealAmount'] + $record['innerDealAmount']);
                    // 已分配
                    $causePerformance['children']['arranged']['masterDealCnt'] += $record['masterDealCntArranged'];
                    $causePerformance['children']['arranged']['masterDealAmount'] += $record['masterDealAmountArranged'];
                    $causePerformance['children']['arranged']['enterpriseDealCnt'] += $record['innerDealCntArranged'];
                    $causePerformance['children']['arranged']['enterpriseDealAmount'] += $record['innerDealAmountArranged'];
                    $causePerformance['children']['arranged']['totalDealCnt'] +=
                        ($record['masterDealCntArranged'] + $record['innerDealCntArranged']);
                    $causePerformance['children']['arranged']['totalDealAmount'] +=
                        ($record['masterDealAmountArranged'] + $record['innerDealAmountArranged']);
                    // 未分配
                    $causePerformance['children']['disarranged']['masterDealCnt'] += $record['masterDealCntDisarranged'];
                    $causePerformance['children']['disarranged']['masterDealAmount'] += $record['masterDealAmountDisarranged'];
                    $causePerformance['children']['disarranged']['enterpriseDealCnt'] += $record['innerDealCntDisarranged'];
                    $causePerformance['children']['disarranged']['enterpriseDealAmount'] += $record['innerDealAmountDisarranged'];
                    $causePerformance['children']['disarranged']['totalDealCnt'] +=
                        ($record['masterDealCntDisarranged'] + $record['innerDealCntDisarranged']);
                    $causePerformance['children']['disarranged']['totalDealAmount'] +=
                        ($record['masterDealAmountDisarranged'] + $record['innerDealAmountDisarranged']);
                }
            }
            array_push($result, $causePerformance);
        }
        return $result;
    }
}