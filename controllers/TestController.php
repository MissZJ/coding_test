<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use kaikaige\tools\search\OpenSearchClient;
use kaikaige\tools\search\CloudsearchIndex;
use kaikaige\tools\search\CloudsearchSearch;
use kaikaige\tools\search\CloudsearchSuggest;
use kaikaige\tools\Mqs;
use kaikaige\tools\mqs\Message;
use kaikaige\tools\mqs\Queue;
use kaikaige\tools\mns\Mns;
use common\models\goods\BaseGoods;
use yii\base\Exception;
use common\models\user\UserMember;

/**
 * Site controller
 */
class TestController extends Controller
{
    public function actionCall() {
        try {
            $return = call_user_func(TestController::className().'::test');
        } catch (\Exception $e) {
            f_d($e);
        }
    }
    
    public function actionTest() {
        $goods = BaseGoods::getGoodsList(['status' => 1], [], [], []);
        f_d($goods); 
    }
    
    public function actionGet($goods_id) {
        $m = \common\models\goods\SupplierGoods::compareGoodsPrice($goods_id);
        f_d($m);
    }

    public function actionGets($city_id) {
        $m = \common\models\goods\DepotCity::getDepotsByCache($city_id);
        f_d($m);
    }
    public function actionGetst($city_id) {
        $key = md5('depot-'.$city_id);
        \Yii::$app->cache->set($key,null);
    }
    
     public function actionGo() {
//         $m = new \common\models\other\OtherRegion;
//         $s = $m->find()->where(["region_type"=>2])->asArray()->all();
//         foreach ($s as $key => $value) {
//              $key = md5('depot-'.$value['region_id']);
//            \Yii::$app->cache->set($key,null);
//         }
         
       set_time_limit(300000000); 
         $m = new \common\models\goods\Depot; 
         $info = $m->findBySql("SELECT
	id,base_id
FROM
	goods_supplier_goods
WHERE`status` = 1
AND `enable` = 1
AND is_deleted = 0
AND price > 0
AND num_avai > 0
GROUP BY base_id")->asArray()->all();
         foreach ($info as $key => $value) {
            $m = \common\models\goods\SupplierGoods::compareGoodsPrice($value['id']);
            echo $m;
            echo $key;
            echo "<br />";
         }
         
         
    }

    
    public static function actionTest2() {
        $goods = BaseGoods::getGoodsList($filters = ['key_words' => '苹果iPhone6S 16G公开版(A1700)', 'city' => 220], $select = [], $sort = [], $page = [], $type = 'mobile');
        f_d($goods);
    }
    
    public function actionSearchGoods() {
        $filters = [
//                 'key_words' => '华为商场',
//                 'id' => 3772,
//                 'city' => 231,
//             'goods_name' => ' ',
//             'status' => 1, 
//             'status' => ['!=' ,1],
//             'base_id' => ['in', [176]],
            'price' => [['>', 99], ['<', 1000]],
        ];
        $select = [];
//         $select = ['id','goods_name', 'bottom_price','type_id', 'base_id'];
        $sort = [
//             'base_id' => '-',
//             'bottom_price' => '-',
            
        ];
        $page = [
            'limit' => 50,
            'offset' => 10
        ];
        $goods = BaseGoods::getGoodsList($filters, $select, $sort, $page, 'not_mobile');
//         $goods = BaseGoods::getGoodsList();
        f_d($goods);
    }
    public function actionMqs() {
        $q = new Mns();
        f_d($q->GetQueueAttributes('order'));
//         $q = new Queue();
//         $ret = $q->Getqueueattributes('order');
//         f_d($ret);
//         $q->Createqueue('t');
//         f_d($q);
        $mqs = new Message();
        $queue_name = 'order';
        f_d($mqs);
        $mqs->SendMessage($queue_name, 'ceshi');
        f_d($mqs);
    }
    
    public function actionSearch() {
        header('Content-type:text/html;charset=utf8');
        $client = new OpenSearchClient();
        $search_obj = new CloudsearchSearch($client);
        $search_obj->addIndex('new_goods');
        
        
        // 指定返回的搜索结果的格式为json
        $search_obj->setFormat("json");
        
        //         $search_obj->setHits(50);
        //         $search_obj->setQueryString("qp:tyc");
        
        $search_obj->setQueryString("list_search:5600");
        
        // 执行搜索，获取搜索结果
        $json = $search_obj->search();
        // 将json类型字符串解码
        $result = json_decode($json,true);
        echo '<pre>';
        print_r($result);
    }
    public function actionOpen() {
        header('Content-type:text/html;charset=utf8');
        $client = new OpenSearchClient();
        $search_obj = new CloudsearchSearch($client);
        $search_obj->addIndex('new_goods');
        
        
        // 指定返回的搜索结果的格式为json
        $search_obj->setFormat("json");
        
//         $search_obj->setHits(50);
//         $search_obj->setQueryString("qp:tyc");
        
//         $search_obj->setQueryString("list_search:摩托");
        $suggest = new CloudsearchSuggest($client);
        $suggest->setIndexName('new_goods');
        $suggest->setSuggestName('xiala');
        $suggest->setQuery('zn');
        $json = $suggest->search();
        $result = json_decode($json,true);
        echo '<pre>';
        print_r($result);
    } 
    
    
    public function actionGetdeopt($id){
        $model = new \common\models\goods\GoodsDepotCity();
        $sql = "SELECT * from goods_depot_city WHERE depot_id = 7";
        $r = $model->findBySql($sql)->asArray()->all();
        foreach ($r as $key => $value) {
            $m = new \common\models\goods\GoodsDepotCity();
            $m->depot_id = $id;
            $m->province = $value['province'];
            $m->city = $value['city'];
            $m->time_line = $value['time_line'];
            $m->save();
        }
    }
    
    public function actionGetCp($goods_id){
        $re = \common\models\goods\SupplierGoods::compareGoodsPrice($goods_id);
        var_dump($re);
    
    }
    
    public function actionRjt()
    {
    	$sql = "DELETE from user_member WHERE id = 244053";
    	$re = \Yii::$app->db->createCommand($sql)->execute();
    	f_d($re);
    	return $this->render('import');
    }
    
}
