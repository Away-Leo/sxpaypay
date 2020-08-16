<?php
/**
 * Created by PhpStorm.
 * Date: 2018-11-03
 * Time: 00:30
 */
namespace Payment\Controller;

/**
 * 用户中心首页控制器
 * Class IndexController
 * @package User\Controller
 */
class IndexController extends PaymentController{



    public function __construct(){
        parent::__construct();
        $_site = ((is_https()) ? 'https' : 'http') . '://' . C("DOMAIN") . '/';
    }



    public function index(){
        logwrite("进入代付分发controller-----------------------------------------");
        //判断是否登录
        isLogin();
        //验证传来的数据
        $post_data = verifyData($this->verify_data_);
		logwrite("post_data=".json_encode($post_data));
        //获取要操作的订单id
        $post_data['id'] = explode(',', rtrim($post_data['id'], ',') );
		
		
        //根据操作查询不同状态的订单
        if ($post_data['opt'] == 'exec') {
            $status = 0;
        } else {
            $status = ['in', '1, 4'];
        }
        $where = ['id'=>['in', $post_data['id']], 'status'=>$status];

        $wttk_lists = $this->selectOrder($where);
		logwrite("wttk_lists=".json_encode($wttk_lists));
       //$post_data['code'] = $post_data['opt'] == 'exec'?$post_data['code']:$wttk_lists[0]['df_id'];
		//获取要代付的通道信息
      
		$post_data['code'] = $post_data['opt'] == 'exec'?$post_data['code']: (empty($wttk_lists[0]['df_id']) ? $post_data['code'] : $wttk_lists[0]['df_id']);
        //获取不到代付的通道信息 就开启 49-50行 关闭46-47行
        logwrite("post_data[code]=".$post_data['code']);
        $pfa_list = $this->findPaymentType($post_data['code']);
        logwrite("pfa_list=".json_encode($pfa_list));
        //检查代付金额与用户金额是否相同
        //$this->checkMoney($wttk_lists['userid'] , $wttk_lists['money']);
		
        //判断代付通道的文件是否存在
        $code = $pfa_list['code'];
        $code || showError('代付渠道不存在！');
        $file = APP_PATH . 'Payment/Controller/' . $code . 'Controller.class.php';
        logwrite("IS_FILE=".is_file($file));
        is_file($file) || showError('代付渠道不存在！');
        //循环存在代付通道的文件限制一次只能操作15条数据
        $opt = ucfirst( $post_data['opt']);
        if($opt == 'Exec' && !session('admin_submit_df')) {
            showError('未通过身份验证！');
        }
        logwrite("进入正题----------------;post_data['opt']=".$post_data['opt']);
        if( count($wttk_lists)<= 15){
            logwrite("开始处理---------------------------");
            $fp = fopen($file, "r");
            foreach($wttk_lists as $k => $v){
                try {
                    logwrite("文件锁：".flock($fp, LOCK_EX));
                    //开启文件锁防止多人操作重复提交
                    if (flock($fp, LOCK_EX)) {
                        if ($opt == 'Exec') {
                            //加锁防止重复提交
                            $res = M('Wttklist')->where(['id' => $v['id'], 'df_lock' => 0])->setField('df_lock', 1);
                            logwrite("res=".$res);
                            if (!$res) {
                                logwrite("加锁失败------------------------");
                                continue;
                            }
                        }						
						
                        $v['money'] = round($v['money'], 2);
                        logwrite("选择的控制器为：".'Payment/' . $code . '/Payment' . $opt);
                        $result = R('Payment/' . $code . '/Payment' . $opt, [$v, $pfa_list]);
                        logwrite("处理结果为：".json_encode($result));
                        if ($result == FALSE) {
                            if ($opt == 'Exec') {
                                M('Wttklist')->where(['id' => $v['id']])->setField('df_lock', 0);
                            }
                            showError('提交失败！');
                        }						
                        if (is_array($result)) {
                            $cost = $pfa_list['rate_type'] ? bcmul($v['money'], $pfa_list['cost_rate'], 2) : $pfa_list['cost_rate'];
                            $data = [
                                'memo' => $result['msg'],
                                'df_id' => $pfa_list['id'],
                                'code' => $pfa_list['code'],
                                'df_name' => $pfa_list['title'],
                                'channel_mch_id' => $pfa_list['mch_id'],
                                'cost_rate' => $pfa_list['cost_rate'],
                                'cost' => $cost,
                                'rate_type' => $pfa_list['rate_type'],
                            ];
                            $this->handle($v['id'], $result['status'], $data);
                        }
                    }
                    if ($opt == 'Exec') {
                        M('Wttklist')->where(['id' => $v['id']])->setField('df_lock', 0);
                    }
                    flock($fp, LOCK_UN);
                } catch (\Exception $e) {
                    if ($opt == 'Exec') {
                        M('Wttklist')->where(['id' => $v['id']])->setField('df_lock', 0);
                    }
                }
            }
            fclose($fp);
            if($opt == 'Query') {
                showSuccess($result['msg']);
            } else {
                showSuccess('请求成功！');
            }
            exit;
        }
        if($opt == 'Exec') {
            session('admin_submit_df', null);
        }
        showError('只能同时请求15条代付数据！');
    }

    //定时任务-查询上游代付订单
    public function evenQuery(){
        $where = ['status'=>1];
        $wttk_lists = $this->selectOrder($where);
        foreach($wttk_lists as $k => $v){
            $file = APP_PATH . 'Payment/Controller/' . $v['code'] . 'Controller.class.php';
            if( is_file($file) ){
                $pfa_list = $this->findPaymentType($v['df_id']);
                $result = R('Payment/'.$v['code'].'/PaymentQuery', [$v, $pfa_list]);
                $result!==FALSE || showError('服务器请求失败！');
                if(is_array($result)){
                    $data = [
                        'msg'       => $result['msg'],
                        'df_id'     => $pfa_list['id'],
                        'code'      => $pfa_list['code'],
                        'df_name'   => $pfa_list['title'],
                    ];
                    $this->handle($v['id'], $result['status'], $data);
                }
            }
            sleep(3);
        }
    }

    //批量查询代付订单状态
    public function batchQuery(){
        //判断是否登录
        isLogin();
        $id = I('post.id', '');
        //获取要查询的订单id
        $id = explode(',', rtrim($id, ',') );
        if(empty($id)) {
            showError('请选择订单！');
        }
        $where['id'] = ['in', $id];
        $wttk_lists = M('Wttklist')->where($where)->select();
        $success = 0;
        foreach($wttk_lists as $k => $v){
            if($v['status'] != 1 && $v['status'] != 4) {
                continue;
            }
            $file = APP_PATH . 'Payment/Controller/' . $v['code'] . 'Controller.class.php';
            if( file_exists($file) ){
                $pfa_list = M('PayForAnother')->where(['id'=>$v['df_id']])->find();
                if(empty($pfa_list)) {
                    continue;
                }
                if($v['additional']) {
                    $v['additional'] = json_decode($v['additional'],true);
                }
                $result = R('Payment/'.$v['code'].'/PaymentQuery', [$v, $pfa_list]);
                if(FALSE === $result) {
                    continue;
                } else {
                    if(is_array($result)){
                        $success++;
                        $data = [
                            'msg'       => $result['msg'],
                            'df_id'     => $pfa_list['id'],
                            'code'      => $pfa_list['code'],
                            'df_name'   => $pfa_list['title'],
                        ];
                        $this->handle($v['id'], $result['status'], $data);
                    }
                }
            } else {
                continue;
            }
        }
        if($success == 0) {
            showError('查询失败！');
        } else {
            showSuccess('查询成功,请在页面刷新后查看订单状态！');
        }
    }
}
