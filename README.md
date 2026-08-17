# Voyti 2FA — Two-Factor Authentication for Voyti

The two-factor authentication base package for [Voyti](https://github.com/YiiRocks/voyti), the Yii3 user-management extension. It carries the whole 2FA subsystem: data, login-confirmation flow, settings screen, backup codes, and per-permission enforcement.

[![Packagist Version](https://img.shields.io/packagist/v/yiirocks/voyti-2fa.svg)](https://packagist.org/packages/yiirocks/voyti-2fa)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/yiirocks/voyti-2fa.svg)](https://php.net/)
[![Packagist](https://img.shields.io/packagist/dt/yiirocks/voyti-2fa.svg)](https://packagist.org/packages/yiirocks/voyti-2fa)
[![GitHub License](https://img.shields.io/github/license/yiirocks/voyti-2fa.svg)](https://github.com/yiirocks/voyti-2fa/blob/main/LICENSE.md)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/yiirocks/voyti-2fa/build.yml?branch=main)](https://github.com/yiirocks/voyti-2fa/actions)

Stats for Nerds

[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-2fa%2Fbadges%2Fcoverage.json)](https://github.com/yiirocks/voyti-2fa/tree/badges)
[![MSI](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-2fa%2Fbadges%2Fmsi.json)](https://github.com/yiirocks/voyti-2fa/tree/badges)
[![Tests](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-2fa%2Fbadges%2Ftests.json)](https://github.com/yiirocks/voyti-2fa/tree/badges)
[![Assertions](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-2fa%2Fbadges%2Fassertions.json)](https://github.com/yiirocks/voyti-2fa/tree/badges)

## Overview

Two-factor authentication is pluggable in Voyti: this base package provides the machinery, and each **method** (email, TOTP, WebAuthn) ships as its own small package on top of it. 2FA becomes active as soon as a method package is installed. It hooks into core's login flow through the `voyti.login-challenge` seam and auto-joins the enforcement middleware chain via the `voyti.enforce-middleware` tag.

## Installation

Do not install this package directly - on its own it provides no working 2FA. Install a **method** package instead and this base is pulled in automatically as a dependency:

- [`yiirocks/voyti-2fa-email`](https://github.com/YiiRocks/voyti-2fa-email) - emailed one-time code
- [`yiirocks/voyti-2fa-totp`](https://github.com/YiiRocks/voyti-2fa-totp) - authenticator-app TOTP
- [`yiirocks/voyti-2fa-webauthn`](https://github.com/YiiRocks/voyti-2fa-webauthn) - WebAuthn / passkeys

## Documentation

The complete reference guide is available at [Yii.Rocks](https://www.yii.rocks/voyti/two-factor/).
