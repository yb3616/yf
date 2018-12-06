<?php
/**
 * 日志
 *
 * @author    姚斌 <yb3616@126>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

class Log
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
   * 站点根目录
   */
  static private $_root = '';

  /**
   * 初始化
   *
   * @param   $config   array   配置
   */
  static public function init( array $config, string $root )
  {
    if( empty( self::$_config ) ){
      self::$_config = $config;
      self::$_root = $root;
    }
  }

  /**
   * 配置文件
   */

  /**
   * 写入日志
   *
   * @param   $data   string | int | array  ...   待写入的内容
   * @param   $path   string                      域
   * @param   $config array                       新配置
   *
   * @return
   */
  static public function set( $data, string $path, array $config=[] )
  {
    $config = $config === [] ? self::$_config : array_merge( self::$_config, $config );
    if( empty( self::$_handle[ $config['engine'] ] ) ){
      $class  = '\\YF\\log\\drivers\\' . $config['engine'];
      self::$_handle['engine'] = new $class;
    }
    $path = self::$_root . $config['root'] . '/' . $path;
    self::$_handle['engine']->set( $data, $path );
  }
}
