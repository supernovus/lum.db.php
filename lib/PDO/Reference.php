<?php

namespace Lum\DB\PDO;

/**
 * A special thin class to represent a referenced column in a specific table.
 */
class Reference
{
  protected $parent;
  protected $table;
  protected $column;

  public function __construct ($table, $column, $parent=null)
  {
    $this->table = $table;
    $this->column = $column;
    $this->parent = $parent;
  }

  public function parent ()
  {
    return $this->parent;
  }

  public function table ()
  {
    return $this->table;
  }

  public function column ()
  {
    return $this->column;
  }

  public function refName ()
  {
    return $this->table . '.' . $this->column;
  }
}
