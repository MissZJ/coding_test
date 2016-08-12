<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use frontend\components\Controller2016;
use common\models\goods\SupplierGoods;
use common\models\other\OtherMainMenu;
use common\models\user\GoodsPrivilege;
use common\models\user\GoodsPrivilegeUser;
use yii\data\ActiveDataProvider;
use yii\db\Query;

class PrivilegeGoodsController extends Controller2016
{ 
	protected  $user_info;
	
	public function init(){
		$this->user_info = f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0');
		parent::init();
	}
	
    /*
    *@description:特权商品
    *@return:string 
    *@author:honglang.shen
    *@date:2016-2-16
    */
    public function actionIndex(){
        $this->layout = '_blank';
        $privilege = f_get('privilege',0);
        $page = f_get('page',1);
        $page_size = 20;
        $start = ($page-1)*$page_size;
        $sort = f_get('sort',0);
        $search = f_get('search','');
        $city = $this->user_info['city'];
        $user_id = $this->user_info['id'];
        $ifPrivilege = GoodsPrivilegeUser::ifUserPrivilege($user_id,$privilege);
        if(empty($ifPrivilege)){
            f_msg('对不起，您没有该购买特权！','/');
        }
        $ad = GoodsPrivilege::find()->select('img_url,link')->where(['id'=>$privilege])->asArray()->one();
        $menu_heng = OtherMainMenu::getMainMenu(1, $this->user_info['city']);

        $query =new Query();
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        $query->select('DISTINCT(b.id),s.supplier_id,min(s.price) price,b.name,b.cover_id,c.depot_id');
        $query->from('goods_depot_city c');
        $query->leftJoin('goods_supplier_goods s','s.depot_id=c.depot_id');
        $query->leftJoin('goods_base_goods b','s.base_id=b.id');
        $query->leftJoin('user_goods_privilege p','p.id=s.privilege');
        $query->leftJoin('user_goods_privilege_user u','u.privilege_id=p.id');
        $query->where(['b.status'=>1,'s.status'=>1,'s.enable'=>1,'p.status'=>1,'u.status'=>1,'c.city'=>$city,'u.user_id'=>$user_id,'s.privilege'=>$privilege]);
        $query->andWhere(['>','s.num_avai',0]);
        if(!empty($search)){
            $query->andWhere(['like','b.name',$search]);
        }
        $query->groupby('b.id');
        if (1 == $sort){
            $query->orderby('s.price ASC');
        }else if(0 == $sort){
            $query->orderby('s.price DESC');
        }
        $query->limit($start,$page_size);

        $goodsInfo = $dataProvider->getModels();
        $total = $dataProvider->getTotalCount();
        $total_page = ceil($total/$page_size);

        return $this->render('index',['goodsInfo'=>$goodsInfo,
           'total'=>$total,
           'page'=>$page,
           'total_page'=>$total_page,
           'sort'=>$sort,
           'menu_heng'=>$menu_heng,
           'user_info'=>$this->user_info,
           'ad'=>$ad,
           'search' => $search,
           'privilege' => $privilege,
        ]);
    }
}
