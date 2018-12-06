<?PHP
/**
 * 会话操作
 *
 * @author    姚斌  <yb3616@126>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use Exception;

class Session
{
  /**
   * 配置文件
   */
  static private $_config = [];

  /**
   * 初始化
   *
   * @param   $config   array   配置
   */
  static public function init( array $config )
  {
    if( empty( self::$_config ) ){
      self::$_config = $config;
      ini_set( 'session.gc_maxlifetime', intval( self::$_config['expire'] ) );
    }
  }

  /**
   * 用的时候在初始化
   *
   * @param   $path   string    路径
   */
  static private function _init( string &$path )
  {
    if( empty( $path ) ){
      $path = self::$_config['path'];
    }
    if( '' === self::id() ){
      session_start();
    }
  }

  /**
   * 检查是否存在
   *
   * @param   $key    string    键
   * @param   $path   string    路径
   *
   * @return  bool
   */
  static public function has( string $key, string $path='' ) : bool
  {
    self::_init( $path );
    return isset( $_SESSION[$path] ) && isset( $_SESSION[$path][$key] );
  }

  /**
   * 写入数据
   *
   * @param   $key    string    键
   * @param   $value            值
   * @param   $path   string    路径
   *
   * @return
   */
  static public function set( string $key, $value, string $path='' )
  {
    if( '*' === $key ){
      throw new Exception( '参数错误: *' );
    }
    self::_init( $path );
    $_SESSION[$path][$key] = $value;
  }

  /**
   * 读取数据
   *
   * @param   $key    string    键
   * @param   $path   string    路径
   *
   * @return
   */
  static public function get( string $key, string $path='' )
  {
    self::_init( $path );
    if( '*' === $path ){
      return $_SESSION;
    }elseif( '*' === $key ){
      return $_SESSION[$path];
    }else{
      return $_SESSION[$path][$key] ? $_SESSION[$path][$key] : false;
    }
  }

  /**
   * 删除数据
   *
   * @param   $key    string    键
   * @param   $path   string    路径
   *
   * @return
   */
  static public function unset( string $key, string $path='' )
  {
    self::_init( $path );
    if( '*' === $path ){
      self::clear();
    }elseif( '*'===$key ){
      unset( $_SESSION[$path] );
    }else{
      unset( $_SESSION[$path][$key] );
    }
  }

  /**
   * 清空数据
   */
  static public function clear()
  {
    $_SESSION = [];
  }

  /**
   * id
   * 获得会话唯一标识
   *
   * @return    string
   */
  static public function id() : string
  {
    return session_id();
  }
}
