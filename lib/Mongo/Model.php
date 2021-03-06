<?php

namespace Lum\DB\Mongo;
use \MongoDB\BSON\ObjectId;
use \MongoDB\{InsertOneResult, UpdateResult};

/**
 * MongoDB base class for object models.
 *
 * It's based on the Lum\DB\PDO\Model class for SQL databases.
 * As such, it offers a similar interface to that class.
 */
abstract class Model extends Simple implements \Iterator, \ArrayAccess
{
  use \Lum\DB\ModelCommon, \Lum\Meta\ClassID;

  public $parent;

  // The value Results::count() passes to Results::rowCount()
  // This is set to true by default for backwards compatibility.
  public $results_use_filtered_count = true;

  protected $childclass;
  protected $resultclass;

  protected $primary_key = '_id';

  // By default we save the build opts in the Model class.
  // Override in subclasses, or by using a model config: "saveOpts":false
  protected $save_build_opts = true;

  protected $resultset;

  public $default_value = null; // Fields will be set to this by default.

  protected $use_object_id = true;

  protected $serialize_ignore = ['server','db','data','resultset'];

  public function __construct ($opts=[])
  {
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }
    if (isset($opts['__classid']))
    {
      $this->__classid = $opts['__classid'];
    }
    if (isset($opts['childclass']))
    {
      $this->childclass = $opts['childclass'];
    }
    if (isset($opts['resultclass']))
    {
      $this->resultclass = $opts['resultclass'];
    }
    if (isset($opts['primary_key']))
    {
      $this->primary_key = $opts['primary_key'];
    }

    parent::__construct($opts);
  }

  public function __sleep ()
  {
    $properties = get_object_vars($this);
    foreach ($this->serialize_ignore as $ignored)
    {
      unset($properties[$ignored]);
    }
    return array_keys($properties);
  }

  public function __wakeup ()
  {
    $this->get_collection();
  }

  public function wrapRow ($data, $opts=[])
  {
    if (isset($opts['rawDocument']) && $opts['rawDocument'])
      return $data;
    if ($data)
    {
      $object = $this->newChild($data, $opts);
      if (isset($object))
        return $object;
      else
        return $data;
    }
  }

  public function newChild ($data=[], $opts=[])
  {
    if (isset($opts['childclass']))
      $class = $opts['childclass'];
    else
      $class = $this->childclass;
    if ($class)
    {
      $data = $this->populate_known_fields($data);
      $opts['parent'] = $this;
      $opts['data']   = $data;
      $opts['pk']     = $this->primary_key;
      return new $class($opts);
    }
  }

  public function getResults ($opts=[])
  {
    if (isset($opts['resultclass']))
      $class = $opts['resultclass'];
    else
      $class = $this->resultclass;
    if ($class && (!isset($opts['rawResults']) || !$opts['rawResults']))
    {
      $opts['parent'] = $this;
      return new $class($opts);
    }

    if (isset($opts['find']))
    {
      $data = $this->get_collection();
      $fopts = isset($opts['findopts']) ? $opts['findopts'] : [];
      $results = $data->find($opts['find'], $fopts);
      if (isset($opts['childclass']) || isset($this->childclass))
      {
        $wrapped = [];
        foreach ($results as $result)
        {
          $wrap = $this->wrapRow($result, $opts);
          if (isset($wrap))
            $wrapper[] = $wrap;
        }
        return $wrapped;
      }
      return $results;
    }
  }

  public function find ($find=[], $findopts=[], $classopts=[])
  {
    $classopts['find'] = $find;
    $classopts['findopts'] = $findopts;
    return $this->getResults($classopts);
  }

  public function findOne ($find=[], $findopts=[], $classopts=[])
  {
    $data = $this->get_collection();
    $result = $data->findOne($find, $findopts);
    return $this->wrapRow($result, $classopts);
  }

  public function rowCount ($find=[], $findopts=[])
  {
    $data = $this->get_collection();
    if (is_callable([$data, 'countDocuments']))
    { // Use the newer method.
      return $data->countDocuments($find, $findopts);
    }
    else
    { // Use the old one.
      return $data->count($find, $findopts);
    }
  }

  public function getDocById ($id, $findopts=[], $classopts=[])
  {
    if ($this->use_object_id)
      $id = Util::objectId($id);
    $pk = $this->primary_key;
    return $this->findOne([$pk => $id], $findopts, $classopts);
  }

  public function save ($doc, $update=null, $options=[])
  {
    $pk = $this->primary_key;
    $data = $this->get_collection();
    if (isset($doc[$pk]))
    {
      if ($this->use_object_id)
        $doc[$pk] = Util::objectId($doc[$pk]);
      $find = [$pk => $doc[$pk]];
      if (isset($update))
      {
        $res = $data->updateOne($find, $update, $options);
      }
      else
      {
        $res = $data->replaceOne($find, $doc, $options);
      }
      $isnew = 0;
    }
    else
    {
      $res = $data->insertOne($doc, $options);
      $isnew = 1;
    }
    return [$isnew, $res, $doc];
  }

  public function deleteId ($id)
  {
    $pk = $this->primary_key;
    if ($this->use_object_id)
      $id = Util::objectId($id);
    $data = $this->get_collection();
    return $data->deleteOne([$pk => $id]);
  }

  // Some helper routines, used in the Item class.

  public function _results ($results, $isSave=false)
  {
    if (isset($results))
    {
      if (is_object($results) && is_callable([$results, 'isAcknowledged']))
      { // Output from a MongoDB write method.
        return $results;
      }
      elseif ($isSave && is_array($results) && isset($results[1]) && is_object($results[1])
        && is_callable([$results[1], 'isAcknowledged']))
      { // Output from Model::save()
        return $results[1];
      }
    }
  }

  public function result_ok ($results, $unwrap=true, $isSave=false) :bool
  {
    if ($unwrap)
    {
      $results = $this->_results($results, $isSave);
    }
    if (isset($results))
    {
      return $results->isAcknowledged();
    }
    return false;
  }

  public function get_insert_id ($results)
  {
    $results = $this->_results($results, true); // Unwrap first.
    if (isset($results) && $this->result_ok($results, false))
    {
      if ($results instanceof InsertOneResult)
      {
        return $results->getInsertedId();
      }
      elseif ($results instanceof UpdateResult)
      {
        return $results->getUpsertedId();
      }
    }
  }

  public function deleted ($results) :bool
  {
    if ($this->result_ok($results))
    {
      $count = $results->getDeletedCount();
      return ($count > 0);
    }
    return false;
  }

  // Iterator interface

  public function rewind ()
  {
    $this->resultset = $this->find();
    return $this->resultset->rewind();
  }

  public function current ()
  {
    $this->resultset->current();
  }

  public function next ()
  {
    $this->resultset->next();
  }

  public function key ()
  {
    $this->resultset->key();
  }

  public function valid ()
  {
    $this->resultset->valid();
  }

  // ArrayAccess interface.

  public function offsetGet ($offset)
  {
    return $this->getDocById($offset);
  }

  public function offsetExists ($offset)
  {
    $doc = $this->getDocById($offset);
    if ($doc)
      return true;
    return false;
  }

  public function offsetSet ($offset, $doc)
  {
    $pk = $this->primary_key;
    $id = new ObjectId($offset);
    $doc->$pk = $id;
    if (is_callable([$doc, 'save']))
      $doc->save();
    else
      $this->save($doc);
  }

  public function offsetUnset ($offset)
  {
    return $this->deleteId($offset);
  }
}

