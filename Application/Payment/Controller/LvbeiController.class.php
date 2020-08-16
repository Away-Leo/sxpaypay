<?php
namespace Payment\Controller;

use Think\Log;

class LvbeiController extends PaymentController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function PaymentExec($data, $config)
    {

        logwrite("进入绿呗代付通道，提交代付订单。。。。。。。。。。。。。。。。。。。。。。。。。。。。");
        $PayForAnother=M("PayForAnother");
        $Channel=$PayForAnother->where(array('code'=>'Lvbei'))->find();
        if(!$Channel){
            Log::record("绿呗代付通道不存在",Log::INFO);
            return array('status' => 3, 'msg' => "错误：绿呗配置不存在");
        }
        $unlockdomain=$Channel['unlockdomain'];
        if($unlockdomain==null||$unlockdomain==""){
            $unlockdomain=$this->_site;
        }
        $notifyurl = $Channel['serverreturn']; //异步通知
        $params = [
            'timestamp'      => $this->getMillisecond(), //开户人
            'apiKey'      => $Channel['signkey'], //开户人
            'signType'      => 'rsa', //开户人
            'outOrderNo'   => $data['orderid'],
            'amount'         => $data['money']*100, //以分为单位
            'accountType' => 1,
            'accountName'      => $data['bankfullname'], //开户人
            'accountNumber'      => $data['banknumber'], //银行卡号
            'bankName'      => $data['bankname'], //银行名称
            'notifyUrl'      => $notifyurl,
        ];

        $params['sign'] = $this->getSign($params, $Channel['private_key']);
        logwrite("开始请求绿呗代付，参数为：".json_encode($params));
        $resultstr = httpRequest($Channel['exec_gateway'], $params,array('Content-Type = application/x-www-form-urlencoded'));
        logwrite("绿呗代付提交：返回内容：" . $resultstr);
        $result = json_decode($resultstr, true);
        if ($result && intval($result['status']) ==1) {
//            $prepareData=$result['data'];
//            $prepareData['md5_secret']=$Channel['signkey'];
//            if($this->md5Sign($prepareData,$Channel['signkey'])==$result['data']['sign']){
                //将代付通道订单号存入代付表
                M('Wttklist')->where(['orderid'=>$data['orderid']])->save(['df_channel_orderid'=>$result['orderNo']]);
                $return = ['status' => 1, 'msg' => $result['msg']];
//            }else{
//                $return = ['status' => 3, 'msg' => "错误：代付返回数据身份验证错误"];
//            }
        } else {
            $return = ['status' => 3, 'msg' => "错误：{$result['msg']}"];
        }
        return $return;
    }

    public function PaymentQuery($data, $config)
    {
        $PayForAnother=M("PayForAnother");
        $Channel=$PayForAnother->where(array('code'=>'YunDa'))->find();
        if(!$Channel){
            Log::record("绿呗代付通道不存在",Log::INFO);
            return array('status' => 3, 'msg' => "错误：绿呗配置不存在");
        }
        $Wttklist=M('Wttklist')->where(['orderid'=>$data['orderid']])->find();
//        $params = [
//            'commercial_no'  => $Channel['mch_id'],
//            'order_no' => $Wttklist['orderid'],
//            'md5_secret'      => $Channel['signkey']
//        ];
        $params = [
            'timestamp'      => $this->getMillisecond(), //开户人
            'apiKey'      => $Channel['signkey'], //开户人
            'signType'      => 'rsa', //开户人
            'outOrderNo'   => $Wttklist['orderid']
        ];

        $params['sign'] = $this->getSign($params, $Channel['private_key']);
        $resultstr = httpRequest($Channel['query_gateway']."?".http_build_query($params));
        logwrite("绿呗支付代付查询：返回内容：" . $resultstr);
        $result = json_decode($resultstr, true, 512, JSON_BIGINT_AS_STRING);
        switch ($result['status']) {

            case "3": //成功
                //将真实信息写入数据库
                M('Wttklist')->where(['orderid'=>$data['orderid']])->save(array(
                    'df_channel_anount'=>$result['amount']/100,
                    'df_channel_deduct'=>$result['fee']
                ));

                $return = ['status' => 2, 'msg' => '代付成功']; //代付成功
                break;
            case '-1': //失败
                $return = ['status' => 3, 'msg' => json_encode($result)]; //代付失败
                break;
            case '2': //处理中
                $return = ['status' => 1, 'msg' => '处理中']; //申请成功
                break;
            case '1': //待处理
                $return = ['status' => 1, 'msg' => '待处理']; //申请成功
                break;
        }
        return $return;
    }

    function getMillisecond() {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

    private function getSign($params,$privateKey)
    {
        if (!$params || !is_array($params))
            return false;

        ksort($params);
//        $privateKeyHeader = "-----BEGIN RSA PRIVATE KEY-----\n";
//        # TODO 私钥
//        $privateKeyContent = $privateKey;
//        $privateKeyContent = wordwrap($privateKeyContent, 64, "\n", true);
//        $privateKeyEnd = "\n-----END RSA PRIVATE KEY-----";
//
//        $privateKey = $privateKeyHeader . $privateKeyContent . $privateKeyEnd;

        logwrite("RSA私钥为:".$privateKey);

        $keyStr = '';
        foreach ($params as $key => $value) {
            if (empty($keyStr))
                $keyStr = $key . '=' . $value;
            else
                $keyStr .= '&' . $key . '=' . $value;
        }

        if (!$keyStr)
            return false;

        try {
            logwrite("RSA签名字符串为：".$keyStr);
            $key = openssl_get_privatekey($privateKey);
            openssl_sign($keyStr, $signature, $key, "SHA256");
            openssl_free_key($key);
            $sign = base64_encode($signature);
            return $sign ? $sign : false;
        } catch (\Exception $e) {

            return false;
        }
    }

    public function notifyurl(){
        logwrite("进入绿呗支付代付回调------------------------");
//        $response=$_POST;
//        $sign=$response['sign'];
//        $PayForAnother=M("PayForAnother");
//        $Channel=$PayForAnother->where(array('code'=>'YunDa'))->find();
//        $response['md5_secret']=$Channel['signkey'];
//        logwrite('新的数组为：'.json_encode($response));
//        if($this->md5Sign($response,$Channel['signkey'])==$sign){
//            logwrite("绿呗支付代付回调签名通过啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦");
//            $orderid=$response['order_no'];
//            $Wttklist=M('Wttklist')->where(['orderid'=>$orderid])->find();
//            $status=3;
//            if($response['order_state']=="FINISH"){
//                $status=2;
//            }else{
//                $status=3;
//            }
//            $data = [
//                'memo'      => '',
//                'df_id'     => $Channel['id'],
//                'code'      => $Channel['code'],
//                'df_name'   => $Channel['title'],
//            ];
//            $this->handle($Wttklist['id'], $status, $data,$response['balance']);
//            echo ("success");
//            exit();
//        }else{
//            logwrite("绿呗支付代付回调签名不通过------------------------");
//            echo ("FAIL");
//            exit();
//        }

    }
}
