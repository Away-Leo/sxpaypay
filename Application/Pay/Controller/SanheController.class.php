<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
use Pay\Lib\Sanhe\HTApi;
use Pay\Lib\Sanhe\Util;

class SanheController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入三河支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $parameter = array(
            'code' => 'Sanhe', // 通道名称
            'title' => '三河',
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
        $notifyurl = $unlockdomain . 'Pay_Sanhe_notifyurl.html'; //异步通知
        $callbackurl = $unlockdomain . 'Pay_Sanhe_callbackurl.html'; //返回通知
        logwrite("notifyurl=".$notifyurl);
//        $data = array(
//            'method'=>'createOrder',
//            'params'=>[
//                'merchant' => $return['mch_id'],
//                'payType' => $return['chennel_code'],
//                'orderNo' => $return['orderid'],
//                'amount' => number_format($return['amount'],2,".","")."",
//                'notify' => $notifyurl,
//                'ip' => getIp()
//            ],
//            'sign'=>''
//
//        );
//        $params=$data['params'];
//        ksort($params);
//        $signText = '';
//        foreach ($params as $k => $v) {
//            if ($k == "sign" || $v == "") continue;
//
//            $signText .= $k . '='.$v.'&';
//        }
//        $signText .= $return['signkey'];
//        logwrite("signText=" . $signText);
//        $data['sign'] = md5($signText);
        $url = 'https://www.trp1688.com/epay/service.json';
        $api=new HTApi($return['mch_id'],$return['signkey'],$url);
        $orderNo = $return['orderid'];
        $amount = number_format($return['amount'], 2, '.', '');
        $notify = $notifyurl;
        $payType = $return['chennel_code'];
        $extra = array();
        $extra['product'] = '';
        $extra['attach'] = '';
        $extra['qrWidth'] = 280;

        $ua = $_SERVER['HTTP_USER_AGENT'];
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("参数为：->" . $payType);
        $qr = $api->createOrder($orderNo, $amount, $notify, $payType, Util::ip(), Util::getBrowser($ua), Util::getOs($ua), $ua, 0, json_encode($extra));
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . json_encode($qr));
        if (isset($qr->link) && strlen($qr->link) != 0){
            $tempdata=[
                'oid'=>$return['orderid'],
                'url'=>$qr->link
            ];
            $order_jump=M('Orderjump');
            $add_result=$order_jump->add($tempdata);
            if($add_result){
                echo $this->_site . 'Pay_Sanhe_topay.html?omega='.$return['orderid'];
            }else{
                $this->showmessage("请求支付链接失败");
            }
        }else{
            $this->showmessage("请求支付链接失败",$qr);
        }
//        $url = 'http://karl-leo.imwork.net/common/test.json';
//        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
//        logwrite("参数为：->" . json_encode($data));
//        $result = $this->http_post_json($url, $data);
//        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
//        logwrite("请求结果为------------------------>" . $result);
//        $resultobj = json_decode($result, true);
//        if(empty($resultobj['img'])||$resultobj['code']!=1){
//            $this->showmessage("请求支付链接失败",$result);
//            exit();
//        }
//        logwrite("url->" . $resultobj['link']);
        //******************存入临时表**********************
//        $tempdata=[
//            'oid'=>$return['orderid'],
//            'url'=>$resultobj['link']
//        ];
//        $order_jump=M('Orderjump');
//        $add_result=$order_jump->add($tempdata);
//        if($add_result){
//            echo $this->_site . 'Pay_Sanhe_topay.html?omega='.$return['orderid'];
//        }else{
//            $this->showmessage("请求支付链接失败");
//        }
    }

    public function topay()
    {
        $oid=I("get.omega");
        logwrite("omege--------------->".$oid);
        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
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
        logwrite("进入三河通知地址==========================================");
        $arr = $_GET;
        $merchant = isset($arr['merchant']) ? $arr['merchant'] : '';
        $orderNo = isset($arr['orderNo']) ? $arr['orderNo'] : '';
        $serializeId = isset($arr['serializeId']) ? $arr['serializeId'] : '';
        $amount = isset($arr['amount']) ? floatval($arr['amount']) : 0;
        $reciveAmount = isset($arr['reciveAmount']) ? floatval($arr['reciveAmount']) : 0;
        $isFinished = isset($arr['isFinished']) ? floatval($arr['isFinished']) : 0;
        $attach = isset($arr['attach']) ? $arr['attach'] : '';
        $sign = isset($arr['sign']) ? $arr['sign'] : '';
        if ($orderNo == '' || $merchant == '' || $amount == 0 || $reciveAmount == 0 || $isFinished == 0 || $serializeId == '' || $sign == '') {
            echo '参数错误';
            die();
        }
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' =>$orderNo])->find(); //获取订单信息
        $api = new HTApi($order_info['memberid'], $order_info['key'], 'https://www.trp1688.com/epay/service.json');
        $result = $api->checkSign($arr, $sign);
        if (!$result) {
            logwrite("验证签名不通过======================================");
            echo '签名错误';
            die();
        }else{
            if(isset($arr['isFinished']) && $arr['isFinished'] == 1){
                $this->EditMoney($orderNo, '', 0);
                echo ("success");
                exit();
            }
        }
    }

}
