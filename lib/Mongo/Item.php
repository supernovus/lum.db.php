<?php

namespace Lum\DB\Mongo;

/**
 * A base class for PDO/SQL database models.
 */
class Item extends \Lum\DB\Child
{
  use \Lum\Data\JSON;

  /**
   * The primary key field. Should never have to be changed for MongoDB
   */
  protected $primary_key = '_id';

  /**
   * Default value for the $retBool parameter of the delete() method. 
   */
  protected $delete_return_boolean = false;

  /**
   * Default value for the 'returnBoolean' options of the save()
   * and saveUpdates() methods.
   */
  protected $save_return_boolean = false;

  /**
   * Default value for the 'returnNewId' option of the save() method.
   */
  protected $save_return_new_id = false;

  /**
   * Use recursive to_array() mode by default?
   */
  protected $recursive_to_array = false;

  /**
   * Return our data in BSON format.
   *
   * Override this if you have custom objects in use,
   * or need to ensure certain fields are in the right format.
   */
  public function to_bson ($opts=[])
  {
    return $this->data;
  }

  /**
   * Save our data back to the database.
   *
   * If the primary key is set, and has not been modified, this will
   * update the existing record with the new data.
   *
   * If the primary key has not been set, or has been modified, this will
   * insert a new record into the database, and in the case of auto-generated
   * primary keys, update our primary key field to point to the new record.
   */
  public function save ($opts=[])
  {
    if ($opts === true)
      $opts = ['all'=>true];

    if (isset($opts['pk']))
      $pk = $opts['pk'];
    else
      $pk = $this->primary_key;

    $data = $this->to_bson($opts);

    $retBool = isset($opts['returnBoolean'])
      ? $opts['returnBoolean']
      : $this->save_return_boolean;

    $retId = isset($opts['returnNewId'])
      ? $opts['returnNewId']
      : $this->save_return_new_id;

    if (isset($data[$pk]) && !isset($this->modified_data[$pk]))
    { // Update an existing row.

      if (isset($opts['all']) && $opts['all'])
      { // A way to force save all data.
        if ($this->clear_on_update)
        {
          $this->modified_data = [];
        }
        return $this->parent->save($data);
      }

      if (count($this->modified_data)==0) return;

      $fields = array_keys($this->modified_data);
#      error_log("<changed>".json_encode($fields)."</changed>");
      $cdata  = [];
      $fc = count($fields);
      for ($i=0; $i< $fc; $i++)
      {
        $field = $fields[$i];
#        error_log("<modified>$field</modified>");
        if ($field == $pk) continue; // Sanity check.
        $cdata[$field] = $data[$field];
      }

      if ($this->clear_on_update)
      { // Clear the modified data.
        $this->modified_data = [];
      }

      $res = $this->parent->save($data, ['$set'=>$cdata]);
      if ($retBool)
      {
        return $this->parent->result_ok($res, true, true);
      }
      return $res;
    }
    else
    { // Insert a new row.
      $res = $this->parent->save($data);

      if ($this->clear_on_insert)
      { // Clear the modified data.
        $this->modified_data = [];
      }

      $newId = $this->parent->get_insert_id($res);
      if ($newId)
      {
        $this->data[$pk] = $newId;
      }

      if ($retId)
      {
        return $newId;
      }
      if ($retBool)
      {
        return isset($newId);
      }

      return $res;
    }
  }

  /**
   * Apply MongoDB update statements directly.
   * This is not for general purpose usage.
   */
  public function saveUpdates ($updates, $opts=[])
  {
    $pk = $this->primary_key;

    $data = $this->to_bson($opts);

    if (isset($data[$pk]))
    {
      $retBool = isset($opts['returnBoolean'])
        ? $opts['returnBoolean']
        : false;

      $results = $this->parent->save($data, $updates);

      if (is_object($results[1]) && $results[1]->isAcknowledged())
      {
        $refresh = isset($opts['refresh']) ? $opts['refresh'] : true;
        if ($this->clear_on_update)
        {
          $this->modified_data = [];
        }
        if ($refresh)
        {
          $this->refresh();
          $results[2] = $this->to_bson($opts);
        }
        if (isset($opts['onUpdated']) && is_callable($opts['onUpdated']))
        {
          call_user_func($opts['onUpdated'], $results, $data, $updates);
        }
        $success = true;
      }
      else
      {
        $success = false;
      }

      if ($retBool)
      {
        return $success;
      }

      return $results;
    }
    else
    {
      throw new \Exception("Attempt to use saveUpdates() on a new document");
    }
  }

  /**
   * Refresh our data from the database.
   */
  public function refresh ($findopts=[])
  {
    $pk = $this->primary_key;
    $id = $this->data[$pk];
    $classopts = ['rawDocument'=>true];
    $data = $this->parent->getDocById($id, $findopts, $classopts);
    if (isset($data))
    {
      $this->data = $data;
      return true;
    }
    else
    {
      return false;
    }
  }

  /** 
   * Delete this item from the database.
   */
  public function delete ($retBool=null)
  {
    if (is_null($retBool))
    {
      $retBool = $this->delete_return_boolean;
    }
    $pk = $this->primary_key;
    $id = $this->data[$pk];
    $res = $this->parent->deleteId($id);

    if ($retBool)
    {
      return $this->parent->deleted($res);
    }

    return $res;
  }

  /**
   * Convert the data to a "flat" array.
   *
   * This only accounts for BSON stuff, so you'll need to override it
   * if you have secondary objects.
   */
  public function to_array ($opts=[])
  {
    if ($this->recursive_to_array && !isset($opts['recursive']))
    { // We want recursive mode.
      $opts['recursive'] = true;
    }
    return Util::toArray($this->data, $opts);
  }

} // end class Item

