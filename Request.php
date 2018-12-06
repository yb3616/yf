<?PHP
/**
 * 请求参数
 *
 * @author    姚斌 <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */

namespace YF;

use Exception;
use YF\Validate;

class Request
{
  /**
   * 请求参数
   */
  static private $_params = [];

  /**
   * 配置
   */
  static private $_config = [];

  /**
   * 初始化参数数据
   *
   * @param   $config   array
   * @param   $root     string
   *
   * @return
   */
  static public function init( array $config, string $root )
  {
    if( [] !== self::$_params ){
      return;
    }
    if( empty( self::$_config ) ){
      self::$_config = $config;
      self::$_config['root'] = $root . self::$_config['root'];
    }

    // 获得 CLI 参数
    $cli_params = [];
    if( isset( $_SERVER['argv'] ) ){
      $args = array_slice( $_SERVER['argv'], 2 );
      foreach( $args as $arg ){
        $temp_param = explode( '=', $arg );
        $cli_params += [$temp_param[0] => urldecode( $temp_param[1] )];
      }
    }

    // 整理所有参数
    $input = file_get_contents( 'php://input', 'r' );
    $param_data = [
      'cli'  => $cli_params,
      'get'  => $_GET,
      'post' => $_POST,
    ];

    if( isset( $_SERVER['CONTENT_TYPE'] ) ){
      $content_type = strtolower( $_SERVER['CONTENT_TYPE'] );
      if( 'application/json' === $content_type ){
        $param_data[ 'json' ] = json_decode( $input, true );

      }elseif( in_array( $content_type, ['application/xml', 'text/xml'] ) ){
        $param_data[ 'xml' ] = simplexml_load_string( $input );
      }
    }

    // 保存数据到数组
    self::$_params['all'] = [];
    foreach( $param_data as $type => $params ){
      if( isset( $params ) ){
        foreach($params as $key => $value) {
          self::$_params[$type][trim($key)] = trim($value);
          self::$_params['all'] += self::$_params[$type];
        }
      }
    }
  }

  /**
   * 绑定参数
   * 参考 $this->_getParam
   *
   * @param   $rule   string | array
   *
   * @return  [array, bool]
   */
  static public function get( $rule ) : array
  {
    return self::_getParam( $rule, 'get' );
  }

  /**
   * 绑定参数
   * 参考 $this->_getParam
   *
   * @param   $rule   string | array
   *
   * @return  [array, bool]
   */
  static public function post( $rule ) : array
  {
    return self::_getParam( $rule, 'post' );
  }

  /**
   * 绑定参数
   * 参考 $this->_getParam
   *
   * @param   $rule   string | array
   *
   * @return  [array, bool]
   */
  static public function json( $rule ) : array
  {
    return self::_getParam( $rule, 'json' );
  }

  /**
   * 绑定参数
   * 参考 $this->_getParam
   *
   * @param   $rule   string | array
   *
   * @return  [array, bool]
   */
  static public function xml( $rule ) : array
  {
    return self::_getParam($rule, 'xml');
  }

  /**
   * 绑定参数
   * 参考 $this->_getParam
   *
   * @param   $rule   string | array
   *
   * @return  [array, bool]
   */
  static public function cli( $rule ) : array
  {
    return self::_getParam( $rule, 'cli' );
  }

  /**
   * 绑定参数
   * 参考 $this->_getParam
   *
   * @param   $rule   string | array
   *
   * @return  [array, bool]
   */
  static public function param( $rule ) : array
  {
    return self::_getParam( $rule, 'all' );
  }

  /**
   * 获得用户上传数据，并做相应验证
   *
   * @param   $rule   string | array
   * @param   $type   string
   *
   * @return  [array, bool]
   */
  static private function _getParam( $rule, string $type ) : array
  {
    if( '*' === $rule ){
      // 返回指定类型下所有数据
      return self::$_params[$type];

    }elseif( empty( self::$_params[$type] ) ){
      // 返回空数据
      return [];

    }elseif( is_string( $rule ) ) {
      // 字符串型
      $rule = array_map( 'trim', explode( ',', $rule ) );

    }elseif( !is_array( $rule ) ){
      throw new Exception( '参数类型不支持' );
    }

    return Validate::verify( self::$_params[$type], $rule );
  }

  /**
   * 获得用户请求方法
   *
   * @return  string
   */
  static public function getMethod() :string
  {
    $sapi = strtolower( PHP_SAPI );
    return 'cli' === $sapi ? $sapi : strtolower( $_SERVER['REQUEST_METHOD'] );
  }

  /**
   * 获得用户请求 uri
   *
   * @return  string
   */
  static public function getURI() :string
  {
    if( 'cli' === self::getMethod() ){
      return $_SERVER['argv'][1];
    }
    return explode( '?', $_SERVER['REQUEST_URI'] )[0];
  }

  /**
   * 获得用户请求IP
   *
   * 注：若有 nginx 反向代理，需要将下面代码置入 nginx 代理块
   * proxy_set_header X-real-ip           $remote_addr;
   *
   * @return  string
   */
  static public function getIP() :string
  {
    // 若有 nginx 反向代理
    // 需将下面的代码放入代理块
    // proxy_set_header X-real-ip           $remote_addr;
    if( 'cli' === self::getMethod() ){
      return  'localhost';
    }
    return isset( $_SERVER['HTTP_X_REAL_IP'] ) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];
  }

  /**
   * 缓存上传的文件（多个）
   */
  static public function files( array $keys )
  {
    $fps = [];
    foreach( $keys as $key => $options ){
      $fps[] = [$key => self::file( $key, $options )];
    }
    return $fps;
  }

  /**
   * 缓存上传的文件
   */
  static public function file( string $key, array $options=[] )
  {
    if( [] === $options ){
      return self::_saveFile( $key );
    }

    // 1.
    // 检查文件类型
    if( !empty( $options['type'] ) ){
      $type = $options['type'];
      if( is_string( $type ) ){
        $type = array_map(function( $v ){
          return strtolower( trim( $v ) );
        }, explode( '|', trim( $type ) ));
      }
      // 限制上传类型
      if( !in_array( explode('/', $_FILES[$key]['type'])[1], $type ) ){
        return false;
      }
    }

    // 2.
    // 检查文件大小 (1KB, 2MB) (,2MB) (1KB,)
    if( !empty( $options['size'] ) ){
      $size = array_map( 'trim', explode( ',', trim( $options['size'] ) ) );
      // 转换单位
      foreach( $size as $k => $row ){
        if( preg_match( '/^(\d+(\.\d+)?)(\w*)$/', $row, $match ) ){
          $size[$k] = self::_unitConversion( floatval( $match[1] ), $match[3] );
        }
      }
      // 判断
      switch( count( $size ) ){
      case 1:
        // 1k - 文件须小于1k
        if( $_FILES[$key]['size'] > $size[0] ){
          return false;
        }
        break;
      case 2:
        if( empty( $size[0] ) ){
          // ,1k - 文件须小于1k
          if( $_FILES[$key]['size'] > $size[1] ) {
            return false;
          }

        }elseif( empty( $size[1] ) ){
          // 1k, - 文件须大于1k（变态要求）
          if( $_FILES[$key]['size'] < $size[0] ){
            return false;
          }

        }else{
          // 1k, 2k - 文件须在1k~2k间
          if( $_FILES[$key]['size'] < $size[0] || $_FILES[$key]['size'] > $size[1] ){
            return false;
          }
        }
        break;
      default: throw new Exception( '[参数错误]' );
      }
    }

    // 3.
    // 检查文件路径是否合法
    if( isset( $options['path'] ) ){
      $path = $options['path'];
    }else{
      $path = '';
    }

    return self::_saveFile( $key, $path );
  }

  /**
   * 缓存上传的文件
   *
   * @param   $key    string    键
   */
  static private function _saveFile( string $key, string $path='' )
  {
    $path = self::$_config['root'] . '/' . ( empty( $path ) ? self::$_config['path'] : $path );
    $fp = self::_createDirs( $path, time() ).'/'.md5( time().rand( 1000,9999 ) ).'.'.$_FILES[$key]['name'];
    // 缓存文件
    return move_uploaded_file( $_FILES[$key]['tmp_name'], $fp ) ? $fp : false;
  }

  /**
   * 字节等单位换算
   *
   * @param   $data   float     数据大小
   * @param   $unit   string    单位
   *
   * @return  double  统一以 bit 为单位输出数据大小
   */
  static private function _unitConversion( float $data, string $unit ) : float
  {
    switch( strtolower( $unit ) ){
    case '':
    case 'b':
    case 'bit':
      return $data;
    case 'k':
    case 'kb':
    case 'kbit':
      return $data * 1024;
    case 'm':
    case 'mb':
    case 'mbit':
      return $data * 1048576;
    case 'g':
    case 'gb':
    case 'gbit':
      return $data * 1073741824;
    default:
      throw new Exception( '不支持的单位' );
    }
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
      mkdir( $fp, 0777 );
      chmod( $fp, 0777 );
    }
  }

  /**
   * 获得请求头信息
   *
   * @param   $key    string    键
   *
   * @return
   */
  static public function getHeader( string $key )
  {
    $key = 'HTTP_' . strtoupper( $key );
    if( isset( $_SERVER[ $key ] ) ){
      return $_SERVER[ $key ];
    }
    return '';
  }
}
