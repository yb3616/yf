<?php
/**
 * 预排序遍历树算法
 * Modified Preorder Tree Traversal Algorithm
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use closure;
use Exception;
use YF\DB;

class MPTTA
{
  /**
   * 参数
   * [
   *    'name' => 'test',         // 数据表名，不带前缀
   *    'id'   => 'id',           // 主键（字段名）
   *    'lft'  => 'left_value',   // 左值（字段名）
   *    'rgt'  => 'right_value',  // 右值（字段名）
   *    'lvl'  => 'level',        // 层级（字段名）
   * ]
   */
  static private $_config = null;

  /**
   * 初始化类
   *
   * @param   $config   array
   * @param   $pre      string  数据库表前缀
   */
  static public function init( array $config, string $pre )
  {
    if( empty( self::$_config ) ){
      self::$_config = $config;
      self::$_config['pre'] = $pre;
    }
  }

  /**
   * 写入配置
   *
   * @param   $config   array
   *
   * @return  $this   object
   */
  static public function setConfig( array $config )
  {
    foreach( $config as $key => $value ){
      self::$_config[$key] = $value;
    }
  }

  /**
   * 往左添加子节点( 闭包参数,适合严谨的场景 )
   *
   * @param   $func   Closure 闭包，第一参数为 入库操作，其需要两个参数 $pid, $data
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  bool
   */
  static public function addChindFunc( closure $func ) : bool
  {
    return DB::transaction( function() use( $func ) : bool {
      $f = function( int $pid, array $data, string $tb, bool $pre=true ) : bool {
        if( true === $pre ){
          $tb = self::$_config['pre'] . $tb;
        }

        return self::_addChild( $pid, $data, $tb );
      };

      return $func( $f );
    } );
  }

  /**
   * 往左添加子节点
   *
   * @param   $pid    int     父节点
   * @param   $data   array   待写入数据
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  bool
   */
  static public function addChild( int $pid, array $data, string $tb, bool $pre=true ) : bool
  {
    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    return DB::transaction( function() use( $pid, $data, $tb ) : bool {
      return self::_addChild( $pid, $data, $tb );
    } );
  }

  /**
   * 添加子节点公共方法
   *
   * @param   $pid    int     父节点主键
   * @param   $data   array   要插入的数据
   * @param   $tb     string  表名
   *
   * @return  bool
   */
  static private function _addChild( int $pid, array $data, string $tb ) : bool
  {
    // 行锁
    $temp = DB::name( $tb )
      ->field([ self::$_config['lft'], self::$_config['lvl'] ])
      ->where([ self::$_config['id'] => $pid ])
      ->find();
    if( empty( $temp ) ){
      return false;
    }

    $lft = intval( $temp[self::$_config['lft']] );
    $lvl = intval( $temp[self::$_config['lvl']] );
    DB::name( $tb )
      ->whereGt([ self::$_config['rgt'] => $lft ])
      ->setInc([ self::$_config['rgt'] => 2 ]);
    DB::name( $tb )
      ->whereGt([ self::$_config['lft'] => $lft ])
      ->setInc([ self::$_config['lft'] => 2 ]);

    $data = array_merge( $data, [
      self::$_config['lft'] => $lft + 1,
      self::$_config['rgt'] => $lft + 2,
      self::$_config['lvl'] => $lvl + 1,
    ] );

    DB::name( $tb )->add( $data );
    return true;
  }

  /**
   * 往右添加兄弟节点
   *
   * @param   $pid    int   兄节点
   * @param   $data   array 待写入数据
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  bool
   */
  static public function addBrother( int $pid, array $data, string $tb, bool $pre=true ) : bool
  {
    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    if( $pid === 0 ){
      return self::_addBrotherLv1( $data, $tb, $pre );
    }

    return DB::transaction( function() use( $pid, $data, $tb ) : bool {
      // 行锁
      $temp = DB::name( $tb )
        ->field([ self::$_config['rgt'], self::$_config['lvl'] ])
        ->where([ self::$_config['id'] => $pid ])
        ->find();
      if( empty( $temp ) ){
        return false;
      }

      $rgt = intval( $temp[self::$_config['rgt']] );
      $lvl = intval( $temp[self::$_config['lvl']] );
      DB::name( $tb )
        ->whereGt([ self::$_config['rgt'] => $rgt ])
        ->setInc([ self::$_config['rgt'] => 2 ]);
      DB::name( $tb )
        ->whereGt([ self::$_config['lft'] => $rgt ])
        ->setInc([ self::$_config['lft'] => 2 ]);

      $data = array_merge( $data, [
        self::$_config['lft'] => $rgt + 1,
        self::$_config['rgt'] => $rgt + 2,
        self::$_config['lvl'] => $lvl,
      ] );

      DB::name( $tb )->add( $data );
      return true;
    } );
  }

  /**
   * 往右添加兄弟节点(顶级节点,未知兄弟节点)
   *
   * @param   $data   array   待写入数据
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  bool
   */
  static private function _addBrotherLv1( array $data, string $tb, bool $pre=true ) : bool
  {
    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    return DB::transaction( function() use( $data, $tb ){
      // 表锁
      $lvl = 1;
      $temp = DB::name( $tb )
        ->field( self::$_config['rgt'] )
        ->where([ self::$_config['lvl'] => $lvl ])
        ->order( '`' . self::$_config['lft'] . '` DESC', false )
        ->find();
      if( empty( $temp ) ){
        // 本表第一条数据
        $rgt = 0;
      }else{
        $rgt = intval( $temp[ self::$_config['rgt'] ] );
      }

      $data = array_merge( $data, [
        self::$_config['lft'] => $rgt + 1,
        self::$_config['rgt'] => $rgt + 2,
        self::$_config['lvl'] => $lvl,
      ] );

      DB::name( $tb )->add( $data );
      return true;
    } );
  }

  /**
   * 删除节点
   *
   * @param   $pid    int   父节点
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  bool
   */
  static public function delete( int $pid, string $tb, bool $pre=true ) : bool
  {
    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    return DB::transaction( function() use( $pid, $tb ){
      return self::_delete( $pid, $tb );
    } );
  }

  /**
   * 删除节点
   *
   * @param   $func   Closure 闭包函数
   *
   * @return  bool
   */
  static public function deleteFunc( closure $func ) : bool
  {
    return DB::transaction( function() use( $func ){
      $f = function( int $pid, string $tb, bool $pre=true ) : bool {
        if( true === $pre ){
          $tb = self::$_config['pre'] . $tb;
        }

        return self::_delete( $pid, $tb );
      };

      return $func( $f );
    } );
  }

  /**
   * 删除节点公共方法
   *
   * @param   $tb     string  表名
   *
   * @return  bool
   */
  static private function _delete( int $pid, string $tb ) : bool
  {
    // 行锁
    $temp = DB::name( $tb )
      ->field([ self::$_config['lft'], self::$_config['rgt'] ])
      ->field(
        [ '`'.self::$_config['rgt'].'`-`'.self::$_config['lft'].'`+1'=>'wth' ],
        false
      )
      ->where([ self::$_config['id'] => $pid ])
      ->find();
    if( empty( $temp ) ){
      return false;
    }

    $lft = intval( $temp[ self::$_config['lft'] ] );
    $rgt = intval( $temp[ self::$_config['rgt'] ] );
    $wth = intval( $temp['wth'] );
    DB::name( $tb )
      ->whereBetween([ self::$_config['lft'] => [ $lft, $rgt ] ])
      ->delete();

    DB::name( $tb )
      ->whereGt([ self::$_config['lft'] => $rgt ])
      ->setDec([ self::$_config['lft'] => $wth ]);

    DB::name( $tb )
      ->whereGt([ self::$_config['rgt'] => $rgt ])
      ->setDec([ self::$_config['rgt'] => $wth ]);

    return true;
  }

  /**
   * 查找所有子孙节点
   *
   * @param   $pid    int     父节点
   * @param   $pid    array   父节点数组
   * @param   $fields string  待查询字段，以半角逗号分割
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  array
   */
  static public function findAllChildren( $pid, string $fields, string $tb, bool $pre=true ) : array
  {
    if( empty( $pid ) ){
      return [];
    }

    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    $fields = array_map( function( $v ){
      return 'c.' . trim( $v );
    }, explode( ',', $fields ) );

    $db = DB::name( $tb . ' c' );
    if( is_array( $pid ) ){
      $db->whereIn([ 'p.' . self::$_config['id'] => $pid ]);
    }elseif( is_int( $pid ) ){
      $db->where([ 'p.' . self::$_config['id'] => $pid ]);
    }else{
      throw new Exception( '参数类型不支持' );
    }

    return $db->join( $tb . ' p' )
      ->field( $fields )
      ->whereGt([ 'c.' . self::$_config['lft']=>'`p`.`' . self::$_config['lft'] . '`' ])
      ->whereLt([ 'c.' . self::$_config['lft']=>'`p`.`' . self::$_config['rgt'] . '`' ])
      ->order( 'c.'. self::$_config['id'] )
      ->select();
  }

  /**
   * 查找所有子节点(不包含孙节点及以下节点)
   *
   * @param   $pid    int     父节点
   * @param   $fields string  待查询字段，以半角逗号分割
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  array
   */
  static public function findChildren( int $pid, string $fields, string $tb, bool $pre=true ) : array
  {
    $fields = array_map( function( $v ){
      return 'c.' . trim( $v );
    }, explode( ',', $fields ) );

    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    return DB::name( $tb . ' c' )
      ->join( $tb . ' p' )
      ->field( $fields )
      ->where([ 'p.' . self::$_config['id']=>$pid ])
      ->where( '`c`.`' . self::$_config['lvl'] . '`-`p`.`' . self::$_config['lvl'] . '`=1', false )
      ->whereGt([ 'c.' . self::$_config['lft']=>'`p`.`' . self::$_config['lft'] . '`' ])
      ->whereLt([ 'c.' . self::$_config['lft']=>'`p`.`' . self::$_config['rgt'] . '`' ])
      ->order( 'c.'. self::$_config['id'] )
      ->select();
  }

  /**
   * 子孙节点数
   *
   * @param   $pid    int     父节点
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  int
   */
  static public function findChildrenNum( int $pid, string $tb, bool $pre=true ) : int
  {
    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    $result = DB::name( $tb )
      ->field([ self::$_config['lft'], self::$_config['rgt'] ])
      ->where([ self::$_config['id'] => $pid ])
      ->find();
    if( empty( $result ) ){
      return 0;
    }

    return ( intval( $result[ self::$_config['rgt'] ] ) - intval( $result[ self::$_config['lft'] ] ) - 1 ) / 2;
  }

  /**
   * 获得父节点路径(包含当前节点)
   *
   * @param   $cid    int     子节点
   * @param   $fields string  待查询字段，以半角逗号分割
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  array
   */
  static public function findParents( int $cid, string $fields, string $tb, bool $pre=true ) : array
  {
    $fields = array_map( function( $v ){
      return 'p.' . trim( $v );
    }, explode( ',', $fields ) );

    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    return DB::name( $tb . ' p' )
      ->join( $tb . ' c' )
      ->field( $fields )
      ->where([ 'c.' . self::$_config['id']=>$cid ])
      ->whereBetween([
        'c.' . self::$_config['lft'] => [
          '`p`.`' . self::$_config['lft'] . '`',
          '`p`.`' . self::$_config['rgt'] . '`',
        ]
      ])
      ->order( 'p.'. self::$_config['id'] )
      ->select();
  }

  /**
   * 获得父节点路径(不包含当前节点)
   *
   * @param   $cid    int     子节点
   * @param   $fields string  待查询字段，以半角逗号分割
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  array
   */
  static public function findParent( int $cid, string $fields, string $tb, bool $pre=true ) : array
  {
    $fields = array_map( function( $v ){
      return 'p.' . trim( $v );
    }, explode( ',', $fields ) );

    if( true === $pre ){
      $tb = self::$_config['pre'] . $tb;
    }

    return DB::name( $tb . ' p' )
      ->join( $tb . ' c' )
      ->field( $fields )
      ->where([ 'c.' . self::$_config['id']=>$cid ])
      ->where( '`c`.`' . self::$_config['lvl'] . '`-`p`.`' . self::$_config['lvl'] . '`=1', false )
      ->whereBetween([
        'c.' . self::$_config['lft'] => [
          '`p`.`' . self::$_config['lft'] . '`',
          '`p`.`' . self::$_config['rgt'] . '`',
        ]
      ])
      ->order( 'p.'. self::$_config['id'] )
      ->find();
  }

  /**
   * 检查该子节点是否在父节点列表中（包含父节点所有子孙节点）
   *
   * @param   $id     int     子节点id
   * @param   $ids    array   父节点列表
   * @param   $tb     string  表名
   * @param   $pre    bool    是否添加表前缀
   *
   * @return  bool
   */
  static public function hasChild( int $id, array $ids, string $tb, bool $pre=true ) : bool
  {
    // 检查 $id 的所有父节点
    $result = self::findParents( $id, self::$_config['id'], $tb, $pre );

    $ids_ = [];
    foreach( $result as $v ){
      $ids_[] = $v[ self::$_config['id'] ];
    }

    return !empty( array_intersect( $ids, $ids_ ) );
  }
}
