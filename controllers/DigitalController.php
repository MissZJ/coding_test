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
use common\models\other\OtherHelpCenterArticle;
use common\models\other\HouseFloor;
use common\models\goods\SupplierGoods;
use common\models\goods\Photo;
use common\models\goods\Brand;
use common\models\goods\Type;
use common\models\goods\TypeBrand;
use common\models\user\UserMember;
use common\models\other\MallRnav;
use common\models\other\OtherMainMenu;
use common\models\user\StoreMall;
use common\models\other\MallBase;
use yii\helpers\ArrayHelper;
use common\models\user\SupplierCityScreen;
use common\models\other\OtherZhRnavInfo;
use common\models\user\Supplier;
use yii\data\Pagination;
use common\models\other\OtherExam;
use common\models\other\OtherExamOption;
use common\models\other\OtherExamUser;
use common\models\other\OtherExamRecord;
use common\models\markting\CouponUser;
use common\models\markting\CouponInfo;
/**
 * 
 * Site controller
 */
class DigitalController extends Controller2016
{
    /**
     * @inheritdoc
     */
    public function getNeedStatAction(){
        $this->mall_id = 2;
        return ['actionIndex'];
    }

    public function actionIndex(){
        //IT商城不开通，将其链接转向用户的标准商城
        $user_id = \Yii::$app->user->id;
        $mall_id = StoreMall::getMainMall($user_id);
        $url = MallBase::getMallUrl($mall_id);
        return $this->redirect($url);


        $this->layout='_blank';
        $user_id = \Yii::$app->user->id;
        $userInfo = f_c('frontend-'.$user_id.'-new_51dh3.0');
        if(!$userInfo) {
            $userInfo = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        }
        $city_id = $userInfo['city'];
        //用户状态校验
        if($userInfo['user_status'] != 1){
        	$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
        			'type'=>'warn',
        			'message'=>'帐号状态异常,即将退出登录',
        			'is_next'=>'yes',
        			'next_url'=>'/site/logout',
        	]);
        	return $view;exit;
        }
        //f_d($userInfo);
        $master_data = MasterNav::find()->where(['status'=>0,'level'=>1])->orderBy('sort')->asArray()->all();
        foreach ($master_data as $key=>$val){
            $data = MasterNav::find()->where(['parent_id'=>$val['id'],'status'=>0,])->asArray()->all();
            $master_data[$key]['child']=$data;
        }

        //$rnav = MallRnav::getRightNavData(2,$city_id,0);
        //左侧导航数据
        $rnav = f_c('house_menu_'.$city_id);
        if($rnav === false) {
            $rnav = [];
            $rnav = MallRnav::getRightNavData(2,$city_id,0);
            f_c('house_menu_'.$city_id,$rnav,3600);
        }
        
        //f_d($rnav);

        $ad = f_c('house_ad_'.$city_id);
        if(!$ad) {
            $ad = [];
            $ad['banner'] = AdInfo::getAd(32,$city_id,8);        //大banner图
            $ad['banner_xia'] = AdInfo::getAd(33,$city_id,3);   //大banner下面图
            $ad['banner_right'] = AdInfo::getAd(34,$city_id,1);   //大banner右边图
            $ad['banner_xia_right'] = AdInfo::getAd(43,$city_id,8);  //大banner下右
            //1F 一周精选
            $ad['new'] = AdInfo::getAd(35,$city_id,6);        //一周精选
            
            $ad['zhineng_left'] = AdInfo::getAd(42,$city_id,1); //智能左边图
            $ad['ad_top'] = AdInfo::getAd(96,$city_id,1);			//顶部广告位
            $ad['ad_middle'] = AdInfo::getAd(109,$city_id,1);		//顶部广告位
            $ad['special_column'] = AdInfo::getAd(114,$city_id,3);	//专栏3个
            f_c('house_ad_'.$city_id,$ad,3600*12);
        }
        // 首先获取楼层的数量
        //获取所有楼层信息
        $floor = f_c('house_other_floor_'.$city_id);
        if(!$floor) {
            $floor_info = HouseFloor::getCityFloor($city_id);
            $floor = HouseFloor::getAllFloorData($floor_info);
            f_c('house_other_floor_'.$city_id,$floor,600);
        }
        //f_d($floor_info);

        $computerTypeList = Type::getTypeListByModule(Yii::$app->params['quotation.computer'],false);
        $partTypeList = Type::getTypeListByModule( Yii::$app->params['quotation.part']);

        //用户信息
        $memder_data = f_c('home_user_ext_'.$user_id);
        if(!$memder_data) {
            $memder_data = UserMember::getHomeInfo($user_id);
            f_c('home_user_ext_'.$user_id,$memder_data,3600);
        }

        $type = 2;
        
        //横向导航栏
        $menu = f_c('house_menuheng_'.$city_id);
        if($menu === false) {
            $menu = OtherMainMenu::getMainMenu($type,$city_id);
            f_c('house_menuheng_'.$city_id,$menu,3600);
        }
        
        //促销公告
        $notice = f_c('shuma_notice'.$city_id);
        if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
            $notice['digital'] = OtherHelpCenterArticle::getArticleApiList(3,4,$city_id,2)['list'];//促销公告
            f_c('shuma_notice'.$city_id,$notice,1800);
        }

        $malls = StoreMall::getMalls($user_id);
        $yes_malls = ArrayHelper::getColumn($malls['yes_malls'], 'id');
        if(!in_array(2, $yes_malls)) {
            f_msg('您无法查看此商城', '/home/index');
        }
        //收益
        $profitInfo = UserMember::getProfitByApi();
        //f_d($floor);
        
        return $this->render('index',[
            'master_data'=>$master_data,
            'ad'=>$ad,
            'floor'=>$floor,
            'computerTypeList'=>$computerTypeList,
            'partTypeList'=>$partTypeList,
            'memder_data'=>$memder_data,
            'rnav'=>$rnav,
            'menu'=>$menu,
            'user_id'=>$user_id,
            'user_info'=>$userInfo,
            'notice'=>$notice,
            'malls'=>$malls,
        	'profitInfo'=>$profitInfo,
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
    
    /*
     * @description:开票专区
     * @author:lxzmy
     * @date:2016-3-8 09:53
     */
    public function actionInvoice(){
        $this->layout = '_blank';
        $sort = f_get('sort',0);
        $intacts = f_get('intacts',0);
        $page_num=  f_get('page_num',0); //当前页码
        $brands = f_get('brand',0);
        $types = f_get('type',0);
        $mall_id = f_get('mall_id',2);
        if(!$mall_id){
            $mall_id = 2;
        }
        $user_id = \Yii::$app->user->id;
        //排序
        $orderBy='';
        if($sort ==1){
            $orderBy ='t1.price asc';
        }else if($sort ==0){
            $orderBy ='t1.price desc';
        }else{
            $orderBy ='t1.weight desc';
        }
        //不刷机
//        $shuaji=[];
//        if($intacts){
//          $shuaji =['t1.is_brush '=>0];
//        }
        $de =1;
        if(isset($brands)&&$brands>0){
            $brands ='t1.brand_id ='.$brands;
        }else{
            $brands ='1='.$de;
        }
        if(isset($types)&&$types>0){
            $types ='t1.type_id ='.$types;
        }else{
            $types ='1 ='.$de;
        }
        $city = $this->user_info['city'];
        $ad = [];     
        $ad['banner'] = AdInfo::getAd(113,$city,8);   //大banner图
        //$rnav = MallRnav::getRightNavData(2,$city,0);  //左侧导航 
        $rnav = OtherZhRnavInfo::getRightNavData(3,$city, 0);  //左侧导航 
        $menu_heng = OtherMainMenu::getMainMenu(2, $city);   //主导航
        
        //查询数据
        $query = (new \yii\db\Query())->select('t1.id,t1.base_id,t1.cover_id,t1.goods_name,t1.price')
                ->from('goods_supplier_goods t1')
                ->leftJoin('user_supplier t2','t1.supplier_id=t2.id')
                ->leftjoin('goods_type t3','t1.type_id=t3.id')
                ->where(['t2.if_disable'=>0])
                ->andwhere(['t2.is_shield'=>1])
                ->andWhere(['t1.status'=>1])
                ->andWhere(['t1.enable'=>1])
                ->andWhere(['t1.is_deleted'=>0])
                ->andWhere(['t1.show_id'=>0])
                ->andWhere(['t2.is_invoice'=>2])
                ->andWhere("t3.mall_id = {$mall_id}")
                ->andWhere($brands)
                ->andWhere($types)
                ->orderBy($orderBy);
        $pages = new Pagination(['totalCount' =>$query->count(), 'defaultPageSize'=>15]);   //创建分页对象
        $page_num = $pages->getPage();
        $goodsInfo = $query->offset($pages->offset)->limit($pages->limit)->all();
        //f_d($query->createCommand()->getRawSql());
        //f_d($goodsInfo);
        return $this->render('invoice',[
                'ad'=>$ad,
                'rnav'=>$rnav,
                'menu_heng'=>$menu_heng,
                'userInfo'=>$this->user_info,
                'sort'=>$sort,
                'intacts'=>$intacts,
                'goodsInfo'=>$goodsInfo,
                'page'=>$pages,
                'brand_id'=>$brands,
                'type_id'=>$types,
                'page_num'=>$page_num,
        ]);
        
        
    }

}
