<?php
namespace frontend\controllers;

use common\models\api\LogisticsInterfaceLog;
use common\models\goods\BaseGoods;
use common\models\goods\Color;
use Yii;
use frontend\components\Controller2016;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\ForbiddenHttpException;
use common\models\goods\Module;
use common\models\goods\Type;
use common\models\goods\TypeBrand;
use common\models\goods\SupplierGoods;
use common\models\user\ShoppingCart;
use yii\helpers\Json;
use yii\helpers\BaseArrayHelper;
use common\models\goods\RelateAccessory;
use common\models\goods\RelateAccessoryNameBase;
use common\models\user\form\FrontendLoginForm;
use common\models\lowest\BaseLowestQuotation;
use common\models\lowest\BaseLowestQuotationModule;
use yii\db\Query;
/**
 * 51报价单
 * Class QuotationController
 * @package frontend\controllers
 */
class QuotationController extends Controller2016{

    private $cart = null;



    public function init() {

        parent::init();
        //购物车信息
        $D2_goods = ShoppingCart::getAllGoods($this->user_info['id'],1);
        $D3_goods = ShoppingCart::getAllGoods($this->user_info['id'],2);
        //再按有效性分组
        $D2_goods = ShoppingCart::group($D2_goods);
        $D3_goods = ShoppingCart::group($D3_goods);

        $this->cart =array_merge($D2_goods['effective'],$D3_goods['effective']);

    }
    /**
     * @description: 找手机
     * @return: string
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015年11月25日下午13:47:00
     */
    public function actionIndex() {

        $moduleId = Yii::$app->params['quotation.phone'];

        if(Yii::$app->request->isAjax){
            return $this->actionList($moduleId);
        }

        $brandId = intval(f_get('brandId',0));//品牌
        $baseId = intval(f_get('baseId',0));//基础商品
        $isBrush = intval(f_get('isBrush',0));//不刷机
        $colorId = intval(f_get('colorId',0));//颜色
        $lowPrice = intval(f_get('lowPrice',0));//价格区间
        $highPrice = intval(f_get('highPrice',0));
        $order = intval(f_get('order',0));//排序类型

        $condition = ['city'=>$this->user_info['city'],'colorId'=>'','isBrush'=>'','baseId'=>'','brandId'=>'', 'moduleId'=>$moduleId, 'lowPrice'=>'','highPrice'=>'', 'order'=>$order];

        $highPrice && $condition['highPrice'] = $highPrice;

        if($lowPrice) {
            $lowPrice && $condition['lowPrice'] = $lowPrice;
            if($highPrice && $lowPrice < $highPrice ){
                $condition['highPrice'] = $highPrice;
            }
        }

        //获取指定模型的品类列表
        $typeInfo = Type::getTypeListByModule($moduleId,true);
        //指定模型的所有关联品类
        $typeIds =[];
        array_map(function($val)use(&$typeIds) {
            $typeIds = array_merge($typeIds,array_keys($val));
        },$typeInfo);
        $condition['typeId'] = $typeIds;

        //获取指定模型品类的品牌列表、校验品牌参数
        $brandInfo = TypeBrand::getBrandListByType($typeIds,true);
        //校验品牌参数
        if ($brandId) {
            $condition['brandId'] = $brandId;
        }


        $baseId && $condition['baseId'] = $baseId;
        $colorId && $condition['colorId'] = $colorId;
        $isBrush && $condition['isBrush'] = 0;

        $dataProvider = SupplierGoods::searchPhoneQuotation($condition);

        $list = $dataProvider->getModels();

        $brandInfo = f_multiArryCategoryByChar($brandInfo);

        return  $this->render('index',[
            'brandInfo' => $brandInfo,
            'condition' => $condition,
            'cart'=>$this->cart,
            'list' => $list,
            'pager' =>  $dataProvider->getPagination()
        ]);

    }

    /**
     * @description: 配件报价
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015年11月25日下午13:47:00
     */
    public function actionPart() {

        $moduleId = Yii::$app->params['quotation.part'];

        if(Yii::$app->request->isAjax){
            return $this->actionList($moduleId);
        }
        $typeId = intval(f_get('typeId',0));//品类
        $brandId = intval(f_get('brandId',0));//品牌
        $accId = intval(f_get('accId',0));//俗称
        $baseId = intval(f_get('modelId',0));//型号
        $order = intval(f_get('order',0));//排序类型
        $lowPrice = intval(f_get('lowPrice',0));//价格区间
        $highPrice = intval(f_get('highPrice',0));
        try {

            $condition = ['city'=>$this->user_info['city'], 'moduleId'=>$moduleId, 'typeId'=>'','brandId'=>'','accId'=>'','baseId'=>'', 'order'=>$order,'low'=>'','high'=>''];
            if($lowPrice) {
                $lowPrice && $condition['low'] = $lowPrice;
                if($highPrice && $lowPrice < $highPrice ){
                    $condition['high'] = $highPrice;
                }
            }

            //获取指定模型的品类列表
            $typeInfo = Type::getTypeListByModule($moduleId,true);
            //指定模型的所有关联品类
            $typeIds =[];
            array_map(function($val)use(&$typeIds){
                $typeIds = array_merge($typeIds,array_keys($val));
            },$typeInfo);

            //校验品类参数
            if ($typeId && in_array($typeId,$typeIds,true)) {
                $condition['typeId'] = $typeId;
            }

            //获取指定模型品类的品牌列表、校验品牌参数
            $brandInfo = TypeBrand::getBrandListByType($typeIds,true);
            $brandId && $condition['brandId'] = $brandId;


            if($accId) {
                $condition['accId'] = $accId;
                $condition['baseId'] = RelateAccessory::getRelBaseId($accId,$typeId,$brandId);
            }
//            if($baseId){
//                $condition['baseId'] = $baseId;
//            }
            //f_d($condition);
            $dataProvider = SupplierGoods::searchPartOrComputerQuotation($condition);
            $typeList = f_multiArryCategoryByChar($typeInfo);
            $brandList = f_multiArryCategoryByChar($brandInfo);

            return  $this->render('part',[
                    'brandInfo' => $brandList,
                    'typeInfo' => $typeList,
                    'condition' => $condition,
                    'cart'=>$this->cart,
                    'dataProvider' => $dataProvider,
                    'pager' =>  $dataProvider->getPagination()
            ]);
        } catch (Exception $ex) {
            f_d($ex);
        }

    }

    /**
     * @description: 电脑报价
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015年11月25日下午13:47:00
     */
    public function actionComputer() {

        $moduleId = Yii::$app->params['quotation.computer'];

        if(Yii::$app->request->isAjax){
            return $this->actionList($moduleId);
        }
        $typeId = intval(f_get('typeId',0));//品类
        $brandId = intval(f_get('brandId',0));//品牌
        $order = intval(f_get('order',0));//排序类型
        $lowPrice = intval(f_get('lowPrice',0));
        $highPrice = intval(f_get('highPrice',0));
        $priceRange = trim(f_get('price',''));//价格区间
        try {

            $condition = ['city'=>$this->user_info['city'], 'moduleId'=>$moduleId, 'typeId'=>'','brandId'=>'', 'order'=>$order,'low'=>'','high'=>''];
            if($lowPrice) {
                $lowPrice && $condition['low'] = $lowPrice;
                if($highPrice && $lowPrice < $highPrice ){
                    $condition['high'] = $highPrice;
                }
            } else if ($priceRange) {
                $priceRangeArray = [];
                if (preg_match("/^(\d+)*\-(\d+)*$/", $priceRange, $priceRangeArray)) {
                    !empty($priceRangeArray[1]) && $condition['low'] = $priceRangeArray[1];
                    !empty($priceRangeArray[2]) && $condition['high'] = $priceRangeArray[2];
                    if ($condition['low'] && $condition['high'] && $condition['low'] > $condition['high']) {
                        $condition['high']='';
                    }
                }
            }
            //获取指定模型的品类列表
            $typeInfo = Type::getTypeListByModule($moduleId,false);
            //指定模型的所有关联品类
//            $typeIds =[];
//            array_map(function($val)use(&$typeIds){
//                $typeIds = array_merge($typeIds,array_keys($val));
//            },$typeInfo);

            //校验品类参数
            if ($typeId) {
                $condition['typeId'] = $typeId;
            }
            //获取指定模型品类的品牌列表、校验品牌参数
            $brandInfo = TypeBrand::getBrandListByType(null,true);
            $brandId && $condition['brandId'] = $brandId;

            $dataProvider = SupplierGoods::searchPartOrComputerQuotation($condition);
//            $typeList = f_multiArryCategoryByChar($typeInfo);
            $brandList = f_multiArryCategoryByChar($brandInfo);
            return  $this->render('computer',[
                'brandInfo' => $brandList,
                'typeInfo' => $typeInfo,
                'condition' => $condition,
                'cart'=>$this->cart,
                'dataProvider' => $dataProvider,
                'pager' =>  $dataProvider->getPagination()
            ]);
        } catch (Exception $ex) {
            f_d($ex);
        }
    }
    /**
     * @description: 获取手机供应商商品列表
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015年11月30日上午10:47:00
     */
    public function  actionPhoneSupplierGoodsList() {

        //if(Yii::$app->request->isAjax){
            $baseId =   intval(f_get('id',0));
            $colorId =  intval(f_get('colorId',0));
            if ($baseId) {
                //$lowestPriceGoods = SupplierGoods::getLowestPriceGoods($baseId,$this->user_info['city']);
                $list = SupplierGoods::getListOfSupplierGoodsByBaseIdAndCity($baseId,$this->user_info['city'],$colorId);
                if(count($list) >0 ) {
                    return $this->renderPartial('_phone_supplier_goods',['list'=>$list,'city'=>$this->user_info['city']]);
                }
            }
       // }
    }
    /**
     * @description: 购物车
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015年11月30日上午10:47:00
     */
    public function  actionCart() {
        if(Yii::$app->request->isAjax){
                    return $this->renderPartial('_cart',['cart'=>$this->cart]);
        }
    }

    /**
     * @description: 根据关键字搜索
     * @param $moduleId
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015-12-07
     * @throws \yii\base\ExitException
     */
    protected  function actionList($moduleId) {
            $flag = f_get('flag','type');
            $keywords = f_get('keywords','');
            $typeList = Type::getTypeListByModule($moduleId,false,$keywords);
            $list=[];
            if(strcmp($flag,'brand')==0){
                $typeId = f_get('typeId','');
                $list = TypeBrand::getBrandListByType($typeId,false,$keywords);
            }elseif(strcmp($flag,'type')==0) {
                $list = $typeList;
            }elseif(strcmp($flag,'color')==0) {
                $baseId = f_get('baseId','');
                $list = Color::getListByBaseGoods($baseId,$keywords);
            }
            elseif(strcmp($flag,'model')==0){ //配件 型号
                $accId = f_get('accId','');
                $list = RelateAccessoryNameBase::getACCRelateBase($accId,$keywords);
            }elseif(strcmp($flag,'acc')==0){ //配件俗称
                $typeId = f_get('typeId','');
                $brandId = f_get('brandId','');
                $list = RelateAccessory::getAccNameByTypeBrand($typeId,$brandId,$keywords);
            } elseif(strcmp($flag,'base')==0){ //手机 型号
                $brandId = f_get('brandId','');
                $keywords = f_get('keywords','');
                $list = BaseGoods::getListByBrandAndType($brandId,array_keys($typeList),$keywords);
            }
            $data=['status'=>0,'data'=>[]];
            if($list && count($list)>0){
                $data=['status'=>1,'data'=>$list];
            }
            echo Json::encode($data);
            Yii::$app->end();
    }

    /**
     * @description: 品牌列表
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015-12-07
     * @throws \yii\base\ExitException
     */
    public  function actionBrandList() {
        if(Yii::$app->request->isAjax){
            $typeId = f_get('typeId','');
            $keywords = f_get('keywords','');
            $list = TypeBrand::getBrandListByType($typeId,false,$keywords);
            $html = '';
            if(count($list) >0) {
                foreach($list as $key=>$val)
                    $html .= "<a data='{$key}'>{$val}</a>";
            }
            echo $html;
            Yii::$app->end();
        }
    }

    /**
     * @description: 基础商品列表
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015-12-07
     * @throws \yii\base\ExitException
     */
    public  function actionBaseGoodsList() {
        if(Yii::$app->request->isAjax){
            $brandId = f_get('brandId','');
            $keywords = f_get('keywords','');
            $typeList = Type::getTypeListByModule(Yii::$app->params['quotation.phone']);
            $list = BaseGoods::getListByBrandAndType($brandId,array_keys($typeList),$keywords);
            $html = '';
            if(count($list) >0) {
                foreach($list as $key=>$val)
                $html .= "<a data='{$key}'>{$val}</a>";
            }
            echo $html;
            Yii::$app->end();
        }
    }

    /**
     * @description: 基础商品颜色
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015-12-07
     * @throws \yii\base\ExitException
     */
    public  function actionBaseGoodsColor() {
        if(Yii::$app->request->isAjax){
            $baseId = intval(f_get('baseId',0));
            $keywords = f_get('keywords','');

            if($baseId){
                $list = Color::getListByBaseGoods($baseId,$keywords);

                if(count($list) >0) {
                    $html = '';
                    foreach($list as $key=>$val){
                        $html .= "<a data='{$key}'>{$val}</a>";
                    }
                    echo $html;
                }
            }

            Yii::$app->end();
        }
    }

    /**
     * @description: 获取最近基础商品价格信息
     * @author: zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015-12-07
     * @throws \yii\base\ExitException
     */
    public function actionHistoryPrice(){

        if(Yii::$app->request->isAjax){
            $baseId = intval(f_get('id',0));
            if($baseId){
                $list = LogisticsInterfaceLog::getHistoryPrice($baseId,$this->user_info['city'],7);
                $data=['status'=>0,'data'=>[]];
                if($list && count($list)>0){

                    array_walk($list,function(&$item,$key){
                        $item = intval($item);
                    });
                    $data=['status'=>1,'data'=>['title'=>BaseGoods::getBaseNameById($baseId),'x'=>array_keys($list),'y'=>array_values($list)]];
                }
                echo Json::encode($data);
            }
            Yii::$app->end();
        }

    }
}
