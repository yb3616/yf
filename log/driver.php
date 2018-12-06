<?php
/**
 * 日志驱动接口
 *
 * @author    姚斌 <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF\log;

interface driver
{
  /**
   * 写入日志(到内存)
   *
   * @param   $data   array|string    写入文件内容
   * @param   $fp     string          路径
   * @return
   */
  public function set( $data, string $fp );
}
