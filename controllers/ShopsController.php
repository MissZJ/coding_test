<?php
namespace frontend\controllers; 

use common\models\goods\GoodsComment;
use common\models\search\search\HotWord;
use common\models\user\UserShopsCollect;
use common\models\user\UserShopsInfo;
use common\models\user\UserShopsSupplier;
use Yii;
use frontend\components\Controller2016;
use yii\base\Exception;
use yii\db\Query;
use common\models\goods\SupplierGoods;
use yii\helpers\Json;
use yii\web\HttpException;
use common\models\markting\MarktingCouponBySupplierCity;
use common\models\markting\MarktingCouponBySupplier;
use common\models\markting\CouponUser;
use common\models\user\Supplier;
use common\models\other\FavoriteShop;

/**
 * @Description: 店铺
 * @author: miaomiao.chen
 * @date: 2015年7月31日 11:25:38
 */
class ShopsController extends Controller2016
{  
    
//     public function behaviors(){
//         return [
//            'access' => [
//                 'class' => \yii\filters\AccessControl::className(),
//                 'rules' => [
//                     [
//                     	'actions' => true,
//                         'allow' => true,
//                         'roles' => ['@', '?'],
//                     ],
//                 	[
//                 		'allow' => true,
//                 		'roles' => ['@'],
//                 	] 
//                 ],
//             ], 
//         ];
//     } 
     
    /**
      * @Title:
      * @Description:
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-27 下午2:38:55
     */
    public function actionSearch() {
        //获取店铺类型
        $typeArr = [
            '1' => '智能机',
            '2' => '功能机',
            '3' => '数码小家电',
            '4' => '电脑及周边',
            '5' => '手机配件',
            '6' => '智能设备',
            '7' => '其他',
        ];
        
        //搜索条件
        $keywords = f_get('keyword');
        if(!$keywords){
            $keywords = f_get('keywords');
        }

        //热搜词查找
        if($keywords){
            $re = HotWord::getHotUrlByKeys($keywords);
            if($re){
               $this->layout = false;
               return $this->redirect($re);
            }
        }

        $type = f_get('type');
        $order_by = f_get('order_by');
        $page = f_get('page',1);
        if ($keywords){
        	$v = f_ck('key_words');
        	if ($v) {
        		$keyword = json_decode($v);
        		if (count($keyword) < 10) {
        			array_push($keyword, $keywords.'-4');
        			f_ck('key_words', json_encode($keyword));
        		}
        	} else {
        		f_ck('key_words', json_encode([$keywords.'-4']));
        	}
        }
        $params = [
            'keywords' => $keywords,
            'type' => $type,
            'order_by' => $order_by,
            'page' => $page
        ];

        $where = ['t1.status' => 1,'t2.if_disable'=>0];
        if(!empty($type)){
            $where['t1.type'] = f_get('type');
        }

        $query = (new Query())->select('t1.*')->from('user_shops_info as t1')
        ->leftJoin('user_supplier as t2','t1.supplier_id = t2.id')
        ->where($where);
        if(!empty($keywords)) {
            $query->andWhere(['like','t1.name',$keywords]);
        }
        
        $page_num = 5;
        $query->limit($page_num)->offset(($page-1)*$page_num);
        $total_page = ceil($query->count()/$page_num);
        
        if(!empty($order_by)) {
            $query->orderBy("t1.score".' '."$order_by");
        }
        
        $shops_info = $query->all();
      
        foreach ($shops_info as $key => $val) {
            $query = (new Query)->select('*')->from('goods_supplier_goods')
                     ->where(['goods_supplier_goods.supplier_id'=>$val['supplier_id'],'status'=>1,'enable'=>1,'is_deleted'=>0]);

            $goods_num = $query->count();
            $shops_info[$key]['goods_num'] = $goods_num;//总共商品数量
            
            $goods = $query->limit('4')->all();
            $shops_info[$key]['goods'] = $goods;//取四个商品
            
            $sql = "select sum(sale_num) as sale_num from goods_supplier_goods where supplier_id = ".$val['supplier_id'];
            $sale_nums = SupplierGoods::findBySql($sql)->asArray()->one();  
            $shops_info[$key]['sale_num'] = $sale_nums['sale_num'];//销量
            
            $shops_info[$key]['isCare'] = FavoriteShop::find()->where(['user_id'=>$this->user_info['id'],'shops_id'=>$val['id']])->count() ? 1 : 0;
            
        }
        return $this->render("search",[
            'shops_info'=>$shops_info,
            'params' => $params,
            'typeArr' => $typeArr,
            'total_page' => $total_page
        ]);
    }

    /**
     * @Title:actionEvaluate
     * @Description: 供应商商铺详情页
     * @author:wangmingcha
     * @date:2015-11-23 下午2:38:55
     */
    public function actionShopDetail(){
        $shop_id = Yii::$app->request->get('shop_id');
        $shop_info = UserShopsInfo::getShopInfoById($shop_id);
        if($shop_info){
            //非营业状态
            if($shop_info['status']==2){
                return $this->renderPartial('empty',[
                    'msg'=>"抱歉，该店铺正在打烊中，请稍候再来！"
                ]);
            }

            if($shop_info['status']==3){
                return $this->renderPartial('empty',[
                    'msg'=>"抱歉，该店铺已被封锁！"
                ]);
            }


            $classify_info = UserShopsInfo::getClassifyInfo($shop_info);
            $topTenList = SupplierGoods::getSupplierTopTenGoods($shop_info['supplier_id']);
            $dp = UserShopsSupplier::getDetailShopGoods($shop_info['supplier_id']);

            //是否关注
            $careCount = FavoriteShop::find()->where(['user_id'=>$this->user_info['id'],'shops_id'=>$shop_id])->count();
            $isCareInfo = ($careCount)?['status'=>2]:['status'=>0];

            //获取供应商发布的红包信息
            $user_info = $this->user_info;
            
            $couponInfo = MarktingCouponBySupplier::isHaveCoupon($shop_info['supplier_id'], $user_info['province'], $user_info['city']);
           
            return $this->renderPartial('shop-detail',[
                'shop_info'=>$shop_info,
                'classify_info'=>$classify_info,
                'topTenList'=>$topTenList,
                'dp'=>$dp,
                'controller'=>$this,
                'couponInfo' => $couponInfo,
                'isCareInfo'=>$isCareInfo,
                'user_info'=>$this->user_info
            ]);
        }else{
            return $this->renderPartial('empty',[
                'msg'=>'抱歉没有找到您所需的店铺!'
            ]);
        }
    }

    /**
      * @Title:actionGetCoupon
      * @Description:获取供应商所发红包
      * @return:
      * @author:huaiyu.cui
      * @date:2015-12-7 下午3:41:10
     */
    public function actionGetCoupon(){
        $data = f_post('data');
        $returnVal = ['status'=>'0','message'=>''];
        
        if($data != null) {
            $coupon_info_id = $data['coupon_info_id'];
            $user_id = \Yii::$app->user->id;
            $admin_id = Supplier::getSupplierNameById($data['supplier_id']);
            $begin_time = $data['begin_time'];
            $end_time = $data['end_time'];
            
            $count = CouponUser::getCouponUserNum($coupon_info_id,$user_id);
            if($count > 0) {
                $returnVal['status'] = '1';
                $returnVal['message'] = '已经领取';
            }else{
                
                $connection = Yii::$app->db;//事务开始
                $transaction = $connection->beginTransaction();
                try{
                    $addReceive = MarktingCouponBySupplier::addReceivedNum($data['id']);
                    $insertStatus = CouponUser::addCoupon($coupon_info_id, $user_id, $admin_id, $begin_time, $end_time);
                    if($addReceive && $insertStatus) {
                        $returnVal['status'] = '1';
                        $returnVal['message'] = '领取成功';
                    }else{
                        $returnVal['message'] = '领取失败';
                    }
                    $transaction->commit();
                }catch (\Exception $e){
                    $transaction->rollBack();
                    $returnVal['message'] = '领取失败';
                }
            }
        }else{
            $returnVal['message'] = '参数为空';
        }
        
        echo json_encode($returnVal);
    }
    
    /**
     * @Title:actionEvaluate
     * @Description: 评价页面
     * @author:wangmingcha
     * @date:2015-11-23 下午2:38:55
     */
    public function actionEvaluate(){
        $shop_id = Yii::$app->request->get('shop_id');
        $shop_info = UserShopsInfo::getShopInfoById($shop_id);
        if($shop_info){
            $classify_info = UserShopsInfo::getClassifyInfo($shop_info);
            $dp = GoodsComment::getAllCommentProviderBySupplierId($shop_info['supplier_id']);

            //是否关注
            $isCareInfo = FavoriteShop::followShop($this->user_info['id'],$shop_id);
            return $this->renderPartial('evaluate',[
                'shop_info'=>$shop_info,
                'classify_info'=>$classify_info,
                'dp'=>$dp,
                'controller'=>$this,
                'isCareInfo'=>$isCareInfo
            ]);
        }else{
            throw new HttpException(404);
        }
    }

}
