<?php
/**
 * 常用中间件
 *
 * @author    姚斌 <yb3616@126>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use Closure;
use YF\Log;
use YF\Request;
use YF\Response;

class Middleware
{
  /**
   * 访问日志
   *
   * @param   $log    Log   日志类
   *
   * @return
   */
  public function AccessLog( Closure $next )
  {
    // 先执行业务代码，生成响应数据
    $next();

    // 写入访问日志
    $uri = Request::getURI();
    Log::set( [
      'ip'     => Request::getIP(),
      'method' => Request::getMethod(),
      'uri'    => $uri,
      'params' => Request::param('*'),
      'return' => Response::data(),
    ], str_replace( '/', '%', $uri), [ 'engine' => 'file', 'root' => '/runtime/access_log' ] );
  }
}
