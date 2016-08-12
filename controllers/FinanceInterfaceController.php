<?php
namespace frontend\controllers;
use common\models\user\UserMember;
use common\models\user\UserShopsSupplier;
use frontend\components\Controller2016;
use Yii;
use common\models\user\Supplier;
use common\models\order\OmsStatus;
use common\models\order\OmsInfo ;
use yii\db\Query ;

class FinanceInterfaceController extends Controller2016
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
    * @Title: actionGetOrderPrice
    * @Description: 根据供应商名称得到某段时间内的订单总额 
    * @return: return_type
    * @author: weijuan.huang
    * @date: 2015-3-19 下午5:33:04
    */    
    public function actionCreditOrderPool() {
        if($_REQUEST['token']){
            //验证
            $time = date('Ymd',time());
            $var = '51DHDKDH&UFO*S163#';
            $token = md5($var.$time);
            if($token == $_REQUEST['token']){ 
                //根据登录名获取供应商ID
                if($_REQUEST['parameters']){        
                    $data = json_decode($_REQUEST['parameters'],true);
                    $begin = date('Y-m-d H:i:s',strtotime($data['begin_date']));
                    $end = date('Y-m-d H:i:s',strtotime($data['end_date']));
                    $supplier_id = Supplier::findOne(['login_account'=>$data['suppliername']]);
                    //某段时间内该供应商订单总额
                    $sql = "select sum(a.order_price) as price ,sum(b.goods_num) as num from order_oms_info a INNER JOIN order_oms_goods b on a.supplier_id = $supplier_id->id and a.order_code = b.order_code and order_time >'$begin' and order_time<'$end' and a.order_status not in (15,20,95,105,185,195,170,80)";
                    $result = \Yii::$app->db->createCommand($sql);
                    $res = $result->queryAll();
                    if(empty($res[0]['price'])){
                        return '0&0';
                    }else{
                        return $res[0]['price'].'&'.$res[0]['num'];
                    }
                }else{
                    return '0&0';
                }
            }
        }
        
    }
    
    /**
    * 
    * @Title: actionGetGoodsPrice
    * @Description: 根据供应商名称得到该供应商所有商品总价 
    * @return: return_type
    * @author: weijuan.huang
    * @date: 2015-3-20 10:01:35
    */  
    public function actionCreditInventoryPool() {
        if($_REQUEST['token']){
            //验证
            $time = date('Ymd',time());
            $var = '51DHDKDH&UFO*S163#';
            $token = md5($var.$time);
            if($token == $_REQUEST['token']){ 
                //根据登录名获取供应商ID
                if($_REQUEST['parameters']){ 
                    $data = json_decode($_REQUEST['parameters'],true);
                    $supplier_id = Supplier::findOne(['login_account'=>$data['suppliername']]);
                    //根据供应商ID得到该供应商所有商品总价
                    $sql = 'select sum(price*num_avai) as price ,sum(num_avai) as num from goods_supplier_goods where supplier_id = '.$supplier_id->id;
                    $result = \Yii::$app->db->createCommand($sql);
                    $res = $result->queryAll();
                    if(empty($res[0]['price'])){
                        return '0&0';
                    }else{
                        return $res[0]['price'].'&'.$res[0]['num'];
                    }
                }else{
                    return '0&0';
                }
            }
        
        }
    }
	
	/*
		单e贷获取订单数据接口	
	*/
	public static function actionGetDaneloandata(){
		$request = Yii::$app->request->post() ;
		$r = ['res'=>100 , 'msg'=>''] ;
		if (isset($request['time']) && isset($request['token']) && isset($request['content'])){
			$n_token = md5(md5($request['time']) . '5bbfd68e674314de6775c6efb3ee9d02') ;
			$token = $request['token'] ;
			if($n_token == $token){			
				$c = json_decode($request['content'],true) ;
				if(null!=$c['supplier_code'] && null!=$c['begin_time'] && null!=$c['end_time']){
					$s_name = $c['supplier_code'] ;
					$head_t = $c['begin_time'] ;
					$tail_t = $c['end_time'] ;
					$s = Supplier::findOne(['login_account'=>$s_name]) ;
					if (!empty($s)){
						$s_id = $s['id'] ;
					
						$data = OmsInfo::getOrdersDetail($s_id,$head_t,$tail_t) ;
					
						$total_price = 0 ;
						$count = count($data) ;
						foreach($data as $v){
							$total_price += $v['amount'] ;
						}
						$total_price = sprintf("%.2f",$total_price) ;
						$r = ['res'=>0 ,'msg'=>'成功','data'=>$data,'total_quantity'=>$count,'total_price'=>$total_price] ;
					}else{
						$r = ['res'=>103,'msg'=>'供应商不存在'] ;
					}
				}else{
					$r = ['res'=>101 ,'msg'=>'缺少参数'] ;
				}
			}else{
				$r = ['res'=>102 ,'msg'=>'token验证失败'] ;
			}
		}else{
			$r = ['res'=>101 ,'msg'=>'缺少参数'] ;
		}
		return json_encode($r) ;
	}
    
	/*
		货e贷获取库存明细数据接口
	*/
	public static function actionGetHuoeloandata(){
		$request = Yii::$app->request->post() ;
		$r = ['res'=>100 , 'msg'=>''] ;
		if (isset($request['time']) && isset($request['token']) && isset($request['content'])){
			$n_token = md5(md5($request['time']) . '5bbfd68e674314de6775c6efb3ee9d02') ;
			$token = $request['token'] ;
			if($n_token == $token){			
				$c = json_decode($request['content'],true) ;
				if (null!=$c['supplier_code']){
					$s = Supplier::findOne(['login_account'=>$c['supplier_code']]) ;
					if (!empty($s)){
						$s_id = $s['id'] ;

						$data = (new Query())->select("e.name as brandName,f.name as supplier_name,a.type_id as typeID, d.name as typeName,a.id as goodsID, a.goods_code as productNo,a.goods_name as productName ,b.name as color,a.num_avai as stockQuantity,case when `c`.`goods_price` is null or `c`.`goods_price`=0 then `a`.`price` when `c`.`goods_price` is not null and `c`.`goods_price`!=0 then `c`.`goods_price` end as price")
							->from('goods_supplier_goods as a')
							->leftJoin('goods_color as b','a.color_id=b.id')
							->leftJoin('goods_intake_goods as c','a.id=c.goods_id')
							->leftJoin('goods_type as d','a.type_id=d.id')
							->leftJoin('goods_brand as e','a.brand_id=e.id')
							->leftJoin('user_supplier as f','a.supplier_id=f.id')
							->where(['a.supplier_id'=>$s_id])
							->andWhere(['>','a.num_avai',0])
							->andWhere(['or',['and','c.goods_price>0','c.goods_price<99999'],['and',['or','c.goods_price is  null','c.goods_price=0'],['and','a.price>0','a.price<99999']]])
							->orderBy('c.add_time desc')
							->all() ;

						$sum = 0 ;
						$t = [] ;
						$d = [] ;
						$count = 0;
						foreach($data as $v){
							if (!isset($t[$v['productNo']])){
								array_push($d,$v) ;
								$t[$v['productNo']] = 1 ;
								$sum += $v['stockQuantity'] * $v['price'] ;
								$count += $v['stockQuantity'] ;
							}
						}
						//$count = count($d) ;
						$sum = sprintf('%.2f',$sum) ;
						$r = ['res'=>0 ,'msg'=>'成功','data'=>$d,'total_quantity'=>$count,'total_price'=>$sum] ;
					}else{
						$r = ['res'=>103,'msg'=>'供应商不存在'] ;
					}
				}else{
					$r = ['res'=>101 ,'msg'=>'缺少参数'] ;
				}
			}else{
				$r = ['res'=>102 ,'msg'=>'token验证失败'] ;
			}
		}else{
			$r = ['res'=>101 ,'msg'=>'缺少参数'] ;
		}
		return json_encode($r) ;
	}

	public static function actionGetELoanAvailableData(){
		$request = \Yii::$app->request->post() ;

		$r = ['res'=>100 , 'msg'=>''] ;
		if (isset($request['time']) && isset($request['token']) && isset($request['content'])){
			$n_token = md5(md5($request['time']) . '5bbfd68e674314de6775c6efb3ee9d02') ;
			$token = $request['token'] ;
			if($n_token == $token){
				$c = json_decode($request['content'],true) ;
				if (isset($c['supplier_code'])  && isset($c['row']) && isset($c['page']) && isset($c['productNo'])){
					$s = Supplier::findOne(['login_account'=>$c['supplier_code']]) ;
					$c['page']--;
					if (!empty($s)){
						$s_id = $s['id'] ;
						$query = (new Query())->select("e.name as brandName,f.name as supplier_name,a.type_id as typeID, d.name as typeName,a.id as goodsID, a.goods_code as productNo,a.goods_name as productName ,b.name as color,a.num_avai as stockQuantity,case when `c`.`goods_price` is null or `c`.`goods_price`=0 then `a`.`price` when `c`.`goods_price` is not null and `c`.`goods_price`!=0 then `c`.`goods_price` end as price")
							->from('goods_supplier_goods as a')
							->leftJoin('goods_color as b','a.color_id=b.id')
							->leftJoin('goods_intake_goods as c','a.id=c.goods_id')
							->leftJoin('goods_type as d','a.type_id=d.id')
							->leftJoin('goods_brand as e','a.brand_id=e.id')
							->leftJoin('user_supplier as f','a.supplier_id=f.id')
							->where(['a.supplier_id'=>$s_id])
							->andWhere(['>','a.num_avai',0])
							->andWhere(['or',['and','c.goods_price>0','c.goods_price<99999'],['and',['or','c.goods_price is  null','c.goods_price=0'],['and','a.price>0','a.price<99999']]]);
						//缓存key
						$key= 'loan_available_data'.'-'.$c['supplier_code'].'-'.$c['row'].'-'.$c['page'];
						if(!empty($c['productNo'])){
							$key = 'loan_available_data'.'-'.$c['supplier_code'].'-'.$c['productNo'].'-'.$c['row'].'-'.$c['page'];
							$query->andWhere(['a.goods_code'=>trim($c['productNo'])]);
						}
						$query->groupBy('a.id');
						$history = f_c($key);
						if(!$history) {
							set_time_limit(0);
							$total = $query->count();
							$info = $query->orderBy('c.add_time desc')->offset($c['page'] * $c['row'])->limit($c['row'])->all();
							if (!empty($info)) {
								$r = ['res' => 0, 'msg' => '成功', 'data' => $info, 'total' => $total];
							} else {
								$r = ['res' => 104, 'msg' => '数据为空', 'data' => [], 'total' => $total];
							}
							f_c($key,$r,1800);
						}else{
							$r = $history;
						}
					}else{
						$r = ['res'=>104,'msg'=>'供应商不存在'] ;
					}
				}else{
					$r = ['res'=>103 ,'msg'=>'缺少参数'] ;
				}
			}else{
				$r = ['res'=>102 ,'msg'=>'token验证失败'] ;
			}
		}else{
			$r = ['res'=>101 ,'msg'=>'缺少参数'] ;
		}
		return json_encode($r) ;
	}

	//获取订单数据接口
	public function actionGetConsumeData()
	{
		$request = \Yii::$app->request->post();
		$r = ['res'=>100 , 'msg'=>''] ;
		if (isset($request['time']) && isset($request['token']) && isset($request['content'])){
			$n_token = md5(md5($request['time']) . '5bbfd68e674314de6775c6efb3ee9d02');
			if ($n_token == $request['token']){
				$content = json_decode($request['content'],true);

				if (isset($content['login_account']) && isset($content['begin_time']) && isset($content['end_time'])){
					$uid = UserMember::find()->where(['login_account'=>$content['login_account']])->one()->id;

					if(!empty($uid)) {
						$query = (new Query())->select('sum(order_price) as total_amount,count(id) as total')->from('order_oms_info')
							->where(['user_id' => $uid])->andWhere(['in', 'order_status', [45, 70, 135, 160, 220, 240, 655, 675]]);
						if (!empty($content['begin_time']) && !empty($content['end_time'])) {
							$query->andWhere(['>', 'order_time', $content['begin_time']])->andWhere(['<', 'order_time', $content['end_time']]);
						} elseif (!empty($content['begin_time']) && empty($content['end_time'])) {
							$query->andWhere(['>', 'order_time', $content['begin_time']]);
						} elseif (empty($content['begin_time']) && !empty($content['end_time'])) {
							$query->andWhere(['<', 'order_time', $content['end_time']]);
						}

						$info = $query->one();

						if (!empty($info)) {
							$r = ['res' => 0, 'msg' => '成功', 'total_amount' => $info['total_amount'],'total'=> $info['total']];
						} else {
							$r = ['res' => 105, 'msg' => '不存在' . $content['begin_time'] . '-' . $content['end_time'] . '的时间范围内的金融订单', 'total_amount' => 0,'total'=> 0];
						}
					}else{
						$r = ['res'=>104 ,'msg'=>'不存在用户'];
					}
				}else{
					$r = ['res'=>103 ,'msg'=>'缺少参数'];
				}
			}else{
				$r = ['res'=>102 ,'msg'=>'密钥验证失败'];
			}
		}else{
			$r = ['res'=>101 ,'msg'=>'缺少参数'];
		}
		return json_encode($r);
	}


}
