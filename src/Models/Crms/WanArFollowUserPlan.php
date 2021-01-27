<?php

namespace Wan\Models\Crms;

use Wan\Models\UserService\WanArUserInfo;

class WanArFollowUserPlan extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['follow_user_plan'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // 联系方式：1:电话2:拜访3:线上沟通
    const FOLLOW_WAY_PHONE = 1;
    const FOLLOW_WAY_DROPBY = 2;
    const FOLLOW_WAY_ONLINE = 3;

    // 问题反馈类型，反馈类型(1:产品使用/缺陷建议2:没下单原因)
    const BACK_TYPE_PRODUCT = 1;
    const BACK_TYPE_NO_ORDER = 2;
    const BACK_TYPE_OTHERS = 3;

    // 从左侧菜单直接进入的页面标识
    const PAGE_TYPE_MENU_INDEX = 'menuIndex';
    // 用户详情页进去的跟进记录页面标识
    const PAGE_TYPE_USER_DETAIL = 'userDetail';

    /**
     * 获取联系（跟进）方式的下拉选元素
     * @return array[]
     */
    public function getFollowWaySelection()
    {
        return [
            ["value"=>self::FOLLOW_WAY_PHONE, "text"=>"电话"],
            ["value"=>self::FOLLOW_WAY_DROPBY, "text"=>"拜访"],
            ["value"=>self::FOLLOW_WAY_ONLINE, "text"=>"线上沟通"],
        ];
    }

    /**
     * 获取一个跟进方式对应的中文文本
     * @param $followWay
     * @return mixed|string
     */
    public function getFollowWayText($followWay)
    {
        foreach (self::getFollowWaySelection() as $item) {
            if ($item["value"] == $followWay) {
                return $item["text"];
            }
        }
        return "";
    }

    /**
     * 获取问题反馈类型的下拉选元素
     * @return array[]
     */
    public function getBackTypeSelection()
    {
        return [
            ["value"=>self::BACK_TYPE_PRODUCT, "text"=>"产品使用/缺陷建议"],
            ["value"=>self::BACK_TYPE_NO_ORDER, "text"=>"没下单原因"],
            ["value"=>self::BACK_TYPE_OTHERS, "text"=>"其他"],
        ];
    }

    /**
     * 获取一个反馈类型对应的中文文本
     * @param $backType
     * @return mixed|string
     */
    public function getBackTypeText($backType)
    {
        foreach (self::getBackTypeSelection() as $item) {
            if ($item["value"] == $backType) {
                return $item["text"];
            }
        }
        return "";
    }

    /**
     * 获取没下单原因对应的详细原因
     * @return string[]
     */
    public function getNoOrderReasons()
    {
        return [
            ["value"=>"操作麻烦", "text"=>"操作麻烦"],
            ["value"=>"报价慢", "text"=>"报价慢"],
            ["value"=>"报价高", "text"=>"报价高"],
            ["value"=>"用别的平台", "text"=>"用别的平台"],
            ["value"=>"转行了", "text"=>"转行了"],
            ["value"=>"自己组建团队", "text"=>"自己组建团队"],
            ["value"=>"平台服务商合作", "text"=>"平台服务商合作"],
            ["value"=>"平台保障", "text"=>"平台保障"],
            ["value"=>"其他", "text"=>"其他"],
        ];
    }

    /**
     * 添加一个跟进计划
     * @param $userId
     * @param $nextFollowTime
     * @param $mark
     * @param array $imageIds
     * @param array $fileUrls
     * @param array $audioUrls
     * @param array $extra - 其他参数
     * @return mixed
     */
    public function addLog(
        $userId, $nextFollowTime, $mark, array $imageIds, array $fileUrls, array $audioUrls, array $extra
    )
    {
        $backType = $extra["backType"] ?? self::BACK_TYPE_PRODUCT;
        $followWay = $extra["followWay"] ?? self::FOLLOW_WAY_PHONE;
        $noOrderReason = $extra["noOrderReason"] ?? [];
        $supplyResult = $extra["supplyResult"] ?? '';

        $presentCustomerId = (new \ArUser())->getUserId();
        $dateTimeNow = date('Y-m-d H:i:s');
        $newRcd = new self();
        $newRcd->user_id = $userId;
        $newRcd->follow_customer_id = $presentCustomerId;
        if (!empty($nextFollowTime)) {
            $newRcd->next_follow_time = $nextFollowTime;
        }
        $newRcd->image_aid = implode(',', $imageIds);
        $newRcd->file_url = json_encode($fileUrls);
        $newRcd->audio_url = implode(',', $audioUrls);
        $newRcd->mark = $mark;
        $newRcd->create_time = $dateTimeNow;
        $newRcd->update_time = $dateTimeNow;
        $newRcd->follow_way = $followWay;
        $newRcd->back_type = $backType;
        $newRcd->detail_reason = implode(",", $noOrderReason);
        $newRcd->back_result = $supplyResult;
        return $newRcd->save();
    }


    /**
     * 数据格式化
     * @param $records
     * @return array
     */
    public function indexDecorator($records)
    {
        $decorator = [];
        $customers = [];
        $warCrmUser = new WanArCrmUser();
        $arFollowPlan = new \ArFollowUserPlan();
        $warUserInfo = new WanArUserInfo();
        $warFollowUser = new WanArFollowUser();
        $userIdsQueried = [];
        foreach ($records as $index=>$record) {
            $tableModel = [
                // 建立返回格式，并赋默认值
                'indexNo' => '',                                    # 序号
                'createTime' => '',                                 # 添加日期
                'nextFollowTime' => '',                             # 下一次跟进日期
                'mark' => '',                                       # 文字
                'pictures' => '',                                   # 图片
                'files' => '',                                      # 文档
                'audios' => '',                                     # 音频
                'customerName' => '',                               # 跟进人
                'followPlanUrl' => '',                              # 跟进计划页面的地址
                'followWay' => $record['follow_way'],               # 跟进方式
                'followWayText' => '',                              # 跟进方式（中文文本）
                'backType' => $record['back_type'],                 # 反馈问题类型
                'backTypeText' => '',                               # 反馈问题类型（中文文本）
                'detailReason' => '',                               # 具体原因
                'backResult' => $record['back_result'],             # 反馈结果
                'userName' => '',                                   # 用户名
                'contactName' => '',                                # 用户姓名
                'userId' => $record['user_id'],
                'detailUrl' => '',                                  # 详情链接
            ];
            // 给每一个项赋具体的值
            $userId = $record['user_id'];
            $tempCustomerId = $record['follow_customer_id'];
            $tableModel['indexNo'] = $index+1;
            $tableModel['createTime'] = $record['create_time'];
            $tableModel['nextFollowTime'] = $record['next_follow_time'];
            if (empty($tableModel["nextFollowTime"])) {
                $tableModel["nextFollowTime"] = "--";
            }
            $tableModel['mark'] = $record['mark'];
            $tableModel['pictures'] = [];
            if (!empty($record["image_aid"])) {
                foreach (explode(',', $record['image_aid']) as $imgAid) {
                    $tableModel['pictures'][] = \Common::GetImgUrlByaid($imgAid);
                }
            }
            $tableModel['files'] = [];
            if (!empty($record["file_url"])) {
                $tableModel['files'] = json_decode($record["file_url"], true);
                if (empty($tableModel["files"])) {
                    $tableModel["files"] = [];
                }
            }
            $tableModel['audios'] = explode(',', $record['audio_url']);
            if (isset($customers[$tempCustomerId])) {
                $tableModel['customerName'] = $customers[$tempCustomerId];
            }
            else {
                $tableModel['customerName'] = $warCrmUser->getByUserId($tempCustomerId, 'uname')['uname'] ?? '';
                $customers[$tempCustomerId] = $tableModel['customerName'];
            }
            $tableModel["followWayText"] = self::getFollowWayText($record["follow_way"]);
            $tableModel["backTypeText"] = self::getBackTypeText($record["back_type"]);
            $tableModel["detailReason"] = $record['detail_reason'];
            if (isset($userIdsQueried[$userId])) {
                $userInfo = $userIdsQueried[$userId];
            }
            else {
                $userInfo = $warUserInfo->getByUserId($userId, 'account, user_name');
                $userIdsQueried[$userId] = $userInfo;
            }
            $tableModel['userName'] = $userInfo['account'] ?? '';
            $tableModel['contactName'] = $userInfo['user_name'] ?? '';
            $followUser = $warFollowUser->getByUserId($userId, 'distribute_status');
            if (isset($followUser['distribute_status'])) {
                $basicType = WanArFollowUser::PAGE_TYPE_BASIC_FROM_INIT;
                if ($followUser['distribute_status'] == WanArFollowUser::DISTRIBUTE_STATUS_PASS) {
                    $basicType = WanArFollowUser::PAGE_TYPE_BASIC_FROM_MARKET;
                }
                $tableModel["detailUrl"] =$warFollowUser->getUserBasicInfoUrl($userId, $basicType);
            }
            if (DEV_ENV == "local") {
                $tableModel["pictures"] = [];
                $tableModel["audios"] = [];
                foreach ([0,1,2] as $i) {
                    array_push(
                        $tableModel["pictures"],
                        \Common::PLACEHOLDER_IMG_URL
                    );
                    array_push(
                        $tableModel["files"],
                        [
                            "value" => "https://qncdn.wanshifu.com/doc/706f68bb75cbfbc62ef0466190c174cd.do",
                            "name" => "ZoePlaceHolder"
                        ]
                    );
                    array_push(
                        $tableModel["audios"],
                        \Common::PLACEHOLDER_M4A_URL
                    );
                }
            }
            $tableModel["followPlanUrl"] = $arFollowPlan->getFollowPlanUrl();
            array_push($decorator, $tableModel);
        }
        return $decorator;
    }
}