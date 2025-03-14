<?php
declare(strict_types=1);

namespace Robert2\Tests;

use Robert2\API\Errors\Exception\ValidationException;
use Robert2\API\Models\Park;

final class ParkTest extends TestCase
{
    public function testGetTotalItems(): void
    {
        $Park = Park::find(1);
        $this->assertEquals(6, $Park->total_items);
    }

    public function testGetTotalAmount(): void
    {
        $Park = Park::find(1);
        $this->assertEquals(101223.80, $Park->total_amount);
    }

    public function testRemoveNotEmptyPark(): void
    {
        $this->expectException(\LogicException::class);
        Park::findOrFail(1)->delete();
    }

    public function testRemoveEmptyPark(): void
    {
        $newPark = Park::create(['name' => 'Vide et éphémère']);
        $isDeleted = Park::findOrFail($newPark->id)->delete();
        $this->assertTrue($isDeleted);
        $this->assertNull(Park::find($newPark->id));
    }

    public function testCreateParkDuplicate(): void
    {
        $this->expectException(ValidationException::class);
        Park::new(['name' => 'default']);
    }
}
