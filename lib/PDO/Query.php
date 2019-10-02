<?php

namespace Lum\DB\PDO;

/**
 * A join operator representing AND.
 */
const Q_AND = ' AND ';

/**
 * A join operator representing OR.
 */
const Q_OR  = ' OR ';

/**
 * Generate a unique query id.
 *
 * @param string $name  The name of the column.
 * @return string  The name with a unique id attached.
 */
function q_id ($name)
{
  return $name.'_'.uniqid();
}

/**
 * The Queryable base class. Both Query and Subquery inherit from this.
 */
abstract class Queryable
{
  protected $parent;
  protected $root;
  protected $join = Q_AND;
  protected $op = '=';

  protected $where = [];
  protected $wdata = [];

  /**
   * Build a Queryable class
   *
   * @param array $opts   An associative array of options:
   *
   *  'parent'    The parent object that called us.
   *
   *  'root'      The Query object at the top of the stack.
   *
   *  'join'      The current WHERE joining operator.
   *              One of the Q_AND or Q_OR namespace constants.
   *              Default: Q_AND
   *
   *  'op'        The default comparison operator.
   *              Default '='
   *
   */
  public function __construct ($opts=[])
  {
    if (isset($opts['parent']))
      $this->parent = $opts['parent'];
    if (isset($opts['root']))
      $this->root = $opts['root'];
    if (isset($opts['join']))
      $this->join = $opts['join'];
    if (isset($opts['op']))
      $this->op = $opts['op'];
  }

  /**
   * Reset the query to a blank slate.
   */
  public function reset ()
  {
    $this->join  = Q_AND;
    $this->op    = '=';
    $this->where = [];
    $this->wdata = [];
    return $this;
  }

  /**
   * Use AND between associative array assignments.
   */
  public function withAnd ()
  {
    $this->join = Q_AND;
    return $this;
  }

  /**
   * Use OR between associative array assignments.
   */
  public function withOr ()
  {
    $this->join = Q_OR;
    return $this;
  }

  /**
   * Use a custom operator for comparisons.
   */
  public function withOp ($op)
  {
    $this->op = $op;
    return $this;
  }

  /**
   * Build a simple where statement and add it to our WHERE query.
   *
   * If no parameters are passed, creates a Subquery, adds it to our
   * current WHERE statement, and returns the Subquery.
   *
   * If one parameter is passed, and is an associative array, we compare
   * each key to each value with the current operator, and join each statement
   * with the current join propety.
   *
   * If two parameters are passed, the first parameter is the column, and the
   * second parameter is the value, we compare them with the current operator.
   *
   * If three parameters are passed, the first is the column, the second is
   * the comparison operator, and the third is the value.
   *
   * @return object  If no parameters were passed, we return the sub-query.
   *                 If at least one parameter was passed, we return $this.
   */
  public function where ($val1=null, $val2=null, $val3=null)
  {
    if (isset($val1))
    {
      $op = $this->op;
      if (is_string($val1) && isset($val2))
      {
        $ph = q_id($val1);
        if (isset($val3))
        { // We're in 'field','=','value' mode.
          $this->where[] = "$val1 $val2 :$ph";
          $this->wdata[$ph] = $val3;
        }
        else
        { // We're in 'field','value' mode.
          $this->where[] = "$val1 $op :$ph";
          $this->wdata[$ph] = $val2;
        }
      }
      elseif (is_array($val1))
      { // We're in ['field'=>'val'] mode.
        $join = $this->join;
        $cnt = count($val1);
        $c = 0;
        foreach ($val1 as $k => $v)
        {
          $ph = q_id($k);
          $this->where[] = "$k $op :$ph";
          $this->wdata[$ph] = $v;
          if ($c != $cnt - 1)
            $this->where[] = $join;
          $c++;
        }
      }
    }
    else
    { // We're creating a sub-query.
      $subquery = $this->subquery();
      $this->where[] = $subquery;
      return $subquery;
    }
    return $this;
  }

  /**
   * Add a bit to the WHERE statement using AND.
   *
   * Accepts the same parameters as where().
   */
  public function and ($val1=null, $val2=null, $val3=null)
  {
    $this->where[] = Q_AND;
    return $this->where($val1,$val2,$val3);
  }

  /**
   * Add a bit to the WHERE statement using OR.
   *
   * Accepts the same parameters as where().
   */
  public function or ($val1=null, $val2=null, $val3=null)
  {
    $this->where[] = Q_OR;
    return $this->where($val1,$val2,$val3);
  }

  /**
   * Compile the WHERE statement.
   *
   * Generally only called when you are finished, against the top level
   * Query object. Will be called on each Subquery automatically.
   *
   * @return array [string $whereStatement, array $whereData]
   */
  public function get_where ()
  {
    $where = '';
    foreach ($this->where as $w)
    {
      if (is_string($w))
      {
        $where .= $w;
      }
      elseif (is_object($w) && is_callable([$w, 'get_where']))
      {
        $subwhere = $w->get_where();
        $where .= '(' . $subwhere[0] . ')';
        foreach ($subwhere[1] as $k => $v)
        {
          $this->wdata[$k] = $v;
        }
      }
    }
    $wdata = $this->wdata;
    return [$where, $wdata];
  }

  /**
   * Build a Subquery and return it.
   *
   * Does not add it to our internal WHERE query, this is not recommended
   * to be called directly, instead use where(), and(), or or() with
   * no parameters to get a Subquery.
   */
  public function subquery ($opts=[])
  {
    $opts['parent'] = $this;
    $opts['root']   = $this->root;
    return new Subquery($opts);
  }

  /**
   * Get the direct parent of this object.
   */
  public function back ()
  {
    return $this->parent;
  }

  /**
   * Get the top-most Query object.
   */
  public function root ()
  {
    return $this->root;
  }
}

/**
 * The primary Query class.
 *
 * All external code should use this.
 *
 * An example assuming $db is a Simple instance:
 *
 * $query = new \Lum\DB\PDO\Query();
 *
 * $query->get('*')
 *  ->where(['col1'=>'val1'])
 *  ->and() // Generating a Subquery here.
 *    ->where('col2','val2')
 *    ->or('col3','!=','val3')
 *  ->limit(10); // Passed back to root Query, see Subquery::__call().
 *
 * $db->select('tablename', $query);
 *
 * The code above would generate an SQL statement of:
 *
 * SELECT * FROM tablename WHERE col1 = :col1_u AND 
 *  (col2 = :col2_u OR col3 = :col3_u) LIMIT 10;
 *
 * With a data structure of:
 *
 * ["col1_u"=>"val1", "col2_u"=>"val2", "col3_u"=>"val3"];
 *
 * Where each "_u" is actually a unique id.
 *
 */
class Query extends Queryable
{
  public $cols;
  public $order;
  public $limit;
  public $offset;
  public $single;
  public $columnData;

  public $fetch;

  public $rawRow;
  public $rawResults;

  /**
   * Build a Query object.
   *
   * In addition to the Queryable constructor, this
   * sets the $this->root property to $this.
   */
  public function __construct ($opts=[])
  {
    parent::__construct($opts);
    $this->root = $this;
  }

  /**
   * Reset to a blank slate.
   *
   * Resets everything from Queryable, plus anything specific to the Query
   * class itself.
   */
  public function reset ()
  {
    unset($this->cols);
    unset($this->order);
    unset($this->limit);
    unset($this->offset);
    unset($this->single);
    unset($this->columnData);
    unset($this->rawRow);
    unset($this->rawResults);
    unset($this->fetch);
    parent::reset();
  }

  /**
   * Specify the columns we want to get in a SELECT statement.
   *
   * @param mixed $cols  Either a string, or an array of strings.
   * @return Query $this
   */
  public function get ($cols)
  {
    $this->cols = $cols;
    return $this;
  }

  /**
   * Alias to get()
   */
  public function select ($cols)
  {
    return $this->get($cols);
  }

  /**
   * Specify the columnData we want to set with an INSERT or UPDATE statement.
   *
   * @param array $coldata  Associative array of values we are setting.
   * @return Query $this
   */
  public function set ($coldata)
  {
    $this->columnData = $coldata;
    return $this;
  }

  /**
   * Alias to set()
   */
  public function insert ($coldata)
  {
    return $this->set($coldata);
  }

  /**
   * Alias to set()
   */
  public function update ($coldata)
  {
    return $this->set($coldata);
  }

  /**
   * Set the sort order.
   *
   * @param string $order  The SQL sort order string.
   * @return Query $this
   */
  public function order ($order)
  {
    $this->order = $order;
    return $this;
  }

  /**
   * Alias to order()
   */
  public function sort ($order)
  {
    return $this->order($order);
  }

  /**
   * Set a LIMIT statement.
   *
   * @param numeric $limit  The LIMIT value.
   * @return Query $this
   */
  public function limit ($limit)
  {
    $this->limit = $limit;
    return $this;
  }

  /**
   * Set an OFFSET statement.
   *
   * @param numeric $offset  The OFFSET value.
   * @return Query $this
   */
  public function offset ($offset)
  {
    $this->offset = $offset;
    return $this;
  }

  /**
   * Set the 'single' option.
   *
   * Similar to 'LIMIT 1' except it also makes the select() method
   * return only a single instance instead of an array.
   *
   * @param bool $single (Optional, default true) Set single option?
   * @return Query $this
   */
  public function single ($single=true)
  {
    $this->single = $single;
    return $this;
  }

  /**
   * Set the 'rawRow' and 'rawResults' options.
   *
   * @return Query $this
   */
  public function raw ()
  {
    $this->rawRow = true;
    $this->rawResults = true;
    return $this;
  }

  /**
   * Set the 'fetch' option to PDO::FETCH_NUM
   *
   * @return Query $this
   */
  public function asArray()
  {
    $this->fetch = \PDO::FETCH_NUM;
    return $this;
  }

  /**
   * Set the 'fetch' option to PDO::FETCH_BOTH
   *
   * @return Query $this
   */
  public function asBoth()
  {
    $this->fetch = \PDO::FETCH_BOTH;
    return $this;
  }

  /**
   * Set the 'fetch' option to PDO::FETCH_ASSOC
   *
   * This is the default 'fetch' option is none is specified.
   *
   * @return Query $this
   */
  public function asAssoc()
  {
    $this->fetch = \PDO::FETCH_ASSOC;
    return $this;
  }

}

/**
 * A Subquery represents a nested \portion of a WHERE statement.
 * 
 * A Subquery is not constructed directly, but by using the
 * $query->subquery() method.
 */
class Subquery extends Queryable
{
  /**
   * Any method we don't recognize, pass to the root.
   */
  public function __call ($method, $args)
  {
    $root = $this->root();
    call_user_func_array([$root, $method], $args);
  }
}
