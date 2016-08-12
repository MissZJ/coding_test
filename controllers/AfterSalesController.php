<?php
namespace frontend\controllers;

use common\models\afterSales\AfterSalesCategory;
use common\models\afterSales\AfterSalesContent;
use common\models\afterSales\AfterSalesHistoryTypeMap;
use common\models\afterSales\AfterSalesSiteFunctionTimeLimit;
use common\models\afterSales\searchs\AfterSalesData;
use common\models\dh\OmsOrderInfo;
use common\models\goods\Brand;
use common\models\goods\Type;
use common\models\order\OmsGoods;
use common\models\other\ProductConfiguration;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use frontend\components\Controller2016;
use common\models\other\OtherRegion;
use common\models\afterSales\AfterSalesType;
use common\models\afterSales\AfterProblemType;
use yii\db\Query;
use common\models\afterSales\AfterSalesOrder;
use common\models\afterSales\AfterSalesGoods;
use common\models\afterSales\AfterSalesSerialCode;
use common\models\goods\SupplierGoods;
use common\models\afterSales\AfterSalesServiceLanding;
use common\models\afterSales\AfterSalesDelivery;
use common\models\goods\Color;
use yii\helpers\Json;
use common\models\user\Supplier;
use common\models\afterSales\AfterSalesDestSupplier;
use common\models\other\CityFunctionManage;
use common\models\order\OmsInfo;
use yii\web\HttpException;

/**
 * Site controller
 */
class AfterSalesController extends Controller2016
{
    /**
     * @inheritdoc
     */
//    public function behaviors()
//    {
//        return [
//            'access' => [
//                'class' => AccessControl::className(),
//                'rules' => [
//                    [
//                        'actions' => [
//                            'get-tips'
//                        ],
//                        'allow' => true,
//                    ],
//                    [
//                        'actions' => ['logout', 'index'],
//                        'allow' => true,
//                        'roles' => ['@'],
//                    ],
//                ],
//            ],
//            'verbs' => [
//                'class' => VerbFilter::className(),
//                'actions' => [
////                     'logout' => ['post'],
//                ],
//            ],
//        ];
//    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
      * @Title:actionIndex
      * @Description:首页
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:24
     */
    public function actionIndex()
    {
       $this->layout = 'ucenter';
       $user_info = $this->user_info;
       //获取退货商品
       $order_code = f_get('order_code');
       $goods_id = f_get('goods_id');
       
       if(!empty($order_code) && !empty($goods_id)) {

           //防止重复提单
           if(!AfterSalesGoods::checkAfterSales($goods_id,$order_code)){
               return f_msg('该商品已成功提单，无需重复操作！','/ucenter/after-sales');
           }
           $user_id = (new Query())->select('user_id')->from("order_oms_info")->where(['order_code' => $order_code])->scalar();
           
           if($user_id != $user_info['id']){
               return $this->redirect('/site/errors');
           }else{
               $goodsInfo = (new Query())->from("order_oms_goods as a")
                                     ->select('a.id,a.goods_id,a.goods_name,a.code,f.name as goods_color,a.goods_num,a.goods_price,d.goods_code,e.img_url,b.consignee,b.province,b.city,b.district,b.address,b.phone,b.success_time,d.type_id,d.brand_id,g.category')
                                     ->leftJoin('order_oms_info as b','a.order_id = b.id')
                                     ->leftJoin("goods_supplier_goods d","a.goods_id = d.id")
                                     ->leftJoin("goods_photo e","d.cover_id=e.id")
                                     ->leftJoin("goods_color f","a.goods_color=f.id")
                                     ->leftJoin("goods_type g","d.type_id=g.id")
                                     ->andWhere(['a.order_code'=>$order_code,'a.goods_id'=>$goods_id,'b.user_id'=>$user_id])
                                     ->one();
               //安徽和江苏地区，用户端屏蔽苹果品牌，类型为智能手机&平板电脑 &笔记本电脑& 一体机 的申请售后入口
               if (in_array(Yii::$app->user->getIdentity()->province, [16, 3]) && in_array($goodsInfo['type_id'], [1, 6, 3, 100]) && $goodsInfo['brand_id'] == 6) {
                   return f_msg('尊敬的用户，苹果品牌商品如需售后，请及时联系400客服处理。','/ucenter/after-sales');
               }
               //揽件物流
               $level = AfterSalesCategory::getCategory($goods_id);
               $delivery = AfterSalesDelivery::getListByProvinceAndLevel($goodsInfo['province'],$level);

               //服务类型
               $type_status = AfterSalesType::getAvailableServiceOfCustomer(['goods_brand'=>$goodsInfo['brand_id'],'goods_type_category'=>$level,'order_success_time'=>$goodsInfo['success_time'],'historySys'=>false,'goods_type'=>$goodsInfo['type_id']]);

               if(empty($type_status)){
                   return f_msg('该商品暂不满足公司售后服务政策，请联系售后客服协助。','/ucenter/after-sales');
               }
               $omsInfo = OmsInfo::findOne(['order_code'=>$order_code]);

               if (isset($omsInfo->special_type) && $omsInfo->special_type == 3) {
                   if (isset($type_status[1])) {
                       unset($type_status[1]);
                   }
               }
               //故障类型
               $problem_type = AfterProblemType::getAvaiableProblemTypeOfCustomer();
               //省份
               $provinceArr = OtherRegion::getRegion('1');

               return $this->render('index',[
                   'type_status' => $type_status,
                   'problem_type' => $problem_type,
                   'provinceArr' => $provinceArr,
                   'goodsInfo' => $goodsInfo,
                   'order_code' => $order_code,
                   'goods_id' => $goods_id,
                   'delivery'=>$delivery,
                   'level' => $level,
               ]);
           } 
              
           
       }else{
           f_msg('加载数据有误，请重试', f_url(['ucenter/order']));
       }
    }

    /**
     * @Title:actionIndex
     * @Description:历史订单首页
     * @return:
     * @author:leo
     * @date:2015-10-30 下午4:38:24
     */
    public function actionOldIndex(){
        $this->layout = 'ucenter';
        $user_info = $this->user_info;
        //获取退货商品
        $order_code = f_get('order_code');
        $goods_id = f_get('goods_id');

        if(!empty($order_code) && !empty($goods_id)) {
            //防止重复提单
            if(!AfterSalesGoods::checkOldAfterSales($goods_id,$order_code)){
                return f_msg('该商品已成功提单，无需重复操作！','/ucenter/history-order');
            }

            $user_id = (new Query())->select('user_id')->from("{{dh_oms_order_info}}")->where(['order_code' =>(string)$order_code])->scalar(Yii::$app->get('db2'));

            if($user_id != $user_info['id']){
                return $this->redirect('/site/errors');
            }else{

                $goodsInfo = (new Query())->select('a.id,a.goods_id,a.goods_name,a.code,a.goods_color,a.goods_num,a.goods_price,d.code,e.img_url,b.consignee,b.province,b.city,b.district,b.address,b.phone,FROM_UNIXTIME(b.last_confirm_time) as success_time,d.type_id,d.brand_id,f.category')
                                          ->from('{{dh_oms_order_goods}} as a')
                                          ->leftJoin('{{dh_oms_order_info}} as b','a.order_id=b.id')
                                          ->leftJoin('{{dh_base_supplier_goods}} as d','a.goods_id=d.id')
                                          ->leftJoin('{{dh_base_img}} as e','d.img_id=e.id')
                                          ->leftJoin('{{dh_base_type}} as f','f.id=d.type_id')
                                          ->andWhere(['a.order_code'=>(string)$order_code,'a.goods_id'=>$goods_id,'b.user_id'=>$user_id])
                                          ->one(Yii::$app->get('db2'));

                //安徽和江苏地区，用户端屏蔽苹果品牌，类型为智能手机&平板电脑 &笔记本电脑& 一体机 的申请售后入口
                if (in_array(Yii::$app->user->getIdentity()->province, [16, 3]) && in_array($goodsInfo['type_id'], [2, 22, 20, 21]) && $goodsInfo['brand_id'] == 6) {
                    return f_msg('尊敬的用户，苹果品牌商品如需售后，请及时联系400客服处理。','/ucenter/after-sales');
                }

                //揽件物流
                $level = AfterSalesCategory::getCategory($goods_id,true);
                $delivery = AfterSalesDelivery::getListByProvinceAndLevel($goodsInfo['province'],$level);
                //服务类型
                $type_status = AfterSalesType::getAvailableServiceOfCustomer(['goods_brand'=>$goodsInfo['brand_id'],'goods_type_category'=>$level,'order_success_time'=>$goodsInfo['success_time'],'historySys'=>true, 'goods_type'=>$goodsInfo['type_id']]);
                if(empty($type_status)){
                    return f_msg('该商品暂不满足公司售后服务政策，请联系售后客服协助。','/ucenter/after-sales');
                }
                //故障类型
                $problem_type = AfterProblemType::getAvaiableProblemTypeOfCustomer();
                //省份
                $provinceArr = OtherRegion::getRegion('1');

                $modeArr = [];
                foreach ($delivery as $key=>$val) {
                    $modeArr[] = $val['mode'];
                }


                return $this->render('old-index',[
                    'type_status' => $type_status,
                    'problem_type' => $problem_type,
                    'provinceArr' => $provinceArr,
                    'goodsInfo' => $goodsInfo,
                    'order_code' => $order_code,
                    'goods_id' => $goods_id,
                    'delivery'=>$delivery,
                    'modeArr'=>$modeArr,
                ]);
            }


        }else{
            f_msg('加载数据有误，请重试', f_url(['ucenter/order']));
        }
    }
    
    /**
      * @Title:actionAddSales
      * @Description:前台申请售后
      * @return:
      * @author:huaiyu.cui
      * @date:2015-11-7 下午3:04:10
     */
    public function actionAddSales() {
        $checkArr = [
            'type_status','num','problem_type','content','contacts','province','city','district','address','phone','order_code','goods_id','lan_logistics','back_method'
        ];

        $flag = true;
        foreach ($checkArr as $value){
            if(!isset($_POST[$value]) || empty($_POST[$value])){
                $flag = false;
            }
        }

        if($flag){
            //防止重复提单
            if(!AfterSalesGoods::checkAfterSales($_POST['goods_id'],$_POST['order_code'])){
                return json_encode('该商品已成功提单，无需重复操作！');
            }
            $user_info = $this->user_info;
            
            $model = new AfterSalesOrder();
            $asGoodsModel = new AfterSalesGoods();
            $asSerialCodeModel = new AfterSalesSerialCode();

            //判断该串是否有异型换机
            $renewGoods = AfterSalesSerialCode::getRenewGoodsOfExceptionState($_POST['serial_code']);
            if($renewGoods){
                $goods_data = SupplierGoods::findOne($renewGoods['r_goods_id']);
                $omsgoods=['supplier_id'=>$goods_data->supplier_id,'goods_price'=>$goods_data->price];
            }else{
                $omsgoods = (new Query())->select('supplier_id,goods_price')->from("order_oms_goods")->where(['order_code'=>$_POST['order_code'],'goods_id'=>$_POST['goods_id']])->one();
                $goods_data = SupplierGoods::findOne($_POST['goods_id']);
            }

            $order_info = OmsInfo::findOne(['order_code'=>$_POST['order_code']]);
            //$typeCategory = Type::getCate($goods_data->type_id);//品类分类
            $typeCategory = AfterSalesCategory::getCategory($goods_data->id);//品类分类

            //可用服务类型
            $vailableService = AfterSalesType::getAvailableServiceOfCustomer(['goods_brand'=>$goods_data->brand_id,'goods_type_category'=>$typeCategory,'order_success_time'=>$order_info->success_time,'historySys'=>false, 'goods_type'=>$goods_data->type_id]);
            if(empty($vailableService[f_post('type_status')])) {
                return json_encode('提交数据不符合业务规则！');
            }

            if(!CityFunctionManage::CheckSales($_POST['province'], $_POST['city'])){
                return json_encode('区域为'.OtherRegion::getRegionName($_POST['province'])->region_name.OtherRegion::getRegionName($_POST['city'])->region_name.'市，售后未开通，请联系售后客服！');
            }
            
            //检验串号
            if(in_array($goods_data->type_id,[1,2,3,4,6,38])){
                if(!empty($_POST['serial_code'])){
                    $check = AfterSalesSerialCode::CheckSerialCode($_POST['order_code'],$_POST['serial_code']);
                    if($check['code'] == '101') {
                        $res = AfterSalesSerialCode::CheckSerialCodeNext($_POST['order_code'],$_POST['serial_code']);
                        if($res['code'] != '200'){
                            return json_encode($res['mes']);
                        }
                    } else {
                        if($check['code'] != '200') {
                            return json_encode($check['mes']);
                        }
                    }
                }else{
                    return json_encode('手机类产品请输入IMEI码，平板类请输入SN号！');
                }
            }
            if(Supplier::getSalesStandard($omsgoods['supplier_id'])==2){
                $destination = AfterSalesDestSupplier::getDestSupplier($omsgoods['supplier_id']);
            }else{
                $destination = AfterSalesServiceLanding::getDesType($_POST['province'], $_POST['city'], $goods_data->type_id, $_POST['type_status'], $goods_data->brand_id,$_POST['district']);
            }

            if(empty($destination)){
                return json_encode('区域为'.OtherRegion::getRegionName($_POST['province'])->region_name.OtherRegion::getRegionName($_POST['city'])->region_name.'市，售后接收地市无法确定，请联系售后客服！');
            }


            $need_content = AfterSalesData::getData($goods_data->type_id,$goods_data->brand_id,$_POST['type_status'],$destination);
            $sales_order = $model::getCode();
            $if_kh = AfterSalesOrder::CheckOrderIfkh($_POST['order_code'],$_POST['goods_id'],$_POST['type_status'],$_POST['problem_type']) == true ? 1 : 0;//是否符合51快换
            if ($if_kh == 1) { //是51快换检查售后地区是否关闭51快换
                $res = AfterSalesSiteFunctionTimeLimit::isClosedFunctionByDistrict($_POST['district'],1);
                if ($res) {
                    $if_kh = 0;
                }
            }
            $out = '';
            if($_POST['type_status'] == 1){
                if($order_info->pay_way == 4){
                    $out = '3';
                }else{
                    $out = '1';
                }
            }
            //提单时间限制
            $limit_sales = AfterSalesOrder::LimitSales($goods_data->type_id, $goods_data->brand_id, $if_kh);
            if($limit_sales['code']!=1){
                return json_encode($limit_sales['mes']);
            }
            
            $model->sales_order = $sales_order;
            $model->order_code = $_POST['order_code'];
            $model->user_id = $user_info['id'];
            $model->contacts = $_POST['contacts'];
            $model->level = $user_info['level'];
            $model->company = $user_info['shop_name'];
            $model->phone = $_POST['phone'];
            $model->province = $_POST['province'];
            $model->city = $_POST['city'];
            $model->district =$_POST['district'];
            $model->address = $_POST['address'];
            $model->type_status = $_POST['type_status'];
            $model->back_method = $_POST['back_method'];
            $model->lan_logistics = $_POST['lan_logistics']; //蜂云物流
            $model->third_logistics = AfterSalesDelivery::getMode($_POST['lan_logistics']) == 3 ? 0 : 1;
            $model->express_code = isset($_POST['express_code'])&&!empty($_POST['express_code']) ? $_POST['express_code'] : '';
            $model->to_express_code = '';
            $model->status = 5;
            if(f_post('type_status') == 1) {
                $model->refund_fee = $_POST['num'] * $omsgoods['goods_price'];
            } else {
                $model->refund_fee = 0;
            }
            $model->reparie_fee = 0;
            $model->collecting_price = 0;
            $model->destination = $destination;
            $model->process_person = '';
            $model->flag = 1;
            $model->come_from = "web网站";
            $model->terminal_name = !empty($_POST['terminal_name']) ? $_POST['terminal_name'] : '';
            $model->terminal_phone = !empty($_POST['terminal_phone']) ? $_POST['terminal_phone'] : '';
            $model->sales_time = date('Y-m-d H:i:s',time());
            $model->if_kh = $if_kh;
            $model->refund_way = $out;
            $connection = Yii::$app->db;//事务开始
            $transaction = $connection->beginTransaction();
            try{
                if($model->save()){
                    $asGoodsModel->sales_id = $model->id;
                    $asGoodsModel->brand_id = $goods_data->brand_id;
                    $asGoodsModel->goods_id = $goods_data->id;
                    $asGoodsModel->color = Color::getColorNameById($goods_data->color_id);
                    $asGoodsModel->supplier_id = $omsgoods['supplier_id'];
                    $asGoodsModel->type_id = $goods_data->type_id;
                    $asGoodsModel->goods_name = $goods_data->goods_name;
                    $asGoodsModel->goods_code = $goods_data->goods_code;
                    $asGoodsModel->depot_id = $goods_data->depot_id;
                    $asGoodsModel->sales_order = $sales_order;
                    $asGoodsModel->content = $_POST['content'];
                    $asGoodsModel->need_content = $need_content;
                    $asGoodsModel->actual_receive_content = '';
                    $asGoodsModel->num = $_POST['num'];
                    $asGoodsModel->repaire_site = '';
                    $asGoodsModel->result = '';
                    $asGoodsModel->problem_type = $_POST['problem_type'];
                    $asGoodsModel->handle_type = 0;
                    $asGoodsModel->goods_price = $omsgoods['goods_price']; // add by wmc
                    $asGoodsModel->apple_id = isset($_POST['apple_id']) && !empty($_POST['apple_id']) ? $_POST['apple_id'] : '';
                    $asGoodsModel->id_passwd = isset($_POST['id_passwd']) && !empty($_POST['id_passwd']) ? $_POST['id_passwd'] : '';
                    $asGoodsModel->open_passwd = isset($_POST['open_passwd']) && !empty($_POST['open_passwd']) ? $_POST['open_passwd'] : '';
                    $asGoodsModel->category = $typeCategory;
                    
                    if($asGoodsModel->save()){
                        //插入after_sales_serial_code表
                        $asSerialCodeModel->sales_order = $sales_order;
                        $asSerialCodeModel->serial_code = trim(preg_replace('/\r|\n/', '', $_POST['serial_code']));
                        $asSerialCodeModel->flag = 0;
                        
                        if($asSerialCodeModel->save()){
                            $transaction->commit();   	//事务结束
                            return json_encode(['sales_order'=>$sales_order]);
                        }else{
                            if($model->hasErrors()){
                                foreach ($asGoodsModel->getFirstErrors() as $value){
                                    $message = $value;
                                }
                                return json_encode($message);
                            }
                        }
                    }else{
                        if($model->hasErrors()){
                            foreach ($asGoodsModel->getFirstErrors() as $value){
                                $message = $value;
                            }
                            return json_encode($message);
                        }
                    }
                }else{
                    if($model->hasErrors()){
                        foreach ($model->getFirstErrors() as $value){
                            $message = $value;
                        }
                        return json_encode($message);
                    }
                }
            }catch (\Exception $e){
                $transaction->rollBack();
            }
        }else{
            return json_encode('数据传输错误！');
        }
    }

    /**
     * @Title:actionAddOldSales
     * @Description:前台申请历史订单售后
     * @return:
     * @author:leo
     * @date:2015-11-7 下午3:04:10
     */
    public function actionAddOldSales(){
        $checkArr = [
            'type_status','num','problem_type','content','contacts','province','city','district','address','phone',
            'order_code','goods_id','lan_logistics','back_method'
        ];

        $flag = true;
        foreach ($checkArr as $value){
            if(!isset($_POST[$value]) || empty($_POST[$value])){
                $flag = false;
            }
        }

        if($flag){
            //防止重复提单
            if(!AfterSalesGoods::checkOldAfterSales($_POST['goods_id'],$_POST['order_code'])){
                return json_encode('该商品已成功提单，无需重复操作！');
            }

            $user_info = $this->user_info;
            $model = new AfterSalesOrder();
            $asGoodsModel = new AfterSalesGoods();
            $asSerialCodeModel = new AfterSalesSerialCode();
            $typeCategory = AfterSalesCategory::getCategory($_POST['goods_id'], true);//品类分类
            if(!CityFunctionManage::CheckSales($_POST['province'], $_POST['city'])){
                return json_encode('区域为'.OtherRegion::getRegionName($_POST['province'])->region_name.OtherRegion::getRegionName($_POST['city'])->region_name.'市，售后未开通，请联系售后客服！');
            }
            $oms_goods = (new Query())->select('code,goods_price,warehouse_id')->from('{{dh_oms_order_goods}}')->where(['order_code'=>$_POST['order_code'],'goods_id'=>$_POST['goods_id']])->one(Yii::$app->get('db2'));
            $goods_data = (new Query())->select('t1.*,t2.name as brand_name')->from('{{dh_base_supplier_goods}} as t1')
                                        ->leftJoin('{{dh_base_brand}} as t2','t1.brand_id=t2.id')->where(['t1.id'=>$_POST['goods_id']])->one(Yii::$app->get('db2'));
            $type_id =AfterSalesHistoryTypeMap::getNewSysType($goods_data['type_id']);
            $brand_id = Brand::find()->select('id')->where(['like','name',$goods_data['brand_name']])->asArray()->scalar();
            $supplier_id = 0;
            $color = (new Query())->select('goods_color')->from('{{dh_oms_order_goods}}')->where(['order_code'=>$_POST['order_code'],'goods_id'=>$_POST['goods_id']])->scalar(Yii::$app->get('db2'));
            $goods_name = $goods_data['name'];
            $goods_id = $_POST['goods_id'];
            $goods_code = $goods_data['code'];
            $goods_price = $oms_goods['goods_price'];
            $depot_id = 0;
            if(empty($brand_id)){$brand_id=0;}
            $type_arr = [1,2,22,20,87,134];
            $destination = AfterSalesServiceLanding::getOldDesType($_POST['province'], $_POST['city'], $type_id, $_POST['type_status'], $brand_id,$_POST['order_code']);

            if(empty($destination)){
                return json_encode('区域为'.OtherRegion::getRegionName($_POST['province'])->region_name.OtherRegion::getRegionName($_POST['city'])->region_name.'市，售后接收地市无法确定，请联系售后客服！');
            }

            $renewGoods = AfterSalesSerialCode::getRenewGoodsOfExceptionState($_POST['serial_code']);
            if($renewGoods){
                $goods_data = SupplierGoods::findOne($renewGoods['r_goods_id']);
                $type_id = $goods_data->type_id;
                $brand_id = $goods_data->brand_id;
                $supplier_id = $goods_data->supplier_id;
                $color = Color::getColorNameById($goods_data->color_id);
                $goods_name = $goods_data->goods_name;
                $goods_id = $goods_data->id;
                $goods_code = $goods_data->goods_code;
                $depot_id = $goods_data->depot_id;
                $goods_price = $goods_data->price;
            }
            
            //检验串号
            if(in_array($type_id,$type_arr)){
                if(!empty($_POST['serial_code'])){
                    $check = AfterSalesSerialCode::CheckSerialCode($_POST['order_code'],$_POST['serial_code']);
                    if($check['code'] == '101') {
                        $res = AfterSalesSerialCode::CheckSerialCodeNext($_POST['order_code'],$_POST['serial_code']);
                        if($res['code'] != '200'){
                            return json_encode($res['mes']);
                        }
                    } else {
                        if($check['code'] != '200') {
                            return json_encode($check['mes']);
                        }
                    }
                }else{
                    return json_encode('手机类产品请输入IMEI码，平板类请输入SN号！');
                }
            }

            $need_content = AfterSalesData::getData($type_id,$brand_id,$_POST['type_status'],$destination);
            $sales_order = $model::getCode();
            $if_kh = 0;

            //提单时间限制
            $limit_sales = AfterSalesOrder::LimitSales($type_id, $brand_id, $if_kh);
            if($limit_sales['code']!=1){
                return json_encode($limit_sales['mes']);
            }
            
            $out = '';
            if($_POST['type_status'] == 1){
                $pay_way = (new Query())->select('pay_way')->from('dh_oms_order_info')->where(['order_code'=>$_POST['order_code']])->scalar(Yii::$app->get('db2'));
                $success_pay_way = [156,164,192];
                if(in_array($pay_way,$success_pay_way)){
                    $out = '3';
                }else{
                    $out = '1';
                }
            }

            $model->sales_order = $sales_order;
            $model->order_code = $_POST['order_code'];
            $model->user_id = $user_info['id'];
            $model->contacts = $_POST['contacts'];
            $model->level = $user_info['level'];
            $model->company = $user_info['shop_name'];
            $model->phone = $_POST['phone'];
            $model->province = $_POST['province'];
            $model->city = $_POST['city'];
            $model->district =$_POST['district'];
            $model->address = $_POST['address'];
            $model->type_status = $_POST['type_status'];
            $model->back_method = $_POST['back_method'];
            $model->lan_logistics = $_POST['lan_logistics']; //蜂云物流
            $model->third_logistics = AfterSalesDelivery::getMode($_POST['lan_logistics']) == 3 ? 0 : 1;
            $model->express_code = isset($_POST['express_code'])&&!empty($_POST['express_code']) ? $_POST['express_code'] : '';
            $model->to_express_code = '';
            $model->status = 5;
            if(f_post('type_status') == 1) {
                $model->refund_fee = $_POST['num'] * $goods_price;
            } else {
                $model->refund_fee = 0;
            }
            $model->reparie_fee = 0;
            $model->collecting_price = 0;
            $model->destination = $destination;
            $model->process_person = '';
            $model->flag = 1;
            $model->come_from = "web网站";
            $model->terminal_name = !empty($_POST['terminal_name']) ? $_POST['terminal_name'] : '';
            $model->terminal_phone = !empty($_POST['terminal_phone']) ? $_POST['terminal_phone'] : '';
            $model->sales_time = date('Y-m-d H:i:s',time());
            $model->tips = $_POST['order_code'].'-2.0系统订单';
            $model->if_kh = $if_kh;
            $model->refund_way = $out;
            $connection = Yii::$app->db;//事务开始
            $transaction = $connection->beginTransaction();
            try{
                if($model->save()){
                    $asGoodsModel->sales_id = $model->id;
                    $asGoodsModel->sales_order = $sales_order;
                    $asGoodsModel->supplier_id = $supplier_id;
                    $asGoodsModel->brand_id = $brand_id;
                    $asGoodsModel->type_id = $type_id;
                    $asGoodsModel->color =$color;
                    $asGoodsModel->goods_id = $goods_id;
                    $asGoodsModel->goods_name = $goods_name;
                    $asGoodsModel->goods_code = $goods_code;
                    $asGoodsModel->depot_id = $depot_id;
                    $asGoodsModel->content = $_POST['content'];
                    $asGoodsModel->need_content = $need_content;
                    $asGoodsModel->actual_receive_content = '';
                    $asGoodsModel->num = $_POST['num'];
                    $asGoodsModel->repaire_site = '';
                    $asGoodsModel->result = '';
                    $asGoodsModel->problem_type = $_POST['problem_type'];
                    $asGoodsModel->handle_type = 0;
                    $asGoodsModel->apple_id = isset($_POST['apple_id']) && !empty($_POST['apple_id']) ? $_POST['apple_id'] : '';
                    $asGoodsModel->id_passwd = isset($_POST['id_passwd']) && !empty($_POST['id_passwd']) ? $_POST['id_passwd'] : '';
                    $asGoodsModel->open_passwd = isset($_POST['open_passwd']) && !empty($_POST['open_passwd']) ? $_POST['open_passwd'] : '';
                    $asGoodsModel->goods_price = $goods_price; //add by wmc
                    $asGoodsModel->order_source =1;
                    $asGoodsModel->category = $typeCategory;
                    if($asGoodsModel->save()){
                        //插入after_sales_serial_code表
                        $asSerialCodeModel->sales_order = $sales_order;
                        $asSerialCodeModel->serial_code = trim(preg_replace('/\r|\n/', '', $_POST['serial_code']));
                        $asSerialCodeModel->flag = 0;
                        if($asSerialCodeModel->save()){
                            $transaction->commit();   	//事务结束
                            return json_encode(['sales_order'=>$sales_order]);
                        }else{
                            if($model->hasErrors()){
                                foreach ($asGoodsModel->getFirstErrors() as $value){
                                    $message = $value;
                                }
                                return json_encode($message);
                            }
                        }
                    }else{
                        if($model->hasErrors()){
                            foreach ($asGoodsModel->getFirstErrors() as $value){
                                $message = $value;
                            }
                            return json_encode($message);
                        }
                    }
                }else{
                    if($model->hasErrors()){
                        foreach ($model->getFirstErrors() as $value){
                            $message = $value;
                        }
                        return json_encode($message);
                    }
                }
            }catch (\Exception $e){
                $transaction->rollBack();
            }
        }else{
            return json_encode('数据传输错误！');
        }

    }

    /**
     * @description:确定接收地市
     * @return: return_type
     * @author: leo
     * @date: 2015年12月7日 09:56:23
     * @review_user:
     */
//    public function actionChangeExpress(){
//        if(isset($_POST['city'])&&!empty($_POST['city'])){
//            $res = [];
//            $data = AfterSalesDelivery::getAfterSalesDelivery($_POST['city']);
//            if(!empty($data)){
//                $h1 = '<option value="">请选择</option>';
//                $h2 = '';
//                $h3 = '';
//                foreach($data as $val){
//                    $h1 .='<option value="'.$val['id'].'">'.$val['delivery_name'].'</option>';
//                }
//
//                if($data[0]['third_logistics']){
//                    $h2.=' <p class="sh_content_pb ex_code"><span><span class="tbl_red">*</span>快递单号:</span><span class="sh_pb_span"><input id="Text1" type="text" class="sh_inpa express_code" name="express_code"/></span></p>';
//                    $h3.='<input type="radio" name="back_method" class="sh_rBtn" checked="checked" value="2">快递至我要订货网';
//                }else{
//                    $h3.='<input  type="radio" name="back_method" class="sh_rBtn" checked="checked" value="1">上门取件';
//                }
//
//                $res = ['code'=>'200','h1'=>$h1,'h2'=>$h2,'h3'=>$h3];
//            }else{
//                $res = ['code'=>'101','msg'=>'售后物流方式暂未覆盖该区域,无法申请售后！'];
//            }
//
//            return Json::encode($res);
//        }
//    }

    /**
     * @description:确定接收地市
     * @return: return_type
     * @author: leo
     * @date: 2015年12月7日 09:56:23
     * @review_user:
     */
    public function actionGetTips(){
        $res = [];
        if(isset($_POST['order_code'])&&isset($_POST['goods_id'])&&isset($_POST['type_status'])){
            $oms_info = OmsInfo::findOne(['order_code'=>$_POST['order_code']]);
            $goods_info = SupplierGoods::findOne($_POST['goods_id']);
            if($goods_info && $oms_info){
                if(Supplier::getSalesStandard($goods_info->supplier_id)==2){
                    $destId = AfterSalesDestSupplier::getDestSupplier($goods_info->supplier_id);
                }else{
                    $destId = AfterSalesServiceLanding::getDesType($oms_info->province, $oms_info->city, $goods_info->type_id, $_POST['type_status'],$goods_info->brand_id,$oms_info->district);
                }

                if($destId){
                    $need_content = AfterSalesData::getData($goods_info->type_id,$goods_info->brand_id,$_POST['type_status'],$destId);
                    $res = ['code'=>'200','mes'=>$need_content];
                }else{
                    $res = ['code'=>'102','mes'=>'售后接收地市获取失败！'];
                }
            }else{
                $res = ['code'=>'102','mes'=>'订单数据获取失败！'];
            }
        }else{
            $res = ['code'=>'101','mes'=>'数据传输错误！'];
        }
        return json_encode($res);
    }

    /**
     * @description:确定接收地市
     * @return: return_type
     * @author: leo
     * @date: 2015年12月7日 09:56:23
     * @review_user:
     */
    public function actionGetOldTips(){
        $res = [];
        if(isset($_POST['order_code'])&&isset($_POST['goods_id'])&&isset($_POST['type_status'])){
            $oms_info = OmsOrderInfo::findOne(['order_code'=>$_POST['order_code']]);
            $goods_info = (new Query())->select('*')->from('{{dh_base_supplier_goods}}')->where(['id'=>$_POST['goods_id']])->one(Yii::$app->get('db2'));
            $brand_name = (new Query())->select('name')->from('{{dh_base_brand}}')->where(['id'=>$goods_info['brand_id']])->one(Yii::$app->get('db2'));
            $type_id = AfterSalesHistoryTypeMap::getNewSysType($goods_info['type_id']);
            $brand_id = Brand::find()->select('id')->where(['name'=>$brand_name])->asArray()->scalar();
            if($goods_info && $oms_info && $brand_id && $type_id){
                $destId = AfterSalesServiceLanding::getOldDesType($oms_info->province, $oms_info->city, $type_id, $_POST['type_status'], $brand_id,$_POST['order_code']);

                if($destId){
                    $need_content = AfterSalesData::getData($type_id,$brand_id,$_POST['type_status'],$destId);
                    $res = ['code'=>'200','mes'=>$need_content];
                }else{
                    $res = ['code'=>'102','mes'=>'售后接收地市获取失败！'];
                }

            }else{
                $res = ['code'=>'102','mes'=>'订单数据获取失败！'];
            }
        }else{
            $res = ['code'=>'101','mes'=>'数据传输错误！'];
        }
        return json_encode($res);
    }

    /**
     * @description:获取问题描述
     * @return: return_type
     * @author: leo
     * @date: 2015年12月7日 09:56:23
     * @review_user:
     */
    public function actionGetContent(){
        if(isset($_POST['problem_type'])&&!empty($_POST['problem_type']) && isset($_POST['goods_id'])&&!empty($_POST['goods_id']) && isset($_POST['isOld'])){
            $data = AfterSalesContent::find()->where(['problem_type'=>$_POST['problem_type']])->orderBy('weight DESC ')->asArray()->all();
            if(!empty($data)){
                if ($_POST['isOld']) {
                    $errorType = [2, 20, 22, 21];
                    $goodsInfo = (new Query())->select('brand_id,type_id')->from('{{dh_base_supplier_goods}}')->where(['id'=>$_POST['goods_id']])->one(Yii::$app->get('db2'));
                } else {
                    $errorType = [1, 3, 6, 100];
                    $goodsInfo = SupplierGoods::find()->select('brand_id,type_id')->where(['id'=>$_POST['goods_id']])->asArray()->one();
                }

                if ($goodsInfo['brand_id'] == 6 && in_array($goodsInfo['type_id'], $errorType)) {
                    foreach ($data as $key => $value) {
                        //性能故障，屏蔽不开机
                        if ($value['problem_type'] == 1 && $value['id'] == 11 ) {
                            unset($data[$key]);
                        }
                    }
                }

                $html = '';
                foreach ($data as $val) {
                    $html .= "<a href='javascript:void(0);' class='sh_content_a' typeId='".$val['id']."' onclick='select_content(this)'>".$val['content']."</a>";
                }
                //性能故障、软件故障、附件故障 屏蔽其他
                if ($goodsInfo['brand_id'] == 6 && in_array($goodsInfo['type_id'], $errorType) && in_array($_POST['problem_type'], [1, 2, 3])) {
                    $res = ['code'=>'200','mes'=>'ok','html'=>$html];
                } else {
                    $html .="<a href='javascript:void(0);' class='sh_content_a other' typeId='0' onclick='select_content(this)'>其他</a>";
                    $res = ['code'=>'200','mes'=>'ok','html'=>$html];
                }
            }else{
                $res = ['code'=>'102','mes'=>'问题描述数据未找到！'];
            }
        }else{
            $res = ['code'=>'101','mes'=>'数据传输错误！'];
        }

        return json_encode($res);
    }
    /**
     * @description: 检查是否符合51快换
     * @author: zhanghy
     * @date ：2016年1月18日10:13:01
     */
    public function actionIfkh(){
        if(isset($_POST['province'])&&!empty($_POST['province'])&&isset($_POST['order_code'])&&!empty($_POST['order_code'])&&isset($_POST['goods_id'])&&!empty($_POST['goods_id'])){
            $res = [];
            $disId = null;
            if(isset($_POST['service_type'])&&!empty($_POST['service_type'])){
                $problem_type = !empty($_POST['problem_type']) ? $_POST['problem_type'] : null;
                if (!empty($_POST['district'])){
                    $disId = $_POST['district'];
                }
                $ifkh = AfterSalesOrder::CheckOrderIfkh($_POST['order_code'],$_POST['goods_id'],$_POST['service_type'],$problem_type,$disId);//检查是否符合51快换
                $html = '';
                if ($ifkh) {
                    $html .= '<p class="sh_content_pb logistics_p"><span><span class="tbl_red">*</span>物流方式:</span>
                        <span class="sh_pb_span">
                        <select id="lan_logistics" class="sh_select lan_logistics" name="lan_logistics">
                        <option value="1" selected="selected">蜂云物流</option></select></span>
                        </p>
                        <p class="sh_content_pb back_md_p"><span><span class="tbl_red">*</span>商品返回方式:</span>
                        <span class="sh_pb_span back_md">
                        <input  type="radio" name="back_method" class="sh_rBtn back_method" checked="checked" value="1">上门取件
                        </span>
                        </p>';
                    $res = ['code' => '200', 'html' => $html];
                } else {
                    if (isset($_POST['isOld'])) {
                        $level = AfterSalesCategory::getCategory($_POST['goods_id'],true);
                    } else {
                        $level = AfterSalesCategory::getCategory($_POST['goods_id']);
                    }
                    $data = AfterSalesDelivery::getListByProvinceAndLevel($_POST['province'],$level);
                    if(!empty($data)){
                        $modeArr = [];
                        foreach ($data as $key=>$val) {
                            $modeArr[] = $val['mode'];
                        }
                        $html .= '<p class="sh_content_pb logistics_p"><span><span class="tbl_red">*</span>物流方式:</span>
                        <span class="sh_pb_span">
                        <select id="lan_logistics" class="sh_select lan_logistics" name="lan_logistics">';
                        foreach ($data as $key=>$val) {
                          $html .= '<option value="'.$val['id'].'">'.$val['name'].'</option>';
                        }
                        $html .= '</select></span></p>';
                        $html .= '<p class="sh_content_pb back_md_p"><span><span class="tbl_red">*</span>商品返回方式:</span>
                        <span class="sh_pb_span back_md">';
                        $is_alert = 0;
                        if (in_array('1',$modeArr) && in_array('3',$modeArr)) {
                            $html .= '<input  type="radio" name="back_method" class="sh_rBtn back_method" checked="checked" value="2">快递至51订货网';
                            $html .= '<p class="sh_content_pb ex_code">
                                    <span><span class="tbl_red">*</span>快递单号:</span>
                                    <span class="sh_pb_span">
                                    <input id="express_code" type="text" class="sh_inpa express_code" name="express_code"/>
                                    </span>';
                            $is_alert = 1;
                        } elseif (in_array('1',$modeArr)) {
                            $html .= '<input  type="radio" name="back_method" class="sh_rBtn back_method" checked="checked" value="2">快递至51订货网';
                            $html .= '<p class="sh_content_pb ex_code">
                                    <span><span class="tbl_red">*</span>快递单号:</span>
                                    <span class="sh_pb_span">
                                    <input id="express_code" type="text" class="sh_inpa express_code" name="express_code"/>
                                    </span>';
                            $is_alert = 1;
                        } else {
                            $html .= '<input  type="radio" name="back_method" class="sh_rBtn back_method" checked="checked" value="1">上门取件';
                        }
                        $html .= '</span></p>';
                        $res = ['code' => '200', 'html' => $html, 'isAlert' => $is_alert];
                    }else{
                        $res = ['code'=>'101','mes'=>'售后物流未覆盖到'.OtherRegion::getRegionName($_POST['province'])->region_name.'地区，请设置对应区域！'];
                    }
                }
            }else{
                $res = ['code'=>'101','mes'=>'请先确定服务类型~'];
            }
            return Json::encode($res);
        }
    }

    /**
     * @description: 检查用户提交售后单省市区是否被限制物流
     * @author :zhanghy
     * @date : 2016年3月22日13:52:49
     */
    public function actionCheckSubmitAddr(){
        $districtId = f_post('district',0);
        $result = [];
        $result['mes'] = AfterSalesSiteFunctionTimeLimit::isClosedFunctionByDistrict($districtId);
        if (empty($result['mes'])) {
            $result['mes'] = '';
        }
        return Json::encode($result);
    }

    /**
     * @description: 获取故障类型
     * @author :zhanghy
     * @date : 2016年3月22日13:52:49
     */
    public function actionGetProblemType(){
        $typeId = f_post('typeId',0);
        $return = [];
        if ($typeId) {
            $problemType = AfterProblemType::getProblemTypeByTypeStatus($typeId);
            if (!empty($problemType)) {
                $html = ' <option value=\'\' >请选择</option>';
                foreach ($problemType as $key => $val) {
                    $html .= " <option value='$key' >$val</option>";
                }
                $return = ['code'=>'200', 'mes'=>$html];
            } else {
                $return = ['code'=>'101', 'mes'=>'故障类型获取失败，请重试~'];
            }
        } else {
            $return = ['code'=>'101', 'mes'=>'服务类型获取失败，请重试~'];
        }
        return json_encode($return);
    }

    /**
     * @description:售后数量检测当前售后单商品可修改数量
     * @return string
     * @date 2016年6月21日14:36:56
     * @author zhanghy
     */
    public function actionCheckAfterSalesNum(){
        if (isset($_POST['order_code']) && isset($_POST['goods_id']) && isset($_POST['temp'])) {
            $afterSalesInfo = (new Query())->select('t1.status,t2.num')->from('after_sales_order as t1')
                ->leftJoin('after_sales_goods as t2','t1.sales_order=t2.sales_order')
                ->where(['t1.order_code'=>$_POST['order_code'],'t2.goods_id'=>$_POST['goods_id']])->all();
            $orderInfo = OmsGoods::find()->where(['order_code'=>$_POST['order_code'],'goods_id'=>$_POST['goods_id']])->asArray()->all();
            if(empty($orderInfo)){
                $orderInfo = (new Query())->from('{{dh_oms_order_goods}}')->where(['order_code'=>$_POST['order_code'],'goods_id'=>$_POST['goods_id']])->all(Yii::$app->get('db2'));
            }
            $result = [];
            if (!empty($afterSalesInfo)) {
                $i = 0;
                foreach ($afterSalesInfo as $val){
                    if($val['status']=='65'){
                        $i+= $val['num'];
                    }else{
                        if (!in_array($val['status'], ['15','25','70','75','80'])) {
                            $i+= $val['num'];
                        }
                    }
                }

                $max_num = $orderInfo[0]['goods_num'] - $i;
                $result = ['max_num'=>$max_num,'mes'=>'*该商品最多只能申请'.$max_num.'个！'];
                echo json_encode($result);exit;
            } else {
                $result = ['max_num'=>$orderInfo[0]['goods_num'],'mes'=>'*该商品最多只能申请'.$orderInfo[0]['goods_num'].'个！'];
                echo json_encode($result);exit;
            }
        }
    }
}

