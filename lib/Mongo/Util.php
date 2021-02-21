<?php

namespace Lum\DB\Mongo;

use \MongoDB\BSON\ObjectId;
use \MongoDB\Model\{BSONArray,BSONDocument};

class Util
{
  static function toArray ($data, $opts=[])
  {
    if ($opts === true)
    { // A shortcut.
      $opts = ['recursive'=>true];
    }
    elseif ($opts === false)
    { // Another shortcut.
      $opts = ['objectId'=>false];
    }

    $getArrayData 
      = isset($opts['getArrayData']) 
      ? $opts['getArrayData'] 
      : false;

    $doId = isset($opts['objectId']) ? $opts['objectId'] : true;

    $doArr
      = isset($opts['bsonArray'])
      ? $opts['bsonArray']
      : true;

    $doObj
      = isset($opts['bsonObject'])
      ? $opts['bsonObject']
      : true;

    $recursive
      = isset($opts['recursive'])
      ? $opts['recursive']
      : false;

    if ($getArrayData 
      && ($data instanceof BSONDocument || $data instanceof BSONArray))
    { // This is a super shortcut.
      return $data->getArrayCopy();
    }

    $array = [];
    foreach ($data as $key => $val)
    {
      if ($doId && $val instanceof ObjectId)
      {
        $array[$key] = (string)$val;
      }
      elseif ($val instanceof BSONArray)
      {
        $array[$key] 
          = $recursive 
          ? static::toArray($val) 
          : $val->getArrayCopy();
      }
      elseif ($val instanceof BSONDocument)
      {
        $array[$key] 
          = $recursive 
          ? static::toArray($val)
          : $val->getArrayCopy();
      }
      else
      {
        if ($recursive && is_array($val))
        {
          $array[$key] = static::toArray($val);
        }
        else
        {
          $array[$key] = $val;
        }
      }
    }
    return $array;
  }

  static function idString ($id)
  {
    if (is_string($id))
    { // Simplest, it's already a string.
      return $id;
    }
    elseif ($id instanceof ObjectId)
    { // The _id property in it's native form.
      return (string)$id;
    }
    elseif (is_object($id) && isset($id->_id))
    { // A document or sub-document with an _id property.
      return (string)$id->_id;
    }
    elseif (is_array($id) && isset($id['_id']))
    { // An array document with an '_id' attribute.
      return (string)$id['_id'];
    }
    elseif (is_array($id) && isset($id['$oid']))
    { // Strict JSON Representation.
      return (string)$id['$oid'];
    }
    else
    { // Don't know what to do with that.
      throw new \Exception("Could not find an 'id' in the passed object: "
        .serialize($id));
    }
  }

  static function objectId ($id)
  {
    if ($id instanceof ObjectId)
    { // It's already what we want.
      return $id;
    }
    else
    { // It's something else, get the id string and return an ObjectId.
      return new ObjectId(static::idString($id));
    }
  }
}