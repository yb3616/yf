<?php
/**
 * 网络请求（带Cookie）
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use Exception;
use YF\File;

class Http
{
  /**
   * 站点根目录
   */
  static private $_root = '';

  /**
   * 初始化
   *
   * @param   $config   array   参数
   * @param   $root     string  站点根目录
   */
  static public function init( array $config )
  {
    self::$_root = File::createDir( $config['root'] );
  }

  /**
   * Get 请求网络资源
   *
   * @param   $url    string    网络地址( http | https )
   * @param   $file   string    缓存相对路径（默认无，则不启用Cookie功能）
   *
   * @return
   */
  static public function get( string $url, string $file='' )
  {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $ch, CURLOPT_HEADER, 0 );

    if( '' !== $file ){
      $fp = self::$_root . '/' . $file;
      curl_setopt( $ch, CURLOPT_COOKIEFILE, $fp );
      curl_setopt( $ch, CURLOPT_COOKIEJAR, $fp );
      if( is_file( $fp ) ){
        // 避免 fast-cgi 与 cli 用户不同导致的无法访问等问题
        chmod( $fp, 0666 );
      }
    }

    $output = curl_exec( $ch );
    if( curl_errno( $ch ) ){
      throw new Exception( curl_error( $ch ) );;
    }
    curl_close( $ch );
    return $output;
  }

  /**
   * Post 请求网络资源
   *
   * @param   $url    string    网络地址( http | https )
   * @param   $config array     请求配置
   *
   * @return
   */
  static public function post( string $url, array $config=[] )
  {
    $ch = curl_init();
    curl_setopt ( $ch, CURLOPT_URL, $url );
    curl_setopt ( $ch, CURLOPT_POST, 1 );
    curl_setopt ( $ch, CURLOPT_HEADER, 0 );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );

    if( !empty( $config['file'] ) ){
      $fp = self::$_root . '/' . $config['file'];
      curl_setopt( $ch, CURLOPT_COOKIEFILE, $fp );
      curl_setopt( $ch, CURLOPT_COOKIEJAR, $fp );
      if( is_file( $fp ) ){
        // 避免 fast-cgi 与 cli 用户不同导致的无法访问等问题
        chmod( $fp, 0666 );
      }
    }

    if( !empty( $config['data'] ) ){
      curl_setopt ( $ch, CURLOPT_POSTFIELDS, $config['data'] );
    }

    $response = curl_exec( $ch );
    if( curl_errno( $ch ) ){
      throw new Exception( curl_error( $ch ) );;
    }
    curl_close( $ch );
    return $response;
  }

  /**
   * Put 请求网络资源
   *
   * @param   $url    string    网络地址( http | https )
   * @param   $config array     请求配置
   *
   * @return
   */
  static public function put( string $url, array $config=[] )
  {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type:application/json' ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER,1 );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );

    if( !empty( $config['file'] ) ){
      $fp = self::$_root . '/' . $config['file'];
      curl_setopt( $ch, CURLOPT_COOKIEFILE, $fp );
      curl_setopt( $ch, CURLOPT_COOKIEJAR, $fp );
      if( is_file( $fp ) ){
        // 避免 fast-cgi 与 cli 用户不同导致的无法访问等问题
        chmod( $fp, 0666 );
      }
    }

    if( !empty( $config['data'] ) ){
      curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $config['data'] ) );
    }

    $response = curl_exec( $ch );
    if( curl_errno( $ch ) ){
      throw new Exception( curl_error( $ch ) );;
    }
    curl_close( $ch );
    return $response;
  }

  /**
   * Delete 请求网络资源
   *
   * @param   $url    string    网络地址( http | https )
   * @param   $config array     请求配置
   *
   * @return
   */
  static public function delete( string $url, array $config=[] )
  {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type:application/json' ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER,1 );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );

    if( !empty( $config['file'] ) ){
      $fp = self::$_root . '/' . $config['file'];
      curl_setopt( $ch, CURLOPT_COOKIEFILE, $fp );
      curl_setopt( $ch, CURLOPT_COOKIEJAR, $fp );
      if( is_file( $fp ) ){
        // 避免 fast-cgi 与 cli 用户不同导致的无法访问等问题
        chmod( $fp, 0666 );
      }
    }

    if( !empty( $config['data'] ) ){
      curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $config['data'] ) );
    }

    $response = curl_exec( $ch );
    if( curl_errno( $ch ) ){
      throw new Exception( curl_error( $ch ) );;
    }
    curl_close( $ch );
    return $response;
  }
}
