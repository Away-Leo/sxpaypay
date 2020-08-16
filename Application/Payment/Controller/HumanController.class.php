<?php
namespace Payment\Controller;

use Think\Log;

class HumanController extends PaymentController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function PaymentExec($data, $config)
    {

        logwrite("进入纯手动代付通道，提交代付订单。。。。。。。。。。。。。。。。。。。。。。。。。。。。");
        $PayForAnother=M("PayForAnother");
        $Channel=$PayForAnother->where(array('code'=>'Human'))->find();
//        if(!$Channel){
//            Log::record("远达代付通道不存在",Log::INFO);
//            return array('status' => 3, 'msg' => "错误：远达配置不存在");
//        }
//        $unlockdomain=$Channel['unlockdomain'];
//        if($unlockdomain==null||$unlockdomain==""){
//            $unlockdomain=$this->_site;
//        }
//        $notifyurl = $Channel['serverreturn']; //异步通知
//        $params = [
//            'commercial_no' => $Channel['mch_id'],
//            'order_no'   => $data['orderid'],
//            'balance'         => $data['money'], //以分为单位
//            'paid_type' => 'MANUAL_BANK',
//            'notify_url'      => $notifyurl,
//            'bank_name'      => $data['bankname'], //银行名称
//            'bank_no'      => $data['banknumber'], //银行卡号
//            'bank_user_name'      => $data['bankfullname'], //开户人
//            'md5_secret'      => $Channel['signkey'], //开户人
//        ];
//
//        $params['sign'] = $this->md5Sign($params, $Channel['signkey']);
//        unset($params['md5_secret']);
//        logwrite("开始请求远达代付，参数为：".json_encode($params));
//        $resultstr = httpRequest($Channel['exec_gateway'], $params,array('Content-Type = application/x-www-form-urlencoded'));
//        logwrite("远达代付提交：返回内容：" . $resultstr);
//        $result = json_decode($resultstr, true);
//        if ($result && intval($result['code']) == '0') {
//            $prepareData=$result['data'];
//            $prepareData['md5_secret']=$Channel['signkey'];
//            if($this->md5Sign($prepareData,$Channel['signkey'])==$result['data']['sign']){
                //将代付通道订单号存入代付表
                M('Wttklist')->where(['orderid'=>$data['orderid']])->save(['df_channel_orderid'=>$data['orderid']]);
                $Wttklist=M('Wttklist')->where(['orderid'=>$data['orderid']])->find();
                $data = [
                    'memo'      => '',
                    'df_id'     => $Channel['id'],
                    'code'      => $Channel['code'],
                    'df_name'   => $Channel['title'],
                ];
                M('Wttklist')->where(['orderid'=>$data['orderid']])->save(array(
                    'df_channel_anount'=>$data['money']
                ));
        M('Tklist')->where(['orderid'=>$data['orderid']])->save(['status'=>2]);
                $this->handle($Wttklist['id'], 2, $data,$data['money']);
                $return = ['status' => 2, 'msg' => '代付成功'];
//            }else{
//                $return = ['status' => 3, 'msg' => "错误：代付返回数据身份验证错误"];
//            }
//        } else {
//            $return = ['status' => 3, 'msg' => "错误：{$result['msg']}"];
//        }
        return $return;
    }

    public function PaymentQuery($data, $config)
    {
//        $PayForAnother=M("PayForAnother");
//        $Channel=$PayForAnother->where(array('code'=>'YunDa'))->find();
//        if(!$Channel){
//            Log::record("远达代付通道不存在",Log::INFO);
//            return array('status' => 3, 'msg' => "错误：远达配置不存在");
//        }
//        $Wttklist=M('Wttklist')->where(['orderid'=>$data['orderid']])->find();
//        $params = [
//            'commercial_no'  => $Channel['mch_id'],
//            'order_no' => $Wttklist['orderid'],
//            'md5_secret'      => $Channel['signkey']
//        ];
//        $params['sign'] = $this->md5Sign($params, $Channel['signkey']);
//        unset($params['md5_secret']);
//        $resultstr = httpRequest($Channel['query_gateway'], $params,array('Content-Type = application/x-www-form-urlencoded'));
//        logwrite("远达支付代付查询：返回内容：" . $resultstr);
//        $result = json_decode($resultstr, true, 512, JSON_BIGINT_AS_STRING);
//        if ($result && intval($result['code']) == 0) {
//            $signStr=$result['paras']['sign'];
//            $datas=$result['data'];
//            $datas['md5_secret']=$Channel['signkey'];
//            $datas['actual']=number_format($datas['actual'],2)."";
//            $datas['deduct']=number_format($datas['deduct'],2)."";
//            $datas['prepaid']=number_format($datas['prepaid'],2)."";
//            $datas['poundage']=number_format($datas['poundage'],2)."";
//            if($this->md5Sign($datas,$Channel['signkey'])==$signStr){
//                $requestResult=$result['data'];
//                switch ($requestResult['order_state']) {
//
//                    case 'FINISH': //成功
//                        //将真实信息写入数据库
//                        M('Wttklist')->where(['orderid'=>$data['orderid']])->save(array(
//                            'df_channel_anount'=>$requestResult['actual'],
//                            'df_channel_deduct'=>$requestResult['deduct'],
//                            'df_channel_poundage'=>$requestResult['poundage'],
//                            'df_channel_prepaid'=>$requestResult['prepaid']
//                        ));

                        $return = ['status' => 2, 'msg' => '代付成功']; //代付成功
//                        break;
//                    case 'REJECT': //失败
//                        $return = ['status' => 3, 'msg' => $requestResult['system_remark']]; //代付失败
//                        break;
//                    case 'PROCESS': //处理中
//                        $return = ['status' => 1, 'msg' => '申请成功']; //申请成功
//                        break;
//                    default:
//                        $return = ['status' => 3, 'msg' => $requestResult['system_remark']]; //代付失败
//                        break;
//                }
//            }else{
//                $return = ['status' => 3, 'msg' => "错误：查询代付结果身份验证出错"];
//            }
//
//        } else {
//            $return = ['status' => 3, 'msg' => "错误：{$result['msg']}"];
//        }

        return $return;
    }

    private function md5Sign($data, $key)
    {
        $signSrc = "";
        ksort($data);
        foreach ($data as $k => $v) {
            if ($k != 'sign') {
                $signSrc .= $k . "=" . $v . "&";
            }
        }
        $signSrc = rtrim($signSrc, '&');
//        $signSrc .= "md5_secret=".$key;
        logwrite("运达生成的签名字符串为:".$signSrc);
        return md5($signSrc);  //MD5加密
    }

    public function encryptDecrypt($string, $key = '', $decrypt = '0')
    {
        if ($decrypt) {
            $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($string), MCRYPT_MODE_CBC, md5(md5($key))), "12");
            return $decrypted;
        } else {
            $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $string, MCRYPT_MODE_CBC, md5(md5($key))));
            return $encrypted;
        }
    }

    public function notifyurl(){
        logwrite("进入远达支付代付回调------------------------");
        $response=$_POST;
        $sign=$response['sign'];
        $PayForAnother=M("PayForAnother");
        $Channel=$PayForAnother->where(array('code'=>'YunDa'))->find();
        $response['md5_secret']=$Channel['signkey'];
        logwrite('新的数组为：'.json_encode($response));
        if($this->md5Sign($response,$Channel['signkey'])==$sign){
            logwrite("远达支付代付回调签名通过啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦啦");
            $orderid=$response['order_no'];
            $Wttklist=M('Wttklist')->where(['orderid'=>$orderid])->find();
            $status=3;
            if($response['order_state']=="FINISH"){
                $status=2;
            }else{
                $status=3;
            }
            $data = [
                'memo'      => '',
                'df_id'     => $Channel['id'],
                'code'      => $Channel['code'],
                'df_name'   => $Channel['title'],
            ];
            $this->handle($Wttklist['id'], $status, $data,$response['balance']);
            echo ("success");
            exit();
        }else{
            logwrite("远达支付代付回调签名不通过------------------------");
            echo ("FAIL");
            exit();
        }

    }
}
