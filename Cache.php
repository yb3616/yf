<?php
/**
 * 缓存
 *
 * @author    姚斌 <yb3616@126>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

class Cache
{
  /**
   * 类实例
   */
  static private $_handle = [];

  /**
   * 配置
   */
  static private $_config = [];

  /**
   * 初始化
   */
  static public function init( array $config, string $root )
  {
    // 载入配置
    if( empty( self::$_config ) ){
      self::$_config = $config;
      self::$_config['app_root'] = $root;
    }
  }

  /**
   * 写入缓存
   *
   * @param   $data   array   待写入的内容
   * @param   $path   string  域
   * @param   $config array   新配置
   *
   * @return
   */
  static public function set( array $data, string $path, array $config=[] )
  {
    self::_init( $path, $config );
    self::$_handle[$config['engine']]->set( $data, $path, $config );
  }

  /**
   * 读取缓存
   *
   * @param   $key    string    键
   * @param   $path   string    域
   * @param   $config array     新配置
   */
  static public function get( string $key, string $path, array $config=[] )
  {
    self::_init( $path, $config );
    return self::$_handle[$config['engine']]->get( $key, $path );
  }

  /**
   * 删除缓存
   *
   * @param   $key    string    键
   * @param   $path   string    域
   * @param   $config array     新配置
   */
  static public function unset( string $key, string $path, array $config=[] )
  {
    self::_init( $path, $config );
    return self::$_handle[$config['engine']]->unset( $key, $path );
  }

  /**
   * 初始化参数
   *
   * @param   &$path    string  相对路径
   * @param   &$config  array   配置
   */
  static private function _init( string &$path, array &$config )
  {
    // 重新配置
    $config = array_merge( self::$_config, $config );

    // 获取驱动实例
    if( empty( self::$_handle[ $config['engine'] ] ) ){
      $class = '\\YF\\cache\\drivers\\' . $config['engine'];
      self::$_handle[ $config['engine'] ] = new $class;
    }

    // 配置绝对路径
    $path = self::$_config['app_root']
      . (substr($path, 0, 1)==='/' ? $config['path'].$path : $config['path'].'/'.$path);
  }
}
