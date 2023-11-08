# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0
### Added
- Support for `symfony/property-access ^6.0`
- Requires `doctrine/annotations ^2.0`

### Dropped
- Support for PHP version lower than `7.3`
- Support for `phpunit/phpunit ^6.0`
- Support for `symfony/property-access ^2.8|^3.0|^4.0`

## 1.3.0
### Added
- Support for PHP 8.

### Changed
- Bumped `phpunit/phpunit` to `^9.0`
- Bumped `psr/log` to `^2.0`

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
