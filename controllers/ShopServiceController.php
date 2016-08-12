<?php
namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use frontend\components\Controller2016;
use common\models\user\ShopServiceCity;
use common\models\other\AdInfo;

class ShopServiceController extends Controller2016
{
	public function actionIndex(){
		$shopService = [];
		$keys = ShopServiceCity::find()->select('ser_id')->where(['city'=>$this->user_info['city']])->asArray()->all();
		foreach ($keys as $val){
			$shopService[] = ShopServiceCity::getShopService($val['ser_id']);
		}
		$ad = [];
        $ad['banner'] = AdInfo::getAd(75,$this->user_info['city']);

		return $this->renderPartial('index',[
			'shopService'=>$shopService,
			'ad'=>$ad,
			]);
	}
	
	
}