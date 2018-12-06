<?php
/**
 * Cookie
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

class Cookie
{
  /**
   * config
   */
  static private $_config = [];

  /**
   * 初始化
   *
   * @param   $config array     配置
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
   * 配置用户配置
   *
   * @param   $config array     配置
   *
   * @return
   */
  static private function _init( array &$config=[] )
  {
    $config = array_merge( self::$_config, $config );
  }

  /**
   * 检查是否存在
   *
   * @param   $key    string    键
   *
   * @return
   */
  static public function has( string $key )
  {
    return isset( $_COOKIE[$key] );
  }

  /**
   * 读取数据
   *
   * @param   $key    string    键
   * @param   $config array     配置
   *
   * @return
   */
  static public function get( string $key, array $config=[] )
  {
    self::_init( $config );
    return self::has($key) ? unserialize( self::decrypt( $_COOKIE[$key], $config ) ) : false;
  }

  /**
   * 写入数据
   *
   * @param   $key    string    键
   * @param   $value            值
   * @param   $config array     配置
   *
   * @return
   */
  static public function set( string $key, $value, array $config=[] )
  {
    self::_init( $config );
    setcookie( $key, self::encrypt( serialize( $value ), $config ), time() + (int)$config['expire'], $config['path'], $config['domain'] );
  }

  /**
   * 删除数据
   *
   * @param   $key    string    键
   * @param   $config array     配置
   *
   * @return
   */
  static public function unset( string $key, array $config=[] )
  {
    self::_init( $config );
    if( self::has($key) ){
      setcookie( $key, null, time()-3600, $config['path'], $config['domain'] );
    }
  }

  /**
   * 清空数据
   *
   * @param   $config array     配置
   *
   * @return
   */
  static public function clear( array $config=[] )
  {
    self::_init( $config );
    foreach( $_COOKIE as $key => $value ){
      setcookie( $key, null, time()-3600, $config['path'], $config['domain'] );
    }
  }

  /**
   * 加密
   *
   * @param   $str    string    待处理字符串
   * @param   $config array     配置
   *
   * @return  string
   */
  static private function encrypt( string $str, array $config )
  {
    return openssl_encrypt( $str, $config['cipher'], $config['salt'], OPENSSL_RAW_DATA, $config['iv'] );
  }

  /**
   * 解密
   *
   * @param   $str    string    待处理字符串
   * @param   $config array     配置
   *
   * @return  string
   */
  static private function decrypt( string $str, array $config )
  {
    return openssl_decrypt( $str, $config['cipher'], $config['salt'], OPENSSL_RAW_DATA, $config['iv'] );
  }
}
