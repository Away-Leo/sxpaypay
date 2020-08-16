<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
class XiaozhiController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入小志支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $parameter = array(
            'code' => 'Xiaozhi', // 通道名称
            'title' => '小志',
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
        $notifyurl = $unlockdomain . 'Pay_Xiaozhi_notifyurl.html'; //异步通知
        $callbackurl = $unlockdomain . 'Pay_Xiaozhi_callbackurl.html'; //返回通知
        logwrite("notifyurl=".$notifyurl);
        $data = array(
            'uid' => $return['mch_id'],
            'istype' => $return['chennel_code'],
            'price' => floatval(number_format($return['amount'], 2, '.', ''))."",
            'orderid' => $return['orderid'],
            'notify_url' => $notifyurl,
            'return_url' => $callbackurl,
            'format' => '1',
            'goodsname' => '1',
            'orderuid' => $return['orderid']
        );
        ksort($data);
        $signText = '';
        foreach ($data as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k . '='.$v.'&';
        }
        $signText .="key=".$return['signkey'];
        logwrite("signText=" . $signText);
        $data['key'] = md5($signText);

        $url = $return['gateway'];
//        $url = 'http://karl-leo.imwork.net/common/test.json';
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求参数为->" . json_encode($data));
        $result = $this->httpRequest($url, $data);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        $resultobj = json_decode($result,true);
        logwrite("判定结果".$resultobj['code']);
        if(intval($resultobj['code'])!=0){
            $this->showmessage("请求支付链接失败",$resultobj);
            exit();
        }
        logwrite("url->" . $resultobj['data']['url']);
        //******************存入临时表**********************
        $tempdata=[
            'oid'=>$return['orderid'],
            'url'=>$resultobj['data']['url']
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->_site . 'Pay_Xiaozhi_topay.html?omega='.$return['orderid'];
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
        $this->display('SysWechatH5/PaySuccess');
    }

    //异步通知
    public function notifyurl()
    {
        logwrite("进入小志通知地址==========================================");
        logwrite("进入小志通知地址".json_encode($_POST));
        $response  = $_POST;
        $sign      = $response['key'];
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $response['orderid']])->find(); //获取订单信息

        ksort($response);
        $signText = '';
        foreach ($response as $k => $v) {
            if ($k == "key" || $v == "") continue;

            $signText .= $k . '='.$v.'&';
        }
        $signText .="key=".$order_info['key'];
        logwrite("signtest=".$signText);
        if(md5($signText)==$sign){
            if($response['status']=="1"){
                $this->EditMoney($response['orderid'], '', 0);
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
