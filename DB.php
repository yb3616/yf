<?php
/**
 * DB类
 *
 * @author    姚斌 <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use PDO;
use Exception;
use Closure;
use YF\Response;

class DB
{
  /**
   * 是否加锁
   */
  static private $_lock = false;

  /**
   * 配置文件
   */
  static private $_config = [];

  /**
   * 是否调试
   */
  static private $_debug = false;

  /**
   * 数据库连接句柄
   */
  static private $_handle = [];

  /**
   * 连接得数据库
   */
  private $_db= 'master';

  /**
   * 参数
   */
  private $_params = [];

  /**
   * PDO 预处理用
   */
  private $_where_v = [];

  /**
   * 初始化
   */
  static public function init( array $config, bool $debug )
  {
    if( empty( self::$_config ) ){
      self::$_config = array_merge( [
        'port'    => 3306,
        'charset' => 'utf8mb4',
        'pre'     => '',
      ], $config );
      self::$_debug = $debug;
    }
  }

  /**
   * 设置表名，不带前缀
   *
   * @param   $name   string    数据库表名
   *
   * @return  object
   */
  static public function table( string $name )
  {
    $self = new self();
    $self->_params['table'] = self::_table( $name );
    return $self;
  }

  /**
   * 设置表名，带前缀
   *
   * @param   $name     string    数据库表名
   *
   * @return  $this
   */
  static public function name( string $name )
  {
    $self = new self();
    $self->_params['table'] = self::_table( self::$_config[$self->_db]['pre'] . $name );
    return $self;
  }

  /**
   * 写入处理后的表名
   *
   * @param   $tb   string    表名
   */
  static private function _table( string $tb )
  {
    return preg_replace( ['/^\s*(\w+)\s.*(\w+)\s*$/', '/^\s*(\w+)\s*$/'], ['`${1}` AS `${2}`', '`${1}`'], $tb );
  }

  /**
   * 连接数据库
   * 在有请求的时候建立连接
   *
   * @param   $key    string    数据库连接键名( 分布式,参考数据库配置文件 )
   */
  static public function handle( string $key='master' )
  {
    if( empty( self::$_handle[$key] ) ){
      if( empty( self::$_config[$key] ) ){
        throw new Exception( '无相应配置' );
      }

      $dsn = self::$_config[$key]['type'];

      if( !empty( self::$_config[$key]['sock'] ) ){
        // unix sock 连接方式
        $dsn .= ':unix_sock=' . self::$_config[$key]['sock'];

      }elseif( !empty( self::$_config[$key]['host'] ) ){
        // host 连接方式
        $dsn .= ':host=' . self::$_config[$key]['host'] . ';port='.self::$_config[$key]['port'];

      }else{
        throw new Exception( '未知连接方式' );
      }
      $dsn .= ';dbname='.self::$_config[$key]['dbname'].';charset='.self::$_config[$key]['charset'];

      self::$_handle[$key] = new PDO( $dsn, self::$_config[$key]['user'], self::$_config[$key]['pass'] );
      self::$_handle[$key]->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }
    return self::$_handle[$key];
  }

  /**
   * group
   *
   * @param   $group
   *
   * @return  object
   */
  public function group( string $group )
  {
    $this->_params['group'] = empty( $this->_params['group'] ) ? '' : $this->_params['group'] . ',';
    $this->_params['group'] .= self::_convert( $group );
    return $this;
  }

  /**
   * 设置limit条件
   *
   * @param   $limit
   *
   * @return  object
   */
  public function limit( string $limit )
  {
    $this->_params['limit'] = $limit;
    return $this;
  }

  /**
   * 排序
   *
   * @param   $order    array|string  数据
   * @param   $convert  bool          是否处理数据？default: false
   *                                  该字段仅对字符串数据有效
   *
   * @return  object
   */
  public function order( $order, bool $convert=true )
  {
    $this->_params['order'] = empty( $this->_params['order'] ) ? '' : $this->_params['order'] . ',';

    $this->_params['order'] .= implode( ',', self::_common( $order, $convert, function( $data ){
      // 字符串或者数字索引数组处理方法
      return implode( ',', array_map( function( $d ){
        return preg_replace( '/^\s*(\w+)(.*)\s*$/', '`$1`$2', $d );
      }, explode( ',', trim( $data ) ) ) );

    }, function() {
      throw new Exception( 'order 参数错误' );
    } ) );

    return $this;
  }

  /**
   * 设置字段
   * 字符串原样返回，字段可能为函数或方法，不能以逗号作为分隔符
   * 数组做判断
   *
   * @param   $field    string | array
   *
   * @return  $this
   */
  public function field( $field, bool $convert=true )
  {
    $this->_params['field'] = empty( $this->_params['field'] ) ? '' : $this->_params['field'] . ',';

    $this->_params['field'] .= implode( ',', self::_common( $field, $convert, function( $data ){
      // 字符串或者数字索引数组处理方法
      return implode( ',', array_map( function( $d ){
        return self::_convert( trim( $d ) );
      }, explode( ',', trim( $data ) ) ) );

    }, function( $k, $v ){
      // 关联数组, 自定义转义规则
      return self::_convert( $k ) . ' AS ' . self::_convert( $v );
    } ) );

    return $this;
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function where( $where, bool $convert=true )
  {
    if( empty( $where ) ){
      return $this;
    }

    $this->_params['where'] = empty( $this->_params['where'] ) ? '' : $this->_params['where'] . ' AND ';

    $assoc_func = function( $k, $v ){
      // 关联数组, 自定义转义规则
      // 'id' => 1
      array_push( $this->_where_v, $v );
      return self::_convert( $k ) . '=?';
    };

    $index_func = function( $data ) use( $assoc_func ){
      // 1. 字符串或者数字索引数组处理方法
      // id = 1                 `id` = 1
      // id > 1                 `id` > 1
      // id < 9                 `id` < 9
      // id <> 1                `id` <> 1
      // id in (1,2,3,4)        `id` in (1,2,3,4)
      // id not in (1,2,3,4)    `id` not in (1,2,3,4)
      // id between 1 and 8     `id` between 1 and 8
      // title like %hello%     `title` like %hello%
      //
      // 2. 二维数组 ( 'or' => [ ['rid'=>1, 'pid'=1], ['rid'=2,'pid'=2] ] )
      // 注：目前仅考虑到 where ，其他情况暂未想到
      // ['rid'=>1, 'pid'=>1]
      if( is_string( $data ) ){
        // 1.
        return preg_replace( '/^(\w+)(.*)$/', '`$1`$2', trim( $data ) );
      }else{
        // 2.
        $temp_sql = [];
        foreach( $data as $a => $b ){
          $temp_sql[] = $assoc_func( $a, $b );
        }
        return '(' . implode( ' AND ', $temp_sql ) .')';
      }
    };

    $this->_params['where'] .= implode( ' AND ', self::_common( $where, $convert, $index_func, $assoc_func ) );

    return $this;
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function whereBetween( array $where, bool $convert=true )
  {
    $k = array_keys( $where )[0];
    $v = array_values( $where )[0];
    $temp = ( $convert ? self::_convert( $k ) : $k ) . ' BETWEEN ' . $v[0] . ' AND ' . $v[1];
    return $this->where( $temp, false );
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function whereIn( array $where, bool $convert=true )
  {
    return $this->where( ['in' => $where], $convert );
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function whereNot( array $where, bool $convert=true )
  {
    return $this->where( ['<>' => $where], $convert );
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function whereGt( array $where, bool $convert=true )
  {
    $field = array_keys( $where )[0];
    if( $convert ){
      $field = self::_convert( $field );
    }

    return $this->where( $field . '>' . array_values( $where )[0], false );
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function whereLt( array $where, bool $convert=true )
  {
    $field = array_keys( $where )[0];
    if( $convert ){
      $field = self::_convert( $field );
    }

    return $this->where( $field . '<' . array_values( $where )[0], false );
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function whereLike( $where, bool $convert=true )
  {
    if( !is_array( $where ) ){
      $where = [$where];
    }
    return $this->where( ['like' => $where], $convert );
  }

  /**
   * 设置查询条件
   * 保存字符串
   *
   * @param   $where    条件
   * @param   $convert  是否转义
   *
   * @return  $this
   */
  public function whereOr( $where, bool $convert=true )
  {
    if( !is_array( $where ) ){
      $where = [$where];
    }
    return $this->where( ['or' => $where], $convert );
  }

  /**
   * sql 不推荐也不支持 bool 类型
   * 所以将 bool 值规定为是否转义对应字段的功能 (转义：$this->_convert())
   *
   * 本函数用于: field | order | where
   *
   * $convert 为是否转义，若$data为数组，则$convert定义为是否默认转义
   *
   * @param   $data       string|array    待处理数据
   * @param   $convert    bool            是否处理
   * @param   $index_func closure         字符串或数字索引数组的处理方法
   * @param   $assoc_func closure         关联数组的处理方法
   * @param   $array_func closure         where (in | or | and) 的处理方法
   *
   * @return  array
   */
  static private function _common( $data, bool $convert, Closure $index_func, Closure $assoc_func )
  {
    if( is_string ( $data ) ){
      return [ $convert ? $index_func( $data ) : $data ];
    }elseif( !is_array ( $data ) ){
      throw new Exception('类型不支持');
    }

    $result = [];
    foreach( $data as $key => $value ){
      if( is_int ( $key ) ){
        // 数字索引数组
        // 无指定是否转义，使用$convert判断是否转义
        // 'xxx'
        array_push( $result, $convert ? $index_func( $value ) : $value );
      }elseif( is_string ( $key ) ){
        $key = trim( $key );
        // 'xxx' => string | int | bool
        if( false === $value ){
          // 不转义： 'xxx' => false
          array_push( $result, $key );
        }elseif( true === $value ){
          // 转义：'xxx' => true
          array_push( $result, $index_func( $key ) );
        }elseif( is_string( $value ) || is_numeric( $value ) ){
          // 转义：'xxx' => string | int
          // field: 'user_id' => 'id'   `user_id` AS `id`
          // where: 'user_id' => 'id'   `user_id`=`id`
          array_push( $result, $assoc_func( $key, $value ) );
        }elseif( is_array ( $value ) ){
          // 专用于 where
          // 转义: 'in' => ['id' => [1,2,3]]
          //       'or' => ['id'=>1, 'status = 2']
          //       'and' => ['id'=>1, 'status = 2']
          //       TODO 'or' => [['rid'=>1, pid=>1], ['rid'=>1, 'pid'=>2]]
          switch( strtolower( $key ) ){
          case 'or':
          case 'and':
            $value = self::_common( $value, $convert, $index_func, $assoc_func );
            array_push( $result, '(' . implode( ' ' . strtoupper( $key ) . ' ', $value ) . ')' );
            break;

          default:
            // 'in' => [ 'id' => [1,3,4] ]
            // '<>' => [ 'id' => 1 ]
            // 'like' => [ 'field' => "%$field%" ]
            $temp = [];
            foreach( $value as $a => $b ){
              $temp_str = $convert ? self::_convert( $a ) : $a;
              $temp_str .= ' ' . strtoupper( $key ) . ' ';
              if( 'in' === $key ){
                $b = array_map( function( $d ){
                  return preg_replace( '/^([^\'\"].*)$/', '"$1"', trim( $d ) );
                }, is_string( $b ) ? explode( ',', $b ) : $b );
                $temp_str .= '(' . implode( ',', $b ) . ')';
              } else {
                $temp_str .= $b;
              }
              array_push( $temp, $temp_str );
            }
            array_push( $result, implode ( ' AND ', $temp ) );
          }
        }
      } else {
        throw new Exception( '未知类型' );
      }
    }

    return $result;
  }

  /**
   * Join 方法
   *
   * @param   $data   array  [ 'tb01 as t1' => 't1.uid = user.id', 'left' ]
   *                         [ 'tb02 as t2' => 't2.uid = user.id' ]
   *                  string 'tb01 t1'
   * @param   $addPre bool  是否添加前缀（默认：添加）
   *
   * @return  $this
   */
  public function join( $data, bool $addPre=true )
  {
    $temp = $this->_join( $data, 'JOIN ', $addPre ? self::$_config[$this->_db]['pre'] : '' );
    $this->_params['join'] = empty( $this->_params['join'] ) ? $temp : $this->_params['join'] . $temp;
    return $this;
  }

  /**
   * Left Join
   *
   * 参考 $this->join();
   */
  public function leftJoin( array $data, bool $addPre=true )
  {
    $temp = $this->_join( $data, 'LEFT JOIN ', $addPre ? self::$_config[$this->_db]['pre'] : '' );
    $this->_params['join'] = empty( $this->_params['join'] ) ? $temp : $this->_params['join'] . $temp;
    return $this;
  }

  /**
   * Right Join
   *
   * 参考 $this->join();
   */
  public function rightJoin( array $data, bool $addPre=true )
  {
    $temp = $this->_join( $data, 'RIGHT JOIN ', $addPre ? self::$_config[$this->_db]['pre'] : '' );
    $this->_params['join'] = empty( $this->_params['join'] ) ? $temp : $this->_params['join'] . $temp;
    return $this;
  }

  /**
   * Inner Join
   *
   * 参考 $this->join();
   */
  public function innerJoin( array $data, bool $addPre=true )
  {
    $temp = $this->_join( $data, 'INNER JOIN ', $addPre ? self::$_config[$this->_db]['pre'] : '' );
    $this->_params['join'] = empty( $this->_params['join'] ) ? $temp : $this->_params['join'] . $temp;
    return $this;
  }

  /**
   * Join 公共方法
   *
   * @param $data   array|string  待处理得数据
   * @param $join   string        join | left join | right join | inner join
   * @param $pre    bool          表前缀
   *
   * @return  string
   */
  static private function _join( $data, string $join, string $pre )
  {
    $result = [];

    if( is_string( $data ) ){
      array_push( $result, $join . self::_table($pre . $data) );

    }elseif( is_array( $data ) ){
      foreach( $data as $k => $v ){
        array_push( $result, $join . self::_table($pre . $k) . ' ON ' . self::_convert( $v ) );
      }
    }
    return implode( ' ', $result );
  }

  /**
   * 自增
   *
   * $param   string|array    $field    'logins': (logins + 1)
   *                                    [ 'logins' => 3 ]: (logins + 3)
   */
  public function setInc( $field )
  {
    $result = [];
    if( is_array ( $field ) ){
      foreach( $field as $k => $v ){
        if( is_int( $k ) ){
          $temp_field = self::_convert( trim( $v ) );
          $temp_step = 1;
        } else {
          $temp_field = self::_convert( trim( $k ) );
          $temp_step = $v;
        }
        array_push( $result, $temp_field . '=' . $temp_field . '+' . $temp_step );
      }
    } else {

      $temp_field = '`' . trim( $field ) . '`';
      $result = [ $temp_field . '=' . $temp_field . '+1' ];
    }
    return $this->update( $result );
  }

  /**
   * 自减
   *
   * $param   string|array    $field    'logins': (logins - 1)
   *                                    [ 'logins' => 3 ]: (logins - 3)
   */
  public function setDec( $field )
  {
    $result = [];
    if( is_array ( $field ) ){
      foreach( $field as $k => $v ){
        if( is_int( $k ) ){
          $temp_field = self::_convert( trim( $v ) );
          $temp_step = 1;
        } else {
          $temp_field = self::_convert( trim( $k ) );
          $temp_step = $v;
        }
        array_push( $result, $temp_field . '=' . $temp_field . '-' . $temp_step );
      }
    } else {

      $temp_field = self::_convert( trim( $field ) );
      $result = [ $temp_field . '=' . $temp_field . '-1' ];
    }
    return $this->update( $result );
  }

  /**
   * 指明要操作得 DB 配置
   * 一次name|table|transaction仅执行一次( 事务中有用 )
   *
   * @param   $db     string    数据库配置
   */
  public function db( string $db ){
    if( empty( $this->_db ) ){
      $this->_db = $db;
    }
  }

  /**
   * 增
   * 支持批量添加和添加一条数据
   *
   * @param   $values   array
   *
   * @return
   */
  public function add( array $values )
  {
    if( empty( $this->_params['table'] ) ){
      throw new exception( '请设置表名' );
    }

    // 批量添加无须报错，只须返回成功次数
    // 非批量添加需要报错
    $throwErr = true;
    if( !empty( $values[0] ) && is_array( $values[0] ) ){
      $throwErr = false;
      $keys = array_keys( $values[0] );
    }else{
      $keys = array_keys( $values );
      $values = [ $values ];
    }

    $sql = 'INSERT INTO ' . $this->_params['table'] . ' (' . implode( ',', array_map( function( $v ){
      return '`'. $v .'`';
    }, $keys ) ) . ') VALUES (' . implode( ',', array_map( function( $v ){
      return ':' . $v;
    }, $keys ) ) . ');';

    // 是否执行sql语句
    // true 执行，返回结果
    // false 不执行，返回 sql语句
    if( empty($this->_params['debug']) ){
      // 是否输出 sql 执行信息
      if( self::$_debug ){
        $start = microtime( true );
        $sql_temp = $sql;
      }
      $stmt = self::handle( $this->_db )->prepare( $sql );
      $sql = 0;
      foreach( $values as $rows ){
        try{
          foreach( $rows as $k => $v ){
            $stmt->bindValue( $k, $v );
          }
          if( $stmt->execute() ){
            $sql++;
          }
        }catch( Exception $e ){
          // 批量添加无须报错，只须返回成功次数
          // 非批量添加需要报错
          if( $throwErr ){
            throw new Exception( $e->getMessage() );
          }
        }
      }
      if( self::$_debug ){
        Response::withAddedHeader('PDO-Running', '[' . (string)( microtime( true ) - $start )*1000 . 'ms] ' . $sql_temp );
      }
    }else{
      $sql = [
        'sql'  => $sql,
        'data' => $values,
      ];
    }
    return $sql;
  }

  /**
   * 删
   * 注意：若有分布式，请删 master 数据
   *
   * @param   $param    bool | array    删除（批量：二维数组；单个：一维数组）的条件
   *                                    true: 全删
   *
   * @return
   */
  public function delete( $param=null )
  {
    if( empty( $this->_params['table'] ) ){
      throw new exception( '请设置表名' );
    }

    if( true === $param ){
      // 全删
      $sql = 'DELETE FROM ' . $this->_params['table'] . ';';

    }else{
      if( is_array( $param ) ){
        if( count( $param ) === count( $param, 1 ) ){
          // 一维数组，删除单条数据
          $this->where( $param );

        }else{
          // 二维数组，批量删除
          $this->whereOr( $param );
        }
      }
      if( empty( $this->_params['where'] ) ){
        // 禁止全删
        throw new Exception( '请设置查询条件' );
      }
      $sql = 'DELETE FROM ' . $this->_params['table'] . ' WHERE ' . $this->_params['where'] . ';';
    }
    // 记录时间
    if( empty($this->_params['debug']) ){
      if( self::$_debug ){
        $start = microtime( true );
        $sql_temp = $sql;
      }
      try{
        $stmt = self::handle( $this->_db )->prepare( $sql );
        foreach( $this->_where_v as $k => $v ){
          $stmt->bindValue( $k + 1, $v );
        }
        $sql = $stmt->execute();
      }catch( Exception $e ){
        throw new Exception( $e->getMessage() );
      }
      if( self::$_debug ){
        Response::withAddedHeader('PDO-Running', '[' . (string)( microtime( true ) - $start )*1000 . 'ms] ' . $sql_temp );
      }
    }else{
      $sql = [
        'sql'  => $sql,
        'data' => $this->_where_v,
      ];
    }
    return $sql;
  }

  /**
   * 改
   *
   * @param   $data   array|string
   *                  array:  ['status' => 1, 'updated_at' => time()]
   *                  string: `status` = `status` + 1
   * @param   $boo    bool    是否全改，默认false
   *
   * @return
   */
  public function update( $data, $boo=false )
  {
    if( empty( $this->_params['table'] ) ){
      throw new exception( '请设置表名' );
    }

    if( is_string( $data ) ){
      $data = [$data];
    }

    $temp = [];
    $temp_data = [];
    foreach( $data as $key => $value ){
      if( is_int( $key ) ){
        // string: `status` = `status` + 1
        array_push( $temp, $value );

      } else {
        // array: status => 1
        array_push( $temp, '`' . $key . '`=?' );
        array_push( $temp_data, $value );
      }
    }

    if( !$boo ){
      if( empty( $this->_params['where'] ) ){
        // 禁止全改
        throw new exception( '请设置查询条件' );
      }

      // 全改
      $sql = 'UPDATE ' . $this->_params['table'] . ' SET ' . implode( ',', $temp ) . ' WHERE ' . $this->_params['where'] . ';';

    }else{
      $sql = 'UPDATE ' . $this->_params['table'] . ' SET ' . implode( ',', $temp ) . ';';
    }

    if( empty($this->_params['debug']) ){
      if( self::$_debug ){
        $start = microtime( true );
        $sql_temp = $sql;
      }
      try{
        $stmt = self::handle( $this->_db )->prepare( $sql );
        foreach( $temp_data as $a => $b ){
          $stmt->bindValue( $a + 1, $b );
        }
        foreach( $this->_where_v as $k => $v ){
          $stmt->bindValue( $k + $a + 2, $v );
        }
        $sql = $stmt->execute();
      }catch( Exception $e ){
        throw new Exception( $e->getMessage() );
      }
      if( self::$_debug ){
        Response::withAddedHeader('PDO-Running', '[' . (string)( microtime( true ) - $start )*1000 . 'ms] ' . $sql_temp );
      }
    }else{
      $sql = [
        'sql'  => $sql,
        'data' => array_merge( $temp_data, $this->_where_v ),
      ];
    }
    return $sql;
  }

  /**
   * 查
   *
   * @param   $count    bool    是否返回总行数（默认不返回）
   *
   * @return  array     总行数存于数组第二位
   */
  public function select( bool $count=false )
  {
    if( empty( $this->_params['table'] ) ){
      throw new exception('请设置表名');
    }
    $sql = 'SELECT ';
    if( !empty( $this->_params['field'] ) ){
      $sql .= $this->_params['field'];
    } else {
      $sql .= '*';
    }
    $sql .= ' FROM '.$this->_params['table'];
    if( !empty( $this->_params['join'] ) ){
      $sql .= ' ' . $this->_params['join'];
    }
    if( !empty( $this->_params['where'] ) ){
      $sql .= ' WHERE ' . $this->_params['where'];
    }
    if( !empty( $this->_params['group'] ) ){
      $sql .= ' GROUP BY ' . $this->_params['group'];
    }
    if( !empty( $this->_params['order'] ) ){
      $sql .= ' ORDER BY ' . $this->_params['order'];
    }
    // 须放最后
    if( !empty( $this->_params['limit'] ) ){
      $sql .= ' LIMIT ' . $this->_params['limit'];
    }
    // 加锁
    if( true === self::$_lock ){
      $sql .= ' FOR UPDATE';
    }
    $sql .= ';';

    if( empty($this->_params['debug']) ){
      if( self::$_debug ){
        $start = microtime( true );
        $sql_temp = $sql;
      }
      // 仅返回关联数组
      try{
        $stmt = self::handle( $this->_db )->prepare( $sql );
        foreach( $this->_where_v as $k=>$v ){
          $stmt->bindValue( $k+1, $v );
        }
        $stmt->execute();
        $sql = $stmt->fetchAll( PDO::FETCH_ASSOC );
      }catch( Exception $e ){
        throw new Exception( $e->getMessage() );
      }
      if( self::$_debug ){
        Response::withAddedHeader('PDO-Running', '[' . (string)( microtime( true ) - $start )*1000 . 'ms] ' . $sql_temp );
      }

      // 返回总行数
      if( $count ){
        $this->_params['field'] = '';
        $this->_params['limit'] = '';
        $count = $this->count();
        // 返回数组
        return [ $sql, $count ];
      }
    }else{
      $sql = [
        'sql'  => $sql,
        'data' => $this->_where_v,
      ];
    }
    return $sql;
  }

  /**
   * 获得一组数据
   *
   * @param   $where    array
   *
   * @return  array
   */
  public function find( array $where = [] )
  {
    $result = $this->where( $where )->limit('1')->select();

    if( isset( $result[0] ) ){
      return $result[0];
    }
    return $result;
  }

  /**
   * 分页查询
   *
   * @param   $page   int   页码，从1开始
   * @param   $rows   int   每页行数
   * @param   $count  bool  是否返回去Limit条件后得总行数
   */
  public function page( int $page, int $rows, bool $count=true )
  {
    return $this->limit( ( $page-1 ) * $rows . ', '. $rows )->select( $count );
  }

  /**
   * 是否输出 sql 语句
   *
   * @param   $debug    bool
   *
   * @return  $this
   */
  public function debug( bool $debug=true )
  {
    $this->_params['debug'] = $debug;
    return $this;
  }

  /**
   * 返回总行数
   *
   * @param   null
   *
   * @return  int|string  行数|sql语句
   */
  public function count()
  {
    $sql = $this->field( 'count(1)', false )->select();
    if( is_array( $sql ) ){
      return empty( $sql ) ? 0 : ( int )$sql[0]['count(1)'];
    }
    return $sql;
  }

  /**
   * 给数据表名和字段名加上 '`'
   *
   * 注：若搜到 '`' 字符，则强制原样输出
   */
  static private function _convert( string $str )
  {
    $link = '`';
    $str = trim( $str );

    if( false === strpos( $str, $link ) ){
      $str2 = preg_replace( '/(^|[^`])(\w+)\.(\w+)/', "$1`$2`.`$3`", $str );
      if( $str2 === $str ){
        return $link . $str . $link;
      }
      return $str2;
    }
    return $str;
  }

  /**
   * 事务支持
   * 注：闭包中的 db 方法无效
   *
   * @param   $func   Closure
   * @param   $db     string    待操作得数据库 默认：master
   */
  static public function transaction( Closure $func, string $db='master' )
  {
    $self = new self;
    $self->_db = $db;
    self::$_lock = true;
    try{
      // 注：仅写入数据库需要事务支持
      self::handle( $self->_db )->beginTransaction();
      $result = $func();
      self::handle( $self->_db )->commit();
      self::$_lock = false;

    }catch( Exception $e ){
      self::$_lock = false;
      self::handle( $self->_db )->rollBack();
      if( self::$_debug ){
        throw new Exception( $e->getMessage() );
      }
      $result = false;
    }
    return $result;
  }
}
