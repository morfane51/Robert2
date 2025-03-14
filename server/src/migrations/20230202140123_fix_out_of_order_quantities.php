<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Robert2\API\Config\Config;

final class FixOutOfOrderQuantities extends AbstractMigration
{
    public function up(): void
    {
        $prefix = Config::getSettings('db')['prefix'];

        $this->execute(sprintf(
            "UPDATE `%smaterials` SET `out_of_order_quantity` = NULL WHERE `is_unitary` = '1'",
            $prefix
        ));
    }

    public function down(): void
    {
        // -
    }
}
