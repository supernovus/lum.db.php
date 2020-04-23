<?php

/**
 * Common functions for example scripts.
 * Don't run this manually, it won't return anything.
 */

function showres ($res)
{
  if (count($res) >= 2)
  {
    $sql = $res[0];
    $info = [];
    $info['data'] = $res[1];
    if (count($res) >= 4)
    {
      $info['whereData'] = $res[2];
      $info['columnData'] = $res[3];
    }
    $infoText = json_encode($info, \JSON_PRETTY_PRINT);
    echo "$sql\n";
    echo "$infoText\n";
  }
  else
  {
    error_log("invalid results returned");
  }
}

