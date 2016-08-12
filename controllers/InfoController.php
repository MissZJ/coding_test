<?php
namespace frontend\controllers;
use Yii;
use frontend\components\Controller2016;

class InfoController extends Controller2016
{

    public function beforeAction($action){
        return true;
    }
    
	/**
	  * @Title:actionClause
	  * @Description:服务协议条款
	  * @return:从2.0挪用过来
	  * @author:huaiyu.cui
	  * @date:2015-12-26 下午1:55:03
	 */
	public function actionClause(){
	    return $this->renderPartial('clause');
	}
}