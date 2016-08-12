<?php
namespace frontend\controllers;

use common\models\markting\PromotionCombineGoods;
use Yii;
use yii\web\Controller;
use common\models\goods\SupplierGoods;
use common\models\user\Supplier;
use common\models\other\OtherMainMenu;
use frontend\components\Controller2016;
use common\models\other\AdInfo;
use common\models\user\SupplierCityScreen;
use common\models\goods\ContractGoodsCity;
use common\models\other\OtherZhRnavInfo;
use common\models\user\CmccUserQd;
use common\models\other\QuestionSurvey;
use common\models\markting\CouponUser;
use common\models\markting\CouponInfo;

class SecondCoverController extends Controller2016
{ 
	protected  $user_info;
	
	public function init(){
		$this->user_info = f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0');
		parent::init();
	}
	
    /*
    *@description:合约机显示基础商品
    *@return:string 
    *@author:honglang.shen
    *@date:2016-1-25
    */
    public function actionIsContract(){
        $this->layout = '_blank';
        $page = f_get('page',1);
        $page_size = 40;
        $start = ($page-1)*$page_size;
        $brand_id = f_get('brand',0);
        $sort = f_get('sort',0);
        $intacts = f_get('intacts',0);
        $attr_id = f_get('attr');
        $keywords = '';
        if(f_get('keywords')){
        	$keywords = trim(strip_tags(f_get('keywords')));
        }
        $attrInfo = [];
        if ($attr_id){
            $attrInfo = explode('-', $attr_id);
        }
        $ad = [];
        $ad['banner'] = AdInfo::getAd(74,$this->user_info['city']);

        $goodsInfo = $data= ContractGoodsCity::getContractGoodsInfo($keywords,'',$attrInfo,$brand_id,$intacts,$this->user_info,$sort, $start, $page_size,$total=true);
        $total = $data_total = ContractGoodsCity::getContractGoodsInfo($keywords,'',$attrInfo,$brand_id,$intacts,$this->user_info,$sort, $start, $page_size,$total=false);
        // f_d($goodsInfo);
        $total_page = ceil(count($total)/$page_size);
        $menu_heng = OtherMainMenu::getMainMenu(1, $this->user_info['city']);
         //导航数据
        $city = $this->user_info['city'];
        $leftNavData = OtherZhRnavInfo::getRightNavData(2,$city, 0);
        // f_d($leftNavData);
        
        return $this->render('contract',['goodsInfo'=>$goodsInfo,
               'total'=>count($total),
               'page'=>$page,
               'total_page'=>$total_page,
               'sort'=>$sort,
               'brand_id'=>$brand_id,
               'intacts'=>$intacts,
               'menu_heng'=>$menu_heng,
               'user_info'=>$this->user_info,
               'ad'=>$ad,
               'rnav'=>$leftNavData,
        	   'userInfo'=>$this->user_info,
        	   'keywords'=>$keywords
        ]);
    }
    /**
     * @desc 蜂型移动终端
     */
    public function actionFengXing(){
    	
    	$page = f_get('page',1);
    	$page_size = 40;
    	$start = ($page-1)*$page_size;
    	$brand_id = f_get('brand',0);
    	$sort = f_get('sort',0);
    	$goodsInfo = SupplierGoods::getFengGoodsInfo($brand_id, $this->user_info['city'],$sort, $start, $page_size);
    	foreach ($goodsInfo as $key=>$info){
    		$goodsInfo[$key]['goodsFrom'] = Supplier::getNickName($info['supplier_id']);
    	}
    	$total = SupplierGoods::getFengGoodsInfo($brand_id, $this->user_info['city'],$sort, $start, $page_size,false);
    	$total_page = ceil($total/$page_size);
    	return $this->render('feng_xing',['goodsInfo'=>$goodsInfo,
    			'total'=>$total,
    			'page'=>$page,
    			'total_page'=>$total_page,
    			'sort'=>$sort,
    			'brand_id'=>$brand_id,
    	]);
    }
    /**
     * @desc:自备机（new）
     * @author : bafeitu
     * @date:2016年1月28日11:00:16
     */
    public function actionIsSelf(){
        $this->layout = '_blank';
        $page = f_get('page',1);
        $page_size = 40;
        $start = ($page-1)*$page_size;
        $brand_id = f_get('brand',0);
        $sort = f_get('sort',0);
        $intacts = f_get('intacts',0);
        $attr_id = f_get('attr');
        $keywords = '';
        if(f_get('keywords')){
        	$keywords = trim(strip_tags(f_get('keywords')));
        }
        $attrInfo = [];
        if ($attr_id){
            $attrInfo = explode('-', $attr_id);
        }
        $ad = [];
        $ad['banner'] = AdInfo::getAd(73,$this->user_info['city']);
        //屏蔽供应商
        $ScreenSupplier = SupplierCityScreen::getAllSupplier($this->user_info['city'],$this->user_info['user_group']);
        $goodsInfo = SupplierGoods::getSelfGoodsInfo($keywords,'',$attrInfo,$brand_id,$intacts,$this->user_info['province'],$sort, $start, $page_size);
        foreach ($goodsInfo as $key=>$info){
            $goodsInfo[$key]['goodsFrom'] = Supplier::getNickName($info['supplier_id']);
        }
        $total = SupplierGoods::getSelfGoodsInfo($keywords,'',$attrInfo,$brand_id,$intacts,$this->user_info['province'],$sort, $start, $page_size,false);
        $total_page = ceil(count($total)/$page_size);
        $menu_heng = OtherMainMenu::getMainMenu(1, $this->user_info['city']);
        //自备机左侧导航
        $city = $this->user_info['city'];
        //导航数据
        $leftNavData = OtherZhRnavInfo::getRightNavData(1,$city, 0);
        // f_d($leftNavData);
        return $this->render('self_index_new',[
                    'goodsInfo'=>$goodsInfo,
                    'total'=>count($total),
                    'page'=>$page,
                    'total_page'=>$total_page,
                    'sort'=>$sort,
                    'brand_id'=>$brand_id,
                    'menu_heng'=>$menu_heng,
                    'user_info'=>$this->user_info,
                    'ad'=>$ad,
                    'intacts'=>$intacts,
                    'ScreenSupplier'=>$ScreenSupplier,
                    'rnav'=>$leftNavData,
        			'keywords'=>$keywords
                ]);
    }
    /*
    *@description:判断用户是否填了渠道信息
    *@return:string 
    *@author:honglang.shen
    *@date:2016-2-17
    *@ modifier: qiang.zuo
    *@ modify-date: 2016-5-18 14:19:37
    */
    public function actionGetUserQd(){
        if($params = \Yii::$app->request->Post()){
            $goods_id = $params['goods_id'];
            $type = isset($params['type'])?$params['type']:0;
            $return['type'] = $type;
            $return['code']='';
            $return['name'] = '';
            $return['tips'] = '';
            if(isset($goods_id)){
                if($type === 0)
                    $is_contra = ContractGoodsCity::find()->where(['goods_id'=>$goods_id])->one();//判断是否为合约机商品
                else
                    $is_contra = $this->getUserQdForPromotion($goods_id);
                if(!empty($is_contra)){
                    $data = CmccUserQd::find()->where(['user_id'=>$this->user_info['id']])->asArray()->one();
                    $province = $this->user_info['province'];
                    if(empty($data)){
                        if($province == 16){ //江苏湖北
                            $return['code'] = '营业厅编码：';
                            $return['name'] = '移动营业厅名称：';
                            $return['tips'] = '（如：营业厅编码：  14329    移动营业厅名称：  终端测试专用厅 ）';
                        }elseif ($province == 14) { //湖南
                            $return['code'] = '营业厅渠道编码：';
                            $return['name'] = '移动营业厅名称：';
                            $return['tips'] = '（如：营业厅渠道编码：  A1LDBDZD    移动营业厅名称： 城南雨花区博达电子指定专营店 ）';
                        }elseif ($province == 22) { //山东
                            $return['code'] = '渠道编码：';
                            $return['name'] = '渠道名称：';
                            $return['tips'] = '（如：渠道编码：  SD.LQ.Og.02.b1.xt    渠道名称：  聊城市东昌府区-东北1-G3手机销售平台 朱兴振便利店 ）';
                        }elseif ($province == 31) { //浙江
                            $return['code'] = '渠道编码：';
                            $return['name'] = '渠道名称：';
                            $return['tips'] = '（如：渠道编号：  14329    渠道名称：  终端测试专用厅 ）';
                        }elseif ($province == 394) {//重庆
                            $return['code'] = '移动工号编码：';
                            $return['name'] = '移动营业厅名称：';
                            $return['tips'] = '（如：移动工号编码：  CQ.CQ.XX.00.aB    移动营业厅名称：  终端测试专用厅 ）';
                        }elseif ($province==13) {
                            $return['code'] = '移动B2B节点名称：';
                            $return['name'] = '移动营业厅名称：';
                            $return['tips'] = '（如：移动B2B节点名称：  HB.XG.08.08.08.08    移动营业厅名称：  江岸区花桥合作营业厅 ）';
                        }
                    }
                }
            }
            return json_encode($return);
        }
    }

    /**
     * @return string
     * date: 2016-5-18 13:58:14
     * author: qiang.zuo
     * description: 针对组合商品的，判断用户是否填了渠道信息
     * review:
     * modifier:
     * modifier_date:
     */
    public function getUserQdForPromotion($promotionId){
        $flag = 0;
        $goodIds = PromotionCombineGoods::find()->select("goods_id")->where(["promotion_id"=>$promotionId])->asArray()->all();
        if($goodIds)
            foreach($goodIds as $vo){
                $is_contra = ContractGoodsCity::find()->where(['goods_id'=>$vo['goods_id']])->one();//判断是否为合约机商品
                if($is_contra) $flag++;
            };
        return $flag;
    }
    /*
    *@description:判断用户是否填了渠道信息
    *@return:string 
    *@author:honglang.shen
    *@date:2016-2-17
    */
    public function actionSaveUserQd(){
        $code =trim($_POST['code']);
        $name = trim($_POST['name']);
        $user_id = $this->user_info['id'];
        $result = 0;
        $res = CmccUserQd::find()->where(['user_id'=>$user_id])->asArray()->one();
        if(!$res){
            $model = new CmccUserQd();
            $model->code = $code;
            $model->name = $name;
            $model->user_id = $user_id;
            if($model->save()){
               $result = 1; 
            }
        }
        return json_encode($result);
    }
    
    /**
     * @desc 问卷调查
     */
    public function actionQuesAn(){
    	
    	if (!empty($_POST)){
    		$userId = $_POST['userId'];
    		$status1 = $_POST['status1'];
    		$status2 = $_POST['status2'];
    		$status3 = $_POST['status3'];
    		$status4 = $_POST['status4'];
    		$status5 = $_POST['status5'];
    		$status6 = implode(',', $status5);
    		$redStatus = rand(1, 1052);
    		if (0 < $redStatus && $redStatus < 753){
    			$redId = 1898;
    		}elseif ($redStatus > 752 && $redStatus < 953){
    			$redId = 1899;
    		}elseif ($redStatus > 952 && $redStatus < 1053){
    			$redId = 1900;
    		}
    		if ($redId == 1900){
    			$num_sql = "SELECT COUNT(*) FROM other_question_survey WHERE red_id = 1900";
    			$num = QuestionSurvey::findBySql($num_sql)->asArray()->scalar();
    			if ($num > 99){
    				$redId = 1898;
    			}
    		}
    		if ($redId == 1899){
    			$num_sql1 = "SELECT COUNT(*) FROM other_question_survey WHERE red_id = 1899";
    			$num1 = QuestionSurvey::findBySql($num_sql1)->asArray()->scalar();
    			if ($num1 > 199){
    				$redId = 1898;
    			}
    		}
    		$return = CouponUser::addCoupon($redId,$userId,'问卷调查',date('Y-m-d H:i:s',time()), date('Y-m-d H:i:s',strtotime('+7 day')));
    		if ($return){
	    		$sql = "UPDATE other_question_survey SET ques_1 = '$status1',ques_2 = '$status2',ques_3 = '$status3',ques_4 = '$status4',ques_5 = '$status6',is_doing = 1,red_id = $redId WHERE user_id = $userId";
	    		$res = \Yii::$app->db->createCommand($sql)->execute();
	    		$amount = CouponInfo::getAmountById($redId);
	    		$redInfo = " 恭喜您获得<span>".$amount."元</span>红包现金";
	    		$data['res'] = $res;
	    		$data['red'] = $redInfo;
	    		return json_encode($data);
    		}
    	}else {
    		$userId = $this->user_info['id'];
    		$screenTime = QuestionSurvey::find()->select('screen_time')->where(['user_id'=>$userId])->asArray()->scalar();
    		if ($screenTime > 0){
    			$screenTime = $screenTime - 1;
    			QuestionSurvey::updateAll(['screen_time'=>$screenTime],['user_id'=>$userId]);
    		}	
    	}
    }
}
