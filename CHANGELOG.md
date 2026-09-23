# Changelog

All notable changes to this project will be documented in this file.

## [2.0.1] - 2026-07-25

### Changed
- Update README (TS-4)
- Push changes (TS-5)

## [2.1.0] - 2026-09-23

### Changed
- Dropped the `jenssegers/optimus` runtime dependency. That package requires
  `symfony/console ^5||^6||^7` for a CLI command this bundle never calls, which kept the
  bundle off Symfony 8 entirely. The two expressions it contributed at runtime now live in
  `Tokenizer`; `OptimusParityTest` pins them bit-for-bit against the real package, so tokens
  minted by earlier versions keep decoding to the same ids. `jenssegers/optimus` and
  `phpseclib/phpseclib` moved to `suggest`/`require-dev` — they are still needed by
  `app:tokenable:generate`, which says so when they are missing.
- `Tokenizer` now refuses to start on a 32-bit PHP build instead of silently producing wrong
  tokens, and exposes the 31-bit ceiling as `Tokenizer::MAX_ID`.

### Added
- `Tokenizer::getEntity()` decodes a token and loads the entity in one call.
- `TokenableValueResolver` now also resolves tokens from the query string, not just from
  route attributes.

### Fixed
- The route-to-entity mapping is no longer read from the warmed cache file while in debug.
  Symfony rebuilds its own generator as soon as a route changes, but that file is only
  rewritten by `cache:warmup`, so a route added since the last warmup kept its parameter
  unencoded — the entity then reached the inner generator and fatally failed in
  `preg_match()` against the route requirement.
- Fixed the profiler's Generated URLs panel missing tokens produced via the |token Twig filter. (TS-8)

