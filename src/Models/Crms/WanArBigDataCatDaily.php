<?php

namespace Wan\Models\Crms;

class WanArBigDataCatDaily extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['bigdata_cat_daily'];
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
     * 获取指定条件下的记录
     * @param  $search
     * @param  $isCount - 是否是数量查询
     * @return  mixed
     */
    public function getRecordsOrCount($search, $isCount=false)
    {
        $page = $search["page"] ?? 1;
        $limit = $search['limit'] ?? 100;
        $offset = ($page - 1) * $limit;

        $ar = new self();
        $table = $ar->tableName();

        $sqlSelect = "SELECT category_id"
            .", SUM(master_deal_cnt) AS masterDealCnt"
            .", SUM(master_deal_cnt_arranged) AS masterDealCntArranged"
            .", SUM(master_deal_cnt_disarranged) AS masterDealCntDisarranged"
            .", SUM(master_deal_amount) AS masterDealAmount"
            .", SUM(master_deal_amount_arranged) AS masterDealAmountArranged"
            .", SUM(master_deal_amount_disarranged) AS masterDealAmountDisarranged"
            .", SUM(inner_enterprise_deal_cnt) AS innerDealCnt"
            .", SUM(inner_enterprise_deal_cnt_arranged) AS innerDealCntArranged"
            .", SUM(inner_enterprise_deal_cnt_disarranged) AS innerDealCntDisarranged"
            .", SUM(inner_enterprise_deal_amount) AS innerDealAmount"
            .", SUM(inner_enterprise_deal_amount_arranged) AS innerDealAmountArranged"
            .", SUM(inner_enterprise_deal_amount_disarranged) AS innerDealAmountDisarranged";
        if ($isCount) {
            $sqlSelect = "SELECT COUNT(*) AS theCount";
        }
        $sqlFrom = " FROM $table";
        // 有些搜索会涉及到关联查询，在这里就要关联好
        $sqlJoin = "";
        $sqlWhere = $this->getWhere($search);
        // 排序规则写到 addition 里面
        $sqlAddition = " GROUP BY $table.category_id";
        if (!$isCount) {
            $sqlAddition .= " LIMIT $limit".
                " OFFSET $offset";
        }
        $sql = $sqlSelect . $sqlFrom . $sqlJoin . $sqlWhere . $sqlAddition;
        $records = $ar->getDbConnection()->createCommand($sql)->queryAll();
        if ($isCount) {
            return $records[0]['theCount'];
        } else {
            return $records;
        }
    }

    /**
     * 获取指定条件下的查询语句
     * @param  $search
     * @return  mixed
     */
    public function getWhere($search)
    {
        $searchCheckTime = $search['searchCheckTime'] ?? '';                # 考核时间
        $tableCatDaily = self::tableName();
        $wanArCrmConfig = new WanArCrmConfig();

        $categoryIds = [];
        foreach ($wanArCrmConfig->getAllCauseCat("value") as $item) {
            array_push($categoryIds, $item["value"]);
        }
        $where = " WHERE 1=1";
        if (count($categoryIds) > 0) {
            $where .= " AND $tableCatDaily.category_id IN "."(".implode(",", $categoryIds).")";
        }
        if(!empty($searchCheckTime)) {
            $searchCheckTime = explode(" - ", $searchCheckTime);
            $startTime = $searchCheckTime[0]." 00:00:00";
            $endTime = $searchCheckTime[1]." 23:59:59";
            $where .= " AND $tableCatDaily.stat_day >= '$startTime' AND $tableCatDaily.stat_day <= '$endTime'";
        }
        return $where;
    }

    /**
     * 获取整个部门的业绩
     * @param $startDay - 开始日期
     * @param $endDay - 结束日期
     * @return mixed
     */
    public function getDepartmentPerformance($startDay, $endDay)
    {
        $sql = "SELECT SUM(master_deal_cnt) AS masterDealCnt"
            .", SUM(master_deal_amount) AS masterDealAmount"
            .", SUM(inner_enterprise_deal_cnt) AS innerDealCnt"
            .", SUM(inner_enterprise_deal_amount) AS innerDealAmount"
            ." FROM ".self::tableName()
            ." WHERE 1=1";
        if (!empty($startDay)) {
            $sql .= " AND stat_day >= '$startDay'";
        }
        if (!empty($endDay)) {
            $sql .= " AND stat_day <= '$endDay'";
        }
        return self::getDbConnection()->createCommand($sql)->queryRow();
    }
}