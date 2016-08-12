<?php
namespace frontend\Controllers;

use frontend\components\Controller2016;
use Yii;
use yii\db\Query;

define("TOKEN", "weiphp");//自己定义的token 就是个通信的私钥

class WeiXinFengYunController extends Controller2016 {

    public $appid = "wxb8217207ba0b238e";//正式
    // public $appid = "wxbec1e61dd04321ab";
    public $appsecret = "7e91c9bcdba0e93b88c6bcd3da6226b3";//正式
    // public $appsecret = "f89a58873f512d175aaa7e699473e773";

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

//    public function actionIndex(){
//        echo 'success';
//    }

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

        //$this->actionValid();exit;#第一次使用完注释掉

        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
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


                if($key == '51FIRE'){
                    $a[0]['Title'] = '51火炬传递';
                    $a[0]['Description'] = '51火炬传递!';
                    $a[0]['PicUrl'] = 'http://follow.jshuabo.net/images/51fire.jpg';
                    $a[0]['Url'] = 'http://follow.jshuabo.net/site/index?open_id='.$postObj->FromUserName;
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }


            }

            #语音推送事件
            if(isset($postObj->Recognition)){//识别用户发送的语音
                $speak = $postObj->Recognition;
                if($speak=="查订单！" || $speak=="查订单"){
                    $a[0]['Title'] = '快来查询您的订单列表';
                    $a[0]['Description'] = '亲,点我查看历史订单吧!';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/wx2015/images/weixin51.jpg';
                    $a[0]['Url'] = 'http://www.51dh.com.cn/mobile-ucenter/order-list';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }
                if($speak=="我要订货！" || $speak=="我要订货"){
                    $a[0]['Title'] = '欢迎来到,我要订货网商城!';
                    $a[0]['Description'] = '亲,欢迎来到我要订货网,点我进入商城,选购手机吧!';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/wx2015/images/weixin51.jpg';
                    $a[0]['Url'] = 'http://www.51dh.com.cn';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }
            }

            #关注-subscribe | 取消关注-unsubscribe 
            if( isset($postObj->Event) ) {
                $key = $postObj->Event;
                if($key == 'subscribe'){
                    $a[0]['Title'] = '关于我们';
                    $a[0]['Description'] = '点我可以更加了解51订货网';
                    $a[0]['PicUrl'] = 'http://www.51dh.com.cn/wx2015/images/weixin-service/about.jpg';
                    $a[0]['Url'] = 'http://www.51dh.com.cn/mobile/about';
                    echo $this->actionTransmitNews($postObj,$a);exit;
                }
            }

            #聊天框自动回复
            if(!empty( $keyword ))
            {
                if($keyword == "你好"){
                    $msgType = "text";
                    $contentStr = '您的回复已收到(●—●)！持续关注惊喜多多';
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;exit;
                }else{
                    $msgType = "text";
                    $contentStr = '您的回复已收到(●—●)！持续关注惊喜多多';
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;exit;
                }

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
        $weixin_access_token = Yii::$app->page_cache->get("weixin_access_token");
        if( $weixin_access_token === false ){
            $u = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appid."&secret=".$this->appsecret;
            $info = file_get_contents($u);
            $access = json_decode($info);
            $weixin_access_token = $access->access_token;
            Yii::$app->page_cache->set("weixin_access_token",$weixin_access_token,36000);
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
                    "name":"蜂云志",
                    "sub_button":[
                        {
                            "type":"view",
                            "name":"蜂云网络",
                            "url":"http://mp.weixin.qq.com/s?__biz=MzA5OTUxMTIyMw==&mid=202704688&idx=2&sn=22d5421f5d06c79fe9dd3b55e7f8d2a3&scene=18#rd"
                        },
                        {
                            "type":"view",
                            "name":"51订货网",
                            "url":"http://www.51dh.com.cn"
                        },
                        {
                            "type":"view",
                            "name":"51巡店",
                            "url":"http://xundian.51dh.com.cn"
                        },
                        {
                            "type":"view",
                            "name":"51巡店后台",
                            "url":"http://xundianms.51dh.com.cn"
                        },
                        {
                            "type":"click",
                            "name":"51火炬传递",
                            "key":"51FIRE"

                        }
                    ]
                },
                {
                    "name":"蜂云颂",
                    "sub_button":[
                        {
                            "type":"view",
                            "name":"我要的产品",
                            "url":"http://mp.weixin.qq.com/s?__biz=MzA5OTUxMTIyMw==&mid=202705727&idx=2&sn=793e12c7e00fa62aba761a4de27b2cac&scene=18#rd"
                        },
                        {
                            "type":"view",
                            "name":"蜂云那些事",
                            "url":"http://mp.weixin.qq.com/s?__biz=MzA5OTUxMTIyMw==&mid=202490211&idx=2&sn=f4f0fda9142f28ebf1f7775585e1da85#rd"
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

    public function actionOauth() {
        $code = $_GET['code'];
    }

}