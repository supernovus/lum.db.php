<?php

namespace Lum\DB;

/**
 * A trait providing common methods for database model classes.
 */
trait ModelCommon
{
  /**
   * Override this in your model classes.
   * It's a list of fields we know about.
   * DO NOT set the primary key in here!
   */
  public $known_fields;
                      
  /**
   * Check and see if a field is known.
   *
   * @param string $field Field name to look for.
   * @return bool
   */
  public function is_known ($field)
  {
    // First check to see if it is the primary key.
    if ($field == $this->primary_key)
    {
      return True;
    }

    if (isset($this->known_fields) && is_array($this->known_fields))
    {
      // Next look through our known_fields.
      foreach ($this->known_fields as $key => $val)
      {
        if (is_numeric($key))
        {
          $name = $val;
        }
        else
        {
          $name = $key;
        }

        if ($field == $name)
        {
          return True;
        }
      }
    }

    return False;
  }

  /**
   * Used by model classes to populate default data for known fields.
   *
   * @param array $data Known fields (with optional default values)
   *
   * If an item has a numeric key, then the value MUST be a string that
   * will be used as the field name. In this case the default value will
   * be taken from `$this->default_value` or `null` if that property does
   * not exist in the class.
   *
   * If an item has a non-numeric string key, then the key will be used
   * as the field name, and the value will be used as the default value.
   *
   * For each item, we will check for an optional getter method named:
   * `get_default_{$field_name}`; If found it will be called to get the
   * actual default value to use. It will be passed two arguments:
   * - The initial default value determined by the above logic.
   * - The $data array itself.
   *
   * @return array The $data after processing known fields
   */
  protected function populate_known_fields ($data)
  {
    if (isset($this->known_fields) && is_array($this->known_fields))
    {
      foreach ($this->known_fields as $key => $val)
      {
        if (is_numeric($key))
        { // ['field1', 'field2', ...]
          $field   = $val;
          $default = $this->default_value ?? null;
        }
        else
        { // ['field1' => $default, ...]
          $field   = $key;
          $default = $val;
        }

        if (array_key_exists($field, $data))
        { // Field was found, next!
          continue;
        }

        $meth = "get_default_$field";
        if (is_callable([$this, $meth]))
        { // A method to get the actual default value
          $default = $this->{$meth}($default, $val);
        }

        $data[$field] = $default;
      }
    }
    return $data;
  }

}

