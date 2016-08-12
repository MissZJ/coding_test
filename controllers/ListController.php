<?php
namespace frontend\controllers;

use Yii;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\helpers\Json;
use yii\web\Controller;
use frontend\components\Controller2016;
use common\models\goods\BaseGoods;
use common\models\goods\Module;
use common\models\goods\Type;
use common\models\goods\TypeBrand;
use common\models\goods\AttrName;
use common\models\goods\SupplierGoods;
use common\models\goods\ModuleTypeRel;
use common\models\goods\DepotCity;
use common\models\search\HotWord;
use common\models\goods\GoodsRestCityGoods;
use common\models\user\SupplierCityScreen;
use common\models\search\KeywordsTypeRelationship;
use common\models\search\KeywordsBrandRelationship;
use common\models\markting\SpecialInfo;
use common\models\user\GoodsPrivilegeUser;
use common\models\other\OtherMainMenu;
use common\models\other\GoodsMallInfo;
use common\models\user\StoreMall;
use common\models\search\NewHotWord;
use common\models\goods\BaseGoodsCity;
use yii\helpers\ArrayHelper;
use common\models\other\MallBase;
use common\models\markting\PromotionCombineGoods;
use common\models\user\UserMember;

class ListController extends Controller2016
{ 
	//private $user_info;
	public function actionIndex(){
		header("Content-Type: text/html;charset=UTF-8");
		$att = $value = $bottom = $up = '';
		$filters = $select = $pages = $baseInfo = [];
		$sorts = ['weight'=>'+'];
		$total = $total_page = 0;
		$page = f_get('page',1);
		$page_size = 40;
		$start = ($page-1)*$page_size;
		
		$type_id = f_get('type',0);
                if(!preg_match('/^\d+$/',$type_id)){
                 return f_msg('搜索条件不符合','/home/index');   
                }
        $StoreMall = StoreMall::getMainMall($this->user_info['id']);
        
        $mall_id = f_get('mall_id',$StoreMall);
         if(!preg_match('/^\d+$/',$mall_id)){
                 return f_msg('搜索条件不符合','/home/index');   
                }
        $mallId = $mall_id;
        $model_id = f_get('model_id',0);
        $filters += ['mall_id'=>$mall_id];
        
        $modelInfo = [];
        $model_id_one = 0;

		if ($type_id != 0){
			$filters += ['type_id'=>$type_id];
            $mall_id = Type::getTypeMall($type_id);
            $_GET['mall_id']=$mall_id;
		}
        $typeInfo = Type::getTypeByMall($mall_id);
       
		$brand_id = f_get('brand',0);
		if ($brand_id != 0){
			$filters += ['brand_id'=>$brand_id];
		}
		
		$attr_id = f_get('attr');
		if ($attr_id){
			$attrInfo = explode('-', $attr_id);
			$att = $attrInfo[0];
                        if(isset($attrInfo[1])){
			$value = $attrInfo[1];
			$filters += [$att=>$value];
                        }
		}

		$sort = f_get('sort',2);
		if (1 == $sort){
			$sorts = ['bottom_price'=>'+'];
		}else if(0 == $sort){
			$sorts = ['bottom_price'=>'-'];
		}else if(3 == $sort){
			$sorts = ['base_id'=>'-'];
		}else if(2 == $sort){
            $sorts += ['type_id'=>'+'];
			$sorts += ['bottom_price'=>'+'];
		}
		
// 		$sorts += ['weight'=>'+']; //排序字段
		
		$priceRange = f_get('priceRange',1);
		if($priceRange != 1){
			$price = explode("-",$priceRange);
			
			$bottom = intval($price[0]);
			$up = intval($price[1]);
			$filters[]=[ '>','bottom_price',$bottom];
			$filters[]=[ '<','bottom_price',$up];
		}
		$yiwang = [0];
		if (1 == $this->user_info['user_group']){
			if($this->user_info['city'] == 232) {
				$yiwang = BaseGoods::getYiWangGoods($this->user_info['id']);			
				$filters += ['base_id' => ['notin', $yiwang]];
			}
			//$filters += ['type_id' => 1];
			$filters += ['attr_value_5' => ['notin', [172,173,176]]];
            $filters += ['attr_value_7' => ['!=', 254]];
		}
                //红米手机 江苏独卖
		if($this->user_info['province'] != 16) {   
                    if($this->user_info['province'] != 3){  // 安徽的可以看到  32886
                            $yiwang = array_merge($yiwang,[32886,32885,32884,31170,31168,14539,14537]);
                            $filters += ['base_id' => ['notin',$yiwang]];
                        }else{         
                            $yiwang = array_merge($yiwang,[32885,32884,31170,31168,14539,14537]);   
                            $filters += ['base_id' => ['notin',$yiwang]];
                        }
		}
		$keyword = trim(strip_tags(mb_convert_encoding(f_get('keyword'),'UTF-8','auto')));
        $wjr_keywords = f_get('keywords','');
        if(!$keyword){
        	if($wjr_keywords){
        		$keyword = trim(strip_tags(mb_convert_encoding(f_get('keywords'),'utf-8','auto')));
        	}
        }
        //根据热搜词定向跳转
        if($keyword){
            $keyword = str_replace(['\'','"', '\\'],'',$keyword);
            
            $re = HotWord::getHotUrlByKeys($keyword,$mallId);
            if($re){
                $this->layout = false;
                return $this->redirect($re);
            }
        }
	
        if(true){  //  code by wmc: 取消通讯商城模块限制
        	//顶级模块
        	//$modelInfo[0] = Module::partsGoodsModuleList(0);
        	$model_info = explode("_",$model_id);
        	$i = 0;
        	$t = 0;
        	$m_t=0;//用户最终选择的非0 的模块；
        	$z = 0;
        	foreach ($model_info as $key => $value) {
        		if($z == 0){
        			$modelInfo[$key]['list'] = Module::AllGoodsModuleList($mall_id);
        			$modelInfo[$key]['key'] = $value;
        		}else{
        			$modelInfo[$key]['list'] = Module::AllGoodsModuleList($mall_id,$i,2);
        			$modelInfo[$key]['key'] = $value;
        		}
        		$i = $value;
        		$t = $key;
        		if($value){
        			$m_t = $value;
        		}
        		$z++;
        	}
        	if($i){
        		$second_model = Module::partsGoodsModuleList($i);
        		if($second_model){
        			if($mall_id == 0){
        				$modelInfo[$t+1]['list'] = Module::AllGoodsModuleList($i);
        			}else{
        				$modelInfo[$t+1]['list'] = Module::partsGoodsModuleList($i);
        			}
        			$modelInfo[$t+1]['key'] = 0;
        		}
        	}
        	$modelIds = array_keys($modelInfo);//获取所有的KEY
        	//条件 根据model_id查找type_id
        	if($model_id != 0){
        		$model_info = explode("_",$model_id);
        		$module_zi = [];
        		$model_data = [];
        		$ty = [];
        		if(count($model_info) == 1){
        			$model_data = Module::find()->select('id')->where(['parent_id'=>$model_id])->asArray()->all();
        			if(empty($model_data)){
        				$model_data[] = ['id'=>$model_id];
        			}
        			foreach($model_data as $key=>$val){
        				$data = ModuleTypeRel::find()->select('type_id')->where(['module_id'=>$val['id']])->andWhere(['status'=>1])->asArray()->all();
        				$ty =array_merge($ty,$data);
        			}
        		}else{
        			if($model_info[1] == 0){
        				$module_zi1 = Module::find()->select('id')->where(['parent_id'=>$model_info[0]])->asArray()->all();
        				foreach($module_zi1 as $key=>$val){
        					$type0 = ModuleTypeRel::find()->select('type_id')->where(['in','module_id',$val['id']])->andWhere(['status'=>1])->asArray()->all();
        					$ty =array_merge($ty,$type0);
        				}
        			}else{
        				$ty += ModuleTypeRel::find()->select('type_id')->where(['in','module_id',$model_info[1]])->andWhere(['status'=>1])->asArray()->all();
        			}
        		}
        		$model_array = [];
        		foreach($ty as $key=>$val){
        			$model_array[] += $val['type_id'];
        		}
        		if($type_id != 0){
        			$filters += ['type_id' => $type_id];
        		}else{
        			if(!empty($model_array)){
        				$filters += ['type_id' => ['in', $model_array]];
        			}
        		}
        	}else{
        		if($type_id != 0){
        			$filters += ['type_id' => $type_id];
        		}
        	}
        	$model_id_one =  end($model_info);
        }
        $searchResult = HotWord::getGoodsByKeywords($keyword);
        
        $flag = 0;
        if($searchResult){
        	$flag = 1;
        }
        if($searchResult){
            $typeInfo = KeywordsTypeRelationship::getTypeListByKeywords($searchResult,$mall_id,$keyword);
            if(empty($typeInfo)){
                if(false && $mall_id == 1){
                	$typeInfo = GoodsMallInfo::getAllTypeByMallAsc($mall_id);
                }else{
                	$flag = 0;
                	$typeInfo = Type::getTypeListByModule($m_t,false,'',$mall_id);
                }
            }
        }else{
            if(false && $mall_id == 1){
            	$typeInfo = GoodsMallInfo::getAllTypeByMallAsc($mall_id);
            }else{
            	$typeInfo = Type::partsGetTypeListByModule($m_t,$mall_id);
            }
        }
        
        
		$is_intact = f_get('is_intact',0);
		if ($is_intact){
			$filters += ['is_brush'=>0];
            $filters += ['type_id' => 1];
		}
		
		if ($keyword){
			$filters += ['key_words'=>$keyword];
			$v = f_ck('key_words');
			if ($v) {
				$keywords = json_decode($v);
				if (!$keywords){
					$keywords = [];
				}
				if (count($keywords) < 10) {
					array_push($keywords, $keyword.'-'.$mall_id);
					f_ck('key_words', json_encode($keywords));
				}
			} else {
				f_ck('key_words', json_encode([$keyword.'-'.$mall_id]));
			}
		}
		$attrInfo = AttrName::getExtendAttr($type_id);
        
        if($searchResult){
            $brandInfo =  KeywordsBrandRelationship::getBrandListByKeywords($searchResult);
            if(empty($brandInfo)){
                if($mall_id == 1){
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAsc($mall_id);
                }else{
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAndModuleAsc($mall_id,$m_t);
                }
            }
        }else {
            if($type_id != 0){
                if($mall_id == 1){
                	$brandInfo = GoodsMallInfo::getAllBrandByMallTypeAsc($mall_id,$type_id);
                }else{
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAndModuleTypeAsc($mall_id,$type_id,$m_t);
                }
            }else{
            	if($mall_id == 1){
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAsc($mall_id);
                }else{
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAndModuleAsc($mall_id,$m_t);
                }
            }
        }

  		$filters += ['city'=>$this->user_info['city']];
        //移动用户屏蔽功能机
        $user_group = 0;
        $user_group = $this->user_info['user_group'];
        
        if($user_group == 1){
        	//移动分站，屏蔽功能机（部分地区例外）[40=>'滁州']
        	if(in_array($this->user_info['city'],[40])) {
        		
        	} else if(isset($filters['type_id']) && $filters['type_id'] == 2){
        		unset($filters['type_id']);
        	}
        	$filters += ['show_id' => ['!=', 1]];
        	$filters += ['type_id' => ['!=', 2]];
        //开放站屏蔽功能机 ['41'=>阜阳]
        } else if(in_array($this->user_info['city'],[41])){
//         	if(($filters['type_id']) && $filters['type_id'] == 2) {
//         		unset($filters['type_id']);
//         		$filters += ['type_id' => ['!=', 2]];
//         	}
        }
        //空字符串 unset空的字符串  抑制报错
        if(isset($filters['key_words'])) {
        	if(!$filters['key_words']){
        		unset($filters['key_words']);
        	}
        }
        //屏蔽商品
        $city = $this->user_info['city'];
        $ScreenGoods = GoodsRestCityGoods::getScreenGoods($city);
        
        if(!empty($ScreenGoods)){
            $filters +=['base_id' => ['notin',$ScreenGoods]];  
        }

  		$select = ['base_id','goods_name','cover_id','keywords','bottom_price'];
  		$pages = ['limit' => $page_size,'offset' =>$start];
  		$type = 'mobile';
  		//$filters += ['status' => 1];
  		$data = BaseGoods::getGoodsList($filters,[],$sorts,$pages,$type);
  		
  		if ('OK' == $data['status']){
  			$baseInfo = $data['result']['items'];
  			$total = $data['result']['total'];
  			$total_page = ceil($total/$page_size);
  			$total_sales = [];
  			foreach ($baseInfo as $key=>$value) {
  				if(!isset($value['total_sales'])) {
  					$baseInfo[$key]['total_sales'] = BaseGoods::getTotalSales($value['base_id']);
  				}
  				$total_sales[$key] = $baseInfo[$key]['total_sales'];
  			}
  			if($sort == 3) {
  				array_multisort($total_sales, SORT_DESC,$baseInfo);
  			}
  		}
  		
  		$malls = StoreMall::getMalls($this->user_info['id']);
  		foreach ($malls['yes_malls'] as $km=>$vm) {
  			if($vm['id'] == $mall_id) {
  				unset($malls['yes_malls'][$km]);
  			}
  		}
  		//未收录热词
  		if($mall_id && $keyword) {
  			NewHotWord::charu($keyword, $mall_id);
  		}

  		if($mall_id == 1 && $keyword == '小米' && isset($this->user_info['province']) && $this->user_info['province'] == 16) {
  		    //通讯商城搜索关键词跳出弹窗提醒用户账户中有红包可以使用
  		    $nowTime = date('Y-m-d H:i:s');
  		    //弹框，8月1号就不弹了
  		    if($nowTime < '2016-08-01 00:00:00') {
  		        $is_exist_coupon = (new Query())->select('t2.id')
  		        ->from('markting_coupon_info as t1')
  		        ->leftJoin('markting_coupon_user as t2','t2.coupon_id = t1.id')
  		        ->andWhere(['in', 't1.id', ['2445','2446']])
  		        ->andWhere(['t2.user_id' => $this->user_info['id'], 't2.status' => 1])
  		        ->andWhere(['<', 't2.start_time', $nowTime])
  		        ->andWhere(['>', 't2.end_time', $nowTime])
  		        ->count();
  		    } else $is_exist_coupon = 0;
  		    
  		} else $is_exist_coupon = 0;
		return $this->render('list',['type_id'=>$type_id,
				'typeInfo'=>$typeInfo,
				'brand_id'=>$brand_id,
				'brandInfo'=>$brandInfo,
				'baseInfo'=>$baseInfo,
				'attrInfo'=>$attrInfo,
				'page'=>$page,
				'total_page'=>$total_page,
				'attr_id'=>$attr_id,
				'sort'=>$sort,
				'priceRange'=>$priceRange,
				'keyword'=>$keyword,
				'total'=>$total,
				'is_intact'=>$is_intact,
                'mall_id'=>$mall_id,
				'mall_name'=>MallBase::getMallName($mall_id),
                'user_group'=>$user_group,
                'user_info'=>$this->user_info,
				'malls'=>$malls,
				'modelInfo'=>$modelInfo,
				'flag'=>$flag,
				'model_id'=>$model_id_one,
		        'is_exist_coupon' => $is_exist_coupon,
		]);
	}
	
	/**
	 * @description:非手机类商品列表,供学习参考
	 * @return: \yii\web\Response|Ambigous <string, string>
	 * @author: sunkouping
	 * @date: 2016年7月1日下午2:27:48
	 * @modified_date: 2016年7月1日下午2:27:48
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionParts(){
		return 'wrong site';
		ini_set('display_errors', 1);
		$keywords = trim(strip_tags(f_get('keyword','')));
        if(!$keywords){
            $keywords = trim(strip_tags(f_get('keywords','')));
        }else {
            $keywords = str_replace(['\'','"', '\\'],'',$keywords);
        }
        $StoreMall = StoreMall::getMainMall($this->user_info['id']);
        $mall_id = f_get('mall_id',$StoreMall);
        $mallId = $mall_id;
        if($mall_id == 0) {
        	$mall_id = $StoreMall;
        }

        //收录热搜词
        if($keywords){
            $re = HotWord::getHotUrlByKeys($keywords,$mallId);
            if($re){
                $this->layout = false;
                return $this->redirect($re);
            }
        }

        $Module = Module::getModuleByMall($mall_id);
        $model_id = f_get('model_id',0);
        //未收录热词
        if($mall_id && $keywords) {
        	NewHotWord::charu($keywords, $mall_id);
        }
        //f_d($model_id);
        $brand_id = intval(f_get('brand',0));
        $type_id = intval(f_get('type',0));
        $sort = intval(f_get('sort',0));
        $priceRange = trim(f_get('priceRange',0));
        //f_d($priceRange);
        $attr_id = f_get('attr',0);
       
        $searchResult = HotWord::getGoodsByKeywords($keywords);
        $flag = 0;
        if($searchResult){
            $flag = 1;
        }
        if ($keywords){
        	$v = f_ck('key_words');
        	if ($v) {
        		$keyword = json_decode($v);
        		if ($keyword && count($keyword) < 10) {
        			array_push($keyword, "{$keywords}-{$mall_id}");
        			f_ck('key_words', json_encode($keyword));
        		}
        	} else {
                $historyKw = ["{$keywords}-{$mall_id}",];
        		f_ck('key_words', Json::encode($historyKw));
        	}
        }
        
		//顶级模块
        //$modelInfo[0] = Module::partsGoodsModuleList(0);
        $model_info = explode("_",$model_id);
        $i = 0;
        $t = 0;
        $m_t=0;//用户最终选择的非0 的模块；
        foreach ($model_info as $key => $value) {
            $modelInfo[$key]['list'] = Module::AllGoodsModuleList($mall_id);
            $modelInfo[$key]['key'] = $value;
            $i = $value;
            $t = $key;
            if($value){
                $m_t = $value;
            }
        }
        if($i){
            $second_model = Module::partsGoodsModuleList($i);
            if($second_model){
                if($mall_id == 0){
                    $modelInfo[$t+1]['list'] = Module::AllGoodsModuleList($i);
                }else{
                    $modelInfo[$t+1]['list'] = Module::partsGoodsModuleList($i);
                }
                $modelInfo[$t+1]['key'] = 0;
            }
        }
        $modelIds = array_keys($modelInfo);//获取所有的KEY
		//类型
        $attrInfo=[];
        if($m_t === 0){
            $m_t = null;
        }
        //根据商城ID修改品类
        if($searchResult){
            $typeInfo = KeywordsTypeRelationship::getTypeListByKeywords($searchResult,$mall_id,$keywords);
            if(empty($typeInfo)){
                $flag = 0;
                $typeInfo = Type::getTypeListByModule($m_t,false,'',$mall_id);
            }
        }else{
            $typeInfo = Type::partsGetTypeListByModule($m_t,$mall_id);
        }

        $typeIds = array_keys($typeInfo);
        if (!$type_id || !in_array($type_id,array_keys($typeInfo),true)) {
            $type_id = null;
        } else {
            $attrInfo = AttrName::getExtendAttr($type_id);
        }
		$attrInfo = AttrName::getExtendAttr($attr_id);
  		//品牌
        if($searchResult){
            $brandInfo =  KeywordsBrandRelationship::getBrandListByKeywords($searchResult);
            if(empty($brandInfo)){
                $brandInfo = TypeBrand::getBrandListByType($typeIds);
            }
        }else {
            $brandInfo = TypeBrand::getBrandListByType($type_id);
        }
        //条件 
        $filters = [];
        //商城
        $filters += ['mall_id'=>$mall_id];
        //价格区间
        if(f_get('priceRange')){
            $price = explode("-",$priceRange);
            $filters[]=[ '>=','price',intval($price[0])];
            $filters[]=[ '<','price',intval($price[1])];
        }
        if(!empty($keywords)){
            $filters += ['key_words' => $keywords];
        }
        //条件 品牌
        if($brand_id != 0){
            $filters += ['brand_id' => $brand_id];
        }
        //条件 根据model_id查找type_id 
        if($model_id != 0){
            $model_info = explode("_",$model_id);
            $module_zi = [];
            $model_data = [];
            $ty = [];
            if(count($model_info) == 1){
                $model_data = Module::find()->select('id')->where(['parent_id'=>$model_id])->asArray()->all();
                if(empty($model_data)){
                    $model_data[] = ['id'=>$model_id];
                }
                foreach($model_data as $key=>$val){
                    $data = ModuleTypeRel::find()->select('type_id')->where(['module_id'=>$val['id']])->andWhere(['status'=>1])->asArray()->all();
                    $ty =array_merge($ty,$data);
                }
            }else{
                if($model_info[1] == 0){
                    $module_zi1 = Module::find()->select('id')->where(['parent_id'=>$model_info[0]])->asArray()->all();
                    //$module_zi2 = Module::find()->select('id')->where(['parent_id'=>$model_info[1]])->asArray()->all();   
                    //$model_data = array_merge($module_zi1, $module_zi2);
                    foreach($module_zi1 as $key=>$val){
                        $type0 = ModuleTypeRel::find()->select('type_id')->where(['in','module_id',$val['id']])->andWhere(['status'=>1])->asArray()->all();
                        $ty =array_merge($ty,$type0);
                    }
                }else{
                   $ty += ModuleTypeRel::find()->select('type_id')->where(['in','module_id',$model_info[1]])->andWhere(['status'=>1])->asArray()->all(); 
                }
            }
            $model_array = [];
            foreach($ty as $key=>$val){
                $model_array[] += $val['type_id'];
            }
            if($type_id != 0){
                $filters += ['type_id' => $type_id];
            }else{
                if(!empty($model_array)){
                	$filters += ['type_id' => ['in', $model_array]];
                } 
            }
        }else{
            if($type_id != 0){
                $filters += ['type_id' => $type_id];
            }
        }
        //移动用户屏蔽功能机
        $user_group = $this->user_info['user_group'];
        if($user_group == 1){
            //屏蔽功能机
            $filters += ['type_id' => ['!=', 2]];
            //屏蔽异网机型
            $yiwang = BaseGoods::getYiWangGoods($this->user_info['id']);
            //$filters += ['base_id' => ['notin', $yiwang]];
            $filters += ['attr_value_5' => ['notin', [172,173,176]]];
            $filters += ['attr_value_7' => ['!=', 254]];
        }
        
        //条件仓库
        $city = $this->user_info['city'];
        $de = DepotCity::find()->select('depot_id')->where(['city'=>$city])->asArray()->all();
        $depot_array = [];
        foreach($de as $key=>$val){
        	$depot_array[] += $val['depot_id'];
        }
        if(!empty($depot_array)){
        	$filters += ['depot_id' => ['in', $depot_array]];	
        }
        //屏蔽供应商
        $ScreenSupplier = SupplierCityScreen::getAllSupplier($city,$this->user_info['user_group']);
        if(!empty($ScreenSupplier)){
            $filters += ['supplier_id' => ['notin', $ScreenSupplier]];
        }
        //屏蔽商品
        $ScreenGoods = GoodsRestCityGoods::getScreenGoods($city);
        if(!empty($ScreenGoods)){
            $filters +=['base_id' => ['notin',$ScreenGoods]];  
        }
       
        $filters += ['status' => 1];
        $filters += ['enable' => 1];
        $filters += ['num_avai' => ['>',0]];
        $filters += ['price' => ['>',0]];

        if($mall_id != 0){
            $filters += ['mall_id' => $mall_id];
        }
        // else if($type_id){
        //     $mall_id = Type::getTypeMall($type_id);
        //     $_GET['mall_id']=$mall_id;
        // }
        //f_d($filters);
        //搜索的字段
        $select =['id','goods_name','price','cover_id','img_url','supplier_id','sale_num'];
        //排序
        if($sort == 3){
        	$sorts = ['price'=>'-'];
        }elseif($sort == 2){
        	$sorts = ['price'=>'+'];
        }elseif ($sort == 1) {
        	$sorts = ['sale_num'=>'-'];
        }elseif ($sort == 4) {
        	$sorts = ['sale_num'=>'+'];
        }else{
        	$sorts = ['sale_num'=>'-'];;
        }
        //分页
        //分页
        $page = f_get('page',1);
    	$pageSize = 20;
    	$pageSet = $pageSize*($page-1);
        $pages = ['limit' => $pageSize, 'offset' => $pageSet];
        //类型
        $type = 'not_mobile';
		
        $data = BaseGoods::getGoodsList($filters, $select, $sorts, $pages, $type);
        // $goodsInfo = BaseGoods::getGoodsList($filters=[], $select=[], $sorts=[], $pages=[], $type);

        $goodsInfo = [];
        $total = 0;
        $total_page = 0;
        if ('OK' == $data['status']){
            $goodsInfo = $data['result']['items'];
            $total = $data['result']['total'];
            $total_page = ceil($total/$pageSize);
            foreach ($goodsInfo as $kk=>$vv) {
            	$special = SpecialInfo::getSpecial($vv['id'],$this->user_info['id']);
            	if($special['status'] == 0) {
            		unset($goodsInfo[$kk]);
            	}
            }
        }
        
        //该用户可看商城
        $malls = StoreMall::getMalls($this->user_info['id']);
		foreach ($malls['yes_malls'] as $km=>$vm) {
        	if($vm['id'] == $mall_id) {
        		unset($malls['yes_malls'][$km]);
        	}
        }
         //f_d($this->user_info);
         // f_d($typeInfo);
		return $this->render('parts',[
			'modelInfo'=>$modelInfo,
			'model_id'=>$model_id,
			'typeInfo'=>$typeInfo,
			'type_id'=>$type_id,
			'brand_id'=>$brand_id,
			'brandInfo'=>$brandInfo,
			'attrInfo'=>$attrInfo,
			'page'=>$page,
			'total_page'=>$total_page,
            'total'=>$total,
			'attr_id'=>$attr_id,
			'priceRange'=>$priceRange,
			'sort'=>$sort,
			'keyword'=>$keywords,
			'goodsInfo'=>$goodsInfo,
            'mall_id'=>$mall_id,
            'city'=>$city,
            'filters'=>$filters,
            'flag'=>$flag,
            'user_info'=>$this->user_info,
				'malls'=>$malls,
            
		]);
	}

    /**
     * @desc 自动搜索
     */
    /**
     * @description: actionAutosearch
     * @author: jiangtao.ren and zend.wang
     * @email ：zendwang@qq.com
     * @date ：2015-12-30 15:11
     */
    public function actionAutosearch()
    {

        //获取参数，如果参数不为空，按条件搜索
//        if(!empty($_POST['searchKey'])){
//            $where = " where t1.name like '%".$_POST['searchKey']."%'";
//        }
        $class_key = intval(f_post('class_key',0));
        $keyWords = trim(strip_tags(f_post('searchKey','')));

        if($keyWords){
            $keyWords = str_replace(['\'','"', '\\'],'',$keyWords);
        }
		//设置缓存key值
		$cacheKey = 'auto-search-'.$class_key.'-'.$keyWords;
		if (f_c($cacheKey)) {
    		$cacheVal = f_c($cacheKey);
			 echo json_encode(['str'=>$cacheVal]);
		} else {
			$str = '';
			//拼接sql,获取前8个数据
			if ($class_key == 4) {
				$sql = "SELECT name FROM user_shops_info WHERE name LIKE '%{$keyWords}%'";
			} else {
				$condition = ' ';
				if ($class_key > 0) {
					$condition .= "  and t3.mall_id = {$class_key} ";
				}
				if ($keyWords) {
					$condition .= "  and t1.name like '%{$keyWords}%' ";
				}
				$sql = 'select t1.name,t3.mall_id from goods_base_goods as t1  left join goods_type t3 on t3.id=t1.type_id where t1.status=1 ' . $condition . ' order by t1.type_id asc,t1.weight desc limit 0,8';
			}

			$base_goods = BaseGoods::findBySql($sql)->asArray()->all();

			$hotwords = HotWord::getHotSearchType($keyWords);
			if ($hotwords) {
				foreach ($hotwords as $k => $val) {
					if ($class_key == 0 || $class_key == 2 || $class_key == 3) {
						$str .= "<li><a href='/list/parts?type=" . $val['type_id'] . "&keyword=" . $keyWords . "'>在 <b style='color:#fc6700'>" . $val['name'] . "</b> 分类中搜索</a></li>";
					} else if ($class_key == 1) {
						$str .= "<li><a href='/list/index?type=" . $val['type_id'] . "&keyword=" . $keyWords . "'>在 <b style='color:#fc6700'>" . $val['name'] . "</b> 分类中搜索</a></li>";
					} elseif ($class_key == 4) {
						$str .= "<li><a href='/shops/search?type=" . $val['type_id'] . "&keyword=" . $keyWords . "'>在 <b style='color:#fc6700'>" . $val['name'] . "</b> 分类中搜索</a></li>";
					}
				}
			}

			if ($base_goods) {
				foreach ($base_goods as $key => $value) {
					switch ($class_key) {
						case 0: {
							if ($value['mall_id'] == 1) {
								$str .= "<li><a href='/list/index?keywords=" . $value['name'] . "'>" . $value['name'] . "</a></li>";
							} else {
								$str .= "<li><a href='/list/parts?keywords=" . $value['name'] . "&mall_id={$value['mall_id']}'>" . $value['name'] . "</a></li>";
							}
							break;
						}
						case 2:
						case 3:
							$str .= "<li><a href='/list/parts?keywords=" . $value['name'] . "&mall_id={$value['mall_id']}'>" . $value['name'] . "</a></li>";
							break;
						case 1:
							$str .= "<li><a href='/list/index?keywords=" . $value['name'] . "'>" . $value['name'] . "</a></li>";
							break;
						case 4:
							$str .= "<li><a href='/shops/search?keywords=" . $value['name'] . "'>" . $value['name'] . "</a></li>";
							break;
					}
				}
			}
			if ($str) $str = "<ul>" . $str . "</ul>";
			f_c($cacheKey, $str, 600);
			echo json_encode(['str' => $str]);
		}
        exit;
    }

    /**
     * @description: 修改基础商品列表和供应商商品列表show_id字段
     * @author: qiang.zuo
     * @date: 2016-1-13 11:03:37
     */
    public function actionGetGoodsShowId(){
        $page = $_GET['page'];
        header("Content-type:text/html;charset=utf-8");
        if(isset($page)){
            $num = $page*20;
            echo $num;
            $sql = "SELECT DISTINCT
                    (t1.id)
                FROM
                    goods_base_goods AS t1
                LEFT JOIN goods_attr_extend AS t2 ON t1.id = t2.base_id
                WHERE
                    	t1.type_id = 1
                AND (
                    t2.attr_value_4 IN (318, 317, 321, 674)
                    OR t2.attr_value_5 IN (173, 172, 176, 354)
                    OR t2.attr_value_7 = 254
                ) limit ".$num.",20";
            $arr = BaseGoods::findBySql($sql)->asArray()->all();
            foreach($arr as $key=>$vo){
                $model = BaseGoods::findOne($vo['id']);
                $model->show_id = 1;
                if($model->save()){
                    echo "基础商品：".$vo['id']."成功"."<br/>";
                }else{
                    echo "基础商品：".$vo['id']."失败"."<br/>";
                }
            }
        }
    }
    public function actionClearGoodsShowId(){
        $page = $_GET['page'];
        header("Content-type:text/html;charset=utf-8");
        if(isset($page)){
            $num = $page*50;
            echo $num;
            $arr = (new Query())->select("distinct(t1.id)")->from("goods_base_goods AS t1")->leftJoin("goods_attr_extend AS t2","t1.id = t2.base_id")->leftJoin("goods_attr_name AS t3","t3.type_id = t1.type_id")->leftJoin("goods_attr_value AS t4","t3.id = t4.attr_name_id")
                ->where("t1.type_id = 2")->andWhere(["in","t4.id",[32, 31,173,172,176, 318, 317,321,254,354,674]])->andWhere("t3.status = 1")->offset($num)->limit("50")->all();
            foreach($arr as $key=>$vo){
                $model = BaseGoods::findOne($vo['id']);
                $model->show_id = 0;
                if($model->save()){
                    echo "基础商品：".$vo['id']."成功"."<br/>";
                }else{
                    echo "基础商品：".$vo['id']."失败"."<br/>";
                }
            }
        }
    }
    
    /**
     * @description:组合套餐列表
     * @return: return_type
     * @author: wufeng
     * @date: 2016年7月22日 下午3:46:13
     * @review_user:
     */
    public function actionPromotion() {
        header("Content-Type: text/html; charset=UTF-8");
        $this->view->title = '组合套餐';
        
        $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
        if(!$user_info) {
            $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        }
        $city = $user_info['city'];
        
		$att = $value = '';
		$select = $pages = $baseInfo = [];
		
		$filters = [
		    'mall_id' => 0,
		    'type_id' => 0,
		    'brand_id' => 0,
		    'is_brush' => null,
		    'key_words' => '',
		    'attr' => [],
		    'notin' => [],
		    'in' => [],
		    'not' => [],
		    'page' => f_get('page',1),
		];
		
		$total = $total_page = 0;
		$page = f_get('page',1);
		$page_size = 10;
		$start = ($page-1)*$page_size;
		
		$type_id = f_get('type',0);
        if(!preg_match('/^\d+$/', $type_id)) return f_msg('搜索条件不符合','/home/index');   
        
        $StoreMall = StoreMall::getMainMall($this->user_info['id']);

		$model_info = [];

        $mall_id = f_get('mall_id', $StoreMall);
        if(!preg_match('/^\d+$/',$mall_id)) return f_msg('搜索条件不符合','/home/index');
        
        $mallId = $mall_id;
        if($mall_id == 1){
        	$model_id = 0;
        }else{
        	$model_id = f_get('model_id',0);
        }
        $filters['mall_id'] = $mall_id;
        //$filters += ['mall_id'=>$mall_id];
        
        $modelInfo = [];
        $model_id_one = 0;

		if ($type_id != 0){
		    $filters['type_id'] = $type_id;
			//$filters += ['type_id'=>$type_id];
            $mall_id = Type::getTypeMall($type_id);
            $_GET['mall_id']=$mall_id;
		}
       
		$brand_id = f_get('brand',0);
		if ($brand_id != 0){
		    $filters['brand_id'] = $brand_id;
			//$filters += ['brand_id'=>$brand_id];
		}
		
		$attr_id = f_get('attr');
		if ($attr_id){
			$attrInfo = explode('-', $attr_id);
			$att = $attrInfo[0];
			if(isset($attrInfo[1])){
    			$value = $attrInfo[1];
    			$filters['attr'][] = ['t9.'.$att => $value];
    			//$filters += [$att=>$value];
            }
		}

		if (1 == $this->user_info['user_group']){
            if($city == 232) {
				$yiwang = BaseGoods::getYiWangGoods($this->user_info['id']);			
				//$filters += ['base_id' => ['notin', $yiwang]];
				$filters['notin'][] = ['t5.id' => $yiwang];
			}
			//$filters += ['type_id' => 1];
			//$filters += ['attr_value_5' => ['notin', [172,173,176]]];
			$filters['notin'][] = ['t9.attr_value_5' => [172,173,176]];
            //$filters += ['attr_value_7' => ['!=', 254]];
			$filters['not'][] = ['t9.attr_value_7' => 254];
		}
		$keyword = trim(strip_tags(mb_convert_encoding(f_get('keyword'),'UTF-8','auto')));
        $wjr_keywords = f_get('keywords','');
        if(!$keyword){
        	if($wjr_keywords){
        		$keyword = trim(strip_tags(mb_convert_encoding(f_get('keywords'),'utf-8','auto')));
        	}
        }
        //根据热搜词定向跳转
        if($keyword){
            $keyword = str_replace(['\'','"', '\\'],'',$keyword);
            
            $re = HotWord::getHotUrlByKeys($keyword,$mallId);
            if($re){
                $this->layout = false;
                return $this->redirect($re);
            }
        }
	
        if($mall_id != 1){
        	//顶级模块
        	//$modelInfo[0] = Module::partsGoodsModuleList(0);
        	$model_info = explode("_",$model_id);
        	$i = 0;
        	$t = 0;
        	$m_t=0;//用户最终选择的非0 的模块；
        	$z = 0;
        	foreach ($model_info as $key => $value) {
        		if($z == 0){
        			$modelInfo[$key]['list'] = Module::AllGoodsModuleList($mall_id);
        			$modelInfo[$key]['key'] = $value;
        		}else{
        			$modelInfo[$key]['list'] = Module::AllGoodsModuleList($mall_id,$i,2);
        			$modelInfo[$key]['key'] = $value;
        		}
        		$i = $value;
        		$t = $key;
        		if($value){
        			$m_t = $value;
        		}
        		$z++;
        	}
        	if($i){
        		$second_model = Module::partsGoodsModuleList($i);
        		if($second_model){
        			if($mall_id == 0){
        				$modelInfo[$t+1]['list'] = Module::AllGoodsModuleList($i);
        			}else{
        				$modelInfo[$t+1]['list'] = Module::partsGoodsModuleList($i);
        			}
        			$modelInfo[$t+1]['key'] = 0;
        		}
        	}
        	$modelIds = array_keys($modelInfo);//获取所有的KEY
        	//条件 根据model_id查找type_id
        	if($model_id != 0){
        		$model_info = explode("_",$model_id);
        		$module_zi = [];
        		$model_data = [];
        		$ty = [];
        		if(count($model_info) == 1){
        			$model_data = Module::find()->select('id')->where(['parent_id'=>$model_id])->asArray()->all();
        			if(empty($model_data)){
        				$model_data[] = ['id'=>$model_id];
        			}
        			foreach($model_data as $key=>$val){
        				$data = ModuleTypeRel::find()->select('type_id')->where(['module_id'=>$val['id']])->andWhere(['status'=>1])->asArray()->all();
        				$ty =array_merge($ty,$data);
        			}
        		}else{
        			if($model_info[1] == 0){
        				$module_zi1 = Module::find()->select('id')->where(['parent_id'=>$model_info[0]])->asArray()->all();
        				foreach($module_zi1 as $key=>$val){
        					$type0 = ModuleTypeRel::find()->select('type_id')->where(['in','module_id',$val['id']])->andWhere(['status'=>1])->asArray()->all();
        					$ty =array_merge($ty,$type0);
        				}
        			}else{
        				$ty += ModuleTypeRel::find()->select('type_id')->where(['in','module_id',$model_info[1]])->andWhere(['status'=>1])->asArray()->all();
        			}
        		}
        		$model_array = [];
        		foreach($ty as $key=>$val){
        			$model_array[] += $val['type_id'];
        		}
        		if($type_id != 0){
        			//$filters += ['type_id' => $type_id];
        		    $filters['type_id'] = $type_id;
        		}else{
        			if(!empty($model_array)){
        				//$filters += ['type_id' => ['in', $model_array]];
        				$filters['in'][] = ['t7.id' => $model_array];
        			}
        		}
        	}else{
        		if($type_id != 0){
        			//$filters += ['type_id' => $type_id];
        			$filters['type_id'] = $type_id;
        		}
        	}
        	$model_id_one =  end($model_info);
        }
        $searchResult = HotWord::getGoodsByKeywords($keyword);
        
        $flag = 0;
        if($searchResult){
        	$flag = 1;
        }
        if($searchResult){
            $typeInfo = KeywordsTypeRelationship::getTypeListByKeywords($searchResult,$mall_id,$keyword);
            if(empty($typeInfo)){
                if($mall_id == 1){
                	$typeInfo = GoodsMallInfo::getAllTypeByMallAsc($mall_id);
                }else{
                	$flag = 0;
                	$typeInfo = Type::getTypeListByModule($m_t,false,'',$mall_id);
                }
            }
        }else{
            if($mall_id == 1){
            	$typeInfo = GoodsMallInfo::getAllTypeByMallAsc($mall_id);
            }else{
            	$typeInfo = Type::partsGetTypeListByModule($m_t,$mall_id);
            }
        }
        
        
		$is_intact = f_get('is_intact',0);
		if ($is_intact){
			//$filters += ['is_brush'=>0];
			$filters['is_brush'] = 0;
            //$filters += ['type_id' => 1];
			$filters['type_id'] = 1;
		}
		
		if ($keyword){
			//$filters += ['key_words'=>$keyword];
			$filters['key_words'] = $keyword;
			$v = f_ck('key_words');
			if ($v) {
				$keywords = json_decode($v);
				if (!$keywords){
					$keywords = [];
				}
				if (count($keywords) < 10) {
					array_push($keywords, $keyword.'-'.$mall_id);
					f_ck('key_words', json_encode($keywords));
				}
			} else {
				f_ck('key_words', json_encode([$keyword.'-'.$mall_id]));
			}
		}
		$attrInfo = AttrName::getExtendAttr($type_id);
        
        if($searchResult){
            $brandInfo =  KeywordsBrandRelationship::getBrandListByKeywords($searchResult);
            if(empty($brandInfo)){
                if($mall_id == 1){
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAsc($mall_id);
                }else{
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAndModuleAsc($mall_id,$m_t);
                }
            }
        }else {
            if($type_id != 0){
                if($mall_id == 1){
                	$brandInfo = GoodsMallInfo::getAllBrandByMallTypeAsc($mall_id,$type_id);
                }else{
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAndModuleTypeAsc($mall_id,$type_id,$m_t);
                }
            }else{
            	if($mall_id == 1){
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAsc($mall_id);
                }else{
                	$brandInfo = GoodsMallInfo::getAllBrandByMallAndModuleAsc($mall_id,$m_t);
                }
            }
        }

  		//$filters += ['city'=>$this->user_info['city']];
        //移动用户屏蔽功能机
        $user_group = 0;
        $user_group = $this->user_info['user_group'];
        
        if($user_group == 1){
        	//移动分站，屏蔽功能机（部分地区例外）[40=>'滁州']
        	if(in_array($this->user_info['city'],[40])) {
        		
        	} else if(isset($filters['type_id']) && $filters['type_id'] == 2){
        		unset($filters['type_id']);
        	}
        	//$filters += ['show_id' => ['!=', 1]];
        	$filters['not'] = ['t4.show_id' => 1];
        	//$filters += ['type_id' => ['!=', 2]];
        	$filters['not'] = ['t7.id' => 2];
        	
        //开放站屏蔽功能机 ['41'=>阜阳]
        } else if(in_array($this->user_info['city'],[41])){
//         	if(($filters['type_id']) && $filters['type_id'] == 2) {
//         		unset($filters['type_id']);
//         		$filters += ['type_id' => ['!=', 2]];
//         	}
        }
        //空字符串 unset空的字符串  抑制报错
        /*
        if(isset($filters['key_words'])) {
        	if(!$filters['key_words']){
        		unset($filters['key_words']);
        	}
        }
        */
        //屏蔽商品
        $ScreenGoods = GoodsRestCityGoods::getScreenGoods($city);
        
        if(!empty($ScreenGoods)){
            //$filters +=['base_id' => ['notin',$ScreenGoods]];
            $filters['notin'][] = ['t5.id' => $ScreenGoods];
        }

  		//$select = ['base_id','goods_name','cover_id','keywords','bottom_price'];
  		$pages = ['limit' => $page_size,'offset' =>$start];
  		$type = 'mobile';
  		
  		//$data = BaseGoods::getGoodsList($filters,[],$sorts,$pages,$type);
  		
  		$data = PromotionCombineGoods::getListOfPromotion($city, $user_id, $filters);
  		
  		if ($data['list']){
  			$total = $data['pager']->totalCount;
  			$total_page = ceil($total / $page_size);
  		}
  		
  		$malls = StoreMall::getMalls($this->user_info['id']);
  		foreach ($malls['yes_malls'] as $km=>$vm) {
  			if($vm['id'] == $mall_id) {
  				unset($malls['yes_malls'][$km]);
  			}
  		}
  		//未收录热词
  		if($mall_id && $keyword) {
  			NewHotWord::charu($keyword, $mall_id);
  		}
  		$this->layout = 'main';
		return $this->render('promotion',['type_id'=>$type_id,
				'typeInfo'=>$typeInfo,
				'brand_id'=>$brand_id,
				'brandInfo'=>$brandInfo,
				'baseInfo'=>$baseInfo,
				'attrInfo'=>$attrInfo,
				'page'=>$page,
				'total_page'=>$total_page,
				'attr_id'=>$attr_id,
				'keyword'=>$keyword,
				'total'=>$total,
				'is_intact'=>$is_intact,
                'mall_id'=>$mall_id,
				'mall_name'=>MallBase::getMallName($mall_id),
                'user_group'=>$user_group,
                'user_info'=>$this->user_info,
				'malls'=>$malls,
				'modelInfo'=>$modelInfo,
				'flag'=>$flag,
				'model_id'=>$model_id_one,
				'model_info' => $model_info,
		        'promotion' => $data['list'],
		]);
    }
    
    
    /*
     * @description : 根据type_id 获取下面多有属性以及属性值
     * @author : lxzmy
     * @date : 2016-2-3 14:12
     */
//    public function actionGetAllAttrByTypeId(){
//        //$type_id = f_get('type_id',0);
//        $type_id = 97;
//        if($type_id){
//            $attrInfo = AttrName::getExtendAttr($type_id);
//            if($attrInfo){
//                foreach($attrInfo as $k=>$v){
//                    $attrInfo[$k] = $v;
//                }
//            }
//            f_d($attrInfo);
//        }else{
//            return false;
//        }
//        return $attrInfo;
//    }
}
