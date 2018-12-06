<?php
/**
 * 缓存驱动接口
 *
 * @author    姚斌 <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF\cache;

interface driver
{
  /**
   * 写入缓存
   *
   * @param   $data   array   写入文件内容
   * @param   $path   string  作用域
   * @param   $config array   配置
   *
   * @return
   */
  public function set( array $data, string $path, array $config );

  /**
   * 读取缓存
   *
   * @param   $key    string  读取键名
   * @param   $path   string  作用域
   *
   * @return
   */
  public function get( string $data, string $path );

  /**
   * 删除缓存
   * 若 $key = '*' 则删除所有缓存
   *
   * @param   $key    string  键名
   * @param   $path   string  作用域
   *
   * @return
   */
  public function unset( string $key, string $path );
}
