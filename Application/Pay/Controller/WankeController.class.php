<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;

use Pay\Lib\wanke\WankeLib;
class WankeController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入万科支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $parameter = array(
            'code' => 'Wanke', // 通道名称
            'title' => '万科',
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
        $notifyurl = $unlockdomain . 'Pay_Wanke_notifyurl.html'; //异步通知
        $callbackurl = $unlockdomain . 'Pay_Wanke_callbackurl.html'; //返回通知
        logwrite("notifyurl=".$notifyurl);
        $data = array(
            'appId' => $return['appid'],
            'channelType' => $return['chennel_code'],
            'notifyUrl' => $notifyurl,
            'money' => $return['amount'],
            'outTradeNo' => $return['orderid'],
            'key' => $return['appsecret'],
            'url' => $return['gateway'],
            'platformKey' => $return['signkey']
        );
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        $result = WankeLib::setOrder($data);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        if($result['code']!='success'){
            $this->showmessage("请求支付链接失败",$result);
            exit();
        }
        logwrite("url->" . $result['url']);
        //******************存入临时表**********************
        $tempdata=[
            'oid'=>$return['orderid'],
            'url'=>$result['url']
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->_site . 'Pay_Wanke_topay.html?omega='.$return['orderid'];
        }else{
            $this->showmessage("请求支付链接失败");
        }
    }

    public function topay()
    {
        $oid=I("get.omega");
        logwrite("omege--------------->".$oid);
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
                'jump_url' => $temporder['url']
            ]);
            $this->display('SysWechatH5/Pay');
        }
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
        logwrite("进入万科通知地址==========================================");
        logwrite("通知参数为：".json_encode($_POST));
        $response  = $_POST;
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $response['outTradeNo']])->find(); //获取订单信息
//        if(WankeLib::notify($order_info['key'])){
            if($response['status']=="2"){
                $this->EditMoney($response['outTradeNo'], '', 0);
                exit("success");
            }else{
                logwrite("未支付======================================");
                exit('error:check sign Fail!');
            }
//        }else {
//            logwrite("验证签名不通过======================================");
//            exit('error:check sign Fail!');
//        }

    }

}
