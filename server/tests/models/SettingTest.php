<?php
declare(strict_types=1);

namespace Robert2\Tests;

use Robert2\API\Models\Setting;
use Robert2\API\Errors\Exception\ValidationException;

final class SettingTest extends TestCase
{
    public function testGetList(): void
    {
        // - Si non spécifiée (ou `withSensitive = true`), les données sensibles doivent être présentes.
        $result = Setting::getList();
        $expected = [
            'eventSummary' => [
                'customText' => [
                    'title' => "Contrat",
                    'content' => "Un petit contrat de test.",
                ],
                'materialDisplayMode' => 'categories',
                'showLegalNumbers' => true,
            ],
            'calendar' => [
                'event' => [
                    'showLocation' => true,
                    'showBorrower' => false,
                ],
                'public' => [
                    'enabled' => true,
                    'uuid' => 'dfe7cd82-52b9-4c9b-aaed-033df210f23b',
                ],
            ],
            'returnInventory' => [
                'mode' => 'start-empty',
            ],
        ];
        $this->assertEquals($expected, $result);

        // - Si `withSensitive = false`, les données sensibles ne sont pas retournées.
        $result = Setting::getList(false);
        $expected = [
            'eventSummary' => [
                'customText' => [
                    'title' => "Contrat",
                    'content' => "Un petit contrat de test.",
                ],
                'materialDisplayMode' => 'categories',
                'showLegalNumbers' => true,
            ],
            'calendar' => [
                'event' => [
                    'showLocation' => true,
                    'showBorrower' => false,
                ],
                'public' => [
                    'enabled' => true,
                ],
            ],
            'returnInventory' => [
                'mode' => 'start-empty',
            ],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetAll(): void
    {
        $result = Setting::get()->toArray();
        $expected = [
            [
                'key' => 'calendar.event.showBorrower',
                'value' => false,
            ],
            [
                'key' => 'calendar.event.showLocation',
                'value' => true,
            ],
            [
                'key' => 'calendar.public.enabled',
                'value' => true,
            ],
            [
                'key' => 'calendar.public.uuid',
                'value' => 'dfe7cd82-52b9-4c9b-aaed-033df210f23b',
            ],
            [
                'key' => 'eventSummary.customText.content',
                'value' => "Un petit contrat de test.",
            ],
            [
                'key' => 'eventSummary.customText.title',
                'value' => "Contrat",
            ],
            [
                'key' => 'eventSummary.materialDisplayMode',
                'value' => 'categories',
            ],
            [
                'key' => 'eventSummary.showLegalNumbers',
                'value' => true,
            ],
            [
                'key' => 'returnInventory.mode',
                'value' => 'start-empty',
            ],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetWithKey(): void
    {
        $result = Setting::getWithKey('eventSummary.materialDisplayMode');
        $this->assertEquals('categories', $result);

        $result = Setting::getWithKey('eventSummary.customText.title');
        $this->assertEquals("Contrat", $result);

        $result = Setting::getWithKey('eventSummary.customText');
        $expected = ['title' => 'Contrat', 'content' => 'Un petit contrat de test.'];
        $this->assertEquals($expected, $result);

        $result = Setting::getWithKey('eventSummary');
        $expected = [
            'customText' => [
                'title' => 'Contrat',
                'content' => 'Un petit contrat de test.',
            ],
            'materialDisplayMode' => 'categories',
            'showLegalNumbers' => true,
        ];
        $this->assertEquals($expected, $result);

        $result = Setting::getWithKey('inexistant');
        $this->assertNull($result);
    }

    public function testUpdateBadKey(): void
    {
        $this->expectException(ValidationException::class);
        Setting::staticEdit(null, ['inexistant' => 'some-text']);
    }

    public function testUpdateBadValue(): void
    {
        $this->expectException(ValidationException::class);
        Setting::staticEdit(null, ['eventSummary.materialDisplayMode' => 'not-valid']);
    }

    public function testUpdate(): void
    {
        Setting::staticEdit(null, [
            'eventSummary.materialDisplayMode' => 'flat',
            'eventSummary.customText.title' => 'test',
            'eventSummary.customText.content' => null,
            'eventSummary.showLegalNumbers' => false,
            'calendar.event.showLocation' => false,
            'calendar.event.showBorrower' => true,
        ]);
        $expected = [
            'eventSummary' => [
                'customText' => [
                    'title' => 'test',
                    'content' => null,
                ],
                'materialDisplayMode' => 'flat',
                'showLegalNumbers' => false,
            ],
            'calendar' => [
                'event' => [
                    'showLocation' => false,
                    'showBorrower' => true,
                ],
                'public' => [
                    'enabled' => true,
                    'uuid' => 'dfe7cd82-52b9-4c9b-aaed-033df210f23b',
                ],
            ],
            'returnInventory' => [
                'mode' => 'start-empty',
            ],
        ];
        $this->assertEquals($expected, Setting::getList());
    }

    public function testReset(): void
    {
        // - Par défaut, le mode d'affichage du matériel est `sub-categories`.
        Setting::find('eventSummary.materialDisplayMode')->reset();
        $this->assertEquals('sub-categories', Setting::getWithKey('eventSummary.materialDisplayMode'));

        // - Par défaut, le calendrier public est désactivé.
        Setting::find('calendar.public.enabled')->reset();
        $this->assertEquals(false, Setting::getWithKey('calendar.public.enabled'));

        // - Par défaut, l'UUID de calendrier est un UUID aléatoire.
        Setting::find('calendar.public.uuid')->reset();
        $this->assertNotEquals('dfe7cd82-52b9-4c9b-aaed-033df210f23b', Setting::getWithKey('calendar.public.uuid'));

        // - Par défaut, le mode des inventaires de retour est 'start-empty'.
        Setting::find('returnInventory.mode')->reset();
        $this->assertEquals('start-empty', Setting::getWithKey('returnInventory.mode'));
    }

    public function testRemove(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Settings cannot be deleted.");
        Setting::findOrFail('calendar.public.enabled')->delete();
    }
}
