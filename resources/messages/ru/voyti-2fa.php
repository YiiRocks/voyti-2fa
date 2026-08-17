<?php

declare(strict_types=1);

return [
    'voyti-2fa.menu.two_factor' => 'Двухфакторная аутентификация',
    'voyti-2fa.security.two_factor_required' => 'Для вашей учётной записи требуется двухфакторная аутентификация. Пожалуйста, включите её, чтобы продолжить.',
    'voyti-2fa.settings.two_factor_enabled' => 'Двухфакторная аутентификация включена',
    'voyti-2fa.settings.two_factor_disabled' => 'Двухфакторная аутентификация отключена',
    'voyti-2fa.validator.invalid_verification_code' => 'Неверный проверочный код.',
    'voyti-2fa.view.two_factor.title' => 'Двухфакторная аутентификация',
    'voyti-2fa.view.two_factor.unavailable' => 'Двухфакторная аутентификация сейчас недоступна, так как не установлены методы аутентификации. Обратитесь к администратору.',
    'voyti-2fa.view.two_factor.code_label' => 'Код аутентификации',
    'voyti-2fa.view.two_factor.verify_button' => 'Проверить',
    'voyti-2fa.view.two_factor.enabled_with_method' => 'Двухфакторная аутентификация по {method} включена',
    'voyti-2fa.view.two_factor.disable' => 'Отключить',
    'voyti-2fa.view.two_factor.disable_confirm_intro' => 'Чтобы отключить двухфакторную аутентификацию, нам нужно подтвердить, что это действительно вы. Вам будет отправлен код подтверждения.',
    'voyti-2fa.view.two_factor.disable_send_code' => 'Отправить код для отключения',
    'voyti-2fa.view.two_factor.enter_code' => 'Введите проверочный код',
    'voyti-2fa.view.two_factor.enable' => 'Включить',
    'voyti-2fa.view.two_factor.loading' => 'Загрузка…',
    'voyti-2fa.view.two_factor.backup_codes_title' => 'Резервные коды',
    'voyti-2fa.view.two_factor.backup_codes_intro' => 'Сохраните эти одноразовые резервные коды в надёжном месте. Каждый код можно использовать один раз для входа, если вы потеряете доступ к своему аутентификатору или почте.',
    'voyti-2fa.view.two_factor.backup_codes_continue' => 'Продолжить',
    'voyti-2fa.view.two_factor.backup_code_hint' => 'Потеряли доступ к устройству или почте? Вы можете ввести один из резервных кодов вместо обычного.',
    'voyti-2fa.view.two_factor.regenerate_backup_codes' => 'Обновить резервные коды',
    'voyti-2fa.view.two_factor.regenerate_backup_codes_intro' => 'При создании нового набора резервных кодов все существующие коды становятся недействительными. Введите текущий код подтверждения или резервный код, чтобы продолжить.',
    'voyti-2fa.view.two_factor.no_backup_codes_remaining' => 'У вас не осталось резервных кодов. Создайте новый набор, чтобы сохранить возможность восстановить доступ при потере устройства.',
];
