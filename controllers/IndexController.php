<?php
namespace frontend\controllers;

use Yii;
use frontend\components\Controller2016;
use common\models\user\Pusher;
use common\models\user\ServiceFaq;

class IndexController extends Controller2016{
    
    
    public function actionIndex(){

        header("Location: http://www.51dh.com.cn");exit;
    }


    /**
     *
     * @Title: actionGetCustomerServiceMessage
     * @Description:  在线客服获取提示语和推广经理信息
     * @return: string
     * @author: yulong.wang
     * @date: 2015-11-16下午6:21:25
     */
    public function actionGetCustomerServiceMessage() {
        $userId = $_POST['user_id'];
        $key = 'frontend-'.$userId.'-new_51dh3.0';
        $userInfo = Yii::$app->cache->get($key);
         
        $zeroTime = strtotime(date('Y-m-d 00:00:00',time()));
        $sixTime = strtotime(date('Y-m-d 06:00:00',time()));
        $elevenTime = strtotime(date('Y-m-d 11:00:00',time()));
        $thirteenTime = strtotime(date('Y-m-d 13:00:00',time()));
        $nineteenTime = strtotime(date('Y-m-d 19:00:00',time()));
        $twentyFourTime = strtotime(date('Y-m-d 23:59:59',time()));
        
        $remark = '老板，您今天在我要订货网订货了吗?';
        if (time() >= $zeroTime && time() < $sixTime) {
            $remark = '老板，夜间早点休息!';
        } elseif (time() >= $sixTime && time() < $elevenTime) {
            $remark = '老板，上午好,新的一天开始了!';
        } elseif (time() >= $elevenTime && time() < $thirteenTime) {
            $remark = '老板，中午好,别忘记吃午饭(⊙o⊙)哦!';
        } elseif (time() >= $thirteenTime && time() < $nineteenTime) {
            $remark = '老板，下午好,别忘记18点订货(⊙o⊙)哦!';
        }
        
        $faqList = ServiceFaq::getFaqList($userInfo['city']);
        $pushMessage = Pusher::getExclusiveServicePusher($userInfo['city'],$userInfo['district']);
        if (!empty($pushMessage)) {
            $pusher = '您的业务经理是 : '.$pushMessage['pusher'].' '.$pushMessage['phone'];
            $returnVal = ['status'=>1,'pusher'=>$pusher,'remark'=>$remark,''];
        } else {
            $returnVal = ['status'=>0,'pusher'=>'','remark'=>$remark];
        }
        return json_encode($returnVal);
    }

}
