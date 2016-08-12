<?php 
/**
 * @Title: enclosing_method
 * @Description:
 * @return: return_type
 * @author: yulong.wang
 * @date: 2015-3-25下午3:36:35
 */

namespace frontend\controllers;
use common\components\swoole\SwooleClient;
use common\components\swoole\SwooleHttpServer;
use common\models\afterSales\LogisticsImg;
use common\models\gw\GwPushInterface;
use common\models\gw\GwPushInterfaceLog;
use common\models\OnlinePayment;
use common\models\sop\SopPunishReasonClassify;
use common\models\user\searchs\UserAdmin;
use common\models\user\UserMember;
use common\models\user\UserSupplierMessageDetail;
use frontend\components\Controller2016;
use Yii;
use yii\db\Query;
use common\models\api\LogisticsInterfaceLog;
use common\models\goods\SupplierGoods;
use common\models\order\RefuseImg;
use common\models\order\OmsInfo;
use common\models\order\OmsLog;
use common\models\order\OmsStatus;
use common\models\order\CreditCardRecord;
use common\models\order\OrderCaseNumber;
use common\models\api\LogisticsAddInv;
use common\models\afterSales\AfterSalesOrder;
use common\models\afterSales\AfterSalesLog;
use common\models\goods\Depot;
use common\models\other\OtherRegion;
use common\models\goods\BuySaleBase;
use yii\helpers\Json;
use common\models\user\Supplier;
use common\models\afterSales\AfterSalesGoods;
use common\models\sop\punish_prox\SopPunishProxCommonOrder;
use common\models\sop\SopPunishDetail;
use common\models\afterSales\AfterSalesSpecialLan;
use common\models\afterSales\AfterSalesSpecialPackage;
use common\models\afterSales\AfterSalesSpecialUpdateLog;
use common\models\goods\LogisticsSalesInventory;
use common\models\order\OrderInvoiceInfo;
use common\models\goods\AllotOrder;
use common\models\goods\AllotStatus;
use common\models\goods\AllotGoods;
use common\models\invoice\OrderInvoiceLog;
use common\models\invoice\InvoiceStatus;
use common\models\wy\WyOrderInfo;

class LogisticsInterfaceController extends Controller2016
{
    
    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [];
    }
    
    /**
     * @Title:beforeAction
     * @Description:复写beforeAction
     * @return:boolean || redirect
     * @author:huaiyu.cui
     * @date:2015-12-2 下午4:54:05
     */
    public function beforeAction($action) {
        return true;
    }
    
    
    /**
     * 
     * @Title: actionMonitoringCode
     * @Description: 订单列表订单监控
     * @return: :|string
     * @author: yulong.wang
     * @date: 2015-12-1下午5:05:09
     */
    public function actionMonitoringCode(){
        $express_code = f_post('express_code','');
        $key = 'mdhl-'.$express_code;
        $waybill = f_c($key);
        if ($waybill !== false) {
            return $waybill;
        } else {
            $return = LogisticsInterfaceLog::getBillMonitor($express_code);
            $out = '';
            if (!empty($return)) {
                $out .= "<div><p style='margin-left:20px;'>运单号 : ".$express_code."</p><hr style='color:#F6F6F6;margin:5px 0'><div>";
                foreach ($return as $key => $value) {
                   if ($key == 0) {
                       $out .= "<div class='log_red'><p><span><em>*</em></span>".trim($value['info'])."</p><p><span>&nbsp;</span>".trim($value['time'])."</p></div>";
                   } else {
                       if ($key%2 == 1){
                           $out .= "<div class='log_gray'><p><span><em>*</em></span>".trim($value['info'])."</p><p><span>&nbsp;</span>".trim($value['time'])."</p></div>";
                       } else {
                           $out .= "<div><p><span><em>*</em></span>".trim($value['info'])."</p><p><span>&nbsp;</span>".trim($value['time'])."</p></div>";
                       }
                   }
               }
            } else {
                $out .= "<p>暂无物流信息</p>";
            }
               
            $key = 'mdhl-'.$express_code;
            if (f_c($key) === false) {
                f_c($key,$out,600);
            }
            return $out;
        }
    }

    
    
    /**
     * 
     * @Title: actionMonitoringCodeInfo
     * @Description: 订单详情页订单监控
     * @return: :|string
     * @author: yulong.wang
     * @date: 2015-12-4下午5:05:42
     */
    public function actionMonitoringCodeInfo(){
        $express_code = f_post('express_code','');
        $key = 'mdhd-'.$express_code;
        $waybill = f_c($key);
        if ($waybill !== false){
            return $waybill;
        } else {
            $return = LogisticsInterfaceLog::getBillMonitor($express_code);
            $out = '';
            if (!empty($return)) {
                foreach ($return as $key => $value) {
                    if ($key == 0) {
                        $out .= "<tr class='order_infotbl_o'>";
                    } else {
                         $out .= "<tr'>";
                    }
                    $out .= '<td>'.$value['time'].'</td>';
                    $out .= '<td>'.$value['info'].'</td>';
                    if ((isset($value['lng']) && $value['lng'] != 0) && (isset($value['lat']) && $value['lat'] != 0)) {
                        $out .= "<td><a class='view_bmap' href='/logistics-interface/bmap?lng=".$value['lng']."&lat=".$value['lat']."&speed=".$value['speed']."'>查看配送地图</a></td>";
                    } else {
                        $out .= '<td></td>';
                    }
                    $out .= '</tr>';
                 }
             }
             
              $key = 'mdhd-'.$express_code;
              if (f_c($key) === false) {
                  f_c($key,$out,600);
              }
              return $out;
         }
    }
    
    
    
    
    /**
     * 
     * @Title: actionBmap
     * @Description: 订单详情页运输车辆gps百度地图定位
     * @return: Ambigous <string, string>
     * @author: yulong.wang
     * @date: 2015-12-4下午5:06:08
     */
    public function actionBmap(){
        $lng = f_get('lng');
        $lat= f_get('lat');
        $speed = f_get('speed');
        return $this->render('bmap',[
            'lng'=>$lng,
            'lat'=>$lat,
            'speed' => $speed,
        ]);
    }
    
    
    
    

    /**
     *
     * @Title: actionSyncDepot
     * @Description: 物流系统订单与51平台同步仓库
     * @return: string
     * @author: yulong.wang
     * @date: 2015-12-4下午1:55:38
     */
    public function actionSyncDepot(){
        $out = [];
        $type = 21;    //物流同步仓库
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-depot';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = stripslashes($_POST['content']);
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
         
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $province = OtherRegion::getRegionId($value['province']);
                    $city = OtherRegion::getRegionId($value['city']);
                    $district = OtherRegion::getRegionId($value['district']);
                    $depot = Depot::findOne(['depot_code'=>$value['depotCode']]);
                    if (!$depot) {
                        $depotMod = new Depot();
                        $depotMod->name = $value['name'];
                        $depotMod->depot_code = $value['depotCode'];
                        $depotMod->chief = $value['chief'];
                        $depotMod->depot_nature = 1;
                        $depotMod->phone = $value['phone'];
                        $depotMod->province = $province;
                        $depotMod->city = $city;
                        $depotMod->district = $district;
                        $depotMod->address = $value['address'];
                        $depotMod->status = $value['status'];
                        $depotMod->remark = $value['remark'];
                        if ($depotMod->save()) {
                            $out['res'] = 0;
                            $out['msg'] = '添加仓库成功,同步仓库成功';
                        } else {
                            $error_arr[$i] = $value['depotCode'].'添加保存失败,同步仓库失败';
                            $out['res'] = 100;
                            $out['msg'] = '保存失败,同步仓库失败';
                        }  
                    } else {
                        $depot->name = $value['name'];
                        $depot->chief = $value['chief'];
                        $depot->depot_nature = 1;
                        $depot->phone = $value['phone'];
                        $depot->province = $province;
                        $depot->city = $city;
                        $depot->district = $district;
                        $depot->address = $value['address'];
                        $depot->status = $value['status'];
                        $depot->remark = $value['remark'];
                        if ($depot->save()) {
                            $out['res'] = 0;
                            $out['msg'] = '修改仓库成功,同步仓库成功';
                           
                        } else {
                            $error_arr[$i] = $value['depotCode'].'修改保存失败,同步仓库失败';
                            $out['res'] = 100;
                            $out['msg'] = '保存失败,同步仓库失败';
                        }
                    }
                    LogisticsInterfaceLog::createLogisticsLog($type,$value['depotCode'],$url,$param,'',$out['res'],$out['msg']);
                    $i++;
                }
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '仓库同步成功';
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                LogisticsInterfaceLog::createLogisticsLog($type,'',$url,$param,'',$out['res'],$out['msg']);
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            LogisticsInterfaceLog::createLogisticsLog($type,'',$url,'','',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
   /**
    * 
    * @Title: actionSyncInventory
    * @Description: 物流全量同步库存至51
    * @return: string
    * @author: yulong.wang
    * @date: 2015-12-2上午9:38:56
    */
    public function actionSyncInventory(){
        $out = [];
        $type = 12;         // 物流全量同步库存;
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-inventory';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
                
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                   $proCode = substr($value['proCode'],5);
                   $goodsInfo = SupplierGoods::findOne(['goods_code'=>$proCode]);
                   if ($goodsInfo) {
                        $supplier = Supplier::findOne($goodsInfo->supplier_id);
                        if ($supplier) {
                            if ($supplier->is_ys == 0) {
                                $oldNum = $goodsInfo->num_avai;
                                $subtractNum = 0;
                                $addNum = 0;
                                $num = $value['quantity'] - $oldNum;
                                if ($num >= 0) {
                                    $addNum = $num;
                                } else {
                                    $subtractNum = $num;
                                }
                                $goodsInfo->num_avai = $value['quantity'];
                                if ($goodsInfo->save()) {
                                    $operator = '物流全量同步库存';
                                    BuySaleBase::createGoodsBuySaleLog($goodsInfo->id, $addNum, $subtractNum, $goodsInfo->num_avai, 3, $operator);
                                    $res = 0;
                                    $mes = $value['proCode'] . '同步库存成功';
                                } else {
                                    $error_arr[$i] = $value['proCode'] . '同步库存失败';
                                    $res = 10;
                                    $mes = $value['proCode'] . "同步库存失败";
                                }
                            } else {
                                $error_arr[$i] = $value['proCode'].'是预售商品,库存同步失败';
                                $res = 10;
                                $mes = $value['proCode'].'是预售商品,同步库存失败';
                            }
                        } else {
                            $error_arr[$i] = $value['proCode'].'此商品供应商不存在,库存同步失败';
                            $res = 10;
                            $mes = "此商品供应商不存在,库存同步失败";
                        }
                    } else {
                        $error_arr[$i] = $value['proCode'].'在51平台上不存在,同步库存失败';
                        $res = 10;
                        $mes = $value['proCode'].'在51平台上不存在，同步库存失败';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$res,$mes);
                    $i++;
               }
               if (!empty($error_arr)) {
                   $out['res'] = 10;
                   $msg = '';
                   foreach ($error_arr as $key=>$val) {
                       $msg .= $val.";";
                   }
                   $out['msg'] = $msg;
               } else {
                   $out['res'] = 0;
                   $out['msg'] = '库存同步成功';
               }
           } else {
                $out['res'] = 100;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $proCode = substr($value['proCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$out['res'],$out['msg']);
                }
           }
        } else {
            $out['res'] = 200;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }



    /**
     *
     * @Title: actionSyncDisabledInventory
     * @Description: 物流全量同步不良品库存至51
     * @return: string
     * @author: yulong.wang
     * @date: 2016-07-11上午11:38:56
     */
    public function actionSyncDisabledInventory(){
        $out = [];
        $type = 43;         // 物流全量同步不良品库存至51;
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-disabled-inventory';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);

            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $proCode = substr($value['proCode'],5);
                    $goodsInfo = SupplierGoods::findOne(['goods_code'=>$proCode]);
                    if ($goodsInfo) {
                        $supplier = Supplier::findOne($goodsInfo->supplier_id);
                        if ($supplier) {
                            $goodsInfo->num_disabled = $value['quantity'];
                            if ($goodsInfo->save()) {
                                $res = 0;
                                $mes = $value['proCode'] . '同步不良品库存成功';
                            } else {
                                $error_arr[$i] = $value['proCode'] . '同步不良品库存失败';
                                $res = 10;
                                $mes = $value['proCode'] . "同步不良品库存失败";
                            }
                        } else {
                            $error_arr[$i] = $value['proCode'].'此商品供应商不存在,不良品库存同步失败';
                            $res = 10;
                            $mes = "此商品供应商不存在,不良品库存同步失败";
                        }
                    } else {
                        $error_arr[$i] = $value['proCode'].'在51平台上不存在,同步不良品库存失败';
                        $res = 10;
                        $mes = $value['proCode'].'在51平台上不存在，同步不良品库存失败';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$res,$mes);
                    $i++;
                }
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '不良品库存同步成功';
                }
            } else {
                $out['res'] = 100;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $proCode = substr($value['proCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$out['res'],$out['msg']);
                }
            }
        } else {
            $out['res'] = 200;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }


    /**
     *
     * @Title: actionSyncAddInventory
     * @Description: 库存增量同步
     * @return: string
     * @author: yulong.wang
     * @date: 2015-7-21上午10:44:55
     */
    public function actionSyncAddInventory(){
        ini_set('memory_limit','640M');
        $out = [];
        $type = 13;   //物流库存增量同步;
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-add-inventory';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
             
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
             
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
    
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                $ad_inv = LogisticsAddInv::find()->where(['serial_number'=>$content['serialNum']])->asArray()->one();
                if (empty($ad_inv)) {
                    foreach ($content['details'] as $value) {
                        $proCode = substr($value['proCode'],5);
                        $supplier_goods = SupplierGoods::findOne(['goods_code'=>$proCode]);
                        $addNum = 0;
                        $subtractNum = 0;
                        if ($supplier_goods) {
                            $supplier = Supplier::findOne($supplier_goods->supplier_id);
                            if ($supplier) {
                                if ($supplier->is_ys == 0) {
                                    if ($value['flag'] == 1) {
                                        $supplier_goods->num_avai = $supplier_goods->num_avai + $value['addQuantity'];
                                        $addNum = $value['addQuantity'];
                                    } else {
                                        $supplier_goods->num_avai = $supplier_goods->num_avai - $value['addQuantity'];
                                        $subtractNum = $value['addQuantity'];
                                    }
                                    $transaction = Yii::$app->db->beginTransaction() ;
									try{
                                        $r = LogisticsAddInv::createAddInv($content['serialNum'],$value['addQuantity'],$value['flag'],'');

	                                    if ($r && $supplier_goods->save()) {
	                                        $operator = '物流增量同步库存';
	                                        BuySaleBase::createGoodsBuySaleLog($supplier_goods->id,$addNum,$subtractNum,$supplier_goods->num_avai,3,$operator);
	                                        $res = 0;
	                                        $mes = $value['proCode'].'增量同步库存成功';
	                                    }else{
		                                    throw new \Exception('同步错误') ;
	                                    }
										$transaction->commit() ;
									}catch(\Exception $e){
										if ($e->getMessage() !='同步错误' && $e->getName() == 'Integrity constraint violation'){
	                                        $res = 0;
	                                        $mes = $value['proCode'].'增量同步库存重复';
										}else{
	                                        $error_arr[$i] = $value['proCode'].'增量同步库存失败';
	                                        $res = 10;
	                                        $mes = $value['proCode'].'增量同步库存失败';
										}
										$transaction->rollback() ;
									}
									
                                } else {
                                    $error_arr[$i] = $value['proCode'].'是预售商品,库存增量同步失败';
                                    $res = 10;
                                    $mes = $value['proCode'].'是预售商品,同步库存增量失败';
                                }
                            } else {
                                $error_arr[$i] = $value['proCode'].'此商品供应商不存在,库存增量同步失败';
                                $res = 10;
                                $mes = $value['proCode'].'此商品供应商不存在,库存增量同步失败';
                            }
                        } else {
                            $error_arr[$i] = $value['proCode'].'在51平台上不存在,同步库存增量失败';
                            $res = 10;
                            $mes = $value['proCode'].'在51平台上不存在，同步库存增量失败';
                        }
                        LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$res,$mes);
                        $i++;
                    }
                    if (!empty($error_arr)) {
                        $out['res'] = 10;
                        $msg = '';
                        foreach ($error_arr as $key=>$val) {
                            $msg .= $val.";";
                        }
                        $out['msg'] = $msg;
                    } else {
                        $out['res'] = 0;
                        $out['msg'] = '库存增量同步成功';
                    }
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '流水号'.$content['serialNum'].'已经处理';
                    LogisticsInterfaceLog::createLogisticsLog($type,$content['serialNum'],$url,$param,'',$out['res'],$out['msg']);
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $proCode = substr($value['proCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$out['res'],$out['msg']);
                }
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }



    /**
     *
     * @Title: actionSyncAddDisabledInventory
     * @Description: 不良品库存增量同步
     * @return: string
     * @author: yulong.wang
     * @date: 2016-7-11上午11:44:55
     */
    public function actionSyncAddDisabledInventory(){
        $out = [];
        $type = 44;   //物流增量同步不良品库存;
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-add-disabled-inventory';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);

            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];

            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);

            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                $ad_inv = LogisticsAddInv::find()->where(['serial_number'=>$content['serialNum']])->asArray()->one();
                if (empty($ad_inv)) {
                    foreach ($content['details'] as $value) {
                        $proCode = substr($value['proCode'],5);
                        $supplier_goods = SupplierGoods::findOne(['goods_code'=>$proCode]);
                        $addNum = 0;
                        $subtractNum = 0;
                        if ($supplier_goods) {
                            $supplier = Supplier::findOne($supplier_goods->supplier_id);
                            if ($supplier) {
                                if ($value['flag'] == 1) {
                                    $supplier_goods->num_disabled = $supplier_goods->num_disabled + $value['addQuantity'];
                                    $addNum = $value['addQuantity'];
                                } else {
                                    $supplier_goods->num_disabled = $supplier_goods->num_disabled - $value['addQuantity'];
                                    $subtractNum = $value['addQuantity'];
                                }
                                $transaction = Yii::$app->db->beginTransaction() ;
                                try {
                                    $r = LogisticsAddInv::createAddInv($content['serialNum'],$value['addQuantity'],$value['flag'],'物流增量同步不良品库存');
                                    if ($r && $supplier_goods->save()) {
                                        $res = 0;
                                        $mes = $value['proCode'].'物流增量同步不良品库存成功';
                                    } else {
                                        throw new \Exception('同步错误') ;
                                    }
                                    $transaction->commit() ;
                                } catch(\Exception $e) {
                                    if ($e->getMessage() !='同步错误' && $e->getName() == 'Integrity constraint violation'){
                                        $res = 0;
                                        $mes = $value['proCode'].'物流增量同步不良品库存重复';
                                    }else{
                                        $error_arr[$i] = $value['proCode'].'物流增量同步不良品库存失败';
                                        $res = 10;
                                        $mes = $value['proCode'].'物流增量同步不良品库存失败';
                                    }
                                    $transaction->rollback() ;
                                }
                            } else {
                                $error_arr[$i] = $value['proCode'].'此商品供应商不存在,物流增量同步不良品库存失败';
                                $res = 10;
                                $mes = $value['proCode'].'此商品供应商不存在,物流增量同步不良品库存失败';
                            }
                        } else {
                            $error_arr[$i] = $value['proCode'].'在51平台上不存在,物流增量同步不良品库存失败';
                            $res = 10;
                            $mes = $value['proCode'].'在51平台上不存在，物流增量同步不良品库存失败';
                        }
                        LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$res,$mes);
                        $i++;
                    }
                    if (!empty($error_arr)) {
                        $out['res'] = 10;
                        $msg = '';
                        foreach ($error_arr as $key=>$val) {
                            $msg .= $val.";";
                        }
                        $out['msg'] = $msg;
                    } else {
                        $out['res'] = 0;
                        $out['msg'] = '物流增量同步不良品库存成功';
                    }
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '流水号'.$content['serialNum'].'已经处理';
                    LogisticsInterfaceLog::createLogisticsLog($type,$content['serialNum'],$url,$param,'',$out['res'],$out['msg']);
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $proCode = substr($value['proCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($type,$proCode,$url,$param,'',$out['res'],$out['msg']);
                }
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    /**
     * 
     * @Title: actionSyncExpressCode
     * @Description: 物流与51dh订单运单号同步
     * @return: string
     * @PB:zeyong.shi
     * @author: yulong.wang
     * @date: 2015-5-6上午9:42:22
     */
    public function actionSyncExpressCode(){
        $out = [];
        $type = 14;    //物流同步运单号
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-express-code';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
             
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
             
            $param = json_encode($param);
             
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $order_code = substr($value['orderCode'],5);
                    
                    if (strpos($order_code,'fp') !== false){
                        $order_info = OrderInvoiceInfo::findOne(['invoice_code'=>$order_code]);
                    } else {
                        $order_info = OmsInfo::findOne(['order_code'=>$order_code]);
                    }

                    if ($order_info) {
                        $order_info->express_code = $value['expressCode'];
                        if ($order_info->save()){
                            $res = 0;
                            $mes = $value['orderCode'].'同步运单号成功';
                            OmsLog::creatLog($order_code,'面单号','物流同步电子面单','物流同步订单面单号');
                        } else {
                            $error_arr[$i] = $value['orderCode'].'同步运单号失败';
                            $res = 10;
                            $mes = $value['orderCode'].'同步运单号失败';
                        }
                    } else {
                        $error_arr[$i] = $value['orderCode'].'在51平台上不存在,同步运单号失败';
                        $res = 100;
                        $mes = $value['orderCode'].'在51平台上不存在,同步运单号失败';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$res,$mes);
                    $i++;
                }
                
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '运单号同步成功';
                }
    
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $order_code = substr($value['orderCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
                }
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
    
    /**
     * 
     * @Title: actionSyncOrderShipped
     * @Description: 物流订单发货与51平台同步订单状态同步
     * @return: string
     * @author: yulong.wang
     * @date: 2015-12-6下午2:17:02
     */
    public function actionSyncOrderShipped(){
        $out = [];
        $type = 15;     //物流同步已发货状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-order-shipped';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
                
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                if (!empty($content)) {
                    foreach ($content as $value) {
                        $order_code = substr($value['orderCode'],5);
                        if (strpos($order_code,'fp') !== false) {
                            $returnVal = OrderInvoiceInfo::changeFpOrderShipped($order_code);
                        } elseif (strpos($order_code,'wy') !== false) {
                            $returnVal = WyOrderInfo::changeWyOrderShipped($order_code);
                        } else {
                            $returnVal = OmsInfo::changeOrderShipped($order_code);
                        }
                        if ($returnVal['res'] != 0) {
                            $error_arr[$i] = $returnVal['msg'];
                        }
                        LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$returnVal['res'],$returnVal['msg']);
                        $i++;
                    }
                }

                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '订单已发货状态同步成功';
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                if (!empty($content)) {
                    foreach ($content as $value) {
                        $order_code = substr($value['orderCode'],5);
                        LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
                    }
                }
            }
        } else {
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',300,'参数缺少');
        }
        return json_encode($out);
    }
    
    
    
    
    
    
    /**
     * 
     * @Title: actionSyncCancelShipped
     * @Description: 同步取消已发货
     * @return: string
     * @author: yulong.wang
     * @date: 2015-10-27下午4:47:42
     */
    public function actionSyncCancelShipped(){
        $out = [];
        $type = 16;   //物流同步取消已发货状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-cancel-shipped';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
             
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $order_code = substr($value['orderCode'],5);
                    if (strpos($order_code,'wy') !== false) {
                        $returnVal = WyOrderInfo::changeWyOrderCancelShipped($order_code);
                    } else {
                        $returnVal = OmsInfo::changeOrderCancelShipped($order_code);
                    }
                    if ($returnVal['res'] != 0) {
                        $error_arr[$i] = $returnVal['msg'];
                    }
                    LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$returnVal['res'],$returnVal['msg']);
                    $i++;
                }
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '订单已发货状态同步成功';
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $order_code = substr($value['orderCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
                }
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
   
    
    
    
    
    
    
    
    /**
     * 
     * @Title: actionSyncOrderSign
     * @Description: 物流系统订单与51平台订单同步签收状态
     * @return: string
     * @author: yulong.wang
     * @date: 2015-12-6下午1:55:38
     */
    public function actionSyncOrderSign(){
        $out = [];
        $type = 17;  // 物流同步签收状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-order-sign';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
            $param = json_encode($param);
             
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
               $order_code = substr($content['orderCode'],5);
              
               if (strpos($order_code,'fp') !== false) {
                   $out = OrderInvoiceInfo::changeFpOrderSign($order_code, $content['sign']);
                   LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
				   
               } elseif (strpos($order_code,'wy') !== false) {
                   $desc = null;
                   if (isset($content['desc']) && $content['desc']!='') {
                       $desc = $content['desc'];
                   } 
                   $out = WyOrderInfo::changeWyOrderSign($order_code, $content['sign'], $content['msg'], $desc);
                   LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
				   
               } else {
                   $desc = null;
                   if (isset($content['desc']) && $content['desc']!='') {
                       $desc = $content['desc'];
                   }
                   
                   $denialCode = null;
                   if (isset($content['denialCode']) && $content['denialCode']!='') {
                       $denialCode = $content['denialCode'];
                   }
				   $rest = [] ;
                   $rest['snaps'] = isset($content['snaps']) ? $content['snaps'] : [] ;
                   $out = OmsInfo::changeOrderSign($order_code, $content['sign'], $content['msg'], $desc, $denialCode, $rest);
                   LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
				   
               } 
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $code_arr = explode('-',$content['orderCode']);
                LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    /**
     *
     * @Title: actionSyncRefuseImg
     * @Description: 物流系统订单同步拒签拍照
     * @return: string
     * @author: yulong.wang
     * @date: 2016-07-5下午5:55:38
     */
    public function actionSyncRefuseImg(){
        $out = [];
        $logType = 42;  // 物流同步拒签拍照
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-refuse-img';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {

            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
            $param = json_encode($param);

            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                if (strpos($content['orderCode'],'51SH-') !== false) {
                    if (strpos($content['orderCode'],'51SH-PJ') !== false) {
                        if (strpos($content['orderCode'],'51SH-PJSH') !== false) {  //售后派件系列
                            $order_code = substr($content['orderCode'], 7);
                            $type = 1;
                        } else {    //售后特殊派件
                            $order_code = substr($content['orderCode'], 5);
                            $type = 3;
                        }
                    } else {
                        if (strpos($content['orderCode'],'51SH-LJ') !== false) {  //售后特殊揽件
                            $type = 2;
                        } else {
                            $type = 0;   //售后揽件
                        }
                        $order_code = substr($content['orderCode'], 5);
                    }
                    $out = LogisticsImg::createAfterSalesImg($content['refuseImg'], $order_code, $type);
                    LogisticsInterfaceLog::createLogisticsLog($logType, $order_code, $url, $param, '', $out['res'], $out['msg']);
                } else {
                    if (strpos($content['orderCode'],'51DH-') !== false) {
                        $order_code = substr($content['orderCode'], 5);
                        $out = RefuseImg::createOrderRefuseImg($content['refuseImg'],$order_code);
                        LogisticsInterfaceLog::createLogisticsLog($logType, $order_code, $url, $param, '', $out['res'], $out['msg']);
                    }
                }
            } else {
                $out['res'] = 300;
                $out['msg'] = '密钥验证失败';
                $code_arr = explode('-',$content['orderCode']);
                LogisticsInterfaceLog::createLogisticsLog($logType,$code_arr[1],$url,$param,'',$out['res'],$out['msg']);
            }
        } else {
            $out['res'] = 400;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }

    
    
    
    /**
     *
     * @Title: actionSyncOrderCaseNumber
     * @Description: 物流同步实仓订单箱号
     * @return: string
     * @author: yulong.wang
     * @date: 2016-07-12下午5:55:38
     */
    public function actionSyncOrderCaseNumber(){
        $out = [];
        $type = 45;  // 物流同步实仓订单箱号
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-order-case-number';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
            $param = json_encode($param);

            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $order_code = substr($content['orderCode'],5);
                $orderInfo = OmsInfo::find()->where(['order_code'=>$order_code])->asArray()->one();
                if (!empty($orderInfo)) {
                    $connection = Yii::$app->db;//事务开始
                    $transaction=$connection->beginTransaction();
                    try {
                        foreach ($content['packageBoxNo'] as $key => $value) {
                            $caseNumber = OrderCaseNumber::findOne(['case_number'=>$value]);
                            if (!$caseNumber) {
                                $caseNumberMod = new OrderCaseNumber();
                                $caseNumberMod->order_code = $order_code;
                                $caseNumberMod->case_number = $value;
                                $caseNumberMod->sign = 1;
                                $caseNumberMod->is_sync = 1;
                                if (!$caseNumberMod->save()) {
                                    throw new \Exception('保存失败');
                                }
                            }
                        }
                        $transaction->commit();
                        $out['res'] = 0;
                        $out['msg'] = '同步成功';
                    } catch(\Exception $e) {
                        $transaction->rollBack();
                        $out['res'] = 100;
                        $out['msg'] = $e->getMessage();
                    }
                    LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
                } else {
                    $out['res'] = 200;
                    $out['msg'] = '订单号在51平台不存在';
                    LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
                }
            } else {
                $out['res'] = 300;
                $out['msg'] = '密钥验证失败';
                $code_arr = explode('-',$content['orderCode']);
                LogisticsInterfaceLog::createLogisticsLog($type,$code_arr[1],$url,$param,'',$out['res'],$out['msg']);
            }
        } else {
            $out['res'] = 400;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }


    /**
     *
     * @Title: actionCheckOrder
     * @Description: 物流同步订单号,51平台检测是否存在
     * @return: string
     * @author: yulong.wang
     * @date: 2016-07-12下午5:55:38
     */
    public function actionCheckOrder(){
        $out = [];
        $type = 47;  // 物流同步订单号,51平台检测是否存在
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-check-order';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
            $param = json_encode($param);

            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $order_code = substr($content['orderCode'],5);
                $orderInfo = OmsInfo::find()->where(['order_code'=>$order_code])->asArray()->one();
                if (!empty($orderInfo)) {
                    $out['res'] = 0;
                    $out['msg'] = '订单号在51平台存在';
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '订单号在51平台不存在';
                }
                LogisticsInterfaceLog::createLogisticsLog($type,$order_code,$url,$param,'',$out['res'],$out['msg']);
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $code_arr = explode('-',$content['orderCode']);
                LogisticsInterfaceLog::createLogisticsLog($type,$code_arr[1],$url,$param,'',$out['res'],$out['msg']);
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($type,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }







    /**
     * 
     * @Title: actionSyncWaybillCodeSh
     * @Description: 售后单同步面单号和揽件信息
     * @return: return_type
     * @author: yulong.wang
     * @date: 2015-8-18下午8:28:50
     */
    public function actionSyncWaybillCodeSh(){
        $out = [];
        $logType = 18;          //售后单同步面单号和揽件信息 
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-waybill-code-sh';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];   
             
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    if ($value['flag'] == 0) {
                        $sales_order = substr($value['orderCode'],5);
                    } else {
                        $sales_order = substr($value['orderCode'],7);
                    }
                    $sale_order_mod = AfterSalesOrder::findOne(['sales_order'=>$sales_order]);
            
                    if ($sale_order_mod) {
                        if ($value['flag'] == 0) {
                            if ($value['sign'] == 0) {
                                $sale_order_mod->status = 25;   //揽件失败
                            } elseif ($value['sign'] == 1){
                                $sale_order_mod->status = 20;   //揽件成功
                                $sale_order_mod->express_code = $value['waybillCode'];
                                $sale_order_mod->back_time = date('Y-m-d H:i:s',time());
                            } elseif ($value['sign'] == 2) {  //待揽件
                                $sale_order_mod->status = 18;
                                $sale_order_mod->express_code = $value['waybillCode'];
                            }
                        } else {  //派件系列
                            $sale_order_mod->to_express_code = $value['waybillCode'];
                        }
                            
                        if ($sale_order_mod->save()) {
                            $out['res'] = 0;
                            $out['msg'] = '物流售后单号为'.$value['orderCode'].'同步面单号和揽件信息成功';
                            $operator = '物流接口';
                            if (isset($value['desc'])) {
                                $msg = $value['desc'];
                                $typeName = '售后单同步面单号和揽件信息';
                            } else {
                                $msg = '物流同步派件面单号';
                                $typeName = '物流同步派件面单号';
                            }
                            AfterSalesLog::createShOrderLog($sales_order,$msg,$typeName,$operator);
                        } else {
                            $error_msg = $sale_order_mod->getErrors();
                            $error_arr[$i] = $error_msg[0];
                            $out['res'] = 100;
                            $out['msg'] = $error_msg[0];
                        }
                        
                    } else {
                        $error_arr[$i] = $value['orderCode'].'在51平台上不存在，同步面单号和揽件信息失败';
                        $out['res'] = 200;
                        $out['msg'] = '售后单号'.$sales_order.'在51平台上不存在';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,json_encode($out),$out['res'],$out['msg']);
                    $i++;              
                }
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '面单号和揽件信息同步成功';
                }
            } else {
                $out['res'] = 300;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    if ($value['flag'] == 0) {
                        $sales_order = substr($value['orderCode'],5);
                    } else {
                        $sales_order = substr($value['orderCode'],7);
                    }
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                }
            }  
        } else {
            $out['res'] = 400;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
    
  
    /**
     * 
     * @Title: actionSyncShippedSh
     * @Description: 物流系统售后订单同步发货状态              
     * @return: string
     * @author: yulong.wang
     * @date: 2015-12-6下午9:01:19
     */
    public function actionSyncShippedSh(){
        $out = [];
        $logType = 19;     //物流系统售后订单同步发货状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-shipped-sh';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
             
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $sales_order = substr($value['orderCode'],7);
                    $sales_order_info = AfterSalesOrder::findOne(['sales_order'=>$sales_order]);
                    if ($sales_order_info) {
                        if ($sales_order_info->if_kh == 1) {
                            $sales_order_info->end_processing_time = date('Y-m-d H:i:s',time());
                            $sales_order_info->save();
                        }
                        $status = 60;
                        $sales_order_save = AfterSalesOrder::ChangeOrderStatus($sales_order_info->id,$status,'物流同步售后订单已发货状态','物流接口');
                        if ($sales_order_save == 1){
                            $res = 0;
                            $mes = $value['orderCode'].'同步售后单已发货状态成功';
                            AfterSalesOrder::checkShOrder($sales_order);
                        } else {
                            $error_arr[$i] = $value['orderCode'].'同步售后单已发货状态失败';
    
                            $res = 10;
                            $mess = $value['orderCode'].'同步售后单已发货状态失败';
                        }
                    } else {
                        $error_arr[$i] = $value['orderCode'].'在51平台上不存在，同步售后单已发货状态失败';
                        $res = 100;
                        $mes = $value['orderCode'].'在51平台上不存在,同步售后单已发货状态失败';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$res,$mes);
                    $i++;
                }
               
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '售后单已发货状态同步成功';
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $sales_order = substr($value['orderCode'],7);
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                }
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
    
    
    /**
     * 
     * @Title: actionSyncSignedSh
     * @Description: 物流系统售后订单同步签收状态
     * @return: string
     * @author: yulong.wang
     * @date: 2015-8-18下午9:07:38
     */
    public function actionSyncSignedSh(){
        $out = [];
        $logType = 20;   //物流系统售后订单同步签收状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-signed-sh';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
                
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                if ($content['flag'] == 0) {
                    $sales_order = substr($content['orderCode'],5);
                } else {
                    $sales_order = substr($content['orderCode'],7);
                }
                
                $sale_order_info = AfterSalesOrder::findOne(['sales_order'=>$sales_order]);
                if ($sale_order_info) {
                   if ($content['flag'] == 0) {
                       $status = 30;
                   } else {
                       if ($content['sign'] == 0) {
                           if (isset($content['desc']) && $content['desc'] != '') {
                               $sale_order_info->refusal_msg = $content['desc'];
                           }
                       }
                       if ($sale_order_info->if_kh == 1) {
                           $sale_order_info->the_last_time = date('Y-m-d H:i:s',time());
                       }
                       $sale_order_info->save();
                       if ($content['sign'] == 0) {
                           $status = 75;   //拒签
                       } else {
                           $status = 70;   //已签收
                       }
                   }
                       
                   if (isset($content['desc']) && $content['desc'] != '') {
                       $desc = $content['desc'];
                   } else {
                       $desc = '物流同步售后订单签收状态';
                   }
                    
                   $afterSalesSave = AfterSalesOrder::ChangeOrderStatus($sale_order_info->id,$status,$desc,'物流接口');
                   if ($afterSalesSave == 1) {
                       $out['res'] = 0;
                       $out['msg'] = $content['orderCode'].'同步售后单签收状态成功';
                   } else {
                       $out['res'] = 100;
                       $out['msg'] = $content['orderCode'].'同步售后单签收状态,保存失败';
                   }
                   LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                } else {
                    $out['res'] = 200;
                    $out['msg'] = '售后单号'.$sales_order.'在51平台上不存在';
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                
                $sales_order = substr($content['orderCode'],7);
                LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
    /**
     * 
     * @Title: actionSyncWaybillCodeSpecialSh
     * @Description: 售后特殊揽件同步面单号和揽件信息
     * @return: return_type
     * @author: yulong.wang
     * @date: 2015-8-18下午8:28:50
     */
    public function actionSyncWaybillCodeSpecialSh(){
        $out = [];
        $logType = 31;          //售后特殊件同步面单号和揽件信息 
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-waybill-code-special-sh';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];   
             
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $sales_order = substr($value['orderCode'],5);
                    if ($value['flag'] == 0) {  //售后特殊揽件
                        $afterSalesSpecial = AfterSalesSpecialLan::findOne(['lan_code'=>$sales_order]);
                    } else {                    //售后特殊派件
                        $afterSalesSpecial = AfterSalesSpecialPackage::findOne(['package_code'=>$sales_order]);
                    }
            
                    if ($afterSalesSpecial) {
                        if ($value['flag'] == 0) {   //售后特殊揽件
                            if ($value['sign'] == 0) {
                                $afterSalesSpecial->status = 25;   //揽件失败
                            } elseif ($value['sign'] == 1){
                                $afterSalesSpecial->status = 20;   //揽件成功
                                $afterSalesSpecial->express_code = $value['waybillCode'];
                            } elseif ($value['sign'] == 2) {  //待揽件
                                $afterSalesSpecial->status = 18;
                                $afterSalesSpecial->express_code = $value['waybillCode'];
                            }
                        } 
                            
                        if ($afterSalesSpecial->save()) {
                            $out['res'] = 0;
                            $out['msg'] = '物流售后特殊揽件单号为'.$value['orderCode'].'同步面单号和揽件信息成功';
                           
                            $typeName = '售后特殊揽件单同步面单号和揽件信息';
                            if (isset($value['desc']) && $value['desc'] != '') {
                                $msg = $value['desc'];
                            } else {
                                $msg = '售后特殊揽件单同步面单号和揽件信息';
                            }
                           AfterSalesSpecialUpdateLog::createAfterSalesSpecialLog($sales_order,$typeName,'物流接口',$msg);
                        } else {
                            $error_arr[$i] = $value['orderCode'].'同步面单号和揽件信息失败';
                            $out['res'] = 100;
                            $out['msg'] = $value['orderCode'].'同步面单号和揽件信息,保存失败';
                        }
                        
                    } else {
                        $error_arr[$i] = $value['orderCode'].'在51平台上不存在，同步面单号和揽件信息失败';
                        $out['res'] = 200;
                        $out['msg'] = '售后特殊件单号'.$sales_order.'在51平台上不存在';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,json_encode($out),$out['res'],$out['msg']);
                    $i++;              
                }
               
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '面单号和揽件信息同步成功';
                }
            } else {
                $out['res'] = 300;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $sales_order = substr($value['orderCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                }
            }  
        } else {
            $out['res'] = 400;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
  
    /**
     *
     * @Title: actionSyncShippedSpecialSh
     * @Description: 同步售后特殊派件已发货状态
     * @return: string
     * @author: yulong.wang
     * @date: 2015-12-6下午9:01:19
     */
    public function actionSyncShippedSpecialSh(){
        $out = [];
        $logType = 32;     //同步售后特殊派件已发货状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-shipped-special-sh';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
             
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $sales_order = substr($value['orderCode'],5);
                    $special_sales_order =AfterSalesSpecialPackage::findOne(['package_code'=>$sales_order]);
                    if ($special_sales_order) {
                        $status = 30;  //售后特殊派件已发货状态
                        $sales_order_save = AfterSalesSpecialPackage::changeOrderStatus($special_sales_order->id,$status,'物流接口','物流同步售后特殊派件已发货状态');
                        if ($sales_order_save == 1){
                            $res = 0;
                            $mes = $value['orderCode'].'同步售后特殊派件已发货状态成功';
                        } else {
                            $error_arr[$i] = $value['orderCode'].'同步售后特殊派件已发货状态失败';
                            $res = 10;
                            $mess = $value['orderCode'].'同步售后特殊派件已发货状态失败';
                        }
                    } else {
                        $error_arr[$i] = $value['orderCode'].'在51平台上不存在，同步售后特殊派件已发货状态失败';
                        $res = 100;
                        $mes = $value['orderCode'].'在51平台上不存在,同步售后特殊派件已发货状态失败';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$res,$mes);
                    $i++;
                }
                 
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '售后特殊派件已发货状态同步成功';
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $sales_order = substr($value['orderCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                }
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
     
    
    
    
    /**
     *
     * @Title: actionSyncSignedSpecialSh
     * @Description: 物流系统同步售后特殊派件签收状态
     * @return: string
     * @author: yulong.wang
     * @date: 2015-8-18下午9:07:38
     */
    public function actionSyncSignedSpecialSh(){
        $out = [];
        $logType = 33;   //同步售后特殊派件签收状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-signed-special-sh';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
    
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $sales_order = substr($content['orderCode'],5);
                if ($content['flag'] == 0) {
                    $special_sale_order = AfterSalesSpecialLan::findOne(['lan_code'=>$sales_order]);
                    $status = 30;
                } else {
                    $special_sale_order = AfterSalesSpecialPackage::findOne(['package_code'=>$sales_order]);
                    if ($content['sign'] == 0) {
                        $status = 45;   //拒签
                    } else {
                        $status = 40;   //已签收
                    }
                }
             
                if ($special_sale_order) {
                    if (isset($content['desc']) && $content['desc'] != '') {
                        $desc = $content['desc'];
                    } else {
                        $desc = '物流系统同步售后特殊派件签收状态';
                    }
                    if ($content['flag'] == 0) {
                        $specialAfterSalesSave = AfterSalesSpecialLan::ChangeOrderStatus($special_sale_order->id,$status,'物流接口',$desc);
                    } else {
                        $specialAfterSalesSave = AfterSalesSpecialPackage::ChangeOrderStatus($special_sale_order->id,$status,'物流接口',$desc);
                    }
                    
                    if ($specialAfterSalesSave == 1) {
                        $out['res'] = 0;
                        $out['msg'] = $content['orderCode'].'同步售后特殊派件签收状态成功';
                    } else {
                        $out['res'] = 100;
                        $out['msg'] = $content['orderCode'].'同步售后特殊派件签收状态,保存失败';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                } else {
                    $out['res'] = 200;
                    $out['msg'] = '售后特殊派件单号'.$sales_order.'在51平台上不存在';
                    LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
    
                $sales_order = substr($content['orderCode'],7);
                LogisticsInterfaceLog::createLogisticsLog($logType,$sales_order,$url,$param,'',$out['res'],$out['msg']);
    
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
    /**
     *
     * @Title: actionSyncSignedSpecialSh
     * @Description: 物流系统同步进销存
     * @return: string
     * @author: yulong.wang
     * @date: 2015-8-18下午9:07:38
     */
    public function actionPurchaseSaleStock(){
        $out = [];
        $logType = 34;   //同步进销存
        $url = 'http://www.51dh.com.cn/logistics-interface/purchase-sale-stock';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
    
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $error_arr = [];
                $i = 0;
                foreach ($content as $value) {
                    $prodCode = substr($value['prodCode'],5);
                    $addSalesInv = LogisticsSalesInventory::createLogisticsSalesInv($prodCode,$value['goodBeginInv'],$value['rejectsBeginInv'],$value['addInv'],$value['backInv'],$value['saleInv'],$value['returnInv'],$value['goodEndInv'],$value['rejectsEndInv']);
                    if ($addSalesInv == 1) {
                        $out['res'] = 0;
                        $out['msg'] = '货号'.$prodCode.'同步进销存成功';
                    } elseif ($addSalesInv == 0) {
                        $error_arr[$i] = '货号'.$prodCode.'同步进销存失败(数据表保存失败)';
                        $out['res'] = 100;
                        $out['msg'] = '货号'.$prodCode.'同步进销存失败(数据表保存失败)';
                    } elseif ($addSalesInv == -1) {
                        $error_arr[$i] = '货号'.$prodCode.'在51平台不存在';
                        $out['res'] = 100;
                        $out['msg'] = '货号'.$prodCode.'在51平台不存在';
                    }
                    LogisticsInterfaceLog::createLogisticsLog($logType,$prodCode,$url,$param,json_encode($out),$out['res'],$out['msg']);
                    $i++;              
                }
               
                if (!empty($error_arr)) {
                    $out['res'] = 10;
                    $msg = '';
                    foreach ($error_arr as $key=>$val) {
                        $msg .= $val.";";
                    }
                    $out['msg'] = $msg;
                } else {
                    $out['res'] = 0;
                    $out['msg'] = '同步进销存成功';
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                foreach ($content as $value) {
                    $prodCode = substr($value['prodCode'],5);
                    LogisticsInterfaceLog::createLogisticsLog($logType,$prodCode,$url,$param,'',$out['res'],$out['msg']);
                }
            }  
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType,$code,$url,$param,'',$out['res'],$out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    /**
     * 
     * @Title: actionSyncCalloutStatus
     * @Description: 物流同步调出作业状态
     * @return: string
     * @author: yulong.wang
     * @date: 2016-4-1下午4:36:02
     */
    public function actionSyncCalloutStatus() {
        $out = [];
        $logType = 37;   //物流同步调出作业状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-callout-status';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
        
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $allot_code = substr($content['allotCode'],8);
                $allotOrderMod = AllotOrder::findOne(['allot_code'=>$allot_code]);
                if ($allotOrderMod) {
                    if ($content['status'] == 0) {  
                        $status = 45;  //调拨超时
                    } else {
                        $status = 30;   //调出中
                    }
                    $statusName = AllotStatus::getAllotStatusName($status);
                    $mark = '物流同步调出作业状态,同步'.$statusName.'状态';
                    $save = AllotOrder::changeAllotStatus($allotOrderMod->id, $status, $mark, '物流系统');
                    if ($save== 1) {
                        $out['res'] = 0;
                        $out['msg'] = '调拨单号'.$allot_code.'同步'.$statusName.'成功';
                    } else {
                        $out['res'] = 100;
                        $out['msg'] = '调拨单号'.$allot_code.'同步'.$statusName.'失败';
                    }
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '调拨单号'.$allot_code.'在51平台不存在';
                }
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $allot_code = substr($content['allotCode'],8);
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType, $code, $url, $param, '', $out['res'], $out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
    
    /**
     *
     * @Title: actionSyncCalloutMess
     * @Description: 物流同步调出单信息
     * @return: string
     * @author: yulong.wang
     * @date: 2016-4-1下午4:36:02
     */
    public function actionSyncCalloutMess() {
        $out = [];
        $logType = 38;   //物流同步调出单信息
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-callout-mess';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
    
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $allot_code = substr($content['allotCode'],8);
                $allotOrderMod = AllotOrder::findOne(['allot_code'=>$allot_code]);
                if ($allotOrderMod) {
                    if ($content['status'] == 1) {
                        $status = 35;  //调出完成
                    } 
                    $statusName = AllotStatus::getAllotStatusName($status);
                    $mark = '物流同步调出单信息,同步'.$statusName.'状态';
                    
                    $connection = Yii::$app->db;//事务开始
                    $transaction=$connection->beginTransaction();
                    try {
                        $save = AllotOrder::changeAllotStatus($allotOrderMod->id, $status, $mark, '物流系统');
                        if ($save== 1) {
                            foreach ($content['detail'] as $val) {
                                $proCode = substr($val['proCode'],5);
                                $allotGoodsMod = AllotGoods::findOne(['allot_id'=>$allotOrderMod->id,'callout_goods_code'=>$proCode]);
                                if ($allotGoodsMod) {
                                    if (isset($val['actualCalloutNum']) && $val['actualCalloutNum'] >= 0) {
                                        $allotGoodsMod->actual_callout_num = $val['actualCalloutNum'];
                                        if (!$allotGoodsMod->save()) {
                                            throw new \Exception('修改调出商品实际数量失败');
                                        }
                                    } 
                                } else {
                                    throw new \Exception('调出商品不正确');
                                }
                            }
                        } else {
                            throw new \Exception('修改调拨单状态失败');
                        }
                        $transaction->commit();
                        $out['res'] = 0;
                        $out['msg'] = '成功';
                    } catch(\Exception $e) {
                        $transaction->rollBack();
                        $out['res'] = 100;
                        $out['msg'] = $e->getMessage();
                    }
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '调拨单号'.$allot_code.'在51平台不存在';
                }
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $allot_code = substr($content['allotCode'],8);
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType, $code, $url, $param, '', $out['res'], $out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
    
    /**
     *
     * @Title: actionSyncReceiveStatus
     * @Description: 物流同步调入作业状态
     * @return: string
     * @author: yulong.wang
     * @date: 2016-4-1下午4:36:02
     */
    public function actionSyncReceiveStatus() {
        $out = [];
        $logType = 39;   //物流同步调入作业状态
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-receive-status';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
    
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $allot_code = substr($content['allotCode'],8);
                $allotOrderMod = AllotOrder::findOne(['allot_code'=>$allot_code]);
                if ($allotOrderMod) {
                    if ($content['status'] == 1) {
                        $status = 40;  //入库中
                    }
                    $statusName = AllotStatus::getAllotStatusName($status);
                    $mark = '物流同步调入作业状态,同步'.$statusName.'状态';
    
                    $save = AllotOrder::changeAllotStatus($allotOrderMod->id, $status, $mark, '物流系统');
                    if ($save== 1) {
                        $out['res'] = 0;
                        $out['msg'] = '同步调拨单号'.$allot_code.'调入状态成功';
                    } else {
                        $out['res'] = 100;
                        $out['msg'] = '修改调拨单状态失败';
                    }
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '调拨单号'.$allot_code.'在51平台不存在';
                }
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $allot_code = substr($content['allotCode'],8);
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType, $code, $url, $param, '', $out['res'], $out['msg']);
        }
        return json_encode($out);
    }
    
    
    /**
     *
     * @Title: actionSyncReceiveMess
     * @Description: 物流同步调入作业信息
     * @return: string
     * @author: yulong.wang
     * @date: 2016-4-1下午4:36:02
     */
    public function actionSyncReceiveMess() {
        $out = [];
        $logType = 40;   //物流同步调入作业信息
        $url = 'http://www.51dh.com.cn/logistics-interface/sync-receive-mess';
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $js_content = $_POST['content'];
            $time = $_POST['time'];
            $token = $_POST['token'];
            $date_time = strtotime($time);
            $content = json_decode($js_content,true);
            $param = [
                'time' => $time,
                'token' => $token,
                'contents' => $js_content,
            ];
    
            $param = json_encode($param);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $allot_code = substr($content['allotCode'],8);
                $allotOrderMod = AllotOrder::findOne(['allot_code'=>$allot_code]);
                if ($allotOrderMod) {
                    if ($content['status'] == 1) {
                        $status = 50;  //调拨成功
                    } else {
                        $status = 55;  //调拨失败
                    }
                    $statusName = AllotStatus::getAllotStatusName($status);
                    $mark = '物流同步调入作业信息,同步'.$statusName.'状态';
                    
                    $connection = Yii::$app->db;//事务开始
                    $transaction=$connection->beginTransaction();
                    try {
                        $save = AllotOrder::changeAllotStatus($allotOrderMod->id, $status, $mark, '物流系统');
                        if ($save== 1) {
                            foreach ($content['detail'] as $val) {
                                $proCode = substr($val['proCode'],5);
                                $allotGoodsMod = AllotGoods::findOne(['allot_id'=>$allotOrderMod->id,'receive_goods_code'=>$proCode]);
                                if ($allotGoodsMod) {
                                    if (isset($val['actualReceiveNum']) && $val['actualReceiveNum'] >= 0) {
                                        $allotGoodsMod->actual_receive_num = $val['actualReceiveNum'];
                                        if (!$allotGoodsMod->save()) {
                                            throw new \Exception('修改调入商品实际数量失败');
                                        }
                                    } 
                                } else {
                                    throw new \Exception('调入商品不正确');
                                }
                            }
                        } else {
                            throw new \Exception('修改调拨单状态失败');
                        }
                        $transaction->commit();
                        $out['res'] = 0;
                        $out['msg'] = '成功';
                    } catch(\Exception $e) {
                        $transaction->rollBack();
                        $out['res'] = 100;
                        $out['msg'] = $e->getMessage();
                    }
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '调拨单号'.$allot_code.'在51平台不存在';
                }
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $allot_code = substr($content['allotCode'],8);
                LogisticsInterfaceLog::createLogisticsLog($logType, $allot_code, $url, $param, json_encode($out), $out['res'], $out['msg']);
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $code = '';
            $param = '';
            LogisticsInterfaceLog::createLogisticsLog($logType, $code, $url, $param, '', $out['res'], $out['msg']);
        }
        return json_encode($out);
    }
    
    
    
    
   
    

    /**
     *
     * @Title: actionPaySuccess
     * @Description:  在线支付后台回调地址
     * @return: json
     * @author: wangmingcha
     * @date: 2015-12-8下午2:05:55
     */
    public function actionAsynPayBack(){
        $param = Json::decode($_POST['parameters']);
        $onlinePayment = new OnlinePayment();
        $rs =  $onlinePayment->asyBackHandler($param);
        echo $rs;
        Yii::$app->end();
    }
    

    /**
     * @Title: actionGetUserEasyLoanOrder
     * @Description: 根据login_name返回该用户使用51云贷支付方式订单信息
     * @return: return_type
     * @author: wei.xie
     * @date: 2015年10月15日09:49:46
     */
    public function actionGetUserEasyloanorder(){
        header("Content-type:text/html;charset=utf-8");
        $out = [];
        if(isset($_POST['time']) && isset($_POST['token']) && isset($_POST['content'])){
            $time = $_POST['time'];
            $token = $_POST['token'];
            $content = json_decode(stripslashes($_POST['content']),true);
            $login_name = $content['login_name'];
            $token_str = md5($time)."5bbfd68e674314de6775c6efb3ee9d02";
            $token_strs = md5($token_str);
            if($token_strs == $token){
                $user_info = UserMember::find()->where(['login_account'=>$login_name])->one();
                $user_id = $user_info->id;
                //查出所有开通51云贷站点所对应的pay_way
                $pay_way_arr = 4;
                if($user_info){
                    //获取该用户使用51云贷支付方式的订单---(订单号,订单详情路径,支付金额,订单时间,订单状态)
                    $omsInfoQuery = OmsInfo::find()->select('id,order_code,order_price,order_time,order_status')->where(['user_id'=>$user_id])->andWhere(['in','pay_way',$pay_way_arr])->orderby('order_time desc');
                    if(isset($content['begin_time']) && preg_match('/^[0-9]{14}$/',$content['begin_time'])){
                        $omsInfoQuery->andWhere(['>=','order_time',date('Y-m-d H:i:s',strtotime($content['begin_time']))]);
                    }

                    if(isset($content['end_time']) && preg_match('/^[0-9]{14}$/',$content['end_time'])){
                        $omsInfoQuery->andWhere(['<=','order_time',date('Y-m-d H:i:s',strtotime($content['end_time']))]);
                    }

                    $cloneOmsInfoQuery = clone $omsInfoQuery;
                    $order_info_one = $omsInfoQuery->one();
                    if(!$order_info_one){
                        $out['res'] = 5;
                        $out['msg'] = '该用户暂无51云贷支付方式订单';
                        $out['data'] = [];
                    }else{
                        $page_no = 1;
                        if(!empty($content['page_no'])){
                            $page_no = $content['page_no'];
                        }
                        $page_size = 10;
                        if(!empty($content['page_size'])){
                            $page_size = $content['page_size'];
                        }
                        $countQuery = clone $cloneOmsInfoQuery;
                        $total_results = $countQuery->count();
                        $total_pages = ceil($total_results/$page_size);
                        $order_info = $cloneOmsInfoQuery->offset(($page_no-1)*$page_size)->limit($page_size)->asArray()->all();
                        foreach($order_info as $key=>$value){
                            $order_info[$key]['url'] = "http://www.51dh.com.cn/ucenter/order?id=".$value['id'];
                            $order_info[$key]['order_time'] = $value["order_time"];
                            $order_info[$key]['order_status'] = OmsStatus::getStatusName($value['order_status']);
                        }
                        $out['res'] = 0;
                        $out['user_id'] = $user_id;
                        $out['login_name'] = $login_name;
                        $out['msg'] = '获取订单信息成功';
                        $out['total_pages'] = $total_pages;
                        $out['total_results'] = $total_results;
                        $out['data'] = $order_info;
                    }
                }else{
                    $out['res'] = 4;
                    $out['msg'] = '该用户不存在';
                    $out['data'] = [];
                }
            }else{
                $out['res'] = 3;
                $out['msg'] = '密钥验证失败';
                $out['data'] = [];
            }
        }else{
            $out['res'] = 2;
            $out['msg'] = '参数缺少';
            $out['data'] = [];
        }
        return json_encode($out);
    }


    /**
     *
     * @Title: actionGetUserOrder
     * @Description: 根据user_id返回该用户近三月的订单
     * @return: return_type
     * @author: jian.zhang.
     *
     * @date: 2015-10-8下午13:11:22
     */
    public function actionGetUserOrder() {
        $out = [];
        if (isset($_POST['time']) && isset($_POST['token']) && isset($_POST['content'])) {
            $time = $_POST['time'];
            $token = $_POST['token'];
            $content = json_decode(stripslashes($_POST['content']),true);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $sql = 'select id from user_member where login_account = "'.$content['login_account'].'"';
                $user_mod = UserMember::findBySql($sql)->asArray()->one();
                if ($user_mod) {
                    $time=time();
                    $senvenMonth=date('Y-m-01 00:00:00',strtotime(date('Y',$time).'-'.date('m',$time).'-01')-3600*24*30*6);
                    $senvenday=date('Y-m-d 23:59:59',strtotime("$senvenMonth +1 month -1 day"));
                    $sixMonth=date('Y-m-01 00:00:00',strtotime(date('Y',$time).'-'.date('m',$time).'-01')-3600*24*30*5);
                    $sixday=date('Y-m-d 23:59:59',strtotime("$sixMonth +1 month -1 day"));
                    $fiveMonth=date('Y-m-01 00:00:00',strtotime(date('Y',$time).'-'.date('m',$time).'-01')-3600*24*30*4);
                    $fiveday=date('Y-m-d 23:59:59',strtotime("$fiveMonth +1 month -1 day"));
                    $fourMonth=date('Y-m-01 00:00:00',strtotime(date('Y',$time).'-'.date('m',$time).'-01')-3600*24*30*3);
                    $fourday=date('Y-m-d 23:59:59',strtotime("$fourMonth +1 month -1 day"));
                    $threeMonth=date('Y-m-01 00:00:00',strtotime(date('Y',$time).'-'.date('m',$time).'-01')-3600*24*30*2);
                    $threeday=date('Y-m-d 23:59:59',strtotime("$threeMonth +1 month -1 day"));
                    $twoMonth=date('Y-m-01 00:00:00',strtotime(date('Y',$time).'-'.date('m',$time).'-01')-3600*12*30);
                    $twoday=date('Y-m-d 23:59:59',strtotime("$twoMonth +1 month -1 day"));
                    $oneMonth=date('Y-m-01 00:00:00',strtotime(date('Y',$time).'-'.date('m',$time).'-01'));
                    $oneday=date('Y-m-d 23:59:59',strtotime("$oneMonth +1 month -1 day"));
                    $order_status = [45,70,135,160,240,675,655];//交易成功订单状态
                    if (date('m',$time)>=6) {
                        $six_month_order_price = OmsInfo::find()->select('order_price')->where(['user_id'=>$user_mod['id'],'order_status'=>$order_status])->andWhere(['between','order_time',$sixMonth,$sixday])->sum('order_price');
                        $senven_month_order_price = OmsInfo::find()->select('order_price')->where(['user_id'=>$user_mod['id'],'order_status'=>$order_status])->andWhere(['between','order_time',$senvenMonth,$senvenday])->sum('order_price');
                    } else{
                        $old_order_status = [30,90,155];//交易成功订单状态
                        $query1 = (new Query())->select('order_price')->from('{{dh_oms_order_info}}')->where(['user_id'=>$user_mod['id'],'order_status'=>$old_order_status])->andWhere(['between','order_time',strtotime($sixMonth),strtotime($sixday)]);
                        $six_month_order_price = $query1->sum('order_price',Yii::$app->get('db2'));
                        $query2 = (new Query())->select('order_price')->from('{{dh_oms_order_info}}')->where(['user_id'=>$user_mod['id'],'order_status'=>$old_order_status])->andWhere(['between','order_time',strtotime($senvenMonth),strtotime($senvenday)]);
                        $senven_month_order_price = $query2->sum('order_price',Yii::$app->get('db2'));
                    }
                    $one_month_order_price = OmsInfo::find()->select('order_price')->where(['user_id'=>$user_mod['id'],'order_status'=>$order_status])->andWhere(['between','order_time',$oneMonth,$oneday])->sum('order_price');
                    $two_month_order_price = OmsInfo::find()->select('order_price')->where(['user_id'=>$user_mod['id'],'order_status'=>$order_status])->andWhere(['between','order_time',$twoMonth,$twoday])->sum('order_price');
                    $three_month_order_price = OmsInfo::find()->select('order_price')->where(['user_id'=>$user_mod['id'],'order_status'=>$order_status])->andWhere(['between','order_time',$threeMonth,$threeday])->sum('order_price');
                    $four_month_order_price = OmsInfo::find()->select('order_price')->where(['user_id'=>$user_mod['id'],'order_status'=>$order_status])->andWhere(['between','order_time',$fourMonth,$fourday])->sum('order_price');
                    $five_month_order_price = OmsInfo::find()->select('order_price')->where(['user_id'=>$user_mod['id'],'order_status'=>$order_status])->andWhere(['between','order_time',$fiveMonth,$fiveday])->sum('order_price');
                    
                    if ($one_month_order_price == '') {
                        $one_month_order_price = 0;
                    }
                    if ($two_month_order_price == '') {
                        $two_month_order_price = 0;
                    }
                    if ($three_month_order_price == '') {
                        $three_month_order_price = 0;
                    }
                    if ($four_month_order_price == '') {
                        $four_month_order_price = 0;
                    }
                    if ($five_month_order_price == '') {
                        $five_month_order_price = 0;
                    }
                    if ($six_month_order_price == '') {
                        $six_month_order_price = 0;
                    }
                    if ($senven_month_order_price == '') {
                        $senven_month_order_price = 0;
                    }
                    if ($content['is_now'] == 1) {
                        if ($content['month_long'] == 6) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $six_month_order_price.','.$five_month_order_price.','.$four_month_order_price.','.$three_month_order_price.','.$two_month_order_price.','.$one_month_order_price;
                        } elseif ($content['month_long'] == 5) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $five_month_order_price.','.$four_month_order_price.','.$three_month_order_price.','.$two_month_order_price.','.$one_month_order_price;
                        } elseif ($content['month_long'] == 4) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $four_month_order_price.','.$three_month_order_price.','.$two_month_order_price.','.$one_month_order_price;
                        } elseif ($content['month_long'] == 3) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $three_month_order_price.','.$two_month_order_price.','.$one_month_order_price;
                        } elseif ($content['month_long'] == 2) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $two_month_order_price.','.$one_month_order_price;
                        } elseif ($content['month_long'] == 1) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $one_month_order_price;
                        }
                    } else {
                        if ($content['month_long'] == 6) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $senven_month_order_price.','.$six_month_order_price.','.$five_month_order_price.','.$four_month_order_price.','.$three_month_order_price.','.$two_month_order_price;
                        } elseif ($content['month_long'] == 5) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $six_month_order_price.','.$five_month_order_price.','.$four_month_order_price.','.$three_month_order_price.','.$two_month_order_price;
                        } elseif ($content['month_long'] == 4) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $five_month_order_price.','.$four_month_order_price.','.$three_month_order_price.','.$two_month_order_price;
                        } elseif ($content['month_long'] == 3) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $four_month_order_price.','.$three_month_order_price.','.$two_month_order_price;
                        } elseif ($content['month_long'] == 2) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $three_month_order_price.','.$two_month_order_price;
                        } elseif ($content['month_long'] == 1) {
                            $out['res'] = 0;
                            $out['msg'] = '获取订单信息成功';
                            $out['data'] = $two_month_order_price;
                        }
                    }
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '该用户在51平台上不存在';
                    $out['data'] = [];
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $out['data'] = [];
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $out['data'] = [];
        }
        return json_encode($out);
    }

     /**
     *
     * @Title: actionGetBaseGoodsLowestPrice
     * @Description: 获取基础商品最低价
     * @return: return_type
     * @author: jian.zhang
     * @date: 2015-8-25下午14:48:22
     */
    public function actionGetBaseGoodsLowestPrice() {
        $out = [];
        if (isset($_POST['time']) && isset($_POST['token']) && isset($_POST['content'])) {
            $time = $_POST['time'];
            $token = $_POST['token'];
            $content = json_decode(stripslashes($_POST['content']),true);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs === $token) {
                $goods = (new Query)->select('t1.price,t1.supplier_id,t1.id')->from('{{%goods_supplier_goods}} as t1')->leftJoin('{{%goods_depot_city}} as t2','t2.depot_id = t1.depot_id')->where(['t2.city'=>$content['cityId'],'t1.base_id'=>$content['baseGoodsId'],'t1.status'=>1,'t1.is_deleted'=>0,'t1.enable'=>1,'t1.color_id'=>$content['color_id']])->orderBy('t1.price asc')->limit(1)->all();              
                $data = [];
                if (!empty($goods)) {
                  foreach ($goods as $key => $value) {
                    $out['res'] = 0;
                    $out['msg'] = '获取最低价格成功';
                    $data['supplier_id'] = $value['supplier_id'];
                    $data['price'] = $value['price'];
                    $data['goods_id'] = $value['id'];
                    $out['data'] = $data;
                  }
                } else {
                  $out['res'] = 100;
                  $out['msg'] = '该商品不存在';
                  $out['data'] = '';
                }
            } else {
              $out['res'] = 200;
              $out['msg'] = '密钥验证失败';
              $out['data'] = '';
            }
        } else {
          $out['res'] = 300;
          $out['msg'] = '参数缺少';
          $out['data'] = '';
        }
        return json_encode($out);
    }

    /**
     *
     * @Title: actionSopPunishRuleCreate
     * @Description: 物流同步SOP处罚规则接口（新增）
     * @return: return_type
     * @author: wangmingcha
     * @date: 2016-3-15下午14:48:22
     */
    public function actionSopPunishRuleCreate(){
        $params = $_POST;
        if(isset($params['token']) && isset($params['time']) && isset($params['content'])){
            $token = $params['token'];
            $validToken = md5(md5($params['time'])."5bbfd68e674314de6775c6efb3ee9d02");
            if($token==$validToken){
                $retData = SopPunishReasonClassify::createPunishReason(Json::decode($params['content']));
            }else{
                $retData['res'] = -5;
                $retData['msg'] = "token校验失败";
            }
        }else{
            $retData['res'] = -6;
            $retData['msg'] = "请求参数缺失";
        }

        echo Json::encode($retData);
        Yii::$app->end();
    }

    /**
     *
     * @Title: actionSopPunishRuleUpdate
     * @Description: 物流同步SOP处罚规则接口（修改）
     * @return: return_type
     * @author: wangmingcha
     * @date: 2016-3-15下午14:48:22
     */
    public function actionSopPunishRuleUpdate(){
        $params = $_POST;
        if(isset($params['token']) && isset($params['time']) && isset($params['content'])){
            $token = $params['token'];
            $validToken = md5(md5($params['time'])."5bbfd68e674314de6775c6efb3ee9d02");
            if($token==$validToken){
                $retData = SopPunishReasonClassify::updatePunishReason(Json::decode($params['content']));
            }else{
                $retData['res'] = -5;
                $retData['msg'] = "token校验失败";
            }
        }else{
            $retData['res'] = -6;
            $retData['msg'] = "请求参数缺失";
        }

        echo Json::encode($retData);
        Yii::$app->end();
    }
  
    public function actionClientStart(){
        GwPushInterface::swooleClientAction();
    }
}
?>
