<?php
declare(strict_types=1);

use Cake\Database\Expression\QueryExpression;
use Illuminate\Support\Carbon;
use Phinx\Migration\AbstractMigration;
use Robert2\API\Config\Config;

final class ChangeReturnQuantityColumnOfEventMaterials extends AbstractMigration
{
    public function up(): void
    {
        /**
         * Cette migration permet de savoir quand un inventaire de retour a été
         * commencé ; en mettant les champs suivants à null par défaut, on peut
         * savoir si une sauvegarde a été effectuée quand le champ n'a plus une
         * valeur strictement `null`.
         */
        $table = $this->table('event_materials');
        $table
            ->changeColumn('quantity_returned', 'integer', [
                'signed' => false,
                'null' => true,
                'default' => null,
            ])
            ->changeColumn('quantity_returned_broken', 'integer', [
                'signed' => false,
                'null' => true,
                'default' => null,
            ])
            ->update();

        $prefix = Config::getSettings('db')['prefix'];

        $this->getQueryBuilder()
            ->update(sprintf('%sevent_materials', $prefix))
            ->set([
                'quantity_returned' => null,
                'quantity_returned_broken' => null,
            ])
            ->where(function (QueryExpression $expression) use ($prefix) {
                $eventsIds = $this->getQueryBuilder()
                    ->select('id')
                    ->from(sprintf('%sevents', $prefix))
                    ->where(function (QueryExpression $subExpression) {
                        return $subExpression->gt('start_date', Carbon::today());
                    });
                return $expression->in('event_id', $eventsIds);
            })
            ->where(['quantity_returned' => 0])
            ->execute();
    }

    public function down(): void
    {
        $prefix = Config::getSettings('db')['prefix'];

        $this->getQueryBuilder()
            ->update(sprintf('%sevent_materials', $prefix))
            ->set(['quantity_returned' => '0'])
            ->where(['quantity_returned IS' => null])
            ->execute();

        $this->getQueryBuilder()
            ->update(sprintf('%sevent_materials', $prefix))
            ->set(['quantity_returned_broken' => '0'])
            ->where(['quantity_returned_broken IS' => null])
            ->execute();

        $table = $this->table('event_materials');
        $table
            ->changeColumn('quantity_returned', 'integer', [
                'signed' => false,
                'null' => false,
                'default' => 0,
            ])
            ->changeColumn('quantity_returned_broken', 'integer', [
                'signed' => false,
                'null' => false,
                'default' => 0,
            ])
            ->update();
    }
}
