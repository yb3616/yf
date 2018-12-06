<?PHP
/**
 * 验证器
 *
 * @author    姚斌  <yb3616@126.com>
 * @License   https://github.com/yb3616/yf/blob/master/LICENSE
 * @Copyright (c) 2018 姚斌
 */
namespace YF;

use Closure;
use Exception;

class Validate
{
  /**
   * public
   *
   * @param   $data   array
   * @param   $rules  array
   *
   * @return  array
   */
  static public function verify( array $data, array $rules ) : array
  {
    $result = [];
    foreach( $rules as $key => $rule ){
      if( is_int( $key ) ){
        // 数字索引数组，键值表示字段名，无失败说明
        $field   = $rule;
        $comment = '';

      }else{
        // 关联数组，键名表示字段名，键值表示验证失败后返回的说明
        $field   = $key;
        $comment = $rule;
      }

      $temp = self::_verify_field( $data, $field );
      if( false === $temp ){
        return [ $comment, false ];

      }elseif( is_array( $temp ) ){
        $result = array_merge( $result, $temp );
      }
    }
    return [ $result, true ];
  }

  /**
   * private
   *
   * @param   $data     array     // 待验证数据
   * @param   $rule     string    // 规则
   *
   * @return  bool | string
   */
  static private function _verify_field( array $data, string $rule )
  {
    // 获得验证方法名
    $validate = array_map( 'trim', explode( '|', $rule ) );
    switch( count( $validate ) ){
    case 1:
      // 无需验证 例如：'field'
      return isset( $data[ $validate[0] ] ) ?  [ $validate[0] => $data[ $validate[0] ] ] : null;
    case 2:
      break;
    default: throw new Exception('管道符异常');
    }

    // 'username|'
    if( empty( $validate[1] ) && !isset( $data[ $validate[0] ] ) ){
      return false;
    }

    // 验证数据
    $temp_validate = array_map( 'trim', explode( ':', $validate[1] ) );

    // 验证
    // 若设置了验证规则，则头位数组值代表验证规则调用的函数，不能为空
    if( !empty( $temp_validate[0] ) ){
      if( 'default'===$temp_validate[0] ){
        return [
          $validate[0] => isset( $data[ $validate[0] ] ) ?
          $data[ $validate[0] ] :
          $temp_validate[1]
        ];
      }

      $temp_func = self::_user_func( $temp_validate[0] );
      $temp_parm = isset( $temp_validate[1] ) ? $temp_validate[1] : '';

      // 验证失败
      if( isset( $data[ $validate[0] ] ) ){
        if( !$temp_func( $data[ $validate[0] ], $temp_parm ) ){
          return false;
        }
      } else {
        return true;
      }
    }
    // 返回指定数据
    return [ $validate[0] => $data[ $validate[0] ] ];
  }

  /**
   * 获得验证方法
   *
   * @param   $func   string | Closure
   *
   * @return  Closure
   */
  static private function _user_func( $func ) : Closure
  {
    if( is_callable( $func ) ){
      // 闭包
      return $func;

    }else{
      if( false === strrpos( $func, '/' ) ){
        // 预定义方法
        $func = '_' . $func;
        return function( $data, $rule ) use($func){
          return self::$func( $data, $rule );
        };

      }else{
        // 自定义验证器
        $class = explode( '/', $func );
        $func = array_pop( $class );
        $class = implode( '\\', $class );
        $a = new $class;
        return function( $data, $rule ) use( $a, $func ){
          return $a->$func( $data, $rule );
        };
      }
    }
  }

  /**
   * 长度验证
   * 存在四种情况：
   *  1、length: 1      - 长度等于1
   *  2、length: ,5     - 长度小于等于5
   *  3、length: 5,     - 长度大于等于5
   *  4、length: 1,5    - 长度在1到5之间（包含1，5）
   *
   *  @param    $data   string    待验证数据
   *  @param    $params string    验证参数
   *
   *  @return   bool
   */
  static private function _length( string $data, string $params ) : bool
  {
    $param = array_map( 'trim', explode( ',', $params ) );
    $count = count( $param );
    $len = strlen( $data );
    switch( count( $param ) ){
    case 1:
      // 验证第一种情况
      return $len===intval( $param[0] );
    case 2:
      break;
    default: throw new Exception('未知长度参数');
    }

    if( $param[0]==='' ){
      // 验证第二种情况
      return $len <= intval( $param[1] );

    }elseif( $param[1]==='' ){
      // 验证第三种情况
      return $len >= intval( $param[0] );

    }else{
      // 验证第四种情况
      return $len >= intval( $param[0] ) && $len <= intval( $param[1] );
    }
  }

  /**
   * 比较数字大小
   *
   * 存在四种情况：
   *  1、compare: 1      - 数值等于1
   *  2、compare: ,5     - 数值小于等于5
   *  3、compare: 5,     - 数值大于等于5
   *  4、compare: 1,5    - 数值在1到5之间（包含1，5）
   *
   *  @param    $data   string    待验证数据
   *  @param    $params string    验证参数
   *
   *  @return   bool
   */
  static private function _compare( string $data, string $params ) : bool
  {
    $param = array_map( 'trim', explode( ',', $params ) );
    $count = count( $param );
    $data = doubleval( $data );
    switch( count( $param ) ){
    case 1:
      // 验证第一种情况
      return $data === doubleval($param[0]);
    case 2:
      break;
    default: throw new Exception('未知长度参数');
    }

    if( $param[0]==='' ){
      // 验证第二种情况
      return $data <= doubleval( $param[1] );

    }elseif( $param[1]==='' ){
      // 验证第三种情况
      return $data >= doubleval( $param[0] );

    }else{
      // 验证第四种情况
      if( doubleval( $param[0] ) > doubleval( $param[1] ) ){
        throw new Exception('参数异常，后一位值需比前一位大');
      }
      return $data>=doubleval( $param[0] ) && $data<=doubleval( $param[1] );
    }
  }

  /**
   * 检查数据是不是整型（不含小数点）
   *
   *  @param    $data   string    待验证数据
   *  @param    $params string    验证参数
   *
   *  @return   bool
   */
  static private function _int( string $data, string $rule ) : bool
  {
    return is_numeric( $data ) && !strpos( $data, '.' );
  }

  /**
   * 检查是否包含
   *
   *  @param    $data   string    待验证数据
   *  @param    $params string    验证参数
   *
   *  @return   bool
   */
  static private function _in( string $data, string $rule ) : bool
  {
    return in_array( $data, array_map( 'trim', explode( ',', $rule ) ) );
  }
}
