<?php
namespace frontend\controllers;
use Yii;
use frontend\components\Controller2016;
use yii\db\Query;
use common\models\markting\XingseInfo;
use common\models\markting\XingseContent;
use common\models\markting\XingseLog;

class PromotionController extends Controller2016
{
   /**
    * @Title: actionsSe
    * @Description: 日常活动控制器
    * @return: return_type
    * @author: KOUPING.SUN
    * @date: 2015-3-26 下午2:22:41
    */
   public function actionIndex($id){
   		header("Content-Type: text/html; charset=UTF-8");
	    if(f_isMobile()) { #手机访问跳转到手机活动页
	        //return $this->redirect('/mobileactive/xing?id='.$id);
	    }
	    $this->layout = '_blank';
	    //用户信息
	    $user_id = \yii::$app->user->id;
	    $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
   		//是否是活动时间
   		$active_time = XingseInfo::find()->where(['<=','begin_time',f_date(time())])
   			->andWhere(['>=','end_time',f_date(time())])
   			->asArray()->one();
	    //获取代码
	    $code = XingseContent::find()->where(['info_id'=>$id])->orderBy('id asc')->asArray()->all();
	    //访问次数记录,不取结果
	    XingseLog::addData($id,$user_info['city']);

   		return $this->render('index',[
   			'active_time'=>$active_time,
   			'code'=>$code,
   		]);		
   }
  	
}
