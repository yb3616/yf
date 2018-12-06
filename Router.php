<?php
/**
 * 路由
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use Closure;
use Exception;
use YF\App;

class Router
{
  /**
   * 路由队列
   */
  private $_queue = [];

  /**
   * miss路由
   */
  private $_miss = [];

  /**
   * 全局路由中间件
   */
  private $_middlewares = [];

  /**
   * 组路由
   */
  private $_uri = '';

  /**
   * 运行程序
   *
   * @param   $fp   string    路由路径
   *
   * @return  null
   */
  static public function run( string $fp )
  {
    // 实例化本类
    $self = new self;

    // 加载路由
    $router = require_once $fp;
    $router( $self );

    $method = Request::getMethod();
    $uri    = Request::getURI();

    // 执行中间件
    if( isset( $self->_queue[$method][$uri] ) ){
      $item = $self->_queue[$method][$uri];
      self::_run_middleware( $item['middlewares'], self::_userFunc( $item['action'] ) );
      return;
    }

    // 无相应的请求地址
    // 返回 404
    if( 'cli' === $method ){
      //  cli无miss路由功能
      echo 404;
      return;
    }

    // 检查 miss 路由
    $getGroup = function( string $uri ){
      return substr( $uri, 0, strrpos( $uri, '/' ) );
    };
    while( true ){
      $uri = $getGroup( $uri );
      if( isset( $self->_miss[$uri] ) ){
        // 执行miss路由
        self::_userFunc( $self->_miss[$uri] )();
        return;
      }
      if( '' === $uri ){
        // 检查到顶
        break;
      }
    }

    // 默认返回404状态码
    http_response_code(404);
  }

  /**
   * 调用中间件
   *
   * @param   $middlewares  array   中间件数组
   * @param   $next         Closure 用户方法
   *
   * @return  null
   */
  static private function _run_middleware( array $middlewares, Closure $next )
  {
    foreach( $middlewares as $middleware ){
      $middleware = self::_userFunc( $middleware, $next );
      $next = function() use( $middleware, $next ){
        return $middleware( $next );
      };
    }
    $next( $next );
  }

  /**
   * 解析字符串，返回闭包
   *
   * @param   $func   Closure
   * @param   $next   Closure
   *
   * @return  Closure
   */
  static private function _userFunc( $func, Closure $next=null ) :Closure
  {
    // 调用用户方法
    if( is_string( $func ) ){
      // $func = 'YF/Middleware/AccessLog';
      $temp = explode( '/', $func );
      // $func = 'AccessLog';
      $func = array_pop( $temp );
      // $class = '\YF\Middleware';
      $class = '\\' . implode( '\\', $temp );
      // 实例化类
      $c = new $class;
      if( is_null($next) ){
        // 用户方法
        $func = function() use( $c, $func ) {
          $c->$func();
        };
      }else{
        // 中间件方法
        $func = function( Closure $next ) use( $c, $func ) {
          $c->$func( $next );
        };
      }

    }else if( !is_callable( $func ) ){
      // 其他
      throw new Exception( '不支持的操作' );
    }
    return $func;
  }

  /**
   * POST
   *
   * @param   $uri          string          路由地址
   * @param   $func         string|Closure  执行方法
   * @param   $middlewares  string|array    路由中间件
   *
   * @return
   */
  public function post( string $uri, $func, $middlewares=[] )
  {
    $this->_merge( 'post', $uri, $func, $middlewares );
  }

  /**
   * DELETE
   *
   * @param   $uri          string          路由地址
   * @param   $func         string|Closure  执行方法
   * @param   $middlewares  string|array    路由中间件
   *
   * @return
   */
  public function delete( string $uri, $func, $middlewares=[] )
  {
    $this->_merge( 'delete', $uri, $func, $middlewares );
  }

  /**
   * PUT
   *
   * @param   $uri          string          路由地址
   * @param   $func         string|Closure  执行方法
   * @param   $middlewares  string|array    路由中间件
   *
   * @return
   */
  public function put( string $uri, $func, $middlewares=[] )
  {
    $this->_merge( 'put', $uri, $func, $middlewares );
  }

  /**
   * CLI
   *
   * @param   $uri          string          路由地址
   * @param   $func         string|Closure  执行方法
   * @param   $middlewares  string|array    路由中间件
   *
   * @return
   */
  public function cli( string $uri, $func, $middlewares=[] )
  {
    $this->_merge( 'cli', $uri, $func, $middlewares );
  }

  /**
   * GET
   *
   * @param   $uri          string          路由地址
   * @param   $func         string|Closure  执行方法
   * @param   $middlewares  string|array    路由中间件
   *
   * @return
   */
  public function get( string $uri, $func, $middlewares=[] )
  {
    $this->_merge( 'get', $uri, $func, $middlewares );
  }

  /**
   * 分配路由
   *
   * @param   $uri          string          路由地址
   * @param   $func         string|Closure  执行方法
   * @param   $middlewares  array           路由中间件
   *
   * @return
   */
  public function param( string $method, string $uri, $func, $middlewares=[] )
  {
    $this->_merge( $method, $uri, $func, $middlewares );
  }

  /**
   * 404路由
   *
   * @param   $func         string|Closure  执行方法
   *
   * @return
   */
  public function miss( $func )
  {
    $this->_miss = array_merge( $this->_miss, [$this->_uri => $func] );
  }

  /**
   * @PRIVATE
   * 工具函数：合并数据
   *
   * @param   $method       string        请求类型
   * @param   $data         array         路由信息
   * @param   $middlewares  string|array  路由中间件
   *
   * @return
   */
  private function _merge( string $method, string $uri, $func, $middlewares=[] )
  {
    $uri    = $this->_uri . $uri;
    $method = strtolower( trim( $method ) );

    $data['action'] = $func;

    // 局部中间件
    if( !is_array( $middlewares ) ){
      $middlewares = [$middlewares];
    }

    // 全局中间件
    if( !empty( $this->_middlewares ) ){
      // 优先级 $middlewares 高
      $middlewares = array_merge( $this->_middlewares, $middlewares );
    }

    $data['middlewares'] = $middlewares;
    $this->_queue[$method][$uri] = $data;
  }

  /**
   * 添加全局路由中间件
   * 注：
   *  对之前的路由皆无效
   *  对之后的路由皆生效
   *
   * @param     $middlewares    array|string    路由中间件
   * @return
   */
  public function add( $middlewares )
  {
    if( empty( $middlewares ) ){
      return;
    }
    if( !is_array( $middlewares ) ){
      $middlewares = [$middlewares];
    }
    $this->_middlewares = array_merge( $this->_middlewares, $middlewares );
  }

  /**
   * 路由分组
   *
   * @param   $uri          string        路由地址
   * @param   $func         Closure       闭包函数
   * @param   $middlewares  string|array  路由中间件
   * @return
   */
  public function group( string $uri, Closure $func, $middlewares=[] )
  {
    $self = new self;
    $self->_uri = $this->_uri . $uri;
    $self->add( $this->_middlewares );
    $self->add( $middlewares );
    $func( $self );

    $this->_miss = array_merge( $this->_miss, $self->_miss );
    $this->_queue = array_merge_recursive( $this->_queue, $self->_queue );
  }
}
