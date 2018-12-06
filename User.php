<?PHP
/**
 * 用户相关表
 *
 * 表结构（参考字段）
 * CREATE TABLE `users` (
 *   `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
 *   `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '登录名',
 *   `password` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '登录密码',
 *   `password_cookie` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cookie 密码（免密登录）',
 *   PRIMARY KEY (`id`),
 *   UNIQUE KEY `username` (`username`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use Exception;

use YF\Session;
use YF\Cookie;

class User
{
  /**
   * 配置内容
   */
  static private $_config = [];

  /**
   * 初始化配置内容
   *
   * @param   $config   array   配置
   *
   * @return
   */
  static public function init( array $config )
  {
    if( empty( self::$_config ) ){
      self::$_config = $config;
    }
  }

  /**
   * 判断当前访问者是否为游客
   *
   * @param
   *
   * @return    bool
   */
  static public function isGuest() : bool
  {
    return !Session::has( 'uid', self::$_config['session']['path'] );
  }

  /**
   * 登录操作（会话保持相关操作）
   *
   * @param   $uid              int     用户主键
   * @param   $password_cookie  string  用户密码（专用于 cookie ）
   * @param   $extr             array   额外保存会话中的参数，越少越好，默认为空数组
   *                                    若需要使用角色相关参数，第三参数请输入['rids'=>[1,2,3...]]
   *                                    请避免覆盖 uid 字段
   *
   * @return
   */
  static public function login( int $uid, string $password_cookie, array $extra=[] )
  {
    Session::set( 'uid', $uid, self::$_config['session']['path'] );
    foreach( $extra as $k => $v ){
      if( $k === 'uid' ){
        throw new Exception( '禁止重写用户主键' );
      }
      Session::set( $k, $v, self::$_config['session']['path'] );
    }

    // 记住密码，用于免密登录
    Cookie::set('uid', self::_cookiePassword( $uid,$password_cookie ), self::$_config['cookie']);
  }

  /**
   * 获得会话中的额外数据
   *
   * $param   $key    string    键
   * $param   $key              值
   *
   * @return
   */
  static public function set( string $key, $value )
  {
    return Session::set( $key, $value, self::$_config['session']['path'] );
  }

  /**
   * 获得会话中的额外数据
   *
   * $param   $key    string    键
   *
   * @return
   */
  static public function get( string $key )
  {
    return Session::get( $key, self::$_config['session']['path'] );
  }

  /**
   * 记住密码（下次可以 Cookie 免密登录）
   *
   * @param   $uid              int
   * @param   $password_cookie  string
   *
   * @return
   */
  static private function _cookiePassword( int $uid, string $password_cookie )
  {
    $expire = intval( self::$_config['cookie']['expire'] );
    $expire = 0 === $expire ? 0: time() + $expire;
    $data = [
      // 用户唯一标识
      'uid'             => $uid,
      // 防止用户上传过期数据
      'expire'          => $expire,
      // 验证数据准确性
      'password_cookie' => $password_cookie,
    ];
    return $data;
  }

  /**
   * 获得用户 cookie 数据
   *
   * @return
   */
  static public function getCookiePasswordData()
  {
    if( !Cookie::has( 'uid', self::$_config['cookie'] ) ){
      return false;
    }
    $data = Cookie::get( 'uid', self::$_config['cookie'] );

    // 防止用户伪造，上传过期数据
    if( time() > $data['expire'] ){
      // 数据过期
      return false;
    }

    unset($data['expire']);
    return $data;
  }

  /**
   * 检查 cookies 密码是否正确
   *
   * @param   $pwd_c    string    passwoed from cookies
   * @param   $pwd_c    string    passwoed from db
   *
   * @return  bool
   */
  static public function checkCookiePassword( string $pwd_c, string $pwd_db ) : bool
  {
    return $pwd_c === $pwd_db;
  }

  /**
   * 注销操作
   */
  static public function logout()
  {
    Session::clear();
    Cookie::clear();
  }

  /**
   * 返回用户主键
   *
   * @return    int
   */
  static public function id() : int
  {
    if( self::isGuest() ){
      return 0;
    }
    return Session::get( 'uid', self::$_config['session']['path'] );
  }

  /**
   * 返回用户角色
   *
   * @param     $type   int   类型: 0 - 直接角色
   *                                1 - 子孙角色
   *                                2 - 所有角色
   * @return    array
   */
  static public function roles( int $type ) : array
  {
    if( self::isGuest() ){
      return [];
    }

    if( !in_array( $type, [0,1,2] ) ){
      throw new Exception( '参数错误：' . $type );
    }

    $rids_r = Session::get( 'rids', self::$_config['session']['path'] );

    if( 2 === $type && !isset( $rids_r[$type] ) ){
      // 缓存所有角色信息
      $rids_r[$type] = array_merge( $rids_r[0], $rids_r[1] );
      Session::set( 'rids', $rids_r, self::$_config['session']['path'] );
    }

    return $rids_r[$type];
  }

  /**
   * 刷新 Session 中的用户角色列表
   *
   * @param   $rids   array   角色列表 0 - 直接角色
   *                                   1 - 子孙角色
   *                                   2 - 所有角色
   * @return
   */
  static public function flushRoles( array $rids )
  {
    $rids_r[2] = array_merge( $rids[0], $rids[1] );

    Session::set( 'rids', $rids, self::$_config['session']['path'] );
  }

  /**
   * 加密密码
   *
   * @param   $pass   string  密码明文
   *
   * @return  string  密码密文
   */
  static public function password_hash( string $pass ) : string
  {
    // 固定 60 字符
    return password_hash( $pass, PASSWORD_BCRYPT );
  }

  /**
   * 验证密码是否正确
   *
   * @param   $pass   string    用户上传密码
   * @param   @hash   string    数据库保存的密码
   *
   * @return  bool
   */
  static public function password_verify( string $pass, string $hash ) : bool
  {
    return password_verify( $pass, $hash );
  }
}
