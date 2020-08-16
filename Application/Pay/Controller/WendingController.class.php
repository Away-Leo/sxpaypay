<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
class WendingController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入稳定币支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $parameter = array(
            'code' => 'Wending', // 通道名称
            'title' => '稳定币',
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
        $notifyurl = $unlockdomain . 'Pay_Wending_notifyurl.html'; //异步通知
        $callbackurl = $unlockdomain . 'Pay_Wending_callbackurl.html'; //返回通知
        logwrite("notifyurl=".$notifyurl);
        $data = array(
            'amounts' => $return['amount']."",
            'partnerId' => $return['mch_id']."",
            'userId' => $return['orderid']."",
            'gameCode' => 'X9IOWSFED1',
            'orderId' => $return['orderid']."",
            'type' => $return['chennel_code']."",
            'exp' => $this->getMillisecond()."",
            'sign' => ''
        );
        $header=array('Content-Type: application/json','X-Notify-URL:'.$notifyurl);
        ksort($data);
        $signText = $return['signkey'];
        foreach ($data as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k . '='.$v.'&';
        }
        $signText .= $return['signkey'];
        logwrite("signText=" . $signText);
        $data['sign'] = strtoupper(md5($signText));

        $url = $return['gateway'];
//        $url = 'http://karl-leo.imwork.net/common/test.json';
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求参数为->" . json_encode($data));
        $result = $this->http_post_json_withheader($url, json_encode($data),$header);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        $resultobj = json_decode($result, true);
        if(empty($resultobj['status'])||intval($resultobj['status'])!=1){
            $this->showmessage("请求支付链接失败",$result);
            exit();
        }
        $resulturl="https://fullbitpay.co/api/wallet/provide/".$resultobj['data']['orderCode']."/".$resultobj['data']['tokenId'];
        //******************存入临时表**********************
        $tempdata=[
            'oid'=>$return['orderid'],
            'url'=>$resulturl
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->_site . 'Pay_Wending_topay.html?omega='.$return['orderid'];
        }else{
            $this->showmessage("请求支付链接失败");
        }
    }

    private function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
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
        logwrite("进入稳定币通知地址==========================================");
        $response  = $_POST;
        $sign      = $response['sign'];
        logwrite("全部参数为：".json_encode($response));
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $response['orderId']])->find(); //获取订单信息

        ksort($response);
        $signText = $order_info['key'];
        foreach ($response as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k . '='.$v.'&';
        }
        $signText .= $order_info['key'];
        if(strtoupper(md5($signText))==$sign){
            if($response['status']=="2"){
                $this->EditMoney($response['orderId'], '', 0);
                exit("1");
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
