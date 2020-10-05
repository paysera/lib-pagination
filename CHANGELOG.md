# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.3.0
### Added
- `ConfiguredQuery` now providers new methods: `setQueryModifier`, `getQueryModifier`. Accepts `callable` type. Receives
the `Doctrine\ORM\Query` object - to allow modifying the query object.

## 1.2.0
### Added
- `ConfiguredQuery` provides new methods: `setItemTransformer`, `getItemTransformer`.
Accepts `callable` type. Receives result item - return value replaces received item in result.

## 1.1.0
### Added
- Support for datetime

## 1.0.0
### Fixed
- support single `group-by` statement in `ResultProvider:findCount` - this enables to properly get count of grouped statements. 
