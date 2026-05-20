# Changelog

All notable changes to ModelMind will be documented in this file.

The format follows the spirit of Keep a Changelog, and this project uses semantic versioning.

## Unreleased

### Added

- Documentation for multilingual answers from a single-language application database.

## v1.0.8 - 2026-05-20

### Added

- Configurable default questions through `assistant.default_questions` and `MODEL_MIND_DEFAULT_QUESTIONS`.
- Configurable session expiry through `memory.session_lifetime_minutes` and `MODEL_MIND_SESSION_LIFETIME_MINUTES`.
- Multilingual route action recovery when the assistant mentions configured records but does not copy exact route tokens.

### Changed

- Renamed the demo link to Market Lane Store in the README.

## v1.0.7 - 2026-05-20

### Added

- Simple and advanced documentation examples that combine the package's main configuration features.
- Dynamic named-route action labels through `label_column` and `label_template`.
- GitHub Sponsors funding metadata.

## v1.0.6 - 2026-05-20

### Added

- Public asset publishing for the default CSS and JavaScript through `model-mind:publish-assets`, the `model-mind-assets` tag, and `model-mind:install --assets`.
- Question-aware model retrieval so answers can include relevant enabled records that are outside the cached static context window.
- `model-mind:clear-context` command for clearing the cached application context after data or config changes.

### Changed

- Split the usage documentation into focused feature guides.

## v1.0.5 - 2026-05-20

### Added

- Configurable named-route actions so assistant answers can produce safe Laravel route buttons from approved route names and parameters.

## v1.0.4 - 2026-05-20

### Added

- GitHub issue templates, pull request template, CI workflow, Dependabot configuration, and community documentation.
- Package test suite with Orchestra Testbench.
- Configurable modal, styles, and scripts views.
- Custom modal design guide.
- Explicit `MODEL_MIND_THEME` support for the default light and dark design.

### Changed

- Simplified `README.md` into a starting guide and moved detailed documentation to `docs/`.
- Consolidated ModelMind database tables into one package migration file for fresh installs.
- Replaced feedback icon buttons with `Helpful` and `Not helpful` text buttons.

## v1.0.3

### Added

- README banner artwork.

## v1.0.2

### Added

- Learning memory from assistant answers, liked answers, manual text, and configured fed texts.
- Faster default provider and context settings.
- Configurable ModelMind table prefix.

## v1.0.1

### Added

- Configurable widget positions, width, offset, and z-index.

## v1.0.0

### Added

- Initial ModelMind package release.
- Blade chat modal, styles, and scripts.
- OpenAI Responses API provider.
- Configurable Eloquent model context.
- Safe column discovery and sensitive column filtering.
- Conversation sessions, messages, feedback, and context inspection command.
