<?php
namespace frontend\controllers;
use frontend\components\Controller2016;
use Yii;
use frontend\models\LoginForm;
use yii\db\Query;
use common\models\user\ClubShare;
use common\models\user\ClubHonor;
use common\models\BaseSupplierGoods;
use common\models\user\UserMember;
use common\models\user\WealthDetail;
use common\models\user\Sign;
use common\models\SellAdInfo;
use ms\modules\article\models\ArticleInfo;
use common\models\other\AdInfo;

class MemberClubController extends Controller2016
{
	/**
	 * @description:会员俱乐部首页
	 * @author: wei.xie honglang.shen
	 * @date: 2015-11-19
	*/
	public function actionIndex(){
		header("Content-type:text/html;charset=utf-8");
		$this->layout='club_main';
		$user_id = \Yii::$app->user->id;
      	$user_message = f_c('frontend-'.$user_id.'-new_51dh3.0');
        	if(!$user_message) {
          	$user_message = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        }

      	$city_id = $user_message['city'];

		$userInfo = WealthDetail::findUserInfo($user_id);
		$city_id = $user_message['city'];
		$touxiang_src = $user_message['touxiang'];
		$level = $userInfo['level'];
		//f_d($level);
		$exp_value = ceil($userInfo['exp_value']);

		$ad['banner'] = AdInfo::getAd(53,$city_id);  //大banner图
		$ad['banner_min'] = AdInfo::getAd(54,$city_id,3);  //banner图下面三个

		//是否签到
		$sign= 0;
		$sign = WealthDetail::find()->where(['user_id'=>$user_id,'type'=>2])->andWhere(['>','add_time',date('Y-m-d',time()).' 00:00:00'])->andWhere(['<','add_time',date('Y-m-d',time()).' 23:59:59'])->asArray()->one();
		if($sign){
			$sign = 1;
		}
		//当前的签到信息
		$sign_info = Sign::getUserSignInfo();
		$continues = $sign_info['continues'];
		switch ($level) {
			case 1:
				$level_src = '/images/member/level/levelbig1.png';
				$next_level = '铜牌会员';
				$next_level_src = '/images/member/level/level02.png';
				$percent = ($exp_value/200)*100;
				break;
			case 2:
				$level_src = '/images/member/level/levelbig2.png';
				$next_level = '银牌会员';
				$next_level_src = '/images/member/level/level03.png';
				$percent = ($exp_value/500)*100;
				break;
			case 3:
				$level_src = '/images/member/level/levelbig3.png';
				$next_level = '金牌会员';
				$next_level_src = '/images/member/level/level04.png';
				$percent = ($exp_value/5000)*100;
				break;
			case 4:
				$level_src = '/images/member/level/levelbig4.png';
				$next_level = '金牌会员';
				$next_level_src = '/images/member/level/level04.png';
				$percent = 100;
				break;
		}

		//防止用户财富值大，但是等级没变，出现大于100%情况
		if($percent>100){
			$percent=100;
		}
		//我的特权数量以及具体的特权
		$pro_id= UserMember::find()->select('province')->where(['id'=>$user_id])->asArray()->one();
		//f_d($pro_id['province']);
		$tequan_info_old = ClubShare::getTequan($pro_id['province'],$level);
		//f_d($tequan_info_old);
		$tequan  =$tequan_info_old[0];
		$my_tequan = $tequan_info_old[1];
		$the_ids = $tequan_info_old[2];
		$tequan_info1 = ClubShare::getTequanByType($the_ids,0);
		//f_d($tequan_info1);
		$tequan_info = [];
		for($i=0;$i<ceil(count($tequan_info1)/5);$i++){
			$tequan_info[] =  array_slice($tequan_info1,$i*5,5);
		}
		//我的勋章(已拥有，未拥有)
		$hornor_info = ClubHonor::getHornorInfoById($user_id);
		$own_hornor = $hornor_info['all'];
		$my_own1 = $hornor_info['own'];
		$all_count = count($own_hornor);
		$my_count = count($my_own1);
		$no_count = $all_count-$my_count;
		$no_own = $hornor_info['no_own'];
		$my_own = [];
		for($i=0;$i<ceil(count($my_own1)/3);$i++){
			$my_own[] =  array_slice($my_own1,$i*3,3);
		}

		return $this->render('index',[
			'user_info'=>$userInfo,
			'level'=>$level,
			'exp_value'=>$exp_value,
			'continues'=>$continues,
			'sign'=>$sign,
			'next_level'=>$next_level,
			'level_src'=>$level_src,
			'next_src'=>$next_level_src,
			'tequan'=>$tequan,
			//'article_info'=>$article_info,
			'ad'=>$ad,
			'tequan_info'=>$tequan_info,
			'touxiang_src'=>$touxiang_src,
			'percent'=>$percent,
			'my_count'=>$my_count,
			'all_count'=>$all_count,
			'no_own'=>$no_own,
			'my_own'=>$my_own,	
		]);
	}
	/**
	 * @Description: 会员特权
	 * @return: return_type
	 * @author: wei.xie honglang.shen
	 * @date: 2015-11-19
	 * @review :
	 */
	public function actionPrivileges(){
		$this->layout = 'club_main';
		$user_id = \Yii::$app->user->id;
		$userInfo = WealthDetail::findUserInfo($user_id);
		$exp_value = ceil($userInfo['exp_value']);
		$touxiang_src = UserMember::find()->where(['id'=>$user_id])->one()->touxiang;
		$city = UserMember::find()->where(['id'=>$user_id])->one()->city;
		
		$ad = AdInfo::getAd(55,$city,1);
		$ad_info = $ad[0];
		//享有的基础特权
		$tequan = UserMember::find()->select('province')->where(['id'=>$user_id])->asArray()->one();
		$level = $userInfo['level'];
		$tequan_info = ClubShare::getTequan($tequan['province'],$level);
		$tequan  =$tequan_info[0];
		$my_tequan = $tequan_info[1];
		$the_ids = $tequan_info[2];
		//特权分类
		$t_info = [];
		$t_info[] = ClubShare::getTequanInfo(1,$my_tequan);
		$t_info[] = ClubShare::getTequanInfo(2,$my_tequan);
		$t_info[] = ClubShare::getTequanInfo(3,$my_tequan);
		$t_info[] = ClubShare::getTequanInfo(4,$my_tequan);
		//f_d($t_info);
		//基础特权
		$base_tequan = ClubShare::getTequanByType($the_ids,1);
		//采购特权
		$caigou_tequan = ClubShare::getTequanByType($the_ids, 2);
		//增值特权
		$extra_tequan = ClubShare::getTequanByType($the_ids, 4);
		return $this->render('privileges',[
			'user_info'=>$userInfo,
			'touxiang_src'=>$touxiang_src,
			'ad_info'=>$ad_info,
			'b_tequan'=>$base_tequan,
			'c_tequan'=>$caigou_tequan,
			'e_tequan'=>$extra_tequan,
			't_info'=>$t_info,
			'tequan'=>$tequan,		
		]);
	}
	/**
	 * @Description: 会员等级
	 * @return: return_type
	 * @author: wei.xie honglang.shen
	 * @date: 2015-11-19
	 * @review :
	 */
	public function actionLevel(){
		$this->layout = 'club_main';
		$user_id = \Yii::$app->user->id;
		$userInfo = WealthDetail::findUserInfo($user_id);
		$exp_value = floor($userInfo['exp_value']);
		$touxiang_src = UserMember::find()->where(['id'=>$user_id])->one()->touxiang;
		$level = $userInfo['level'];
		$tequan = UserMember::find()->select('province')->where(['id'=>$user_id])->asArray()->one();
		$tequan_info_old = ClubShare::getTequan($tequan['province'],$level);
		$tequan  =$tequan_info_old[0];
		//当前的签到信息
		$sign_info = Sign::getUserSignInfo();
		$continues = $sign_info['continues'];
		switch ($level) {
			case 1:
				$level_src = '/images/member/level/level01.png';
				$next_level = '铜牌会员';
				$next_level_src = '/images/member/level/level02.png';
				$percent = ($exp_value/200)*0.3*100;
				$remain = 200-$exp_value;
				$pass = 57;
				break;
			case 2:
				$level_src = '/images/levelbig2.png';
				$next_level = '银牌会员';
				$next_level_src = '/images/member/level/level03.png';
				$percent = (($exp_value-200)/300 +0.3)*100;
				$remain = 500-$exp_value;
				$pass = 67;
				break;
			case 3:
				$level_src = '/images/member/level/level03.png';
				$next_level = '金牌会员';
				$next_level_src = '/images/member/level/level04.png';
				$percent = (($exp_value-500)/4500 +0.63)*100;
				$remain = 5000-$exp_value;
				$pass = 75;
				break;
			case 4:
				$level_src = '/images/member/level/level04.png';
				$next_level = '金牌会员';
				$percent = 100;
				$remain = 0;
				$pass = 87;
				break;
		}
		return $this->render('level',[
			'user_info'=>$userInfo,
			'touxiang_src'=>$touxiang_src,
			'level'=>$level,
			'next_level'=>$next_level,
			'level_src'=>$level_src,
			'tequan'=>$tequan,
			'continues'=>$continues,
			'percent'=>$percent,
			'exp_value'=>$exp_value,
			'remain_value'=>$remain,
			'pass'=>$pass,
		]);
	}
	public function actionMoney(){
		$this->layout = 'club_main';
		//会员信息
		$user_id = \Yii::$app->user->id;
		$userInfo = WealthDetail::findUserInfo($user_id);
		//f_d($userInfo);
		$exp_value = ceil($userInfo['exp_value']);
		$touxiang_src = UserMember::find()->where(['id'=>$user_id])->one()->touxiang;
		//会员今日财富值变化
		//当前day的零点
		$now_day = date("Y-m-d ",time());
		$now_day_zero = $now_day."00:00:00";
// 		f_d($now_day_zero);
		//当前时间
		$now_time = date("Y-m-d H:i:s",time());
		$sql = "select * from user_wealth_detail where user_id = '$user_id' && add_time > '$now_day_zero' && add_time < '$now_time'";
		$res = WealthDetail::findBySql($sql)->asArray()->all();//获取今日的所有流水
		if($res){
			$caifu = 1;
		}else{
			$caifu = 0;
		}
		//获取财富值流水
		$models = [];
		$rows = WealthDetail::getWealthDetail($user_id);
		$pages  =$rows['pages'];
		$pages = \yii\widgets\LinkPager::widget([
				'pagination' => $pages,
				'firstPageLabel' => '首页',
				'prevPageLabel' => '上一页',
				'nextPageLabel' => '下一页',
				'lastPageLabel' => '尾页',
		]);
		foreach($rows as $vo){
			if(!is_object($vo)){
				$models[] = $vo;
			}
		}
		return $this->render('money',[
				'models'=>$models,
				'pages'=>$pages,
				'touxiang_src'=>$touxiang_src,
				'exp_value'=>$exp_value,
				'user_info'=>$userInfo,
				'caifu'=>$caifu,
		]);
	}
	/**
	 * @Description: 会员勋章
	 * @return: return_type
	 * @author: honglang.shen
	 * @date: 2015-12
	 * @review :
	 */
	public function actionHonor(){
		$this->layout = "club_main";
		$user_id = \Yii::$app->user->id;
		$userInfo = WealthDetail::findUserInfo($user_id);
		
		$touxiang_src = UserMember::find()->where(['id'=>$user_id])->one()->touxiang;
		//获取用户当前未拥有,所拥有的勋章
		$hornor_info1 = ClubHonor::getHornorInfoById($user_id);
		//f_d($hornor_info1);
		$own_hornor = $hornor_info1['all'];
		$my_own = $hornor_info1['own'];
		$no_own1 = [];
		$no_own = [];
		if($hornor_info1['no_own']){
			foreach($hornor_info1['no_own'] as $value_no){
				$each_no = ClubHonor::find()->select('type,name,tips')->where(['type'=>$value_no])->asArray()->one();
				$no_own1[] = $each_no;
			}
			for($i=0;$i<ceil(count($no_own1)/3);$i++){
				$no_own[] =  array_slice($no_own1,$i*3,3);
			}
		}
		$new_count = count($no_own);
		//f_d($no_own);
		//勋章总数量,已经获得，未获得数量
		$all_count = count($own_hornor);
		$my_count = count($my_own);
		$no_count = $all_count-$my_count;
		//数组处理
		$all_hornor_info = [];
		for($i=0;$i<ceil(count($own_hornor)/8);$i++){
			$all_hornor_info[] =  array_slice($own_hornor,$i*8,8);
		}
		return $this->render('honor',[
				'user_info'=>$userInfo,
				'touxiang_src'=>$touxiang_src,
				'hornor_info'=>$hornor_info1,
				'all_hornor' =>$all_hornor_info,
				'my_count'=>$my_count,
				'no_count'=>$no_count,
				'all_count'=>$all_count,
				'my_own'=>$my_own,
				'no_own'=>$no_own,
				'new_count'=>$new_count,
		]);
	}
	/**
     * @Description: 晒单专区
     * @return: return_type
	 * @author: wei.xie honglang.shen
	 * @date: 2015-11-19
     * @review :
     */
	public function actionShare(){
		$this->layout = 'club_main';
		$type=f_get('type',1);
		if($type ==1){
			$type_title = '全部帖子';
		}elseif($type==2){
			$type_title = '热门帖';
		}elseif($type==3){
			$type_title = '精华帖';
		}
		//获取用户60天内交易成功的订单(同一个base_id下的商品取一个)
		$order_info = ClubShare::getSuccessOrder();
		// f_d($order_info);
		return $this->render('share',[
				'type'=>$type,
				'type_title'=>$type_title,
				'order_info'=>$order_info,
			]);
	}
	/**
     * @Description: 晒单专区详情页
     * @return: return_type
     * @author: wei.xie
     * @date: 2015年9月9日15:10:10
     * @review :
     */
	public function actionComment(){
		$this->layout = 'blank';
		$goods_info = BaseSupplierGoods::find()->select('id,color,name')->where(['id'=>f_get('id')])->asArray()->one();
		return $this->render('comment',[
				'goods_info'=>$goods_info,
			]);
	}
	/**
	 * @Description: 用户晒单详情页
	 * @return: return_type
	 * @author: wei.xie honglang.shen
	 * @date: 2015-11-19
	 * @review :
	 */
	public function  actionDetails(){
		//$this->layout='index2';
		$type=f_get('type');
		if($type ==1){
			$type_title = '全部帖子';
		}elseif($type==2){
			$type_title = '热门帖';
		}elseif($type==3){
			$type_title = '精华帖';
		}
		$share_id = f_get('id');
		$share_info = ClubShare::find()->where(['id'=>$share_id,'status'=>1])->asArray()->one();
		$click_num = $share_info['click_num'];
		$user_id = $share_info['user_id'];
		$user_name = UserMember::find()->where(['id'=>$share_info['user_id']])->one()->user_name;
		$goods_info = SupplierGoods::find()->select('id,cover_id,price,name')->where(['id'=>$share_info['goods_id']])->asArray()->one();
		$share_other = MemberClubShare::find()->select('id,title,type')->where(['user_id'=>$user_id,'status'=>1])->andWhere(['!=', 'id',$share_id])->limit(3)->asArray()->all();
		//浏览量加一
		$model = MemberClubShare::find()->where(['id'=>$share_id,'status'=>1])->one();
		$model->click_num = $click_num+1;
		$model->save();
		return $this->render('details',[
				'share_info'=>$share_info,
				'type_title'=>$type_title,
				'type'=>$type,
				'user_name'=>$user_name,
				'goods_info'=>$goods_info,
				'share_other'=>$share_other,
				'share_id'=>$share_id,
		]);
	}
	/**
	 * @Description: 用户晒单
	 * @return: return_type
	 * @author:wei.xie
	 * @date: 2015年9月14日15:46:54
	 * @review :
	 */
	public function actionGetComment(){
		header("Content-type:text/html;charset=utf-8");
		$user_id = $_POST['user_id'];
		$title = $_POST['title'];
		$content = $_POST['content'];
		$share_file = $_FILES['sharefile'];
	}
	/**
	 * @Description:按类型查询帖子
	 * @return: return_type
	 * @author:wei.xie honglang.shen
	 * @date: 2015-11-20
	 * @review :
	 */
	public function actionGetShare(){
		$data_i = f_post('data_i');
		//1--全部帖子2---热门帖子3--精华帖子
		if($data_i==1){
			$share_info = ClubShare::find()->where(['type'=>1,'status'=>1])->asArray()->all();
		}elseif($data_i==2){
			$share_info = ClubShare::find()->where(['type'=>2,'status'=>1])->asArray()->all();
		}else{
			$share_info = ClubShare::find()->where(['type'=>3,'status'=>1])->asArray()->all();
		}
		$out = "";
		if($share_info){
			foreach($share_info as $key=>$value){
				$out.="<li class='item'>
                    <a href='/member-club/details?id=".$value['id']."&&type=".$data_i."' class='a-img'>
                        <img src='/images/member/img1.jpg'/>
                    </a>
                    <div class='v_body'>
                        <div class='v_body_title'>
                            <div class='title_img'>
                                <img src='/images/member/tx.png'/>
                            </div>
                            <div class='title_p'>
                                <p class='title_p_p1'>昵称昵称</p>
                                <p class='title_p_p2'><a href='#'>".$value['title']."</a></p>
                            </div>
                        </div>
                        <div class='v_body_dp'>
                           ".$value['content']."
                        </div>
                        <div class='v_body_zan'>
                            <div class='v_body_zan_div'>
                                <img src='/images/member/zan.png'/><span>".$value['praise']."</span>人觉得很赞
                            </div>
                        </div>
                    </div>
                </li>";
			}
			return $out;
		}else{
			return '暂无帖子';
		}
	}
	/**
	 * @Description:实时更新点赞数量
	 * @return: return_type
	 * @author:wei.xie
	 * @date: 2015年9月15日15:54:01
	 * @review :
	 */
	public function actionGetPraiseNum(){
		$praise = f_get('num');
		$share_id = f_get('id');
		$model = ClubShare::find()->where(['id'=>$share_id,'status'=>1])->one();
		$model->praise = $praise+1;
		$model->save();
		if($model->save()){
			$out = $praise+1;
		}else{
			$out = '未知错误';
		}
		return $out;
	}
	
}