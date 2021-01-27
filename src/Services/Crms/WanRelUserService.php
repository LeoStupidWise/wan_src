<?php

namespace Wan\Services\Crms;

use Wan\Models\Crms\WanArBigDataUserStat;
use Wan\Models\Crms\WanArFollowUser;
use Wan\Models\Crms\WanArFollowUserSub;
use Wan\Models\Crms\WanArRelUsers;
use Wan\Models\Crms\WanArUserContacts;
use Wan\Models\OrderConfigService\WanArConfigServerCategory;
use Wan\Models\UserService\WanArUserInfo;

class WanRelUserService
{
    /**
     * 列表的格式化
     * @param $records - 从数据库获取的数据
     * @param $requestUserId - 当前请求页面的 userId
     * @return  array
     */
    public function indexDecorator($records, $requestUserId)
    {
        $warUserInfo = new WanArUserInfo();
        $warUserContacts = new WanArUserContacts();
        $warBigDataUserStat = new WanArBigDataUserStat();
        $result = [];
        foreach($records as $record) {
            $anotherUserId = $record['another_user_id'];
            if ($requestUserId == $anotherUserId) {
                $anotherUserId = $record['one_user_id'];
            }
            $tableModel = [
                // 建立返回格式，并赋默认值
                'primaryId' => $record['id'],                   # 主键
                'userId' => $anotherUserId,                     # 关联账号ID
                'userName' => '',                               # 关联账号名称
                'userPhone' => '',                              # 关联账号手机号
                'customerName' => '',                           # 客户姓名
                'status' => '',                                 # 状态
                'orderCnt' => '',                               # 下单量
                'orderAmount' => '',                            # 下单金额
                'paidCnt' => 0,                                 # 付款量
                'paidAmount' => 0,                              # 付款金额
                'dealCnt' => '',                                # 成交量
                'dealAmount' => '',                             # 成交金额
            ];

            $userInfoRcd = $warUserInfo->getByUserId($anotherUserId, 'account, status, phone');
            $bigDataUserStatRcd = $warBigDataUserStat->getByUserId(
                $anotherUserId,
                'total_order_cnt, total_order_amount, total_deal_cnt, total_deal_amount, total_paid_cnt'
                .', total_paid_amount'
            );
            // 给每一个项赋具体的值
            $tableModel['userName'] = $userInfoRcd['account'] ?? '';
            $tableModel['userPhone'] = $userInfoRcd['phone'] ?? '';
            $tableModel['customerName'] = $warUserContacts->getUserMainContact(
                $anotherUserId,
                'name'
            )['name'] ?? '';
            $tableModel['status'] = $warUserInfo->getStatusText($userInfoRcd['status']);
            $tableModel['orderCnt'] = $bigDataUserStatRcd['total_order_cnt'] ?? 0;
            $tableModel['orderAmount'] = $bigDataUserStatRcd['total_order_amount'] ?? 0;
            $tableModel['paidCnt'] = $bigDataUserStatRcd['total_paid_cnt'];
            $tableModel['paidAmount'] = $bigDataUserStatRcd['total_paid_amount'] ?? 0;
            $tableModel['dealCnt'] = $bigDataUserStatRcd['total_deal_cnt'] ?? 0;
            $tableModel['dealAmount'] = $bigDataUserStatRcd['total_deal_amount'] ?? 0;
            array_push($result, $tableModel);
        }
        return $result;
    }

    /**
     * 把另一个用户 $anotherUserId 添加到当前用户 $oneUserId，确定一个关联关系
     * @param $oneUserId
     * @param $anotherUserId
     * @return array
     */
    public function addRelation($oneUserId, $anotherUserId)
    {
        $wanArRelUser = new WanArRelUsers();
        $wanArUserInfo = new WanArUserInfo();
        $wanArConfigServerCategory = new WanArConfigServerCategory();

        $anotherUser = $wanArUserInfo->getByUserId($anotherUserId, 'user_id');
        if (!isset($anotherUser['user_id'])) {
            return resultError(4002, '未找到用户 ID 对应的用户');
        }
        // 校验 2 个用户进行关联的合法性
        $tempResult = self::doesTwoUserValidAddToRelation($oneUserId, $anotherUserId);
        if ($tempResult['code'] != 1) {
            $invalidCatId = $tempResult['data']['categoryId'];
            $categoryRcd = $wanArConfigServerCategory->getById($invalidCatId, 'cn_name');
            return resultError(
                4001,
                '用户中存在相同类目有不同负责人',
                [
                    'categoryName' => $categoryRcd['cn_name']
                ]
            );
        }
        $record = $wanArRelUser->getRelationRecord($oneUserId, $anotherUserId);
        if (!empty($record)) {
            return resultError(4001, '关联关系已存在');
        }
        if (!$wanArRelUser->addRelation($oneUserId, $anotherUserId)) {
            return resultError(4101, '添加失败，请联系管理员');
        }
        return resultSuccess();
    }

    /**
     * 两个用户进行关联时，是否合法，将 anotherUserId 添加作为 oneUserId 的关联账号
     *      用户存在相同服务类目不同负责人情况无法进行关联
     * @param $oneUserId
     * @param $anotherUserId
     * @return bool
     */
    public function doesTwoUserValidAddToRelation($oneUserId, $anotherUserId)
    {
        $wanArFollowUser = new WanArFollowUser();
        $wanArFollowUserSub = new WanArFollowUserSub();

        $oneFollowers = [];
        $anotherFollowers = [];
        $followUserRecord = $wanArFollowUser->getByUserId($oneUserId, 'customer_follow_cat_id, customer_id');
        $followUserSubRecords = $wanArFollowUserSub->getByUserId($oneUserId, 'serve_cat_id, customer_id');
        if (!empty($followUserRecord)) {
            $oneFollowers[$followUserRecord['customer_follow_cat_id']] = $followUserRecord['customer_id'];
        }
        foreach ($followUserSubRecords as $followUserSubRecord) {
            $oneFollowers[$followUserSubRecord['serve_cat_id']] = $followUserSubRecord['customer_id'];
        }
        $followUserRecord = $wanArFollowUser->getByUserId($anotherUserId, 'customer_follow_cat_id, customer_id');
        $followUserSubRecords = $wanArFollowUserSub->getByUserId($anotherUserId, 'serve_cat_id, customer_id');
        if (!empty($followUserRecord)) {
            $anotherFollowers[$followUserRecord['customer_follow_cat_id']] = $followUserRecord['customer_id'];
        }
        foreach ($followUserSubRecords as $followUserSubRecord) {
            $anotherFollowers[$followUserSubRecord['serve_cat_id']] = $followUserSubRecord['customer_id'];
        }
        foreach ($oneFollowers as $followCategoryId=>$followCustomerId) {
            if (isset($anotherFollowers[$followCategoryId])) {
                if ($anotherFollowers[$followCategoryId] != $followCustomerId) {
                    return resultError(
                        4001,
                        '2 个账号存在服务类目不同负责人的情况',
                        [
                            'categoryId' => $followCategoryId
                        ]
                    );
                }
            }
        }
        return resultSuccess();
    }
}