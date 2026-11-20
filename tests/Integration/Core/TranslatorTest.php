<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Translator;

class TranslatorTest extends TestCase
{
    public function testLangDirPointsToJsonSourceInRepository(): void
    {
        $dir = realpath(Translator::langDir());

        $this->assertNotFalse($dir);
        $this->assertStringEndsWith('frontend/src/i18n', $dir);
        $this->assertFileExists($dir . '/de.json');
    }

    public function testTranslatesFromJsonFiles(): void
    {
        $translator = Translator::getInstance();
        $translator->setLocale('de');

        $this->assertSame('Turnier abmelden', $translator->translate('events.unregister'));
    }
}
