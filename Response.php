<?PHP
/**
 * 响应
 * 参考 PSR-7 https://www.php-fig.org/psr/psr-7/
 *
 * @author    姚斌 <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */

namespace YF;

use Exception;

class Response
{
  /**
   * 响应数据
   */
  static private $_data = [ 'json' => [], 'html' => '' ];

  /**
   * 写入请求头
   *
   * @param   $type   string    键
   * @param   $value  string    值
   *
   * @return  self::class
   */
  static public function withHeader( string $type, string $value )
  {
    header( $type . ':' . $value );
  }

  /**
   * 写入请求头（额外）
   *
   * @param   $type   string    键
   * @param   $value  string    值
   *
   * @return  self::class
   */
  static public function withAddedHeader( string $type, string $value )
  {
    header( $type . ':' . $value, false );
  }

  /**
   * 写入状态码
   *
   * @param   $code   int   状态吗
   *
   * @return  self::class
   */
  static public function withStatus( int $code )
  {
    http_response_code( $code );
  }

  /**
   * 返回 html 数据
   *
   * @param   $data   string|float|int  返回数据
   * @param   $code   int               响应代码
   *
   * @return  self::class
   */
  static public function withHtml( $data, int $code=null )
  {
    // 过滤不应该有的数据
    if( !is_string( $data ) && !is_numeric( $data ) ){
      throw new Exception( '[参数异常]' );
    }

    // 写入状态
    if( !is_null( $code ) ){
      self::withStatus( $code );
    }

    // 缓存数据
    self::$_data['html'] .= $data;
  }

  /**
   * 返回 json 数据
   *
   * @param   $data   array   返回数据
   * @param   $code   int     响应代码
   *
   * @return  self::class
   */
  static public function withJson( array $data, int $code=null )
  {
    // 写入状态
    if( !is_null( $code ) ){
      self::withStatus( $code );
    }

    // 缓存数据
    self::$_data['json'] = array_merge( self::$_data['json'], $data );
  }

  /**
   * 析构函数: 向客户端返回数据
   *
   * @param
   *
   * @return
   */
  static public function send()
  {
    if( !empty( self::$_data['json'] ) ){
      // json
      self::withHeader( 'Content-Type', 'application/json;charset=utf-8' );
      echo json_encode( self::$_data['json'], JSON_UNESCAPED_UNICODE );

    }elseif( !empty( self::$_data['html'] ) ){
      // html
      self::withHeader( 'Content-Type', 'text/html;charset=utf-8' );
      echo self::$_data['html'];
    }
  }

  /**
   * 获得响应数据
   *
   * @return    array | string
   */
  static public function data()
  {
    if( !empty( self::$_data['json'] ) ){
      // json
      return self::$_data['json'];

    }elseif( !empty( self::$_data['html'] ) ){
      // html
      return self::$_data['html'];
    }
  }
}
