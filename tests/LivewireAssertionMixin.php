<?php

namespace Padmission\Tickets\Tests;

use Closure;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Assert;

class LivewireAssertionMixin
{
    protected function getMountedActionModalHtml(): Closure
    {
        return function (): string {
            $partialName = 'action-modals';
            $partials = data_get($this->lastState->getEffects(), 'partials', []);

            if (! array_key_exists($partialName, $partials)) {
                Assert::fail('No mounted action modal data found inside partials.');
            }

            return $partials[$partialName];
        };
    }

    public function assertMountedActionModalSee(): Closure
    {
        return function ($values, $escape = true) {
            $html = $this->getMountedActionModalHtml();

            foreach (Arr::wrap($values) as $value) {
                Assert::assertStringContainsString(
                    $escape ? e($value) : $value,
                    $html
                );
            }

            return $this;
        };
    }

    public function assertMountedActionModalDontSee(): Closure
    {
        return function ($values, $escape = true) {
            $html = $this->getMountedActionModalHtml();

            foreach (Arr::wrap($values) as $value) {
                Assert::assertStringNotContainsString(
                    $escape ? e($value) : $value,
                    $html
                );
            }

            return $this;
        };
    }

    public function assertMountedActionModalSeeHtml(): Closure
    {
        return function ($values) {
            $html = $this->getMountedActionModalHtml();

            foreach (Arr::wrap($values) as $value) {
                Assert::assertStringContainsString(
                    $value,
                    $html
                );
            }

            return $this;
        };
    }

    public function assertMountedActionModalDontSeeHtml(): Closure
    {
        return function ($values) {
            $html = $this->getMountedActionModalHtml();

            foreach (Arr::wrap($values) as $value) {
                Assert::assertStringNotContainsString(
                    $value,
                    $html
                );
            }

            return $this;
        };
    }
}
