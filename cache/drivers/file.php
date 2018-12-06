<?php
/**
 * File 驱动
 *
 * TODO
 *    一个生命周期内只做一次写操作，其余的放到内存中操作
 *
 * @author    姚斌
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF\cache\drivers;

use YF\cache\driver;
use Exception;

class file implements driver
{
  private $_data = [];
  /**
   * 缓存数据
   * 可缓存除 null | 函数 外的所有数据（带类型）（TODO: 待考证）
   *
   * 注：目录为域，文件名为键名，文件内容为键值
   *
   * @param   $data   array   缓存键值对
   * @param   $path   string  路径
   * @param   $config array   配置
   *
   * @return
   */
  public function set( array $data, string $path, array $config )
  {
    // 过期时间
    $expire = floatval( $config['expire'] );
    if( 0.0 !== $expire ){
      $expire += time();
    }

    // 新建缓存根目录
    self::_createDir( $path, time() );

    // 写入数据
    foreach( $data as $k => $v ){
      self::_writeData( $path . '/' . $k, serialize( [ $expire => $v ] ) );
    }
  }

  /**
   * 删除缓存
   * 若 $key = '*'，则删除整个域目录
   *
   * @param   $key    string  键
   * @param   $path   string  路径
   *
   * @return
   */
  public function unset( string $key, string $path )
  {
    if( '*' === $key ){
      self::_unsetAll( $path );
    }else{
      self::_unset( $path . '/' . $key );
    }
  }

  /**
   * 删除文件
   *
   * @param   $fp   string    文件路径
   *
   * @return
   */
  static private function _unset( string $fp )
  {
    if( !is_file( $fp ) ){
      return;
    }
    if( unlink( $fp ) ){
      // 检查下域目录是否为空，若为空，则删除域目录
      $dp = dirname( $fp );
      if( count( scandir( $dp ) ) === 2 ){
        rmdir( $dp );
      }
    }
  }

  /**
   * 删除整个域
   *
   * @param   string  $dp   域绝对路径
   *
   * @return  bool
   */
  static private function _unsetAll( string $dp )
  {
    if( !is_dir( $dp ) ){
      return;
    }
    $result = [];
    $fps = scandir( $dp );
    foreach( $fps as $fp ){
      if( '.' !== $fp && '..' !== $fp ){
        self::_unset( $fp );
      }
    }
  }

  /**
   * 根据 键名(域) 获得缓存数据
   *
   * @param   string    $key    键名
   * @param   string    $path   域
   *
   * @return  mixed
   */
  public function get( string $key, string $path )
  {
    if( '*' === trim( $key ) ){
      return self::_getAll( $path );
    }
    $fp = $path . '/' . $key;
    return self::_get( $fp );
  }

  /**
   * 根据 键名 获得缓存数据
   *
   * @param   string    $fp   文件(键名)路径
   *
   * @return
   */
  static private function _get( string $fp )
  {
    if( !is_file( $fp ) ){
      return false;
    }

    foreach( unserialize( file_get_contents( $fp ) ) as $time => $data ){
      // 当配置参数 expire 为字符串 "0" 时写入的数据，忽略过期时间
      if( 0 === $time || 0.0 === $time || time() <= $time ){
        return $data;
      }else{
        // 文件时间过期，删除以减轻缓存压力
        self::_unset( $fp );
        return false;
      }
    }
  }

  /**
   * 根据 域 获得缓存数据
   *
   * @param   string    $dp   文件(域)路径
   *
   * @return
   */
  static private function _getAll( string $dp )
  {
    if( !is_dir( $dp ) ){
      return false;
    }
    $result = [];
    $fps = scandir( $dp );
    foreach( $fps as $fp ){
      if( '.' !== $fp && '..' !== $fp ){
        if( !is_null( $data = self::_get( $dp . '/' . $fp ) ) ){
          $result = array_merge( $result, [ $fp => $data ] );
        }
      }
    }
    return $result;
  }

  /**
   * 写入缓存
   *
   * param    $fp   string    文件绝对路径
   * param    $data string    已经序列化的文件内容字符串
   */
  static private function _writeData( string $fp, string $data )
  {
    if( is_file( $fp ) ){
      file_put_contents( $fp, $data );
    }else{
      file_put_contents( $fp, $data );
      chmod( $fp, 0666 );
    }
  }

  /**
   * 创建目录
   *
   * @param   $fp   string    绝对路径
   */
  static private function _createDir( string $fp )
  {
    $fp_last = dirname( $fp );
    if( !is_dir( $fp_last ) ){
      self::_createDir( $fp_last );
    }
    if( !is_dir( $fp ) ){
      mkdir( $fp, 0777 );
      chmod( $fp, 0777 );
    }
  }
}
