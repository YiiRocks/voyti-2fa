# Yii3 Voyti 2FA Changelog

## 1.0.2 under development

- New: Add `voyti:2fa:disable` console command to disable two-factor authentication for a user.
- Bug: Avoid double-dispatching `BeforeLoginEvent` by calling `LoginCompletionService::finalize()` instead of `::complete()` when completing a 2FA-confirmed login.

## 1.0.1 - August 21, 2026

- Chg: Consolidate Bootstrap5 views into `yiirocks/voyti-views-bootstrap5` package.

## 1.0.0 - August 20, 2026

- Initial release.
