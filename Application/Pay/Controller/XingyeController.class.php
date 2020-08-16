<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;

use Pay\Lib\Xingye\WeiFuTong\Utils;
use Pay\Lib\Xingye\WeiFuTong\payclasses\ClientResponseHandler;
use Pay\Lib\Xingye\WeiFuTong\payclasses\PayHttpClient;
use Pay\Lib\Xingye\WeiFuTong\payclasses\RequestHandler;

class XingyeController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入兴业银行支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $pay_type = I('request.pay_type');
        //商城系统下订单
        $donewithrandomurl = 'http://ygh.yanwujing.cn/flowapi.php?step=donewithrandom';
        $shopparams=[
            'amount'=>I("post.pay_amount", 0),
            'paytype'=>'wx'
        ];
        $shopresult=$this->httpRequest($donewithrandomurl,$shopparams);
        logwrite("收到商城支付日志ID为:".$shopresult);
        if($shopresult&&$shopresult!=""&&!stristr($shopresult,'table')&&intval($shopresult)>0){
            $parameter = array(
                'code' => 'Xingye', // 通道名称
                'title' => '兴业支付',
                'exchange' => 1, // 金额比例
                'gateway' => '',
                'orderid' => '',
                'out_trade_id' => $orderid,
                'body' => $body,
                'channel' => $array
            );

            // 订单号，可以为空，如果为空，由系统统一的生成
            $return = $this->orderadd($parameter);
            $unlockdomain=$return['unlockdomain'];
            if($unlockdomain==null||$unlockdomain==""){
                $unlockdomain=$this->_site;
            }
//        $notifyurl = $unlockdomain . 'Pay_Xingye_notifyurl.html'; //异步通知
            $notifyurl = 'http://ygh.yanwujing.cn/flowapi.php?step=payresponse&ctl=Xingye'; //异步通知
            logwrite("notifyurl=".$notifyurl);
            $data = array(
                'account' => $return['mch_id'],
                'payMoney' => $return['amount'],
                'body' => $return['orderid'],
                'lowOrderId' => $return['orderid'].'-'.intval($shopresult),
                'notifyUrl' => $notifyurl,
                'sign' => '',
            );
            ksort($data);
            $signText = '';
            foreach ($data as $k => $v) {
                if ($k == "sign" || $v == "") continue;

                $signText .= $k .'='.$v.'&';
            }
            $signText .= 'key='.$return['signkey'];
            logwrite("signText=" . $signText);
            $data['sign'] = strtoupper(md5($signText));
//        $url = 'http://154.221.255.2/api/Pay/pay';
//        $url = 'http://127.0.0.1:9999/getPayUrl';
            $url = 'http://ipay.833006.net/tgPosp/services/payApi/allQrcodePay';
//        $url = 'https://tgpay.833006.net/tgPosp/services/payApi/unifiedorder';
            logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
            logwrite("参数为------------------------>" . json_encode($data));
            $result = $this->http_post_json($url, json_encode($data));
            logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
            logwrite("请求结果为------------------------>" . $result);
            $resultobj = json_decode($result, true);
            if($resultobj['status']=='100'||$resultobj['status']==100){
                logwrite("url->" . $resultobj['codeUrl']);
                //******************存入临时表**********************
                import("Vendor.phpqrcode.phpqrcode", '', ".php");
                $qrcodestr=\QRcode::getQRcode($resultobj['codeUrl']);
                $tempdata=[
                    'oid'=>$return['orderid'],
                    'url'=>$qrcodestr,
                    'jtype'=>0,
                    'oriurl'=>$resultobj['codeUrl'],
                    'amount'=>$return['amount']
                ];
                $order_jump=M('Orderjump');
                $add_result=$order_jump->add($tempdata);
                if($add_result){
                    echo $this->_site . 'Pay_Xingye_topay.html?omega='.$return['orderid'];
                }else{
                    $this->showmessage("请求支付链接失败");
                }
            }
        }else{
            logwrite("生成支付订单错误信息为：".$shopresult);
            $this->showmessage("生成订单出错");
        }

    }

    public function topay()
    {
        $oid=I("get.omega");
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $device='IOS';
        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
            $device='Android';
        }else{
            $device='other';
        }
        logwrite("omege--------------->".$oid."=device=".$device);
        $clientip=getIp();
        M('Order')->where(['pay_orderid' => $oid])->save(['device' => $device,'client_ip'=>$clientip]);
        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
        logwrite("temporder===============".json_encode($temporder));
        if($temporder){
            $this->assign([
                'jump_url' => $temporder['url'],
                'amount' => $temporder['amount'],
            ]);
            $this->display('SysWechatH5/PayXingye');
        }
    }

    public function topaybank()
    {
//        $oid=I("get.omega");
//        logwrite("omege--------------->".$oid);
//        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
//        logwrite("temporder===============".json_encode($temporder));
//        if($temporder){
//            $this->assign([
//                'jump_url' => $temporder['url']
//            ]);
            $this->display('SysWechatH5/PayBank');
//        }
    }

    //同步通知
    public function callbackurl()
    {
//        $Order      = M("Order");
//
//        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["out_trade_no"]])->getField("pay_status");
//        if ($pay_status != 0) {
//            $this->EditMoney($_REQUEST["out_trade_no"], '', 1);
//        } else {
//            exit("error");
//        }
    }

    //异步通知
    public function notifyurl()
    {
        logwrite("进入兴业通知地址==========================================");
        $response=$_POST;
        $sign=$response['sign'];
        $lowOrderId=explode("-",$response['lowOrderId']);
        //获取订单信息
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $lowOrderId[0]])->find(); //获取订单信息
        if($order_info){
            $singkey=$order_info['key'];
            ksort($response);
            logwrite("此订单KEY为：".$singkey);
            logwrite("收到的参数：".json_encode($response));
            $signData = '';
            foreach ($response as $key => $val) {
                if ($key == "sign" || $val == "") continue;
                $signData .= $key . '=' . $val . "&";
            }
            $signData.="key=".$singkey;
            if(strtoupper(md5($signData))==$sign){
                $state=$response['state'];
                if($state=='0'){
                    $this->EditMoney($lowOrderId[0], '', 0);
                    echo ("SUCCESS");
                    $donewithrandomurl = 'http://ygh.yanwujing.cn/flowapi.php?step=paysuccess';
                    $shopparams=[
                        'logid'=>$lowOrderId[1]
                    ];
                    $this->http_post_json($donewithrandomurl,json_encode($shopparams));
                    exit();
                }
            }else{
                logwrite("兴业验证签名不通过======================================");
                echo "FALSE";
                exit();
            }
        }else{
            logwrite("兴业通知未查询到该订单======================================");
            echo "FALSE";
            exit();
        }
    }

}
