<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
class Luoyao2Controller extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入自营支付宝支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_Luoyao_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_Luoyao_callbackurl.html'; //返回通知

        $parameter = array(
            'code' => 'LuoyaoH5', // 通道名称
            'title' => '罗耀支付宝H5',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body' => $body,
            'channel' => $array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $data = array(
            'pay_memberid' => '200467278',
            'pay_notifyurl' => $notifyurl,
            'pay_callbackurl' => $callbackurl,
            'pay_amount' => $return['amount'],
            'pay_orderid' => $return['orderid'],
            'pay_md5sign' => '',
            'pay_applydate' => date("Y-m-d H:i:s",time()),
            'pay_bankcode' => "925"
        );
        ksort($data);
        $signText = '';
        foreach ($data as $k => $v) {
            if ($k == "pay_md5sign" || $v == "") continue;

            $signText .= $k."=" . $v."&";
        }
        $signText .= 'key=it7n9flt9rusesbqutt0ox4n0vfewcei';
        logwrite("signText=" . $signText);
        $data['pay_md5sign'] = strtoupper(md5($signText));

        $url = 'https://www.xiuzhoubank.com/Pay_Index.html';
        logwrite("data->" . json_encode($data));
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        $result = $this->httpRequest($url, $data);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        //******************存入临时表**********************
        $tempdata=[
            'oid'=>$return['orderid'],
            'jtype'=>1,
            'url'=>$result
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->_site . 'Pay_Luoyao2_topay.html?omega='.$return['orderid'].'&jtype=1';
        }else{
            $this->showmessage("请求支付链接失败");
        }
    }

    public function topay()
    {
        $oid=I("get.omega");
        $jtype=I("get.jtype");
        logwrite("omege--------------->".$oid);
        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
        logwrite("temporder===============".json_encode($temporder));
        if($jtype=="1"){
            echo $temporder['url'];
        }else{
            if($temporder){
                $this->assign([
                    'jump_url' => $temporder['url'],
                    'jtype'=>$temporder['jtype']
                ]);
                $this->display('SysWechatH5/Pay');
            }
        }
    }


    //同步通知
    public function callbackurl()
    {
        $Order      = M("Order");
       
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["out_trade_no"]])->getField("pay_status");
        if ($pay_status != 0) {
            $this->EditMoney($_REQUEST["out_trade_no"], '', 1);
        } else {
            exit("error");
        }
    }

    //异步通知
    public function notifyurl()
    {
//        $arr=$_POST;
//        $alipaySevice = new \AlipayTradeService($config);
//        $alipaySevice->writeLog(var_export($_POST,true));
//        $result = $alipaySevice->check($arr);

        logwrite("进入罗耀支付支付宝回调-------------------》");
        $response  = $_POST;
        logwrite("全部参数为----------》".json_encode($response));
        //POST参数
        $requestarray = array(
            'memberid'    => I('request.memberid', 0, 'intval'),
            'orderid'     => I('request.orderid', ''),
            'amount'      => I('request.amount', ''),
            'transaction_id'   => I('request.transaction_id', ''),
            'datetime'    => I('request.datetime', ''),
            'returncode'    => I('request.returncode', ''),
            'attach'   => I('request.attach', '')
        );
        logwrite("待签名参数为----------》".json_encode($requestarray));
        $md5key        = 'it7n9flt9rusesbqutt0ox4n0vfewcei';
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $pay_md5sign   = I('request.sign');
        logwrite("pay_md5sign=".$pay_md5sign."md5keysignstr=".$md5keysignstr);
        if ($pay_md5sign == $md5keysignstr) {
            if ($response['returncode'] == '00') {
                $this->EditMoney($response['orderid'], '', 0);
                exit("ok");
            }
        } else {
            logwrite("罗耀验证签名错误");
            echo "签名验证不通过";
        }
    }

}
