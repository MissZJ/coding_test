<?php
namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use frontend\components\Controller2016;
use common\models\other\MasterNav;
use common\models\other\Nav;
use common\models\other\AdInfo;
use common\models\other\IndexFloor;
use common\models\other\IndexFloorGoods;
use common\models\other\HoneFloor;
use common\models\goods\SupplierGoods;
use common\models\goods\Photo;
use common\models\goods\Brand;
use common\models\goods\Type;
use common\models\goods\TypeBrand;
/**
 * Site controller
 */
class IntelHouseController extends Controller2016
{
    /**
     * @inheritdoc
     */
    public function actionIndex(){
      $user_id = \Yii::$app->user->id;
      $userInfo = f_c('frontend-'.$user_id.'-new_51dh3.0');
        if(!$userInfo) {
          $userInfo = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        }
      $city_id = $userInfo['city'];

      //f_d($userInfo);
      $master_data = MasterNav::find()->where(['status'=>0,'level'=>1])->orderBy('sort')->asArray()->all();
          foreach ($master_data as $key=>$val){
          $data = MasterNav::find()->where(['parent_id'=>$val['id'],'status'=>0,])->asArray()->all();
          $master_data[$key]['child']=$data;
        }
        
      $ad = [];
      $ad['banner'] = AdInfo::getAd(14,$city_id);        //大banner图
      $ad['banner_right'] = AdInfo::getAd(15,$city_id,2);   //大banner右边图
      //1F 一周精选
      $ad['new'] = AdInfo::getAd(16,$city_id,2);        //新品上架
      $ad['hot'] = AdInfo::getAd(17,$city_id,2);        //热卖直销
      $ad['cheep'] = AdInfo::getAd(18,$city_id,2);        //低价促销
      $ad['zhineng_left'] = AdInfo::getAd(22,$city_id,1);     //智能左边图
      //f_d($ad);
      // 首先获取楼层的数量
      $floor_info = HoneFloor::getCityFloor($city_id,0,1);  
      //f_d($floor_info);
      //获取所有楼层信息
      $floor = HoneFloor::getAllFloorData($floor_info); 
      //f_d($floor);

      $computerTypeList = Type::getTypeListByModule(Yii::$app->params['quotation.computer'],false);
      $partTypeList = Type::getTypeListByModule( Yii::$app->params['quotation.part']);

        return $this->render('index',[
        'master_data'=>$master_data,
        'ad'=>$ad,
        'floor'=>$floor,
        'computerTypeList'=>$computerTypeList,
        'partTypeList'=>$partTypeList,
        ]);
    }

    public function actionGetBrand(){
        $type = $_GET['type'];
        $sql = "SELECT b.id,b.name FROM goods_type t LEFT JOIN goods_type_brand tb ON t.id=tb.type_id LEFT JOIN goods_brand b ON tb.brand_id=b.id WHERE t.id=".$type;
        $brand = Brand::findBySql($sql)->asArray()->all();
        //f_d($brand);
        if($brand){
          return json_encode($brand);
        }
    }
   
}
