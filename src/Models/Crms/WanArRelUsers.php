<?php

namespace Wan\Models\Crms;

class WanArRelUsers extends \CActiveRecord
{
    public function tableName()
    {
        return \Yii::app()->params['table_alias']['crms']['rel_users'];
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
        $limit = $search['limit'] ?? 15;
        $offset = ($page - 1) * $limit;

        // 一系列的 Model 初始化
        $ar = new self();
        $table = $ar->tableName();

        $sqlSelect = "SELECT *";
        if ($isCount) {
            $sqlSelect = "SELECT COUNT(*) AS theCount";
        }
        $sqlFrom = " FROM $table";
        // 有些搜索会涉及到关联查询，在这里就要关联好
        $sqlJoin = "";
        $sqlWhere = $this->getWhere($search);
        // 排序规则写到 addition 里面
        $sqlAddition = "";
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
        $userId = $search['userId'] ?? '';                              # 用户 id
        $where = " WHERE 1=1";
        if(!empty($userId)) {
            // 填写了 用户 id
            $where .= " AND (one_user_id = $userId OR another_user_id = $userId)";
        }
        return $where;
    }

    /**
     * 获取属于一个用户的记录
     * @param $userId
     * @return mixed - 最多返回一条记录，或者没有
     */
    public function getByUserId($userId)
    {
        return self::model()->find(
            [
                'condition' => 'one_user_id = :userId OR another_user_id = :userId',
                'params' => [
                    ':userId' => $userId
                ]
            ]
        );
    }

    /**
     * 获取两个用户之间关联记录
     * @param $oneUserId
     * @param $anotherUserId
     * @return mixed
     */
    public function getRelationRecord($oneUserId, $anotherUserId)
    {
        return self::model()->find(
            [
                'condition' => '(one_user_id = :oneUserId AND another_user_id = :anotherUserId)'
                    .' OR (one_user_id = :anotherUserId AND another_user_id = :oneUserId)',
                'params' => [
                    ':oneUserId' => $oneUserId,
                    ':anotherUserId' => $anotherUserId
                ]
            ]
        );
    }

    /**
     * 添加一个关联关系
     * @param $oneUserId
     * @param $anotherUserId
     * @return mixed
     */
    public function addRelation($oneUserId, $anotherUserId)
    {
        $newRecord = new self();
        $timeNow = date('Y-m-d H:i:s');
        $newRecord->one_user_id = $oneUserId;
        $newRecord->another_user_id = $anotherUserId;
        $newRecord->create_time = $timeNow;
        $newRecord->update_time = $timeNow;
        return $newRecord->save();
    }

    /**
     * 删除一条记录
     * @param $primaryId - 记录的主键
     */
    public function deleteOne($primaryId)
    {
        return self::model()->deleteAll(
            "id = :id",
            [
                ':id' => $primaryId
            ]
        );
    }
}