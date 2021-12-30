<?php

/**
 * Iterable query results.
 *
 * Plus direct access to the result columns
 * without creating an item object first.
 *
 * In addition to the array-like access, this also
 * provides object-like attributes.
 *
 */

namespace Lum\DB\PDO;

class ResultBag extends ResultArray
{
  protected function offsetGet ($offset): mixed
  {
    $row = current($this->results);
    return $row[$offset];
  }

  protected function offsetExists ($offset): bool
  {
    $row = current($this->results);
    return isset($row[$offset]);
  }

  protected function __get ($name): mixed
  {
    return $this->offsetGet($name);
  }

  protected function __isset ($name): bool
  {
    return $this->offsetExists($name);
  }

  protected function __set ($name, $value): void
  {
    $this->offsetSet($name, $value);
  }

  protected function __unset ($name): void
  {
    $this->offsetUnset($name);
  }

}
