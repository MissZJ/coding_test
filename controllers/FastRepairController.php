<?php 
namespace frontend\controllers;
use common\models\afterSales\AfterSalesGoods;
use common\models\afterSales\AfterSalesSerialCode;
use common\models\goods\Brand;
use frontend\components\Controller2016;
use yii\base\Exception;
use yii\web\controller;
use Yii;
use yii\db\Query;
use common\models\afterSales\AfterSalesRepairFaultDet;
use common\models\other\OtherRegion;
use common\models\afterSales\AfterSalesOrder;
use common\models\afterSales\AfterSalesRepairSetting;
use common\models\afterSales\AfterSalesDest;
use common\models\afterSales\AfterSalesDelivery;
use common\models\afterSales\AfterSalesRepairProvincePrice;

class FastRepairController extends Controller2016 {

    
   public function actionIndex()
   {
       $brand_id = 0;
       if(isset($_GET['brand_id'])){
           $brand_id = $_GET['brand_id'];
       }
       $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
       //f_d($user_info);
       $rep_set_info = AfterSalesRepairSetting::getFastRepairInfo($user_info['province']);
       if(!$rep_set_info){
           f_msg('对不起，该省份未开通此服务！','/fast-repair/welcome');
       }
       
       //获取品牌列表
       $brand_list = \common\models\afterSales\AfterSalesRepairBrand::getBrandList();
       //f_d($brand_list);
      $time = date('Y-m-d H:i:s',time());
       return $this->renderPartial('index',["brand_list"=>$brand_list,
                                            'time'=>$time,
                                            "brand_id"=>$brand_id,]);
   }
   
   //获取商品型号
   public function  actionGetGoodsByBrand($id){
       $goods_list = \common\models\afterSales\AfterSalesRepairGoods::getGoodsListByBrandId($id);
       if($goods_list){
           $str = "";
           foreach ($goods_list as $key => $value) {
               $str .= "<li onclick='c_g(this,\"goods_list\",".$value['id'].")'>".$value['goods_name']."</li>";
            }
            echo $str;
       }
   }
   
   //获取商品颜色
   public function actionGetColorByGoodId($id)
   {
       $color_list = \common\models\afterSales\AfterSalesRepairColor::getcolorListByGoodsId($id);
       $str = "";
       foreach ($color_list as $key => $value) {
           $str .= "<li onclick='c_c(this,\"color_list\",".$key.")'>".$value."</li>";
       }
       echo $str;
   }
   
   //获取商品一级故障
   public function actionGetGoodsFault($id)
   {
       $fault = \common\models\afterSales\AfterSalesRepairFault::getFaultByColor($id);
      $str = "";
       foreach ($fault as $key => $value) {
           $str .= "<li onclick='c_f(this,\"select-guzhang\",".$value['id'].")'>".$value['fault_name']."</li>";
       }
       echo $str;
   }
   
    //获取商品故障明细
   public function actionGetGoodsFaultDet($id)
   {
       $fault_det = \common\models\afterSales\AfterSalesRepairFaultDet::getFaultByFault($id);
     $str = "";
       foreach ($fault_det as $key => $value) {
           $str .= "<li onclick='c_fd(this,\"select-xiangxigz\",".$value['id'].")'>".$value['fault_name']."</li>";
       }
       echo $str;
   }
   
    //获取商品故障明细
   public function actionGetRepairPlan($id)
   {
       $province = $this->user_info['province'];
       $data = AfterSalesRepairProvincePrice::getPrice($id,$province);
       $model = new AfterSalesRepairFaultDet;
       $r = $model->find()->where(['id'=>$id])->asArray()->one();
       if(!empty($data)){
           $r['price'] = $data;
       }
       $str = json_encode($r);
       echo $str;
   }
   
   
   public function actionWelcome()
   {
       return $this->renderPartial('welcome');
   }


   
   //提交至提交个人信息处
   public function actionInputUserInfo()
  {
       if(isset($_POST['f_brand']) && isset($_POST['f_gname']) && isset($_POST['f_color']) && isset($_POST['f_fault']) && isset($_POST['f_fault_det']) && isset($_POST['f_tips'])){
           //f_d($_POST);
           $info = json_encode($_POST);
           //echo $info;exit;
           $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
           $user_name = $user_info['user_name'];
           $phone = $user_info['phone'];
           $province = OtherRegion::getRegionNameStr($user_info['province']);
           $city = OtherRegion::getRegionNameStr($user_info['city']);
           $district = OtherRegion::getRegionNameStr($user_info['district']);
           $address = $user_info['address'];
           $f_tips = $_POST['f_tips'];
           //f_d($address_info);
           //f_d(RepairSetting::getFastRepairInfo(16));
           $rep_set_info = AfterSalesRepairSetting::getFastRepairInfo($user_info['province']);
           if($rep_set_info){
               $get_type = $rep_set_info['get_type'];
               $repair_address = "";
               $get_address = AfterSalesDest::getData($rep_set_info['dest']);
               if($get_type == 1){
                   $pro = OtherRegion::getRegionNameStr($get_address->province);
                   $cit = OtherRegion::getRegionNameStr($get_address->city);
                   $dis = OtherRegion::getRegionNameStr($get_address->district);
                   $repair_address = $pro.$cit.$dis.$get_address->address."(".$get_address->contacts.")".$get_address->phone;
                   
                  // f_d($address_info);
               }
           }else{
               echo "数据异常";exit;
           }
           $express = AfterSalesDelivery::getListByProvinceAndLevel($user_info['province'],1);
//           f_d($province);
           return $this->renderPartial('input_user_info',["user_name"=>$user_name,"phone"=>$phone,
                                                            'province'=>$province,
                                                            'city'=>$city,
                                                            'district'=>$district,
                                                            'address_info'=>$address,
                                                            "f_tips"=>$f_tips,'info'=>$info,
                                                            'get_type'=>$get_type,
                                                            'repair_address'=>$repair_address,
                                                            'express' => $express,
                                                            ]);
      
       }else{
        //f_d($_POST); 
        //  return $this->renderPartial('input_user_info');
           echo "数据异常";exit;
       }
       
   }
   
      //提交至提交至信息处理
      public function actionCompare()
      {
          if(isset($_POST['u_user_name']) && isset($_POST['u_phone'])  && isset($_POST['info']) && isset($_POST['u_code']) && isset($_POST['u_address_info']) && isset($_POST['u_tips']) && isset($_POST['u_courier_name']) && isset($_POST['u_courier_code'])){
              $info = json_decode($_POST['info']);
              $user_name = $_POST['u_user_name'];
              $phone = $_POST['u_phone'];
              $code = $_POST['u_code'];
              $address_info = $_POST['u_address_info'];
              if($_POST['u_courier_name'] == 1){
                  $courier_name = "蜂云物流";
              }else{
                  $courier_name = AfterSalesDelivery::getName($_POST['u_courier_name']);
              }
              $courier_name_code = $_POST['u_courier_name'];//物流名称代码
              $courier_code = $_POST['u_courier_code'];//物流运单号
              $tips = $_POST['u_tips'];//备注
              $data = AfterSalesRepairProvincePrice::getPrice($info->f_fault_det, $this->user_info['province']);
              if(!empty($data)){
                  $price = $data;
              }else{
                  $price = AfterSalesRepairFaultDet::getPrice($info->f_fault_det);
              }
              
              return $this->renderPartial('compare',['info'=>$info,
                                                    'user_name'=>$user_name,
                                                    'phone'=>$phone,
                                                    'code'=>$code,
                                                    'address_info'=>$address_info,
                                                    'courier_name'=>$courier_name,
                                                    'courier_name_code'=>$courier_name_code,
                                                    'courier_code'=>$courier_code,
                                                    'price'=>$price,
                                                    'tips'=>$tips,
                                                    ]);
          }else{
              echo "数据异常";exit;
          }
      }
      
      public function actionTest(){
          f_d(f_s("user_info"));
      }
      
      //提交51快修订单处理
      public function actionSubFastOrder()
      {
          if(isset($_POST['user_name']) && isset($_POST['phone']) && isset($_POST['address']) && isset($_POST['brand'])&&isset($_POST['type']) && isset($_POST['color']) && isset($_POST['code']) && isset($_POST['goods'])
             && isset($_POST['fault_det']) && isset($_POST['courier_name_code']) && isset($_POST['courier_code'])&& isset($_POST['tips']) && isset($_POST['price'])){

            $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');

            $rep_set_info['dest'] = AfterSalesRepairSetting::getFastRepairInfo($user_info['province']);
            $model = new AfterSalesOrder();
            $model->sales_order = AfterSalesOrder::getCode();
    		$model->order_code = '';
    		$model->user_id = $user_info['id'];
    		$model->level = $user_info['level'];
    		$model->contacts = $_POST['user_name'];
    		$model->company = $user_info['shop_name'];
    		$model->phone = $_POST['phone'];
    		$model->province = $user_info['province'];
    		$model->city = $user_info['city'];
    		$model->district = $user_info['district'];
    		$model->address = $_POST['address'];
    		$model->type_status = 8;//售后类型
            $model->lan_logistics = $_POST['courier_name_code']; //三方物流公司
                if($_POST['courier_name_code'] == 1){
                    $model->third_logistics = 0; //是三方物流
                    $model->express_code = '';
                }else{
                    $model->third_logistics = 1;
                    $model->express_code = $_POST['courier_code'];
                }
            $model->status = 5;
    		$model->sales_time = date('Y-m-d H:i:s',time());//售后单生成时间
    		$model->tips = $_POST['tips'];
    		$model->process_person = '';
            $model->duty_decide = 4;//责任初判断
    		$model->refund_fee = 0;//退款金额
    		if($_POST['courier_name_code'] == 1){
    		    $model->reparie_fee = $_POST['price']+10;//维修费用
    		}else{
    		    $model->reparie_fee = $_POST['price'];//维修费用
    		}
    		
            $model->collecting_price = 0;
    		$model->destination = intval($rep_set_info['dest']['dest']);
    		$model->the_last_time = '';
    		$model->pre_processing_time = '';
    		$model->flag = 4;
    		$model->come_from = "快修";//到这里
            $model->terminal_name = '快修';
    		$model->terminal_phone = '快修';
              $connection = Yii::$app->db;//事务开始
              $transaction = $connection->beginTransaction();
              try{
                  if($model->save()){
                      $m = new AfterSalesGoods();
                      $m->sales_order = $model->sales_order;
                      $m->sales_id = $model->id;
                      $m->supplier_id = 0;
                      $m->brand_id = Brand::getBrandIdByName($_POST['brand']);
                      $m->type_id = $_POST['type'];
                      $m->color = $_POST['color'];
                      $m->goods_id = 0;
                      $m->goods_name = $_POST['goods'];
                      $m->content = $_POST['fault_det'];//保修内容
                      $m->need_content = "51快修";//售后所需内容
                      $m->actual_receive_content = '51快修';//实际接收内容
                      $m->repaire_site = '';//维修点
                      $m->result = '';//处理结果
                      $m->num = 1;//商品数量
                      $m->order_source = 0;
                      if($m->save()){
                          $model_serial = new  AfterSalesSerialCode();
                          $model_serial->sales_order = $model->sales_order;
                          $model_serial->serial_code = trim(preg_replace('/\r|\n/', '', $_POST['code']));
                          $model_serial->flag = 0;
                          if(!$model_serial->save()){
                              throw new \Exception('快修订单串号插入异常！');
                          }
                      }else{
                          throw new \Exception('快修订单商品插入异常！');
                      }
                  }else{
                      throw new \Exception('快修订单插入异常！');
                  }

                  $transaction->commit();
                  return $this->redirect('success');
              }catch (\Exception $e){
                  $transaction->rollBack();
                  $this->redirect('/site/errors');
              }
          }
          
      }
      
      //提交成功页面
      public function actionSuccess()
      {
          return $this->renderPartial('success');
      }
   
   
}