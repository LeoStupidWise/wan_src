<?php

namespace Wan\Services\Crms;

use Wan\Models\Crms\WanArUserContacts;
use Wan\Models\UserService\WanArUserInfo;

class WanUserContactsService
{
    /**
     * 列表的格式化
     * @param  $records - 从数据库获取的数据
     * @param bool $isExport - 是否是导出
     * @return  array
     */
    public function indexDecorator($records, $isExport=false)
    {
        // 一系列的数据库表初始化
        $wanArUserContacts = new WanArUserContacts();
        $result = [];
        foreach($records as $record) {
            $tableModel = [
                // 建立返回格式，并赋默认值
                'primaryId' => $record['id'],                   # 主键
                'contactName' => $record["name"] ?? "",         # 联系人姓名
                'gender' => '',                                 # 性别，文本
                'genderValue' => $record['gender'],             # 性别，对应的数据库值
                'phone' => $record["phone"] ?? "",              # 电话号码
                'qq' => $record["qq"] ?? "",                    # QQ
                'wx' => $record["wx"] ?? "",                    # 微信
                'position' => '',                               # 职位，文本
                'positionValue' => $record['position'],         # 职位，对应的数据库值
                'isMain' => 0,                                  # 是否是主要联系人
            ];

            // 给每一个项赋具体的值
            $tableModel['gender'] = $wanArUserContacts->getGenderText($record["gender"]);
            $tableModel['position'] = $wanArUserContacts->getPositionText($record["position"]);
            $tableModel['isMain'] = $wanArUserContacts->isMain($record["status"]);
            if ($isExport) {
                $exportRow = [];
                array_push($exportRow, $tableModel['contactName']);
                array_push($exportRow, $tableModel['gender']);
                array_push($exportRow, $tableModel['phone']);
                array_push($exportRow, $tableModel['qq']);
                array_push($exportRow, $tableModel['wx']);
                array_push($exportRow, $tableModel['position']);
                array_push($result, $exportRow);
            }
            else {
                array_push($result, $tableModel);
            }
        }
        return $result;
    }

    /**
     * 将一个联系人设置为用户的主要联系人/普通联系人
     *      - 如果已经存在一个主要联系人，这个主要联系人会被置为普通
     * @param $userContactId
     * @param $isToMain - 是否是设置为主要
     *      0：设为普通
     *      1：设为主要
     * @return array
     */
    public function setToMainOrNormal($userContactId, $isToMain)
    {
        $wanArUserContact = new WanArUserContacts();
        $userContactRcd = $wanArUserContact::model()->findByPk($userContactId);
        if (empty($userContactRcd)) {
            return resultError(4002, "数据库记录不存在");
        }
        if ($userContactRcd["status"] == $wanArUserContact::STATUS_DELETED) {
            return resultError(4002, "联系人不存在");
        }
        if ($isToMain) {
            if ($userContactRcd["status"] == $wanArUserContact::STATUS_MAIN) {
                return resultError(4012, "该用户已经是主要联系人");
            }
        }
        if (!$isToMain) {
            if ($userContactRcd["status"] == $wanArUserContact::STATUS_VALID) {
                return resultError(4012, "该用户已经是普通联系人");
            }
        }
        $transaction = $wanArUserContact::model()->dbConnection->beginTransaction();
        try {
            $wanArUserContact->setAllToNormal($userContactRcd["user_id"]);
            if ($isToMain) {
                $wanArUserContact->setOneToMain($userContactId);
            }
        } catch (\Exception $exception) {
            $transaction->rollback();
            return resultError($exception->getCode(), $exception->getMessage());
        }
        $transaction->commit();
        return resultSuccess();
    }

    /**
     * 联系人导出的历史记录
     * @param $historyRecords
     * @return array
     */
    public function exportHistoryFormat($historyRecords)
    {
        $result = [];
        $arUserInfo = new WanArUserInfo();
        foreach ($historyRecords as $historyRecord) {
            $temp = [
                'addedAt' => '',
                'conditions' => []
            ];
            $tempCondition = [
                'desc' => '',
                'value' => ''
            ];
            $condition = json_decode(base64_decode($historyRecord['con']), true)["params"] ?? [];

            $userId = $condition['userId'] ?? '';                 # 导出用户
            $temp['addedAt'] = date('Y-m-d H:i:s', $historyRecord['add_time']);
            // 导出用户
            $tempCondition['desc'] = '导出用户';
            if (!empty($userId)) {
                $tempCondition['value'] = $arUserInfo->getByUserId($userId, "account")["account"] ?? "--";
            } else {
                $tempCondition['value'] = '--';
            }
            $temp['conditions'][] = $tempCondition;

            if (count($temp['conditions']) > 0) {
                foreach ($historyRecord as $key=>$value) {
                    if (!in_array($key, ['add_time', 'con'])) {
                        $temp[$key] = $value;
                    }
                }
            }
            $result[] = $temp;
        }
        return $result;
    }
}