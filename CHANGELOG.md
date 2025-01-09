# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.1.0] - 2025-01-09
### Changed
- Fleshed out the documentation in the ModelCommon trait.
- The `ModelCommon::populate_known_fields()` protected method can now get 
  the default value for known fields by using specifically named methods
  in the implementing class.

## [3.0.0] - 2024-01-10
### Added
- This changelog to track changes more explicitly.
### Changed
- Renamed `Lum\DB\Schemata` namespace to `Lum\DB\PDO\Schemata`.
- Moved `Lum\DB\PDO` into `lum-db-pdo` package.
- Moved `Lum\DB\Mongo` into `lum-db-mongo` package.

[Unreleased]: https://github.com/supernovus/lum.db.php/compare/v3.1.0...HEAD
[3.1.0]: https://github.com/supernovus/lum.db.php/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/supernovus/lum.db.php/releases/tag/v3.0.0

