<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
class DingshengController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入鼎盛支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $parameter = array(
            'code' => 'Dingsheng', // 通道名称
            'title' => '鼎盛',
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
        $notifyurl = $unlockdomain . 'Pay_Dingsheng_notifyurl.html'; //异步通知
        $callbackurl = $unlockdomain . 'Pay_Dingsheng_callbackurl.html'; //返回通知
        logwrite("notifyurl=".$notifyurl);
        $data = array(
            'appid' => $return['appid']."",
            'pay_type' => $return['chennel_code'],
            'amount' => number_format($return['amount'], 2, '.', '')."",
            'callback_url' => $notifyurl,
            'success_url' => $callbackurl,
            'error_url' => $callbackurl,
            'out_uid' => $return['orderid']."",
            'out_trade_no' => $return['orderid']."",
            'version' => 'v1.1',
            'sign' => ''
        );
        ksort($data);
        $signText = '';
        foreach ($data as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k . '='.$v.'&';
        }
        $signText .= 'key='.$return['signkey'];
        logwrite("signText=" . $signText);
        $data['sign'] = strtoupper(md5($signText));

        $url = $return['gateway'];
//        $url = 'http://karl-leo.imwork.net/common/test.json';
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求参数为->" . json_encode($data));
//        $result = $this->httpRequest($url, $data);
//        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
//        logwrite("请求结果为------------------------>" . $result);
//        $resultobj = json_decode($result, true);
//        if(empty($resultobj['code'])||intval($resultobj['code'])!=1){
//            $this->showmessage("请求支付链接失败",$result);
//            exit();
//        }
//        logwrite("url->" . $resultobj['data']['pay_url']);
        //******************存入临时表**********************
        $tempdata=[
            'oid'=>$return['orderid'],
            'jtype'=>1,
            'oriurl'=>json_encode($data),
            'url'=>$url
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->_site . 'Pay_Dingsheng_topay.html?omega='.$return['orderid'];
        }else{
            $this->showmessage("请求支付链接失败");
        }
    }

    public function topay()
    {
        $oid=I("get.omega");
        $jtype=I("get.jtype");
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
        $paras=json_decode($temporder['oriurl'],true);
        unset($paras['__hash__']);
        if($temporder){
            $this->assign([
                'jump_url' => $temporder['url'],
                'jtype'=>$temporder['jtype'],
                'oriurl'=>json_decode($temporder['oriurl'])
            ]);
            $this->display('SysWechatH5/PayHtml');
        }
    }

    //同步通知
    //同步通知
    public function callbackurl()
    {
        $this->display('SysWechatH5/PaySuccess');
    }

    //异步通知
    public function notifyurl()
    {
        logwrite("进入通知地址==========================================");
        $response  = $_POST;
        $sign      = $response['sign'];
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $response['out_trade_no']])->find(); //获取订单信息

        ksort($response);
        $signText = '';
        foreach ($response as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k . '='.$v.'&';
        }
        $signText .= 'key='.$order_info['key'];
        if(strtoupper(md5($signText))==$sign){
            if($response['callbacks']=="CODE_SUCCESS"){
                $this->EditMoney($response['out_trade_no'], '', 0);
                exit("success");
            }else{
                logwrite("未支付======================================");
                exit('error:check sign Fail!');
            }
        }else {
            logwrite("验证签名不通过======================================");
            exit('error:check sign Fail!');
        }

    }

}
