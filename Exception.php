<?php
/**
 * 异常类
 *
 * @author    姚斌 <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

class Exception
{
  /**
   * 错误信息
   */
  static private $_data = [];

  /**
   * 非致命错误处理函数
   */
  static public function error( $errno, $errmsg, $errfile, $errline )
  {
    array_push( self::$_data, [
      'errno'   => $errno,
      'errmsg'  => $errmsg,
      'errfile' => $errfile,
      'errline' => $errline,
    ] );
  }

  /**
   * 致命错误处理函数
   */
  static public function exception( $e )
  {
    $trace_r = preg_split( '/#\d+\s+/', $e->getTraceAsString() );
    array_shift( $trace_r );
    array_push( self::$_data, [
      'errclass' => get_class( $e ),
      'errmsg'   => $e->getMessage(),
      'errtrace' => $trace_r,
      'errline'  => $e->getLine(),
      'errfile'  => $e->getFile(),
    ] );
    self::send();
  }

  /**
   * 发送数据
   */
  static public function send()
  {
    if( !empty(self::$_data) ){
      header( 'Content-Type:application/json;charset=utf-8' );
      echo json_encode( self::$_data, JSON_UNESCAPED_UNICODE );
    }
  }
}
