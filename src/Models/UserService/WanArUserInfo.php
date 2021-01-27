<?php

namespace Wan\Models\UserService;

class WanArUserInfo extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['user_service']['user_info'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // 闪装总包用户ID，测试环境
    const INNER_ENTERPRISE_USER_IDS_TEST = [3735430449, 4958027784, 4958127223, 4958027551];
    // 生产环境
    const INNER_ENTERPRISE_USER_IDS_PROD = [
        12470967541, 12437188708, 12470947851, 12470959347, 12470937642, 12498219235, 12693966276
    ];

    // status 字段的枚举
    const USER_STATUS_ALL = [
        ['value' => 1, 'text' => '正常'],
        ['value' => 2, 'text' => '禁用'],
        ['value' => 3, 'text' => '已注销'],
    ];

    /**
     * 获取多个用户ID对应的用户记录
     * @param $userIds
     * @param string $select
     * @return array
     */
    public function getByUserIds(string $userIds, $select="*")
    {
        if (empty($userIds)) {
            return [];
        }
        return self::model()->findAll(
            [
                "select" => $select,
                "condition" => "user_id IN (".$userIds.")"
            ]
        );
    }

    /**
     * zoe
     * 通过 userId 找到一个用户的信息
     * @param $userId
     * @param string $select
     * @return array
     */
    public function getByUserId($userId, $select="*")
    {
        return self::model()->find([
            'select' => $select,
            'condition' => 'user_id=:userId',
            'params' => [
                'userId' => $userId
            ]
        ]);
    }

    /**
     * 获取闪装总包的用户ID
     * @return int[]
     */
    public function getInnerEnterpriseUserIds()
    {
        if (DEV_ENV == "prod") {
            return self::INNER_ENTERPRISE_USER_IDS_PROD;
        }
        return self::INNER_ENTERPRISE_USER_IDS_TEST;
    }

    /**
     * 获取用户状态对应的文本
     * @param $status
     * @return mixed|string
     */
    public function getStatusText($status)
    {
        $text = '';
        foreach (self::USER_STATUS_ALL as $item) {
            if ($item['value'] == $status) {
                $text = $item['text'];
                break;
            }
        }
        return $text;
    }

    /**
     * account 的模糊搜索
     * @param $str
     * @param string $select
     * @return mixed
     */
    public function getLike($str, $select="*")
    {
        $str = "%$str%";
        return self::model()->findAll([
            'select' => $select,
            'condition' => 'account LIKE :account',
            'params' => [
                ':account' => $str
            ]
        ]);
    }
}