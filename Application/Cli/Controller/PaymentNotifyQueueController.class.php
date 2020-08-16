<?php

namespace Cli\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author mapeijian
 * @date   2018-06-06
 */
class PaymentNotifyQueueController extends Controller
{
    public function index()
    {
        echo "[" . date('Y-m-d H:i:s'). "] 自动执行代付通知触发\n";
        Log::record("自动执行代付通知触发", Log::INFO);
        $List=M('wttk_notify_queue')->where(array('is_finish'=>0))->limit(0,10)->select();
        foreach ($List as $k => $v){
            if(intval($v['callback_num'])>40){
                continue;
            }
            $membernotify=$v['notify_url'];
            $params = [
                'amount' => $v['amount'],
                'actual_amount' => $v['actual_amount'],
                'status' => 'success',
                'out_trade_no' => $v['out_trade_no']
            ];
            $Member = D('Member')->where(array('id' => $v['member_id']))->find();
            $memberapikey = $Member['apikey'];

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
            curl_setopt($ch, CURLOPT_URL, $v['']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $notifystr);
            logwrite("代付通知商户地址为-------------》" . $membernotify);
            $contents = curl_exec($ch);
            logwrite("通知结果为：" . $contents);
            curl_close($ch);
            if (strstr(strtolower($contents), "ok") == false||strlen($contents)>2) {
                Log::record("自动执行代付通知-通知商户失败", Log::INFO);
                M("wttk_notify_queue")->where(array('out_trade_no'=>$v['out_trade_no']))->save(['callback_num'=>intval($v['callback_num'])+1]);
            }else{
                M("wttk_notify_queue")->where(array('out_trade_no'=>$v['out_trade_no']))->save(['is_finish'=>1]);
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