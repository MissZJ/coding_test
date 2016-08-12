<?php 
namespace frontend\controllers;

use frontend\components\Controller2016;
use common\util\Curl;

class SaiNuoController extends Controller2016
{   
       /*
      @description: 主页面
      @author: honglang.shen
      @date :2016-3-9
     */
    public function actionIndex(){
        
        //导航
        $curl = new Curl();
        $nav_url = SAi_NUO_URL."/inter/nav";
        $result = f_c('sainuo_result');
        if($result === false){
            $result = $curl->get($nav_url);
            $result = json_decode($result);
             f_c('sainuo_result',$result,600);
             $result = f_c('sainuo_result');
        }
        $nav = $result->articleNav;
        $article = $result->articleInfo;
        $down = $result->down;
        $recommend = $result->recommend;
        $hot = $result->hot;

        $nav_id = 0;
        $page = 1;
        $page_size = 10;
        $count = ceil(count($article)/$page_size);
        if(isset($_GET['nav_id'])||isset($_GET['page'])){
            $nav_id = $_GET['nav_id'];
            $page = $_GET['page'];
            $startPage = ($page-1)*$page_size;
            $data = ['nav_id' => $nav_id,'page_size'=>$page_size,'startPage'=>$startPage];
            $curl = new Curl();
            $nav_url = SAi_NUO_URL."/inter/check-nav";
            $result2 = $curl->get($nav_url.'?'.http_build_query($data));
            $result2 = json_decode($result2);
            $article =  $result2->article;
            $count = $result2->count;
        }
       $allPage = $count;
        return $this->render('index',[
                'nav' => $nav,
                'article' => $article,
                'down' => $down,
                'recommend' => $recommend,
                'hot' => $hot,
                'nav_id'=>$nav_id,
                'page' =>$page,
                'allPage'=>$allPage,
            ]);
    }

     /*
      @description: 详情
      @author: lu.xu
      @date :2016-3-10
     */
    public function actionDetail(){
          $article_id = $_GET['id'];
          $nav_id = $_GET['nav_id'];
          $data=['article_id'=>$article_id,'nav_id'=>$nav_id];
          $curl = new Curl();
          //$nav_url = "http://sainuo.51dh.com/inter/detail";
          $nav_url = SAi_NUO_URL."/inter/detail";
          $result = $curl->get($nav_url.'?'.http_build_query($data));
          $result2 = json_decode($result);
           $article =  $result2->article;
           $articles =  $result2->articles;
           if(count($result2->article1)){
           $article1 =  $result2->article1;
           $article1=$article1[0];
           }else{
              $article1=1; 
           }
           if(count($result2->article2)){
           $article2 =  $result2->article2;
           $article2=$article2[0];
           }else{
              $article2=1; 
           }
          $nav_name =  $result2->nav_name;
        return $this->render('detail',[
            'article' => $article[0],
            'articles' => $articles,
           'article1' =>$article1,
            'article2' => $article2,
            'nav_name'=>$nav_name[0],
        ]);
    }

    /*
      @description: 下载
      @author: honglang.shen
      @date :2016-3-11
     */
    public function actionDownPdf(){
        $filename = $_GET['filename'];
        $attr = explode('/',$filename);
        $pdf = (array_pop($attr));
        // We'll be outputting a PDF
        header('Content-type: application/pdf');
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename='.$pdf);
        // The PDF source is in original.pdf
        readfile(IMAGE_URL."/".$filename);
    }
}
?>