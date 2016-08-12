<?php
namespace frontend\controllers;

use common\models\OnlinePayment;
use common\models\other\OtherHelpCenterArticle;
use common\models\other\OtherHelpCenterClassify;
use frontend\components\Controller2016;
use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\web\Controller;
use yii\web\HttpException;


class HelpCenterController extends Controller2016{

    /**
     * @description:帮助中心页面
     * @return:
     * @author: wangmingcha
     * @date: 2015年11月6日下午15:06:12
     */

    public function actionIndex(){
        $region_id = $this->user_info['city'];
        $renderData = OtherHelpCenterClassify::getFrontIndexRenderData($region_id);
        return $this->renderPartial('index', ['renderData'=>$renderData]);
    }


    /**
     * @description:客户评价
     * @return:
     * @author: wangmingcha
     * @date: 2015年11月6日下午18:06:12
     */
    public function actionCustomerEvl(){
        if(Yii::$app->request->getIsAjax()){
            $ajaxRet = [];
            $art_id = Yii::$app->request->post('art_id');
            $model = OtherHelpCenterArticle::findOne($art_id);
            if($model){
                if(Yii::$app->request->post('flag')==1){
                    $model->good_num++;
                }else if(Yii::$app->request->post('flag')==2){
                    $model->bad_num++;
                }else{
                    throw new Exception(500);
                }
                if($model->save(false)){
                    $ajaxRet['msg'] = 'success';
                }
            }
            echo Json::encode($ajaxRet);
            Yii::$app->end();
        }
    }

}
