<?php

namespace Wan\Models\Crms;

class WanArCrmUser extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['crm_user'];
    }

    public static function model()
    {
        return parent::model(__CLASS__);
    }

    public function getDbConnection()
    {
        return \Yii::app()->db;
    }

    // 状态：1-正常，2-被删除
    const IS_DEL_NO = 1;
    const IS_DEL_YES = 2;

    /**
     * 获取所有用户
     * @param string $select
     * @param bool $includeOff - 是否包含被失效状态的用户
     * @param bool $includeTest - 是否包含测试职位的用户，即 user_position_id 为 58
     * @return mixed
     */
    public function getAll($select="*", $includeOff=false, $includeTest=true)
    {
        $params = [
            "select" => $select,
        ];
        $params["condition"] = "";
        $params["params"] = [];
        if (!$includeOff) {
            $params["condition"] = 'is_del = :statusOn';
            $params["params"][":statusOn"] = self::IS_DEL_NO;
        }
        if (!$includeTest) {
            if (!empty($params["condition"])) {
                $params["condition"] .= " AND";
            }
            $params["condition"] .= " user_position_id != :positionId";
            $params["params"][":positionId"] = WanArCrmConfig::POSITION_GROUP_TEST_ID;
        }
        return self::model()->findAll($params);
    }

    /**
     * 获取当前登录用户的ID
     * @return mixed|string
     */
    public function getUserId(){
        $token = \Token::isToken($_COOKIE['CrmsToken'], \Yii::app()->params['extra']['key']);
        $token = explode('@', $token[0]);
        return $token[0];
    }

    /**
     * 通过用户ID（$usreId）获取这个用户的信息
     * @param $userId
     * @param string $select
     * @return mixed
     */
    public function getByUserId($userId, $select="*")
    {
        return self::model()->find([
            'select' => $select,
            'condition' => 'id = :userId',
            'params' => [
                ':userId' => $userId
            ]
        ]);
    }

    /**
     * 模糊搜索用户名
     * @param $str
     * @param string $select
     * @return mixed
     */
    public function getLike($str, $select="*")
    {
        $str = '%'.$str.'%';
        return self::model()->findAll(
            [
                'select' => $select,
                'condition' => 'uname LIKE :likeUname',
                'params' => [
                    ':likeUname' => $str
                ]
            ]
        );
    }

    /**
     * 获取 id 在 userIds 中的用户
     * @param $userIds
     * @param string $select
     * @return array
     */
    public function getByIds(array $userIds, $select="*")
    {
        if (count($userIds) == 0) {
            return [];
        }
        return self::model()->findAll([
            'select' => $select,
            'condition' => 'id IN '."(".implode(",", $userIds).")"
        ]);
    }

    /*
     * 获取一个用户的额外信息
     * @return array
     */
    public function getExtraInfo($userId=null, $userRcd=null)
    {
        if (empty($userId)) {
            $userId = self::getUserId();
        }
        $result = [
            'user' => [],                                   # 用户原始记录
            'admin' => 0,                                   # 是否是超管
            'isDepartmentLeader' => 0,                      # 是否是部门经理
            'isGroupLeader' => 0,                           # 是否是事业部经理
            'groupIds' => [],                               # 所在事业部 ID，只能有一个的
        ];
        if (empty($userRcd)) {
            $userRcd = self::getByUserId($userId);
        }
        $result["user"] = $userRcd;
        if(in_array('99999', explode(',', $userRcd['ugroup']))){
            $result["admin"] = 1;
        }
        if (self::isDepartmentLeader($userRcd)) {
            $result['isDepartmentLeader'] = 1;
        }
        if (self::isGroupLeader($userRcd)) {
            $result['isGroupLeader'] = 1;
        }
        array_push($result['groupIds'], $userRcd['user_cause_id']);
        return $result;
    }

    /**
     * 一个用户是否是部门经理
     * @param $userRcd
     * @return bool
     */
    public function isDepartmentLeader($userRcd)
    {
        $result = false;
        if ($userRcd['user_position_id'] == WanArCrmConfig::POSITION_DEPARTMENT_LEADER_ID) {
            $result = true;
        }
        return $result;
    }

    /**
     * 一个用户是否是事业部经理
     * @param $userRcd
     * @return bool
     */
    public function isGroupLeader($userRcd)
    {
        $result = false;
        if ($userRcd['user_position_id'] == WanArCrmConfig::POSITION_GROUP_LEADER_ID) {
            $result = true;
        }
        return $result;
    }

    /**
     * 获取一个用户可以管辖到的后台用户（按部门来算）
     * @param null $userId
     * @return array|mixed
     */
    public function getUserHeeler($userId=null)
    {
        if (empty($userId)) {
            $userId = self::getUserId();
        }
        $userRcd = self::getByUserId($userId);
        $userInfo = self::getExtraInfo($userId, $userRcd);
        if ($userInfo["admin"] || $userInfo["isDepartmentLeader"]) {
            $customers = self::getAll("*", false, false);
        }
        else if ($userInfo["isGroupLeader"]) {
            $customers = self::getByDepartId($userInfo["groupIds"][0] ?? 0);
        }
        else {
            $customers = [$userRcd];
        }
        return $customers;
    }

    /**
     * 获取某个部门下面的用户
     * @param $departId
     * @param bool $includeTest - 是否包含测试职位的用户，即 user_position_id 为 58
     * @return mixed
     */
    public function getByDepartId($departId, $includeTest=false)
    {
        $params = [
            'condition' => 'user_cause_id = :departId AND is_del = :statusOn',
            'params' => [
                ':departId' => $departId,
                ':statusOn' => self::IS_DEL_NO
            ]
        ];
        if (!$includeTest) {
            $params["condition"] .= " AND user_position_id != :positionId";
            $params["params"][":positionId"] = WanArCrmConfig::POSITION_GROUP_TEST_ID;
        }
        return self::model()->findAll($params);
    }
}