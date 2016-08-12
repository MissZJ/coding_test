<?php

namespace frontend\controllers;

use Yii;
use frontend\components\Controller2016;
use common\models\user\UserMember;
use common\models\user\MemberCxd;
use yii\db\Query;
use yii\data\Pagination;

/**
 * 前台-个人信息
 * Class UcenterController
 * @package frontend\controllers
 */
class SelfController extends Controller2016
{

    public function init(){
        $this->layout = 'ucenter';
        parent::init();
    }
    /**
     * @Title: actionIndex
     * @Description: 用户诚信点
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月02日上午9:55:12
     */
    public function actionReputationRecord() {
       	//获取用户信息
       	$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
		
    	//获取 扣除记录//分页信息
    	$page = f_get('page',1);
    	
    	$query = (new Query())->select('*')
    		->from('{{user_member_cxd}}')
    		->where(['user_id'=>$user_info['id']]);
    	$countQuery = clone $query;
      	$pages = new Pagination(['totalCount' => $countQuery->count()]);
      	$totalCount = $pages->totalCount;
      	$records = $query->offset(($page-1)*5)
      	    ->orderBy('id desc')
          	->limit(5)
          	->all();
    	
    	$pages = ceil($pages->totalCount/5);
    	
//     	f_d($pages);
		return $this->render('reputation_record',[
				'user_info'=>$user_info,
				'records'=>$records,
				'page'=>$page,
				'pages'=>$pages,
				'totalCount'=>$totalCount,
    	]);
    }
    /**
     * @Title: actionIndex
     * @Description: 个人中心首页
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月02日上午9:55:12
     */
    public function actionGetRecords() {
    	//获取用户信息
    	$user_id = \Yii::$app->user->id;
    	
    	//获取 扣除记录//分页信息
    	$page = f_post('page',1);
    	 
    	$query = (new Query())->select('*')
    	->from('{{user_member_cxd}}')
    	->where(['user_id'=>$user_id]);
    	$countQuery = clone $query;
    	$pages = new Pagination(['totalCount' => $countQuery->count()]);
    	$totalCount = $pages->totalCount;
    	$records = $query->offset(($page-1)*5)
    	->limit(5)
    	->all();
    	 
    	$pages = ceil($pages->totalCount/5);
    	$this->layout = 'main';
    	//     	f_d($pages);
    	return $this->renderAjax('ajax_reputation_record',[
    			'records'=>$records,
    			'page'=>$page,
    			'pages'=>$pages,
    			'totalCount'=>$totalCount,
    	]);
    }
    
}
