# lum.db.php

## Summary

Database abstraction classes for PHP.

These have additional runtime requirements past those in the default
`require` property, depending on which libraries you intend to use.

See the sections below, or see `require-dev` for a full list.

## Classes

### Internal

These are used by the rest of the libraries, and generally aren't needed
in public code.

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\DB\Child            | Abstract class for ORM children.                  |
| Lum\DB\ModelCommon      | A trait for ORM model classes.                    |
| Lum\DB\ResultToArray    | A trait providing a `to_array()` method.          |

### PDO

| Class                      | Description                                    |
| -------------------------- | ---------------------------------------------- |
| Lum\DB\PDO\InsertArray     | A simplistic INSERT statement builder.         |
| Lum\DB\PDO\Item            | A class representing an Item in an ORM model.  |
| Lum\DB\PDO\Model           | An abstract class representing an ORM model.   |
| Lum\DB\PDO\Query           | An SQL Query builder class.                    |
| Lum\DB\PDO\ResultArray     | A result class that acts like an array.        |
| Lum\DB\PDO\ResultBag       | A quirky extension of ResultArray.             |
| Lum\DB\PDO\ResultSet       | A lazy loading result class. A good default.   |
| Lum\DB\PDO\Simple          | A simple PDO wrapper library.                  |
| Lum\DB\PDO\WhereArray      | A simplistic WHERE statement builder.          |
| Lum\DB\PDO\Simple\Alter    | A trait with  ALTER TABLE methods.             |
| Lum\DB\PDO\Simple\NativeDB | A trait for running engine-specific SQL files. |

### MongoDB

Classes for working with MongoDB via the `mongodb` extension (not the older
`mongo` extension) and the `mongodb/mongodb' library.

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\DB\Mongo\Item       | A class representing an Item in an ORM model.     |
| Lum\DB\Mongo\Model      | An abstract class representing an ORM model.      |
| Lum\DB\Mongo\Results    | A class representing a set of query results.      |
| Lum\DB\Mongo\Simple     | A simple MongoDB wrapper library.                 |

### Schemata

Classes for working with Schemata files. 

It's currently specific to PDO schemata, and uses it's own custom JSON-based
format for the schema definition files. It's really awkward, but is being used
in several production systems, so here it remains for the time being.

It's currently also limited to working with custom classes that extend the
`Lum\DB\PDO\Simple` class, and include the `Lum\DB\PDO\Simple\NativeDB` trait.

This entire set of classes is actually planned to be replaced by a new library
using a Yaml-based language called DIML (a cousin to RIML) to define the db
schemata instead of database-specific SQL files. When that happens, I will
drop these libraries from this package, and bump the major version number.

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\DB\Schemata\Tables  | A class representing a full set of table schemas. |
| Lum\DB\Schemata\Table   | An internal class representing a table schema.    |

## TODO

Write some tests, using the SQLite PDO driver.

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.db.php)
 * [Packageist](https://packagist.org/packages/lum/lum-db)

## Author

Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)
