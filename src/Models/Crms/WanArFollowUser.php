<?php

namespace Wan\Models\Crms;

class WanArFollowUser extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['follow_user'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // 跳往至详情页的来源，init: 初始化用户列表, market: 市场用户列表
    const PAGE_TYPE_BASIC_FROM_INIT = 'init';
    const PAGE_TYPE_BASIC_FROM_MARKET = 'market';

    // 状态，wait: 待分配, checking: 审核中, pass: 审核通过
    const DISTRIBUTE_STATUS_WAIT = 'wait';
    const DISTRIBUTE_STATUS_CHECKING = 'checking';
    const DISTRIBUTE_STATUS_PASS = 'pass';

    /**
     * 根据用户获取一条记录
     * 注意这里没有去管分配状态
     * @param $userId
     * @param string $select
     * @return mixed
     */
    public function getByUserId($userId, $select="*")
    {
        return self::model()->find([
            'select' => $select,
            'condition' => 'user_id = :userId',
            'params' => [
                ':userId' => $userId
            ]
        ]);
    }

    /**
     * 获取基本信息页面的 URL
     * @param $userId
     * @param string $pageFrom - 页面跳转来源，
     *      init - 从初始化用户列表跳转而来，market - 从市场用户列表跳转而来
     * @return string
     */
    public function getUserBasicInfoUrl($userId, $pageFrom=self::PAGE_TYPE_BASIC_FROM_MARKET)
    {
        return \Yii::app()->createUrl('/user/followed/basic').'?userId='.$userId.'&pageFrom='.$pageFrom;
    }
}