<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests;

use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\TwoFactor\tests\Support\VoytiConfigFactory;

/**
 * This package contributes its "Two-Factor" link to core's account-settings menu through the
 * `accountMenuItems` param (core merges it in under its own key). This verifies both ends of that
 * seam: the contributed config, and core's menu builder actually rendering it.
 */
final class AccountMenuContributionTest extends TestCase
{
    public function testContributesTwoFactorLinkToCoreAccountMenu(): void
    {
        /** @var array{'yiirocks/voyti': array{accountMenuItems: list<array{label: string, category: string, route: string}>}} $params */
        $params = require dirname(__DIR__) . '/config/params.php';
        $accountMenuItems = $params['yiirocks/voyti']['accountMenuItems'];

        self::assertSame(
            [['label' => 'voyti-2fa.menu.two_factor', 'category' => 'voyti-2fa', 'route' => 'voyti/user-two-factor']],
            $accountMenuItems,
        );

        // Feed the contributed items through core's account-menu builder to confirm the seam renders
        // the link with the translated label and the generated 2FA settings URL.
        $menu = MenuView::account(
            VoytiConfigFactory::create(accountMenuItems: $accountMenuItems),
            new FakeUrlGenerator(),
            $this->createTranslator(),
        );

        $twoFactorLink = null;
        foreach ($menu as $item) {
            if ($item['label'] === 'Two-Factor Authentication') {
                $twoFactorLink = $item;
                break;
            }
        }

        self::assertNotNull($twoFactorLink);
        self::assertSame('//voyti/user-two-factor', $twoFactorLink['url']);
    }
}
