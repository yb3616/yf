<?php
/**
 * 指定返回值类型
 * 便于 IDE 等自动识别
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use YF\Cache;
use YF\Cookie;
use YF\Config;
use YF\DB;
use YF\Exception;
use YF\Router;
use YF\Request;
use YF\Response;
use YF\Log;
use YF\MPTTA;
use YF\Session;
use YF\User;
use YF\Http;
use YF\File;

class App
{
  /**
   * 运行程序
   *
   * @param   $router_fp    string    路由文件路径（相对路径）
   *
   * @return  $self         object    返回实例化的对象
   */
  static public function run( string $router_fp )
  {
    // 手动初始化
    $root = dirname( __DIR__ );
    Config::init( $root, '/config' );

    // 输出程序执行时间（伪，跳过了加载文件composer.php以及config/*的时间）
    if( Config::get( 'app.debug' ) ){
      $start = microtime( true );
    }

    // 配置环境
    self::_init();

    // 路由
    Router::run( $root . $router_fp );

    // 输出执行时间
    if( Config::get( 'app.debug' ) ){
      Response::withHeader( 'APP-Running', (string)( (microtime(true)-$start)*1000 ) . 'ms' );
    }

    // 发送响应数据
    Exception::send();
    Response::send();
  }

  /**
   * 设置环境
   * 暂时放配置文件中
   */
  static private function _init()
  {
    // 获得配置参数
    $config = Config::get('app');

    // 调试
    ini_set( 'display_errors', 0 );
    ini_set( 'display_startup_errors', 0 );
    if( $config['debug'] ){
      set_error_handler( ['\\YF\\Exception', 'error'], E_ALL | E_STRICT );
      set_exception_handler([ '\\YF\\Exception', 'exception' ]);
    }

    //  设置时区
    date_default_timezone_set( $config['timezone'] );

    // 初始化静态类
    // 按照先初始化无YF依赖类，再依次初始化依赖类
    // 无依赖类
    File::init( Config::get( 'app.root' ) );
    Log::init( Config::get('log'), Config::get('app.root') );
    Cookie::init( Config::get( 'cookie' ) );
    Session::init( Config::get('session') );
    Cache::init( Config::get( 'cache' ), Config::get( 'app.root' ) );

    // DB 依赖 Response 类
    DB::init( Config::get('db'), Config::get('app.debug') );

    // User 依赖 Session 和 Cookie 类
    User::init( Config::get('user') );

    // Http 依赖 File 类
    Http::init( Config::get( 'http' ) );

    // MPTTA 依赖 DB 类
    MPTTA::init( Config::get('mptta'), Config::get( 'db.master' )['pre'] );

    // Request 依赖 Response 类
    Request::init( Config::get('request'), Config::get('app.root') );
  }
}
