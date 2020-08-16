<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-08-22
 * Time: 14:34
 */

namespace Payment\Controller;

/**
 * 用户中心首页控制器
 * Class IndexController
 * @package User\Controller
 */

use Think\Controller;

class PaymentController extends Controller
{

    //网站地址
    protected $_site;
    protected $verify_data_ = [
        'code' => '请选择代付方式！',
        'id' => '请选择代付订单！',
        'opt' => '操作方式错误！',
    ];


    public function __construct()
    {
        parent::__construct();
        $this->_site = ((is_https()) ? 'https' : 'http') . '://' . C("DOMAIN") . '/';
    }

    protected function findPaymentType($code = 'default')
    {
        $where['status'] = 1;
        if ($code == 'default') {
            $where['is_default'] = 1;
        } else {
            $where['id'] = $code;
        }
        $list = M('PayForAnother')->where($where)->find();
        $list || showError('支付方式错误');
        return $list;
    }

    protected function selectOrder($where)
    {

        $lists = M('Wttklist')->where($where)->select();
        $lists || showError('无该代付订单或订单当前状态不允许该操作！');
        foreach ($lists as $k => $v) {
            $lists[$k]['additional'] = json_decode($v['additional'], true);
        }
        return $lists;
    }


    protected function checkMoney($uid, $money)
    {
        $where = ['id' => $uid];
        $balance = M('Member')->where($where)->getField('balance');
        $balance < $money && showError('支付金额错误');
    }

    protected function handle($id, $status = 1, $return, $channelReturnAmount = null)
    {

        //处理成功返回的数据
        $data = array();
        if ($status == 1) {
            $data['status'] = 1;
            $data['memo'] = '申请成功！';
        } else if ($status == 2) {
            $data['status'] = 2;
            $data['cldatetime'] = date('Y-m-d H:i:s', time());
            $data['memo'] = '代付成功';
        } else if ($status == 3) {
            $data['status'] = 4;
            $data['memo'] = isset($return['memo']) ? $return['memo'] : '代付失败！';
        }
        if (in_array($status, [1, 2, 3])) {
            $data = array_merge($data, $return);
            if ($channelReturnAmount != null) {
                $data['df_channel_anount'] = $channelReturnAmount;
            }
            $where = ['id' => $id];
            M('Wttklist')->where($where)->save($data);

            if ($status == 2) {//进行商户系统回调通知
                $Wttk = M('Wttklist')->where($where)->find();
                if (!empty($Wttk['api_order_trade_no']) && $Wttk['api_order_trade_no'] != "") {
                    $api_order = M("df_api_order")->where(array('id' => $Wttk['de_api_id']))->find();
                    $Member = D('Member')->where(array('id' => $api_order['userid']))->find();
                    $memberapikey = $Member['apikey'];
                    $membernotify = $api_order['notifyurl'];
                    if(!empty($membernotify)||$membernotify!=""){
                        $params = [
                            'amount' => $Wttk['money'],
                            'status' => 'success',
                            'out_trade_no' => $api_order['out_trade_no']
                        ];
                        $params['sign'] = $this->createSign($memberapikey, $params);

                        //进行通知
                        $notifystr = "";
                        foreach ($params as $key => $val) {
                            $notifystr = $notifystr . $key . "=" . $val . "&";
                        }
                        $notifystr = rtrim($notifystr, '&');
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_URL, $membernotify);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $notifystr);
                        logwrite("代付通知商户地址为-------------》" . $membernotify);
                        $contents = curl_exec($ch);
                        logwrite("通知结果为：" . $contents);
                        curl_close($ch);
                        if (strstr(strtolower($contents), "ok") == false||strlen($contents)>2) {
                            $WttkNotifyQueue=M("wttk_notify_queue")->where(array('out_trade_no'=>$api_order['out_trade_no']))->find();
                            if(!$WttkNotifyQueue){
                                $addpara=[
                                    'member_id'=>$api_order['user_id'],
                                    'callback_num'=>1,
                                    'notify_url'=>$membernotify,
                                    'is_finish'=>0,
                                    'amount'=>$Wttk['money'],
                                    'actual_amount'=>$channelReturnAmount,
                                    'status'=>'success',
                                    'out_trade_no'=>$api_order['out_trade_no']
                                ];
                                M("wttk_notify_queue")->add($addpara);
                            }
                        }
                    }
                }
            }
        }

    }

    protected function createSign($Md5key, $list)
    {
        ksort($list);
        $md5str = "";
        foreach ($list as $key => $val) {
            if (!empty($val)) {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));
        return $sign;
    }

}