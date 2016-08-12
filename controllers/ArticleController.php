<?php
namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use frontend\components\Controller2016;
use common\models\other\ArticleNav;
use common\models\other\ArticleInfo;
use common\models\other\HotChannelRank;
use common\models\other\HotSearchRank;
use common\models\other\HotPkRank;
use common\models\other\HotSellingRank;
use common\models\other\ArticleJoinMes;
use common\models\other\OtherRegion;
use common\models\other\AdInfo;
use yii\helpers\Json;
use yii\db\Query;
use yii\data\Pagination;
use common\models\other\ExportLog;
/**
 * Site controller
 */
class ArticleController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [];
    }
        /**
   * @description:热销行情
   * @author: honglang.shen
   * @date: 2015-12-18
   * @modified_user:  sunkouping
   */
  public function actionHot(){
    $this->layout='_article';
    $ad_big = ArticleInfo::getArticleAd();
    //挂件配图区
    $ad_info = f_c('ad_info_login');
    if(!$ad_info) {  
      $ad_info = [];
        $ad_info = (new \yii\db\Query())->from("{{other_ad_position}} a")
        ->leftJoin("{{other_ad_info}} b","b.position_id=a.id")
        ->where(['a.id'=>59,'a.is_deleted'=>0,'b.status'=>1])
        ->orderBy('b.sort desc')
        ->all();
      f_c('ad_info_login',$ad_info,3600*12);
    }
    //获取手机厮杀排行榜等等
    $channel_info = HotChannelRank::find()->orderby('rank asc')->limit(10)->asArray()->all();
    $search_info = HotSearchRank::find()->orderby('rank asc')->limit(10)->asArray()->all();
    $pk_info = HotPkRank::find()->orderby('rank asc')->limit(10)->asArray()->all();
    $selling_info = HotSellingRank::find()->orderby('rank asc')->limit(10)->asArray()->all();
    return $this->render('hot',[
        //'nav_info'=>$nav_info,
        'channel_info'=>$channel_info,
        'search_info'=>$search_info,
        'pk_info'=>$pk_info,
        'selling_info'=>$selling_info,
        'ad_big'=>$ad_big,
        'hot_ad'=>$ad_info,
    ]);
  }
    /**
   * @description:招商加盟
   * @return:
   * @author: honglang.shen
   * @date: 2015-12-19
   * @modified_user: sunkouping
   */
  public function actionJoin(){
    $this->layout='_article';
    //获取大图区
    $ad_big = ArticleInfo::getArticleAd();
    return $this->render('join',[
        //'nav_info'=>$nav_info,
        'ad_big'=>$ad_big,
    ]);
  }
  /**
     *
     * @Title: actionRegionById
     * @Description:  通过区域id获取下属区域名称
     * @return: string ['region_id'=>'region_name']
     * @author: kai.gao
     * @date: 2014-12-19
     */
    public function actionRegionById($pid)
    {
      $region = OtherRegion::getRegion($pid);
      if (empty($region)) {
          $region_name = OtherRegion::getAllProvinceByCache();
          $region[$pid] = $region_name;
      }
      return Json::encode($region);
    }
    /**
   * @description:招商加盟留言表
   * @author: honglang.shen
   * @modified_user:  sunkouping
   * @modified_date: 2015-12-19
   */
  public function actionGetMesInfo(){
    header("Content-type:text/html;charset=utf-8");
    if(isset($_POST['name'])){
        $model = new ArticleJoinMes();
        $model->name = $_POST['name'];
        $model->province = $_POST['province'];
        $model->city = $_POST['city'];
        $model->phone = $_POST['phone'];
        $model->message = $_POST['message'];
        $model->email = $_POST['email'];
        $model->qq = $_POST['qq'];
            if($model->save()){
              return $this->redirect('/article/join');
            }   
    }else{
      return $this->redirect('/article/join');  
    }
    
  }
  /*快速导出招商加盟信息*/
  public function actionExportJoinMesInfo(){
    header("Content-Type: text/html;charset=UTF-8");
    $field = "t1.name 姓名,
              t1.phone 手机号码,
              t1.qq  QQ号码,
              t1.email 邮箱,
              t2.region_name  省,
              t3.region_name   市,
              t1.message 留言内容
            ";
      $sql = "SELECT $field 
          FROM other_article_join_mes AS t1
          LEFT JOIN other_region AS t2 ON t1.province=t2.region_id
          LEFT JOIN other_region AS t3 ON t1.city=t3.region_id";
      $key = md5('51dh_news'.date('d', time()) * date('Y', time()));
      $url = file_get_contents(FAST_EXPORT_URL."/shell/mysql_excel3.php?word=".urlencode($sql)."&key=$key");
      ExportLog::createExportlog(\Yii::$app->user->id, '快速导出满减明细');
      exit ($url);
  }
  /**
   * @description:获取文章的详情页
     * @author: honglang.shen
   * @date: 2015-12-23
  */
  public function actionDetail(){
    header("Content-type:text/html;charset=utf-8");
    $this->layout='index2';
    $nav_info = ArticleNav::find()->where(['status'=>1])->orderBy('id desc')->limit(2)->asArray()->all();
    $article_id = f_get('article_id',81);
    $nav_id = f_get('nav_id',2);
    //获取对应的文章信息
    $list=ArticleInfo::find()->where(['id'=>$article_id,'nav_id'=>$nav_id])->asArray()->one();
    //公告广告
    $ad_info = f_c('ad_info_login');
    if(!$ad_info) {  
      $ad_info = [];
      $ad_info= (new \yii\db\Query())->from("{{other_ad_position}} a")
      ->leftJoin("{{other_ad_info}} b","b.position_id=a.id")
      ->where(['a.id'=>58,'a.is_deleted'=>0,'b.status'=>1])
      ->orderBy('b.sort desc')->offset(0)->limit(2)
      ->all();
      f_c('ad_info_login',$ad_info,3600*12);
    }
    return $this->render('detail',[
        'nav_info'=>$nav_info,
        'list' => $list,
        'ad_info'=>$ad_info,
    ]);
  }
  /**
   * @description:获取未登录文章的详情页
   * @author: honglang.shen
   * @date: 2015-12-23
   */
  public function actionList(){
    $page = f_get('page',1);
    $nav_id = f_get('nav_id',2);
    if(!f_c('page_cache_article_'.$page.$nav_id)){
      header("Content-type:text/html;charset=utf-8");
      $this->layout='index2';
      $nav_id = f_get('nav_id');
      if(!$nav_id){
        //获取的是峰云快讯
        $nav_info1 = ArticleNav::find()->where(['sort'=>2,'status'=>1])->asArray()->one();
        $nav_id = $nav_info1['id'];
      }
      $nav_info = ArticleNav::find()->where(['status'=>1])->orderBy('sort desc')->limit(2)->asArray()->all();
      //取得数据
      $query = ArticleInfo::find()->where(['nav_id'=>$nav_id]);
      $countQuery = clone $query;
      //分页
      $pages = new Pagination(['totalCount' => $countQuery->count(),'defaultPageSize'=>5]);
      $article_info = f_c('article_info');
      if(!$article_info) {  
        $article_info = $query->orderBy('publish_time desc')
        ->offset($pages->offset)
        ->limit($pages->limit)
        ->all();
        f_c('article_info',$article_info,3600*2);
      }
      $pages = \yii\widgets\LinkPager::widget([
          'pagination' => $pages,
          'firstPageLabel' => '首页',
          'prevPageLabel' => '上一页',
          'nextPageLabel' => '下一页',
          'lastPageLabel' => '尾页',
      ]); 
      //广告
      $ad_info = f_c('ad_info_login');
      if(!$ad_info) {  
        $ad_info = [];
        $ad_info= (new \yii\db\Query())->from("{{other_ad_position}} a")
        ->leftJoin("{{other_ad_info}} b","b.position_id=a.id")
        ->where(['a.id'=>58,'a.is_deleted'=>0,'b.status'=>1])
        ->orderBy('b.sort desc')->offset(0)->limit(2)
        ->all();
        f_c('ad_info_login',$ad_info,3600*12);
      }
     
     $page_cache_article = $this->render('list',[
          'article_info'=>$article_info,
          'pages'=>$pages,
          'nav_info'=>$nav_info,
          'ad_info'=>$ad_info,
      ]);
     //写入缓存
      f_c('page_cache_article_'.$page.$nav_id,$page_cache_article,3600);
      return $this->render('list',[
          'article_info'=>$article_info,
          'pages'=>$pages,
          'nav_info'=>$nav_info,
          'ad_info'=>$ad_info,
      ]);
    }else{
      return f_c('page_cache_article_'.$page.$nav_id);
    }
  }   
}
