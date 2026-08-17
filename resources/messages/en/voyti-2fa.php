<?php

declare(strict_types=1);

return [
    'voyti-2fa.menu.two_factor' => 'Two-Factor Authentication',
    'voyti-2fa.security.two_factor_required' => 'Two-factor authentication is required for your account. Please enable it to continue.',
    'voyti-2fa.settings.two_factor_enabled' => 'Two-factor authentication has been enabled',
    'voyti-2fa.settings.two_factor_disabled' => 'Two-factor authentication has been disabled',
    'voyti-2fa.validator.invalid_verification_code' => 'Invalid verification code.',
    'voyti-2fa.view.two_factor.title' => 'Two-Factor Authentication',
    'voyti-2fa.view.two_factor.unavailable' => 'Two-factor authentication is currently unavailable because no authentication methods are installed. Please contact your administrator.',
    'voyti-2fa.view.two_factor.code_label' => 'Authentication Code',
    'voyti-2fa.view.two_factor.verify_button' => 'Verify',
    'voyti-2fa.view.two_factor.enabled_with_method' => 'Two-factor authentication via {method} is enabled',
    'voyti-2fa.view.two_factor.disable' => 'Disable',
    'voyti-2fa.view.two_factor.disable_confirm_intro' => 'To disable two-factor authentication, we need to verify it is really you. A verification code will be sent to you.',
    'voyti-2fa.view.two_factor.disable_send_code' => 'Send Code to Disable',
    'voyti-2fa.view.two_factor.enter_code' => 'Enter the verification code',
    'voyti-2fa.view.two_factor.enable' => 'Enable',
    'voyti-2fa.view.two_factor.loading' => 'Loading…',
    'voyti-2fa.view.two_factor.backup_codes_title' => 'Backup Codes',
    'voyti-2fa.view.two_factor.backup_codes_intro' => 'Save these one-time backup codes somewhere safe. Each can be used once to sign in if you lose access to your authenticator or email.',
    'voyti-2fa.view.two_factor.backup_codes_continue' => 'Continue',
    'voyti-2fa.view.two_factor.backup_code_hint' => 'Lost access to your device or email? You can enter one of your backup codes instead.',
    'voyti-2fa.view.two_factor.regenerate_backup_codes' => 'Regenerate Backup Codes',
    'voyti-2fa.view.two_factor.regenerate_backup_codes_intro' => 'Generating a new set of backup codes invalidates all existing ones. Enter your current verification code or a backup code to confirm.',
    'voyti-2fa.view.two_factor.no_backup_codes_remaining' => 'You have no backup codes remaining. Regenerate a new set to make sure you can still recover access if you lose your device.',
];
