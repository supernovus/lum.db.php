<?php

namespace Lum\DB;

/**
 * Base child class for ORM style models.
 */
abstract class Child implements \ArrayAccess
{
  /**
   * The model instaqnce that spawned us.
   */
  public $parent;              

  /**
   * Do we want to auto-save changes?
   */
  public $auto_save = False;

  protected $data;             // The hash data returned from a query.
  protected $table;            // The database table we belong to.
  protected $modified_data;    // Fields we have modified.
  protected $save_value;       // Used in batch operations.

  protected $clear_on_update = true; // Clear modified_data on update()
  protected $clear_on_insert = true; // Clear modified_data on insert()

  protected $primary_key = 'id';

  protected $strict_keys = false; // Change to true to force strict keys.
  protected $warn_keys   = false; // If strict_keys is false, then warn?
  protected $null_keys   = false; // If strict_keys is false, return null?

  /** 
   * To make aliases to the database field names, override the $aliases
   * member in your sub-classes. It should be an associative array, where
   * the key is the alias, and the value is the target database field.
   * NOTE: Aliases are not known about by the Model or ResultSet objects, 
   * so functions like getRowByField and manual SELECT statements must use 
   * the real database field names, not aliases!
   */
  protected $aliases = array();

  /**
   * Assume the primary key is automatically generated by the database.
   */
  protected $auto_generated_pk = True;

  /**
   * A list of virtual properties. 
   * These must be accessed using _set_$name and _get_$name
   */
  protected $virtuals = [];

  // Build a new object.
  public function __construct ($opts, $parent=null, $table=null, $pk=null)
  { 
    $data = null;
    if (isset($opts, $opts['parent']) && !isset($parent))
    { // Using new-style constructor.
      $this->parent = $opts['parent'];
      if (isset($opts['data']))
        $data = $opts['data'];
      if (isset($opts['table']))
        $this->table = $opts['table'];
      if (isset($opts['pk']))
        $this->primary_key = $opts['pk'];
    }
    elseif (isset($opts, $parent))
    { // Using old-style constructor.
      $data = $opts;
      $opts = [];
      $this->parent = $parent;
      if (isset($table))
        $this->table = $table;
      if (isset($pk))
        $this->primary_key = $pk;
    }
    else
    {
      throw new \Exception("Invalid constructor for DB\Item");
    }

    if ($data === null || is_bool($data))
    {
      if (is_callable([$this, 'default_data']))
      {
        $data = $this->default_data($opts);
      }
      else
      {
        $data = [];
      }
    }

    if (is_callable([$this, 'init_data']))
    {
      $data = $this->init_data($data, $opts);
    }

    $this->data = $data;
    $this->modified_data = [];
  }

  /** 
   * Look for a field
   *
   * If the field exists in the database, it's returned unchanged.
   *
   * If an alias exists, the alias target field will be returned.
   *
   * If neither exists, and the field is not the primary key,
   * an exception will be thrown.
   */
  protected function db_field ($name, $strict=null)
  {
    if (is_array($this->data) && array_key_exists($name, $this->data))
      return $name;
    elseif (is_object($this->data) && isset($this->data->$name))
      return $name;
    elseif (array_key_exists($name, $this->aliases))
      return $this->aliases[$name];
    elseif ($name == $this->primary_key)
      return $name;
    elseif ($this->parent->is_known($name))
      return $name;
    elseif (in_array($name, $this->virtuals))
      return $name;
    else
    {
      if (!isset($strict))
        $strict = $this->strict_keys;
      $message = "Unknown field '$name' in ".json_encode($this->data);
      if ($strict)
      {
        throw new \Exception($message);
      }
      else
      {
        if ($this->warn_keys)
          error_log($message);
        if ($this->null_keys)
          return Null;
        else
          return $name;
      }
    }
  }

  /**
   * Get a list of column names.
   */
  public function column_names ()
  {
    if (is_array($this->data))
    {
      return array_keys($this->data);
    }
    elseif (is_object($this->data))
    {
      if (is_callable([$this->data, 'keys']))
      {
        return $this->data->keys();
      }
      elseif (is_callable([$this->data, 'getArrayCopy']))
      {
        return array_keys($this->data->getArrayCopy());
      }
      elseif (is_callable([$this->data, 'to_array']))
      {
        return array_keys($this->data->to_array());
      }
    }
  }

  /** 
   * Set a database field.
   *
   * Unless $auto_generated_pk is set to False, this will throw an
   * exception if you try to set the value of the primary key.
   */
  public function __set ($field, $value)
  {
    $name = $this->db_field($field);

    if ($name == $this->primary_key)
    {
      if ($this->auto_generated_pk)
      {
        throw new \Exception('Cannot overwrite primary key.');
      }
      elseif (!isset($this->data[$name]))
      {
        $this->data[$name] = true; // a defined initial value.
      }
    }

    if (!in_array($name, $this->virtuals))
    {
      $this->setModified($name, true);
    }

    $meth = "_set_$field";
    if (is_callable(array($this, $meth)))
    {
      $this->$meth($value);
    }
    else
    {
      $this->data[$name] = $value;
    }
    if ($this->auto_save)
    {
      $this->save();
    }
  }

  /**
   * Mark a field as modified.
   */
  public function setModified ($field, $fieldIsName=false)
  {
    if ($fieldIsName)
      $name = $field;
    else
      $name = $this->db_field($field);

    $modval = null;
    if (isset($this->data[$name]))
      $modval = $this->data[$name];
    $this->modified_data[$name] = $modval;
  }

  /** 
   * Restore the previous value (we only store one.)
   * Does not work with auto_save turned on.
   */
  public function restore ($name)
  {
    $name = $this->db_field($name);
    if (isset($this->modified_data[$name]))
    {
      $this->data[$name] = $this->modified_data[$name];
      unset($this->modified_data[$name]);
    }
  }

  /** 
   * Undo all modifications.
   * Does not work with auto_save turned on.
   */
  public function undo ()
  {
    foreach ($this->modified_data as $name => $value)
    {
      $this->data[$name] = $value;
    }
    $this->modified_data = [];
  }

  /** 
   * Get a database field.
   */
  public function __get ($field)
  {
    $name = $this->db_field($field);
    $meth = "_get_$field";
    if (is_callable(array($this, $meth)))
    {
      return $this->$meth();
    }
    else
    {
      return $this->data[$name];
    }
  }

  /** 
   * See if a database field is set.
   * 
   * For the purposes of this method, '' is considered unset.
   */
  public function __isset ($name)
  {
    $meth = "_isset_$name";
    if (is_callable([$this, $meth]))
    {
      return $this->$meth();
    }
    $name = $this->db_field($name, False);
    if (isset($name))
      return (isset($this->data[$name]) && $this->data[$name] != '');
    else
      return False;
  }

  /** 
   * Sets a field to null.
   */
  public function __unset ($name)
  {
    $meth = "_unset_$name";
    if (is_callable([$this, $meth]))
    {
      return $this->$meth();
    }
    $name = $this->db_field($name);
    $this->modified_data[$name] = $this->data[$name];
    $this->data[$name] = null;
    if ($this->auto_save)
    {
      $this->save();
    }
  }

  /**
   * ArrayAccess interface, alias to __isset().
   */
  public function offsetExists ($name): bool
  {
    return $this->__isset($name);
  }

  /**
   * ArrayAccess interface, alias to __set().
   */
  public function offsetSet ($name, $value): void
  {
    return $this->__set($name, $value);
  }

  /**
   * ArrayAccess interface, alias to __unset().
   */
  public function offsetUnset ($name): void
  {
    return $this->__unset($name);
  }

  /**
   * ArrayAccess interface, alias to __get().
   */
  public function offsetGet ($name): mixed
  {
    return $this->__get($name);
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
  abstract public function save ($opts=[]);

  /** 
   * Delete this item from the database.
   */
  abstract public function delete ();

  /** 
   * Start a batch operation. 
   *
   * We disable the 'auto_save' feature, saving its value for later.
   */
  public function start_batch ()
  {
    $this->save_value = $this->auto_save;
    $this->auto_save = False;
  }

  /** 
   * Finish a batch operation.
   *
   * We restore the auto_save value, and if it was true, save the data.
   */
  public function end_batch ()
  {
    $this->auto_save = $this->save_value;
    if ($this->auto_save)
    {
      $this->save();
    }
  }

  /** 
   * Cancel a batch operation. 
   *
   * We run $this->undo() and then restore the auto_save value.
   */
  public function cancel_batch ()
  {
    $this->undo();
    $this->auto_save = $this->save_value;
  }

} // end class Child

