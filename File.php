<?php
/**
 * 文件操作
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

class File
{
  /**
   * 站点根目录
   */
  static private $_root = '';

  /**
   * 初始化
   */
  static public function init( string $root )
  {
    self::$_root = $root;
  }

  /**
   * 递归创建目录，并设置权限
   *
   * @param   $fp   string    目录相对路径，请以 '/' 字符打头
   * @param   $mode int       目录权限，默认 0777
   *
   * @return  string  目录绝对路径
   */
  static public function createDir( string $fp, int $mode=0777 ) : string
  {
    $fp = self::$_root . $fp;
    $fp_last = dirname( $fp );
    if( !is_dir( $fp_last ) ){
      self::createDir( $fp_last );
    }
    if( !is_dir( $fp ) ){
      mkdir( $fp, $mode );
      chmod( $fp, $mode );
    }
    return $fp;
  }
}
