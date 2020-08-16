<?php

/**
 * 命令行入口
 * 使用方式： php cli.php controller_action
 * 示例： php cli.php unfreeze_index
 * 也可简写为： php cli.php unfreeze
 */

if (PHP_SAPI != 'cli') {
    die('cli only!');
}

// 检测PHP环境
if (version_compare(PHP_VERSION, '5.4.0', '<')) {
    die('require PHP > 5.4.0 !');
}
define('hopedir', dirname(__FILE__).DIRECTORY_SEPARATOR);
// 定义应用目录
define('APP_PATH', dirname(__FILE__).'/Application/');
/**
 * 系统调试设置
 * 项目正式部署后请设置为false
 */
define('APP_DEBUG', true);

/*
 * 缓存目录设置
 * 此目录必须可写，建议移动到非WEB目录
 */
define ( 'RUNTIME_PATH', dirname(__FILE__).'/Runtime/' );

//绑定模块
define ( 'BIND_MODULE','Cli');

// 引入ThinkPHP入口文件
require dirname(__FILE__).'/core/ThinkPHP.php';