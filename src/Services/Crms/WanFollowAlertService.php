<?php

namespace Wan\Services\Crms;

use Wan\Models\Crms\WanArCrmUser;
use Wan\Models\Crms\WanArFollowAlert;
use Wan\Models\UserService\WanArUserInfo;

class WanFollowAlertService
{
    /**
     * 列表的格式化
     * @param $records - 从数据库获取的数据
     * @param bool $isExport - 是否是导出
     * @return  array
     */
    public function indexDecorator($records, $isExport=false)
    {
        // 一系列的数据库表初始化
        $result = [];
        $wanArCrmUser = new WanArCrmUser();
        $wanArUserInfo = new WanArUserInfo();
        $wanArFollowAlert = new WanArFollowAlert();

        $allUsers = $wanArCrmUser->getAll("id, uname");
        $allUsers = \ArrayHelper::index($allUsers, "id");
        foreach($records as $record) {
            $tableModel = [
                // 建立返回格式，并赋默认值
                'primaryId' => $record['id'],               # 主键
                'createTime' => $record["create_time"],     # 创建日期
                'createCus' => '',                          # 创建人
                'responseCus' => '',                        # 负责人
                'title' => $record["title"],                # 标题
                'followUsers' => '',                        # 跟进用户
                'followTime' => $record["follow_time"],     # 跟进日期
                'reminder' => '',                           # 提前提醒
                'content' => $record["content"],            # 提醒内容
            ];
            // 给每一个项赋具体的值
            $tableModel["createCus"] = $allUsers[$record["create_id"]]["uname"];
            $responseCus = [];
            foreach (explode(",", $record["to_customer_ids"]) as $responseCusId) {
                array_push($responseCus, $allUsers[$responseCusId]["uname"]);
            }
            $tableModel['responseCus'] = implode(",", $responseCus);
            $userInfos = [];
            foreach ($wanArUserInfo->getByUserIds($record["to_user_ids"], "account") as $userInfo) {
                array_push($userInfos, $userInfo["account"]);
            }
            $tableModel["followUsers"] = implode(",", $userInfos);
            $tableModel["reminder"] = "提前".$wanArFollowAlert->getReminderText($record['reminder']);
            if (!$isExport) {
                array_push($result, $tableModel);
            }
            else {
                array_push(
                    $result,
                    [
                        $tableModel["createTime"],
                        $tableModel["createCus"],
                        $tableModel["responseCus"],
                        $tableModel["title"],
                        $tableModel["followUsers"],
                        $tableModel["followTime"],
                        $tableModel["reminder"],
                        $tableModel["content"],
                    ]
                );
            }
        }
        return $result;
    }

    /**
     * 跟进提醒列表历史下载记录的输出格式化
     * @param $historyRecords
     * @return array
     */
    public function exportHistoryFormat($historyRecords)
    {
        $result = [];
        $wanArCrmUser = new WanArCrmUser();
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

            $searchCreateTime = $condition['searchCreateTime'] ?? '';    # 创建日期
            $searchFollowTime = $condition['searchFollowTime'] ?? '';    # 跟进日期
            $searchCreateCus = $condition['searchCreateCus'] ?? '';      # 创建人
            $searchResponseCus = $condition['searchResponseCus'] ?? '';  # 负责人

            $temp['addedAt'] = date('Y-m-d H:i:s', $historyRecord['add_time']);

            // 创建日期
            $tempCondition['desc'] = '创建日期';
            if (!empty($searchCreateTime)) {
                $tempCondition['value'] = $searchCreateTime;
            } else {
                $tempCondition['value'] = '全部';
            }
            $temp['conditions'][] = $tempCondition;

            // 跟进日期
            $tempCondition['desc'] = '跟进日期';
            if (!empty($searchFollowTime)) {
                $tempCondition['value'] = $searchFollowTime;
            } else {
                $tempCondition['value'] = '全部';
            }
            $temp['conditions'][] = $tempCondition;

            // 创建人
            $tempCondition['desc'] = '创建人';
            if (!empty($searchCreateCus)) {
                $tempCondition['value'] = $wanArCrmUser->getByUserId($searchCreateCus, "uname")["uname"];
            } else {
                $tempCondition['value'] = '全部';
            }
            $temp['conditions'][] = $tempCondition;

            // 负责人
            $tempCondition['desc'] = '负责人';
            if (!empty($searchResponseCus)) {
                $tempCondition['value'] = $wanArCrmUser->getByUserId($searchResponseCus, "uname")["uname"];
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