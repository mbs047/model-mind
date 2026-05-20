# Changelog

All notable changes to ModelMind will be documented in this file.

The format follows the spirit of Keep a Changelog, and this project uses semantic versioning.

## Unreleased

### Added

- Ranked question-aware retrieval with weighted columns, retrieval scores, fuzzy typo matching, and multilingual normalization.
- Optional Laravel Scout candidate retrieval before database ranking.
- Optional vector search extension through `ModelMindVectorSearcher` for embeddings or external vector indexes.
- Retrieval documentation for column weights, fuzzy settings, multilingual normalization, Scout, and vector searchers.

## v1.0.13 - 2026-05-21

### Added

- Built-in configuration presets for store, admin, support, docs, and CRM Laravel applications.
- `MODEL_MIND_PRESET` project marker and `model-mind:preset` Artisan command for listing, previewing, and exporting preset recommendations.
- Preset recommendations for model config, starter questions, retrieval settings, security settings, and route actions.
- Preset documentation with guidance for adapting each application type safely.

## v1.0.12 - 2026-05-21

### Added

- Headless JSON API mode with separate configurable API routes for custom React, Vue, Inertia, and mobile UIs.
- API manifest endpoint that exposes assistant labels, feature flags, limits, and endpoint URLs without rendering the Blade modal.
- `model-mind-api` rate limiter and API route configuration for stateless clients that carry `session_id`.
- Documentation and tests for the embeddable API contract.

## v1.0.11 - 2026-05-21

### Added

- Source citations for assistant answers with model label, record label, cited columns, and optional route action buttons.
- Source citation prompt tokens plus server-side token validation and cleanup.
- Citation inference for answers that clearly mention enabled records, including multilingual responses.
- Configurable citation labels, token name, max citation count, max cited columns, and source citation documentation.

## v1.0.10 - 2026-05-20

### Added

- Authorization and user-aware context with current user, guard, role, permission, and tenant prompt context.
- Per-model user and tenant query scoping plus Laravel Gate/policy checks before records enter AI context.
- Authorization callbacks and `HasModelMindContext::modelMindAuthorization()` for model-owned access rules.
- Route action authorization so tokens for records the current user cannot view are discarded.
- Documentation for admin, SaaS, customer portal, tenant, Gate, and callback authorization setups.

## v1.0.9 - 2026-05-20

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
