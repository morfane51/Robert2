<?php
declare(strict_types=1);

namespace Robert2\Tests;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Robert2\API\Errors\Exception\ValidationException;
use Robert2\API\Models\Beneficiary;

final class BeneficiaryTest extends TestCase
{
    public function testSetSearch(): void
    {
        // - Prénom
        $result = (new Beneficiary)->setSearch('cli')->getAll()->get();
        $this->assertEquals(1, $result->count());
        $this->assertEquals(['Client Benef'], $result->pluck('full_name')->all());

        // - Prénom nom
        $result = (new Beneficiary)->setSearch('client ben')->getAll()->get();
        $this->assertEquals(1, $result->count());
        $this->assertEquals(['Client Benef'], $result->pluck('full_name')->all());

        // - Nom Prénom
        $result = (new Beneficiary)->setSearch('fountain jean')->getAll()->get();
        $this->assertEquals(1, $result->count());
        $this->assertEquals(['Jean Fountain'], $result->pluck('full_name')->all());

        // - Email
        $result = (new Beneficiary)->setSearch('@robertmanager.net')->getAll()->get();
        $this->assertEquals(2, $result->count());
        $this->assertEquals(['Jean Fountain', 'Roger Rabbit'], $result->pluck('full_name')->all());

        // - Référence
        $result = (new Beneficiary)->setSearch('0001')->getAll()->get();
        $this->assertEquals(1, $result->count());
        $this->assertEquals(['Jean Fountain'], $result->pluck('full_name')->all());

        // - Société
        $result = (new Beneficiary)->setSearch('Testing')->getAll()->get();
        $this->assertEquals(1, $result->count());
        $this->assertEquals(['Jean Fountain'], $result->pluck('full_name')->all());
    }

    public function testUnserialize(): void
    {
        $result = Beneficiary::unserialize([
            'first_name' => 'Roger',
            'last_name' => 'Rabbit',
            'reference' => '0004',
            'email' => 'tester2@robertmanager.net',
            'phone' => null,
            'street' => null,
            'postal_code' => null,
            'locality' => null,
            'country_id' => null,
            'pseudo' => 'robbie',
            'password' => '123abc',
            'note' => null,
        ]);
        $expected = [
            'reference' => '0004',
            'note' => null,
            'person' => [
                'first_name' => 'Roger',
                'last_name' => 'Rabbit',
                'email' => 'tester2@robertmanager.net',
                'phone' => null,
                'street' => null,
                'postal_code' => null,
                'locality' => null,
                'country_id' => null,
            ],
            'user' => [
                'email' => 'tester2@robertmanager.net',
                'pseudo' => 'robbie',
                'password' => '123abc',
            ],
        ];
        $this->assertEquals($expected, $result);

        // - Supprime les données liées à la personne qui ne sont pas attendues.
        $result = Beneficiary::unserialize([
            'first_name' => 'Roger',
            'last_name' => 'Rabbit',
            'reference' => '0004',
            'person_id' => 2,
            'person' => [
                'phone' => null,
                'street' => null,
                'postal_code' => null,
                'locality' => null,
                'country_id' => null,
            ],
        ]);
        $expected = [
            'reference' => '0004',
            'person' => [
                'first_name' => 'Roger',
                'last_name' => 'Rabbit',
            ],
        ];
        $this->assertEquals($expected, $result);

        // - Cas ou les données de la personne sont manquantes.
        $result = Beneficiary::unserialize(['reference' => '0010']);
        $this->assertEquals(['reference' => '0010'], $result);

        // - Cas ou il n'y a pas de données.
        $this->assertEquals([], Beneficiary::unserialize([]));
    }

    public function testCreateWithoutData(): void
    {
        $this->expectException(ValidationException::class);
        Beneficiary::new([]);
    }

    public function testBadData(): void
    {
        $this->expectException(ValidationException::class);
        Beneficiary::new(['pseudo' => 'Sans email!']);
    }

    public function testBadDataDuplicateRef(): void
    {
        $this->expectException(ValidationException::class);
        Beneficiary::new([
            'first_name' => 'Paul',
            'last_name' => 'Newtests',
            'email' => 'paul@tests.new',
            'reference' => '0005',
        ]);
    }

    public function testCreateWithoutPerson(): void
    {
        $this->expectException(ValidationException::class);
        Beneficiary::new(['reference' => '0009']);
    }

    public function testCreate(): void
    {
        Carbon::setTestNow(Carbon::create(2023, 2, 10, 15, 00, 00));

        $data = [
            'note' => null,
            'person' => [
                'first_name' => 'José',
                'last_name' => 'Gatillon',
                'email' => 'test@other-benef.net',
                'phone' => null,
                'street' => null,
                'postal_code' => '74000',
                'locality' => 'Annecy',
                'country_id' => 2,
            ],
        ];

        $result = Beneficiary::new($data)->append('user')->toArray();
        $expected = [
            'id' => 5,
            'reference' => null,
            'person_id' => 8,
            'company_id' => null,
            'can_make_reservation' => false,
            'note' => null,
            'created_at' => '2023-02-10 15:00:00',
            'updated_at' => '2023-02-10 15:00:00',
            'deleted_at' => null,
            'first_name' => 'José',
            'last_name' => 'Gatillon',
            'full_name' => 'José Gatillon',
            'email' => 'test@other-benef.net',
            'phone' => null,
            'street' => null,
            'postal_code' => '74000',
            'locality' => 'Annecy',
            'full_address' => '74000 Annecy',
            'company' => null,
            'country' => [
                'id' => 2,
                'name' => 'Suisse',
                'code' => 'CH',
                'created_at' => '2021-02-12 13:21:02',
                'updated_at' => '2021-02-12 13:21:02',
                'deleted_at' => null,
            ],
            'country_id' => 2,
            'user_id' => null,
            'user' => null,
            'person' => [
                'id' => 8,
                'user_id' => null,
                'first_name' => 'José',
                'last_name' => 'Gatillon',
                'email' => 'test@other-benef.net',
                'phone' => null,
                'street' => null,
                'postal_code' => '74000',
                'locality' => 'Annecy',
                'country_id' => 2,
                'created_at' => '2023-02-10 15:00:00',
                'updated_at' => '2023-02-10 15:00:00',
                'full_name' => 'José Gatillon',
                'full_address' => '74000 Annecy',
                'country' => [
                    'id' => 2,
                    'name' => 'Suisse',
                    'code' => 'CH',
                    'created_at' => '2021-02-12 13:21:02',
                    'updated_at' => '2021-02-12 13:21:02',
                    'deleted_at' => null,
                ],
                'user' => null,
            ],
        ];
        $this->assertSame($expected, $result);
    }

    public function testUpdateNotFound(): void
    {
        $this->expectException(ModelNotFoundException::class);
        Beneficiary::staticEdit(999, []);
    }

    public function testUpdate(): void
    {
        $result = Beneficiary::staticEdit(2, ['note' => "Très bon client."]);
        $this->assertEquals("Très bon client.", $result->note);

        // - Test update avec des données de "Person"
        $data = ['person' => ['first_name' => 'Jessica']];
        $result = Beneficiary::staticEdit(2, $data);
        $this->assertEquals('Jessica Rabbit', $result->person->full_name);
    }
}
