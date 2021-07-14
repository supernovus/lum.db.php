<?php

namespace Lum\DB;

/**
 * A trait providing a default to_array() method for Result* classes.
 */
trait ResultToArray
{
  /**
   * Create a flat array out of our data.
   */
  public function to_array ($opts=[])
  {
    $array = [];
    $shallow = isset($opts['shallow']) ? $opts['shallow'] : false;
    foreach ($this as $that)
    {
      if (!$shallow && is_object($that) && is_callable([$that, 'to_array']))
        $item = $that->to_array($opts);
      else
        $item = $that;
      $array[] = $item;
    }
    return $array;
  }

  /**
   * Get a shallow array of result items.
   */
  public function getArrayCopy ()
  {
    return $this->to_array(['shallow'=>true]);
  }
}

