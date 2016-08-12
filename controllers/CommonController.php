<?php
namespace frontend\controllers;
header("Content-type: text/html; charset=utf-8");
use yii;
use ms\components\Controller2016;
use common\models\other\OtherRegion;
use yii\helpers\Json;
use yii\web\NotFoundHttpException;
/**
 * 
 * @Description: 公共控制器 
 * @author: kai.gao
 * @date: 2014-11-20 下午8:51:51
 */
class CommonController extends Controller2016 {
    
    
    /**
     * 
     * @Title: actionRegionById
     * @Description:  通过区域id获取下属区域名称
     * @return: string ['region_id'=>'region_name']
     * @author: kai.gao
     * @date: 2014-11-21 下午12:37:36
     */
    public function actionRegionById($pid){
        $region = OtherRegion::getRegion($pid);
        echo Json::encode($region);
    }
        
}
?>