<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\FormCheckbox;
use PHPUnit\Framework\TestCase;

final class FormCheckboxTest extends TestCase
{
    public function testUncheckedWhenKeyMissing(): void
    {
        $this->assertFalse(FormCheckbox::isChecked([], 'est_hors_serie'));
    }

    public function testCheckedWhenValueIsOne(): void
    {
        $this->assertTrue(FormCheckbox::isChecked(['est_hors_serie' => '1'], 'est_hors_serie'));
    }

    public function testUncheckedWhenHiddenZeroWasSent(): void
    {
        $this->assertFalse(FormCheckbox::isChecked(['est_hors_serie' => '0'], 'est_hors_serie'));
    }

    public function testCheckedWhenDuplicateValuesEndWithOne(): void
    {
        $this->assertTrue(FormCheckbox::isChecked(['est_hors_serie' => ['0', '1']], 'est_hors_serie'));
    }
}
