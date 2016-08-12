<?php
namespace frontend\controllers;

use common\models\other\OtherActivityModuleBase;
use common\models\other\OtherActivityModuleItem;
use wsc\components\Controller2016;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\HttpException;


class ActivityModuleController extends Controller2016{
    public function beforeAction($action){
        return true;
    }

    /**
     * @description:活动模块查看页面
     * @return:
     * @author: wangmingcha
     * @date: 2015年11月4日下午15:06:12
     */

    public function actionFrontView(){
        $id = Yii::$app->request->get('id');
        $model = [];
        $model['baseData'] = OtherActivityModuleBase::find()->select('*')->where('id=:id',[':id'=>$id])->one();
        if($model['baseData']){
            $model['floorList'] = OtherActivityModuleItem::getItemData($id);
            return $this->render('front-view', $model);
        }else{
            throw new HttpException('404');
        }
    }
}
