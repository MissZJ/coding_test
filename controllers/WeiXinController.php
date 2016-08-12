<?php
namespace frontend\controllers;

use common\models\other\OtherAdClickStat;
use Yii;
use yii\debug\models\search\Debug;
use yii\filters\AccessControl;
use yii\helpers\Json;
use yii\web\Controller;
use yii\db\Query;

define("TOKEN", "weixin");//自己定义的token 就是个通信的私钥
class WeiXinController extends Controller{ 
//	/**
//	 * @description:通讯商城首页
//	 * @return: return_type
//	 * @author: sunkouping
//	 * @date: 2015年11月23日下午2:50:41
//	*/
//	public function actionIndex(){
//		f_d(123);
//	}
//        
        
    public $appid = "wx284067f9bb2ad6f5";//正式
    public $appsecret = "24ae27bfe26750d67854b5ba41756ed9";//正式
//    public $appsecret = "983f2cb4e219e1ad3573c5ec915652e4";//正式

    public function behaviors() {
        $actionArr = ['switch'];
        
        return [
           'access' => [
                'class' => \yii\filters\AccessControl::className(),
                'rules' => [
                    [
                    	'actions' => $actionArr,
                        'allow' => true,
                        'roles' => ['@', '?'],
                    ],
                	[
                		'allow' => true,
                		'roles' => ['@'],
                	] 
                ],
            ], 
        ];
    }
    


    public function actionSwitch(){
        if(isset($_GET['cd']) && $_GET['cd'] == 1){
            $this->actionCreatecd();
        }else if(isset($_GET['cd']) && $_GET['cd'] == 2){
            $this->actionDeleteMenu();
        }else if(isset($_GET['openid']) && isset($_GET['info'])){
            $this->actionPushChart($_GET['openid'],$_GET['info']);
        }else if(isset($_GET['keycode'])){
            echo $this->actionGetQrcode($_GET['keycode']);
        }else if(isset($_GET['openid'])){
            echo $this->actionGetUserInfo($_GET['openid']);
        }else{
            $this->actionMain();
        }
    }

    public function actionMain() {

        #$this->actionValid();exit;#第一次使用完注释掉

        $postStr = isset($GLOBALS["HTTP_RAW_POST_DATA"])?$GLOBALS["HTTP_RAW_POST_DATA"]:NULL ;
        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $keyword = trim($postObj->Content);
            $time = time();
            $textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[%s]]></MsgType>
            <Content><![CDATA[%s]]></Content>
            <FuncFlag>0<FuncFlag>
            </xml>";
            
            #自定义点击菜单推送事件
            if( isset($postObj->EventKey) ) {
                $key = $postObj->EventKey;
                if($key == 'FAST'){
                    $a[0]['Title'] = '快速订货';
                    $a[0]['Description'] = '我要订货网更多好商品,轻松订货';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/weixin51.jpg';
                    $a[0]['Url'] = 'http://wsc.51dh.com.cn/';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }

                if($key == '51SHOP'){
                    $a[0]['Title'] = '百元开店,就在51云店';
                    $a[0]['Description'] = '点击图文消息,开始免费体验51云店';
                    $a[0]['PicUrl'] = 'http://yd.51dh.com.cn/App/Modules/weixin/weixinportal/images/51ydwxbanner.jpg';
                    $a[0]['Url'] = 'http://yd.51dh.com.cn/App/Modules/weixin/weixinportal/excessive.html?openid='.$postObj->FromUserName;
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }

                if($key == 'MICRO_SHOP'){
                    $a[0]['Title'] = '欢迎来到,我要订货网商城!';
                    $a[0]['Description'] = '亲,欢迎来到我要订货网,点我进入商城,选购手机吧!';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/weixin51.jpg';
                    $a[0]['Url'] = 'http://wsc.51dh.com.cn';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }

                if($key == 'ORDER_LIST'){
                    $a[0]['Title'] = '快来查询您的订单列表';
                    $a[0]['Description'] = '亲,点我查看历史订单吧!';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/weixin51.jpg';
                    $a[0]['Url'] = 'http://wsc.51dh.com.cn/ucenter/order';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }

                #和51mm聊天
                if ( $key == "CHAT_51MM" ) {
                    $msgType = "text";
                    $contentStr = "51MM出去逛街了噢mo-礼物，她很快就会回来mo-害羞，如果你有什么话想对她说mo-玫瑰，请直接的这里留言噢mo-微笑";
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;exit;
                }

                #赢彩票
                if ( $key == "RED_BAG" ) {
                    $a[0]['Title'] = '【支招】当51订货网在线客服忙时，你该怎么办？';
                    $a[0]['Description'] = '描述：近期随着51订货网的不断发展，我们的用户越来越多，客服MM们每天也忙得不可开交，很多时候用户打过来的电话都处于占线中，为了更好的服务大家，以后有任何问题可通过这些方式进行交流，在这里，你也能第一时间了解到51订货网的最新动态！';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/active_1_19.jpg';
                    $a[0]['Url'] = 'http://mp.weixin.qq.com/s?__biz=MjM5NDAxNzg4OA==&mid=401962651&idx=1&sn=a3fa5059278891400926c765da3f388b#rd';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                    // $msgType = "text";
                    // $contentStr = "活动已下架，更多精彩活动敬请期待";
                    // $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    // echo $resultStr;exit;
                }

                #关于我们
                if($key == 'ABOUT'){
                    $a[0]['Title'] = '关于我们';
                    $a[0]['Description'] = '点我可以更加了解51订货网';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/weixin51.jpg';
                    $a[0]['Url'] = 'http://wsc.51dh.com.cn/ucenter/order';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }
            }

            #语音推送事件
            if(isset($postObj->Recognition)){//识别用户发送的语音
                $speak = $postObj->Recognition;
                if($speak=="查订单！" || $speak=="查订单"){
                    $a[0]['Title'] = '快来查询您的订单列表';
                    $a[0]['Description'] = '亲,点我查看历史订单吧!';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/weixin51.jpg';
                    $a[0]['Url'] = 'http://wsc.51dh.com.cn/ucenter/order';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }
                if($speak=="我要订货！" || $speak=="我要订货"){
                    $a[0]['Title'] = '欢迎来到,我要订货网商城!';
                    $a[0]['Description'] = '亲,欢迎来到我要订货网,点我进入商城,选购手机吧!';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/weixin51.jpg';
                    $a[0]['Url'] = 'http://wsc.51dh.com.cn/ucenter/order';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }
            }

            #关注-subscribe | 取消关注-unsubscribe 
            if( isset($postObj->Event) ) {
                $key = $postObj->Event;
                if($key == 'subscribe'){
                    $a[0]['Title'] = '关于我们';
                    $a[0]['Description'] = '点我可以更加了解51订货网';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/weixin/weixin51.jpg';
                    $a[0]['Url'] = 'http://wsc.51dh.com.cn/ucenter/order';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }
            }

            #聊天框自动回复
            if(!empty( $keyword ))
            {
                $msgType = "text";
                $contentStr = '您的回复已收到(●—●)！持续关注惊喜多多';
                $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                echo $resultStr;
            }else{
                echo '您的回复已收到(●—●)！持续关注惊喜多多';
            }
        }else {
            echo '您的回复已收到(●—●)！持续关注惊喜多多';
            exit;
        }
    }

    public function actionValid() {
        $echoStr = $_GET["echostr"];
        if($this->actionCheckSignature()){
            echo $echoStr;
            exit;
        }
    }
    
    //微信客服给用户发送消息
    public function actionPushChart($openid,$info){
        
        $i = json_decode($info);
        $data = '{
            "touser":"ofZ7cw-g4B5hCbEKqxzaMqOV5KTI",
            "msgtype":"news",
            "news":{
                "articles": [
                    {
                        "title":"'.$i->title.'",
                        "description":"'.$i->des.'",
                        "url":"'.$i->url.'",
                        "picurl":"'.$i->pic.'"
                    }
                ]
            }
        }';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$this->actionGetAccessToken());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
          return curl_error($ch);
        }
        curl_close($ch);
        return $tmpInfo;
        
    }

    //获取用户的openid
    private function actionGetOpenid($object){
        return $object->FromUserName;
    }

    //获取access_token
    private function actionGetAccessToken(){
        $weixin_access_token = f_c("weixin_access_token1");
        if( $weixin_access_token === false ){
            $u = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;
            $info = file_get_contents($u);
            $access = json_decode($info);
            $weixin_access_token = $access->access_token;
            f_c("weixin_access_token1",$weixin_access_token,36000);
        }
        return $weixin_access_token;
    }
    
    //生成带参数的二维码
    public function actionGetQrcode($keycode){
        $data = '{
                "action_name": "QR_LIMIT_STR_SCENE", 
                "action_info": {
                    "scene": {
                        "scene_str": "'.$keycode.'"
                    }
                }
            }';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$this->actionGetAccessToken());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
          return curl_error($ch);
        }
        curl_close($ch);
        
        $tinfo = json_decode($tmpInfo);
        if(!isset($tinfo->ticket)){
            return $tmpInfo;
        }else{
            $ticket = $tinfo->ticket;
            $u = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$ticket;
            return $u;
        }
        
    }

    //获取用户信息
    public function actionGetUserInfo($openid){
        $u = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$this->actionGetAccessToken().'&openid='.$openid.'&lang=zh_CN';
        return file_get_contents($u);
    }

    //响应接口
    private function actionCheckSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token =TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
 
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
    
    //回复图文消息
    private function actionTransmitNews($object, $newsArray)
    {
        if(!is_array($newsArray)){
            return;
        }
        $itemTpl = "    <item>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
                </item>
            ";
        $item_str = "";
        foreach ($newsArray as $item){
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $xmlTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[news]]></MsgType>
            <ArticleCount>%s</ArticleCount>
            <Articles>
            $item_str</Articles>
            </xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }
    
    //生成菜单
    public function actionCreatecd(){
        $data = '{
            "button":[
                {
                    "name":"来订货",
                    "sub_button":[
                        {
                            "type":"view",
                            "name":"快速订货",
                            "url":"http://wsc.51dh.com.cn/"
                        },
                        {
                            "type":"view",
                            "name":"微商城",
                            "url":"http://wsc.51dh.com.cn"
                        },
                        {
                            "type":"view",
                            "name":"51云店",
                            "url":"http://dwz.cn/2cPgcD"
                        },
                        {
                            "type":"view",
                            "name":"关于我们",
                            "url":"http://wsc.51dh.com.cn/ucenter/order"
                        }
                    ]
                },
                {
                    "type":"view",
                    "name":"为你服务",
                    "url":"http://mp.weixin.qq.com/s?__biz=MjM5NDAxNzg4OA==&mid=401962651&idx=1&sn=a3fa5059278891400926c765da3f388b#rd"
                },
                {
                    "name":"我的51",
                    "sub_button":[
                        {
                            "type":"view",
                            "name":"我的红包",
                            "url":"http://wsc.51dh.com.cn/coupon/index"
                        },
                        {
                            "type":"view",
                            "name":"订单查询",
                            "url":"http://wsc.51dh.com.cn/ucenter/order"
                        },
                        {
                            "type":"view",
                            "name":"开店攻略",
                            "url":"http://wsc.51dh.com.cn/"
                        },
                        {
                            "type":"view",
                            "name":"2015账单",
                            "url":"http://wsc.51dh.com.cn/year-account3/index"
                        }
                        
                    ]
                }
            ]
        }';
        echo $this->actionCreateMenu($data);
    }
    
    //创建菜单
    public function actionCreateMenu($data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$this->actionGetAccessToken());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
          return curl_error($ch);
        }
        curl_close($ch);
        return $tmpInfo;

    }

    //获取菜单
    public function actionGetMenu(){
        return file_get_contents("https://api.weixin.qq.com/cgi-bin/menu/get?access_token=".$this->actionGetAccessToken());
    }

    //删除菜单
    public function actionDeleteMenu(){
        return file_get_contents("https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=".$this->actionGetAccessToken());
    }
	
}
