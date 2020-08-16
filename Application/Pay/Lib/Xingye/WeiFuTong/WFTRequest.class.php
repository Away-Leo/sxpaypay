<?php
/**
 * 支付接口调测例子
 * ================================================================
 * index 进入口，方法中转
 * submitOrderInfo 提交订单信息
 * queryOrder 查询订单
 * 
 * ================================================================
 */
namespace Pay\Lib\Xingye\WeiFuTong;


use Pay\Lib\Xingye\WeiFuTong\payclasses\RequestHandler;
use Pay\Lib\Xingye\WeiFuTong\payclasses\ClientResponseHandler;
use Pay\Lib\Xingye\WeiFuTong\payclasses\PayHttpClient;
use Pay\Lib\Xingye\WeiFuTong\Utils;
Class WFTRequest{
  

    private $resHandler = null;
    private $reqHandler = null;
    private $pay = null;
    private $cfg = null;
    
    public function __construct(){
    }

    public function initCons($url,$key){
        $this->resHandler = new ClientResponseHandler();
        $this->reqHandler = new RequestHandler();
        $this->pay = new PayHttpClient();

        $this->reqHandler->setGateUrl($url);

        $sign_type = 'MD5';
        
//        if ($sign_type == 'MD5') {
        logwrite("key=".$key);
            $this->resHandler->setKey($key);
            $this->reqHandler->setSignType($sign_type);
//        } else if ($sign_type == 'RSA_1_1' || $sign_type == 'RSA_1_256') {
//            $this->reqHandler->setRSAKey($this->cfg->C('private_rsa_key'));
//            $this->resHandler->setRSAKey($this->cfg->C('public_rsa_key'));
//            $this->reqHandler->setSignType($sign_type);
//        }
    }
    
//    public function index(){
//        $method = isset($_REQUEST['method'])?$_REQUEST['method']:'submitOrderInfo';
//        switch($method){
//            case 'submitOrderInfo'://提交订单
//                $this->submitOrderInfo();
//            break;
//            case 'queryOrder'://查询订单
//                $this->queryOrder();
//            break;
//            case 'closeOrder'://关闭订单
//                $this->closeOrder();
//            break;
//            case 'submitRefund'://提交退款
//                $this->submitRefund();
//            break;
//            case 'queryRefund'://查询退款
//                $this->queryRefund();
//            break;
//            case 'callback':
//                $this->callback();
//            break;
//        }
//    }
    
    /**
     * 提交订单信息
     */
    public function submitOrderInfo($paras=array()){
        $this->initCons($paras['url'],$paras['key']);
        $this->reqHandler->setKey($paras['key']);
        $this->reqHandler->setParameter('mch_create_ip',$paras['mch_create_ip']);
        $this->reqHandler->setParameter('total_fee',$paras['total_fee']);
        $this->reqHandler->setParameter('body',$paras['body']);
        $this->reqHandler->setParameter('out_trade_no',$paras['out_trade_no']);
        $this->reqHandler->setParameter('service','unified.trade.native');//接口类型：unified.trade.native   表示统一扫码
     // $this->reqHandler->setParameter('service','pay.alipay.native');//接口类型：pay.alipay.native  表示支付宝扫码
        // $this->reqHandler->setParameter('service','pay.unionpay.native');//接口类型：pay.unionpay.native   表示银联钱包扫码
        $this->reqHandler->setParameter('mch_id',$paras['mch_id']);//必填项，商户号，由平台分配
        $this->reqHandler->setParameter('version','2.0');
        $this->reqHandler->setParameter('sign_type','MD5');
        
        
        //通知地址，必填项，接收平台通知的URL，需给绝对路径，255字符内格式如:http://wap.tenpay.com/tenpay.asp

		$this->reqHandler->setParameter('notify_url',$paras['notify_url']);// 支付成功异步回调通知地址，目前默认是空格，商户在测试支付和上线时必须改为自己的，且保证外网能访问到
        $this->reqHandler->setParameter('nonce_str',mt_rand());//随机字符串，必填项，不长于 32 位
        $this->reqHandler->createSign();//创建签名
        
        $data = Utils::toXml($this->reqHandler->getAllParameters());
        logwrite("生成的最终参数为：".$data);
        $this->pay->setReqContent($this->reqHandler->getGateURL(),$data);
        if($this->pay->call()){
            $this->resHandler->setContent($this->pay->getResContent());
            $this->resHandler->setKey($this->reqHandler->getKey());
            if($this->resHandler->isTenpaySign()){
                //当返回状态与业务结果都为0时才返回支付二维码，其它结果请查看接口文档
                if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
                    return array('code_img_url'=>$this->resHandler->getParameter('code_img_url'),
                                           'code_url'=>$this->resHandler->getParameter('code_url'),
                                           'code_status'=>$this->resHandler->getParameter('code_status'),
                                           'type'=>$this->reqHandler->getParameter('service'));
                }else{
                    return array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('err_code').' Error Message:'.$this->resHandler->getParameter('err_msg'));
                }
            }
            return array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('status').' Error Message:'.$this->resHandler->getParameter('message'));
        }else{
            return array('status'=>500,'msg'=>'Response Code:'.$this->pay->getResponseCode().' Error Info:'.$this->pay->getErrInfo());
        }
    }

    /**
     * 查询订单
     */
    public function queryOrder(){
        $this->reqHandler->setReqParams($_POST,array('method'));
        $reqParam = $this->reqHandler->getAllParameters();
        if(empty($reqParam['transaction_id']) && empty($reqParam['out_trade_no'])){
            echo json_encode(array('status'=>500,
                                   'msg'=>'请输入商户订单号,平台订单号!'));
            exit();
        }
        $this->reqHandler->setParameter('version',$this->cfg->C('version'));
        $this->reqHandler->setParameter('service','unified.trade.query');//接口类型：unified.trade.query
        $this->reqHandler->setParameter('mch_id',$this->cfg->C('mchId'));//必填项，商户号，由平台分配
        $this->reqHandler->setParameter('nonce_str',mt_rand());//随机字符串，必填项，不长于 32 位
        $this->reqHandler->setParameter('sign_type',$this->cfg->C('sign_type'));
        $this->reqHandler->createSign();//创建签名
        $data = Utils::toXml($this->reqHandler->getAllParameters());

        $this->pay->setReqContent($this->reqHandler->getGateURL(),$data);
        if($this->pay->call()){
            $this->resHandler->setContent($this->pay->getResContent());
            $this->resHandler->setKey($this->reqHandler->getKey());
            if($this->resHandler->isTenpaySign()){
                $res = $this->resHandler->getAllParameters();
                Utils::dataRecodes('查询订单',$res);
                //支付成功会输出更多参数，详情请查看文档中的7.1.4返回结果
                echo json_encode(array('status'=>200,'msg'=>'查询订单成功，请查看result.txt文件！','data'=>$res));
                exit();
            }
            echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('status').' Error Message:'.$this->resHandler->getParameter('message')));
        }else{
            echo json_encode(array('status'=>500,'msg'=>'Response Code:'.$this->pay->getResponseCode().' Error Info:'.$this->pay->getErrInfo()));
        }
    }

   /* 关闭订单*/
    
    public function closeOrder() {
        $this->reqHandler->setReqParams($_POST,array('method'));
        $reqParam = $this->reqHandler->getAllParameters();
        if(empty($reqParam['out_trade_no'])){
            echo json_encode(array('status'=>500,
                                   'msg'=>'请输入商户订单号!'));
            exit();
        }
        $this->reqHandler->setParameter('version',$this->cfg->C('version'));
        $this->reqHandler->setParameter('service','unified.trade.close');//接口类型：unified.trade.close
        $this->reqHandler->setParameter('mch_id',$this->cfg->C('mchId'));//必填项，商户号，由平台分配
        $this->reqHandler->setParameter('nonce_str',mt_rand());//随机字符串，必填项，不长于 32 位
        $this->reqHandler->setParameter('sign_type',$this->cfg->C('sign_type'));
        $this->reqHandler->createSign();//创建签名
        $data = Utils::toXml($this->reqHandler->getAllParameters());

        $this->pay->setReqContent($this->reqHandler->getGateURL(),$data);
        if($this->pay->call()){
            $this->resHandler->setContent($this->pay->getResContent());
            $this->resHandler->setKey($this->reqHandler->getKey());
            if($this->resHandler->isTenpaySign()){
           //当返回状态与业务结果都为0时才返回支付二维码，其它结果请查看接口文档
                if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
                    
                    $res = $this->resHandler->getAllParameters();
                    Utils::dataRecodes('关闭订单',$res);
                    echo json_encode(array('status'=>200,'msg'=>'关闭订单成功,请查看result.txt文件！','data'=>$res));
                    exit();
                }else{
                    echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('err_code').' Error Message:'.$this->resHandler->getParameter('err_msg')));
                    exit();
                }
            }
            echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('status').' Error Message:'.$this->resHandler->getParameter('message')));
        }else{
            echo json_encode(array('status'=>500,'msg'=>'Response Code:'.$this->pay->getResponseCode().' Error Info:'.$this->pay->getErrInfo()));
        }
    }
    /**
     * 提交退款
     */
    public function submitRefund(){
        $this->reqHandler->setReqParams($_POST,array('method'));
        $reqParam = $this->reqHandler->getAllParameters();
        if(empty($reqParam['transaction_id']) && empty($reqParam['out_trade_no'])){
            echo json_encode(array('status'=>500,
                                   'msg'=>'请输入商户订单号或平台订单号!'));
            exit();
        }
        $this->reqHandler->setParameter('version',$this->cfg->C('version'));
        $this->reqHandler->setParameter('service','unified.trade.refund');//接口类型：unified.trade.refund
        $this->reqHandler->setParameter('mch_id',$this->cfg->C('mchId'));//必填项，商户号，由平台分配
        $this->reqHandler->setParameter('nonce_str',mt_rand());//随机字符串，必填项，不长于 32 位
        $this->reqHandler->setParameter('op_user_id',$this->cfg->C('mchId'));//必填项，操作员帐号,默认为商户号
        $this->reqHandler->setParameter('sign_type',$this->cfg->C('sign_type'));

        $this->reqHandler->createSign();//创建签名
        $data = Utils::toXml($this->reqHandler->getAllParameters());//将提交参数转为xml，目前接口参数也只支持XML方式

        $this->pay->setReqContent($this->reqHandler->getGateURL(),$data);
        if($this->pay->call()){
            $this->resHandler->setContent($this->pay->getResContent());
            $this->resHandler->setKey($this->reqHandler->getKey());
            if($this->resHandler->isTenpaySign()){
                //当返回状态与业务结果都为0时才返回支付二维码，其它结果请查看接口文档
                if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
                    
                    $res = $this->resHandler->getAllParameters();
                    Utils::dataRecodes('提交退款',$res);
                    echo json_encode(array('status'=>200,'msg'=>'退款成功,请查看result.txt文件！','data'=>$res));
                    exit();
                }else{
                    echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('err_code').' Error Message:'.$this->resHandler->getParameter('err_msg')));
                    exit();
                }
            }
            echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('status').' Error Message:'.$this->resHandler->getParameter('message')));
        }else{
            echo json_encode(array('status'=>500,'msg'=>'Response Code:'.$this->pay->getResponseCode().' Error Info:'.$this->pay->getErrInfo()));
        }
    }

    /**
     * 查询退款
     */
    public function queryRefund(){
        $this->reqHandler->setReqParams($_POST,array('method'));
        if(count($this->reqHandler->getAllParameters()) === 0){
            echo json_encode(array('status'=>500,
                                   'msg'=>'请输入商户订单号,平台订单号,商户退款单号,平台退款单号!'));
            exit();
        }
        $this->reqHandler->setParameter('version',$this->cfg->C('version'));
        $this->reqHandler->setParameter('service','unified.trade.refundquery');//接口类型：unified.trade.refundquery
        $this->reqHandler->setParameter('mch_id',$this->cfg->C('mchId'));//必填项，商户号，由平台分配
        $this->reqHandler->setParameter('nonce_str',mt_rand());//随机字符串，必填项，不长于 32 位
        $this->reqHandler->setParameter('sign_type',$this->cfg->C('sign_type'));
        
        $this->reqHandler->createSign();//创建签名
        $data = Utils::toXml($this->reqHandler->getAllParameters());//将提交参数转为xml，目前接口参数也只支持XML方式

        $this->pay->setReqContent($this->reqHandler->getGateURL(),$data);//设置请求地址与请求参数
        if($this->pay->call()){
            $this->resHandler->setContent($this->pay->getResContent());
            $this->resHandler->setKey($this->reqHandler->getKey());
            if($this->resHandler->isTenpaySign()){
                //当返回状态与业务结果都为0时才返回支付二维码，其它结果请查看接口文档
                if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
                    
                    $res = $this->resHandler->getAllParameters();
                    Utils::dataRecodes('查询退款',$res);
                    echo json_encode(array('status'=>200,'msg'=>'查询成功,请查看result.txt文件！','data'=>$res));
                    exit();
                }else{
                    echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$this->resHandler->getParameter('err_code')));
                    exit();
                }
            }
            echo json_encode(array('status'=>500,'msg'=>$this->resHandler->getContent()));
        }else{
            echo json_encode(array('status'=>500,'msg'=>'Response Code:'.$this->pay->getResponseCode().' Error Info:'.$this->pay->getErrInfo()));
        }
    }
    
    /**
     * 异步通知回调
     */
    public function callback($key){
        $xml = file_get_contents('php://input');
		file_put_contents('1.txt',$xml);//检测是否执行callback方法，如果执行，会生成1.txt文件，且文件中的内容就是通知参数
        $this->resHandler->setContent($xml);
		//var_dump($this->resHandler->setContent($xml));
        $this->resHandler->setKey($key);
        if($this->resHandler->isTenpaySign()){
            if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
				$tradeno = $this->resHandler->getParameter('out_trade_no');
				// 此处可以在添加相关处理业务，校验通知参数中的商户订单号out_trade_no和金额total_fee是否和商户业务系统的单号和金额是否一致，一致后方可更新数据库表中的记录。
				//更改订单状态
				
				ob_clean();
				return $tradeno;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getXml(){
        return file_get_contents('php://input');
    }

    public function checkSignAndResult($key){
        logwrite("进入签名和对");
        $xml=file_get_contents('php://input');
        $this->resHandler = new ClientResponseHandler();
        $this->resHandler->setContent($xml);
        $this->resHandler->setKey($key);
        if($this->resHandler->isTenpaySign()){
            if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getOrderId(){
        logwrite("进入获取ID");
        $xml=file_get_contents('php://input');
        $this->resHandler = new ClientResponseHandler();
        $this->resHandler->setContent($xml);
        logwrite("设置之后的参数为：".json_encode($this->resHandler->getAllParameters()));
        return $this->resHandler->getParameter('out_trade_no');
    }
}
