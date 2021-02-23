<?php

namespace Lum\DB\Mongo;

use \MongoDB\BSON\ObjectId;
use \MongoDB\Model\{BSONArray,BSONDocument};

class Util
{
  static function toArray ($data, array $opts=[])
  {
#    error_log("Util::toArray(data, ".json_encode($opts).")");

    $passthrough
      = isset($opts['passthrough']) 
      ? $opts['passthrough'] 
      : false;

    if ($passthrough
      && ($data instanceof BSONDocument || $data instanceof BSONArray))
    { // This is a super shortcut, we're passing through to getArrayCopy().
      return $data->getArrayCopy();
    }

    $doId = isset($opts['objectId']) ? $opts['objectId'] : false;

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

    $array = [];
    foreach ($data as $key => $val)
    {
      if ($doId && $val instanceof ObjectId)
      { // Stringify ObjectId instances.
        $array[$key] = (string)$val;
      }
      elseif ($doArr && $val instanceof BSONArray)
      { // Serializing BSONArray specifically.
        $array[$key] 
          = $recursive 
          ? static::toArray($val, $opts) 
          : $val->getArrayCopy();
      }
      elseif ($doObj && $val instanceof BSONDocument)
      { // Serializing BSONDocument specifically.
        $array[$key] 
          = $recursive 
          ? static::toArray($val, $opts)
          : $val->getArrayCopy();
      }
      else
      { 
        if ($recursive && is_array($val))
        {
          $array[$key] = static::toArray($val, $opts);
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