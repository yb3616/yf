<?php
/**
 * File 驱动
 *
 * @author    姚斌
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF\log\drivers;

use YF\log\driver;
use Exception;

class file implements driver
{
  /**
   * 日志数据   'file_path' => 'data'
   */
  private $_data = [];

  /**
   * 写入日志（内存中）
   *
   * @param   $data   all     写入的内容
   * @param   $path   string  写入的相对路径
   *
   * @return
   */
  public function set( $data, string $path )
  {
    $this->_data[$path][] = $data;
  }

  /**
   * 缓存日志到最终存储介质
   */
  public function __destruct()
  {
    // 唯一时间戳，以避免跨天 BUG
    $now = time();

    foreach( $this->_data as $fp => $data ){
      $fp = self::_createDirs($fp, $now) . '/' . date('d', $now) . '.log';
      self::_writeData( $fp, serialize([ date( 'Y-m-d H:i:s', $now ) => $data ]) );
    }
  }

  /**
   * 写入日志
   *
   * param    $fp   string    文件绝对路径
   * param    $data string    已经序列化的文件内容字符串
   */
  static private function _writeData( string $fp, string $data )
  {
    file_put_contents( $fp, $data . PHP_EOL, FILE_APPEND );
    // 当 fast-cgi 创建的文件, 被 cli 更新下内容后, 此时 chmod 会产生警告
    // 这是正常现象，屏蔽当前警告信息即可
    @chmod( $fp, 0666 );
  }

  /**
   * 生成目录
   *
   * @param   $fp     string  文件相对路径（相对与站点根路径）
   * @param   $now    int     当前时间戳
   *
   * @return  string  文件相对路径
   */
  static private function _createDirs( string $fp, int $now ) : string
  {
    self::_createDir( $fp );
    $fp = $fp . '/' . date('Y', $now);
    self::_createDir( $fp );
    $fp = $fp . '/' . date('m', $now);
    self::_createDir( $fp );

    return $fp;
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
      mkdir( $fp );
      chmod( $fp, 0777 );
    }
  }
}
