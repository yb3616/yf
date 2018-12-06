<?php
/**
 * 读取配置
 * 注：不可改变当前文件（Config.php）路径位置
 *
 * @author    姚斌 <yb3616@126>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */

namespace YF;

use Exception;

class Config
{
  /**
   * 配置变量
   */
  static private $_config = [];

  /**
   * 载入配置
   *
   * @param   $root   string    站点根目录（绝对路径）
   * @param   $config string    配置文件夹路径（相对路径）
   *
   * @return  null
   */
  static public function init( string $root, string $config )
  {
    // 防止重复读取配置
    if( [] !== self::$_config ){
      return;
    }

    // 设置配置文件夹路径
    $dp = $root . $config;
    if( !is_dir( $dp ) ){
      throw new Exception( '文件夹不存在' );
    }

    // 读取配置，并覆盖（若有）全局环境变量
    foreach( scandir( $dp, 0 ) as $value ) {
      if( !in_array( $value, ['.', '..'] ) ){
        $key = preg_replace( [ '/_local\.ini$/i', '/\.ini/i' ], '', $value );
        if( $key !== $value ){
          $key = strtolower( $key );
          // 解析 '.ini' | '_local.ini' 文件
          if( isset( self::$_config[$key] ) ){
            // array_merge 不允许第一个参数为非数组类型
            self::$_config[$key] = array_merge( self::$_config[$key], parse_ini_file( $dp . '/' . $value, true ) );
          }else{
            self::$_config[$key] = parse_ini_file( $dp . '/' . $value, true );
          }
        }
      }
    }

    // 覆盖全局环境变量
    self::$_config['app']['root'] = $root;
  }

  /**
   * 读取配置内容
   *
   * @param   $key    string    键 例如：db.pass | db | *
   *
   * @return  mixed
   */
  static public function get( string $key )
  {
    $key = trim( $key );

    if( '*' === $key ){
      return self::$_config;
    }

    $str = explode( '.', $key );
    switch( count( $str ) ){
    case 1:
      return self::$_config[$str[0]];
    case 2:
      return self::$_config[$str[0]][$str[1]];
    default:
      throw new Exception( '[参数错误]: ' . $key );
    }
  }

  /**
   * 写入配置
   * 临时生效（当前生命周期内）
   *
   * @param   $key    string    键 例如：db.pass | db
   * @param   $value  mixed     值
   */
  static public function set( string $key, $value )
  {
    $key = trim( $key );

    if( '*' === $key ){
      throw new Exception( '[参数错误]: ' . $key );
    }

    $str = explode( '.', $key );
    switch( count( $str ) ){
    case 1:
      self::$_config[$str[0]] = $value;
      break;
    case 2:
      self::$_config[$str[0]][$str[1]] = $value;
      break;
    default:
      throw new Exception( '[参数错误]: ' . $key );
    }
  }

  /**
   * 检查是否存在配置
   *
   * @param   $key    string    键 例如：db.pass | db | *
   *
   * @return  bool
   */
  static public function has( string $key ) : bool
  {
    $key = trim( $key );

    if( '*' === $key ){
      return isset( self::$_config );
    }

    $str = explode( '.', $key );
    switch( count( $str ) ){
    case 1:
      return isset( self::$_config[$str[0]] );
    case 2:
      return isset( self::$_config[$str[0]][$str[1]] );
    default:
      throw new Exception( '[参数错误]: ' . $key );
    }
  }
}
