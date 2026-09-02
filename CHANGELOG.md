# Yii3 Voyti 2FA Changelog

## 1.0.4 under development

## 1.0.3 - September 2, 2026

- Enh: Count failed two-factor login verifications through the existing authentication event, add atomic email-code attempt and expiry tracking, and group method routes under `2fa.methodRoutes`.

## 1.0.2 - August 30, 2026

- New: Add `voyti:2fa:disable` console command to disable two-factor authentication for a user.
- Bug: Avoid double-dispatching `BeforeLoginEvent` by calling `LoginCompletionService::finalize()` instead of `::complete()` when completing a 2FA-confirmed login.

## 1.0.1 - August 21, 2026

- Chg: Consolidate Bootstrap5 views into `yiirocks/voyti-views-bootstrap5` package.

## 1.0.0 - August 20, 2026

- Initial release.
