<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Support;

use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;

/**
 * Builds a real CurrentRoute for tests instead of mocking the final class. Passing a name populates
 * the matched route so getName() returns it; passing none leaves getName() null.
 */
trait CurrentRouteTrait
{
    protected function createCurrentRoute(?string $name = null): CurrentRoute
    {
        $currentRoute = new CurrentRoute();

        if ($name !== null) {
            $currentRoute->setRouteWithArguments(Route::get('/')->name($name), []);
        }

        return $currentRoute;
    }
}
