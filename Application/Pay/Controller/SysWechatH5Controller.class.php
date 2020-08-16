<?php
namespace Pay\Controller;

use Org\Util\WxH5Pay;
class SysWechatH5Controller extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function Pay($array)
    {
        logwrite("进入自营微信支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');

//        $donewithrandomurl = 'http://ygh.yanwujing.cn/flowapi.php?step=donewithrandom';
//        $shopparams=[
//            'amount'=>I("post.pay_amount", 0),
//            'paytype'=>'wx'
//        ];
//        $shopresult=$this->httpRequest($donewithrandomurl,$shopparams);
//        logwrite("收到商城支付日志ID为:".$shopresult);
//        if($shopresult&&$shopresult!=""&&!stristr($shopresult,'table')){
            $parameter = array(
                'code' => 'SysWechatH5', // 通道名称
                'title' => '官方自营微信H5',
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
            $notifyurl = $unlockdomain . 'Pay_SysWechatH5_notifyurl.html'; //异步通知
            $callbackurl = $unlockdomain . 'Pay_SysWechatH5_callbackurl.html'; //返回通知
            logwrite("notifyurl=".$notifyurl);
            //统一下单方法
            $wechatH5Pay = new WxH5Pay($return['appid'],$return['mch_id'],$notifyurl,$return['signkey']);
            $params['body'] = '订单-'.$return['orderid'];                   //商品描述
            $params['out_trade_no'] = $return['orderid'];   //自定义的订单号，不能重复
            $params['total_fee'] = $return['amount']*100;                   //订单金额 只能为整数 单位为分
            $params['trade_type'] = 'MWEB';                  //交易类型 JSAPI | NATIVE |APP | WAP
            logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
            $resultobj=$wechatH5Pay->unifiedOrder( $params );
            logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
            if($resultobj['return_code']=="SUCCESS"&&$resultobj['result_code']=="SUCCESS"){
                //******************存入临时表**********************
                $tempdata=[
                    'oid'=>$return['orderid'],
                    'url'=>$resultobj['mweb_url']
                ];
                $order_jump=M('Orderjump');
                $add_result=$order_jump->add($tempdata);
                if($add_result){
                    echo $this->buildResponse($this->_site . 'Pay_SysWechatH5_topay.html?omega='.$return['orderid'],$array);
                }else{
                    $this->showmessage($resultobj['return_msg']);
                }
            }else{
                $this->showmessage("请求支付地址失败",$resultobj['return_msg']);
            }
//        }
    }

    public function topay()
    {
        $oid=I("get.omega");
        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
        if($temporder){
            $this->assign([
                'jump_url' => $temporder['url'],
                'oid' => $oid
            ]);
            $this->display('SysWechatH5/Pay');
        }
    }

    // 服务器点对点返回
    public function notifyurl()
    {
        logwrite("进入微信服务器回调---------------------------->");
        //获取接口数据，如果$_REQUEST拿不到数据，则使用file_get_contents函数获取
        $post = $_REQUEST;
        if ($post == null) {
            $post = file_get_contents("php://input");
        }

        if ($post == null) {
            $post = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        }

        if (empty($post) || $post == null || $post == '') {
            //阻止微信接口反复回调接口  文档地址 https://pay.weixin.qq.com/wiki/doc/api/H5.php?chapter=9_7&index=7，下面这句非常重要!!!
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            echo $str;
            exit('Notify 非法回调');
        }
//
//        /*****************微信回调返回数据样例*******************
//         * $post = '<xml>
//         * <return_code><![CDATA[SUCCESS]]></return_code>
//         * <return_msg><![CDATA[OK]]></return_msg>
//         * <appid><![CDATA[wx2421b1c4370ec43b]]></appid>
//         * <mch_id><![CDATA[10000100]]></mch_id>
//         * <nonce_str><![CDATA[IITRi8Iabbblz1Jc]]></nonce_str>
//         * <sign><![CDATA[7921E432F65EB8ED0CE9755F0E86D72F]]></sign>
//         * <result_code><![CDATA[SUCCESS]]></result_code>
//         * <prepay_id><![CDATA[wx201411101639507cbf6ffd8b0779950874]]></prepay_id>
//         * <trade_type><![CDATA[APP]]></trade_type>
//         * </xml>';
//         *************************微信回调返回*****************/
        logwrite("进入微信服务器回调参数为:".json_encode($post));
        libxml_disable_entity_loader(true); //禁止引用外部xml实体
//
        $xml = simplexml_load_string($post, 'SimpleXMLElement', LIBXML_NOCDATA);//XML转数组

        $post_data = $xml;

        /** 解析出来的数组
         *Array
         * (
         * [appid] => wx1c870c0145984d30
         * [bank_type] => CFT
         * [cash_fee] => 100
         * [fee_type] => CNY
         * [is_subscribe] => N
         * [mch_id] => 1297210301
         * [nonce_str] => gkq1x5fxejqo5lz5eua50gg4c4la18vy
         * [openid] => olSGW5BBvfep9UhlU40VFIQlcvZ0
         * [out_trade_no] => fangchan_588796
         * [result_code] => SUCCESS
         * [return_code] => SUCCESS
         * [sign] => F6890323B0A6A3765510D152D9420EAC
         * [time_end] => 20180626170839
         * [total_fee] => 100
         * [trade_type] => JSAPI
         * [transaction_id] => 4200000134201806265483331660
         * )
         **/
        logwrite("接收到的微信的所有参数为------------->".json_encode($post_data));
        $post_data=json_decode(json_encode($post_data),true);
        logwrite("out_trade_no=------------->".$post_data['out_trade_no']);
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $post_data['out_trade_no']])->find(); //获取订单信息
        logwrite("order_info------------------->".json_encode($order_info));
        //订单号
        if ($order_info) {
            //平台支付key
            $wxpay_key = $order_info['key'];

            //接收到的签名
            $post_sign = $post_data['sign'];
            unset($post_data['sign']);

            //重新生成签名
            $newSign = $this->MakeSign($post_data, $wxpay_key);
            logwrite("接收到的签名：".$post_sign."生成的签名：".$newSign);
            //签名统一，则更新数据库
            if ($post_sign == $newSign) {
                logwrite("签名统一--------------------->".json_encode($order_info));
                $this->EditMoney($post_data['out_trade_no'], 'Wxwap', 0);
                //阻止微信接口反复回调接口  文档地址 https://pay.weixin.qq.com/wiki/doc/api/H5.php?chapter=9_7&index=7，下面这句非常重要!!!
                $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
                echo $str;
            }else{
                logwrite("签名验证不通过，订单信息为：".json_encode($order_info));
            }
        }
    }

    function MakeSign($params, $key)
    {
        //签名步骤一：按字典序排序数组参数
        ksort($params);
        $string = $this->ToUrlParams($params);  //参数进行拼接key=value&k=v
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $key;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    function ToUrlParams($params)
    {
        $string = '';
        if (!empty($params)) {
            $array = array();
            foreach ($params as $key => $value) {
                $array[] = $key . '=' . $value;
            }
            $string = implode("&", $array);
        }
        return $string;
    }
}

?>
