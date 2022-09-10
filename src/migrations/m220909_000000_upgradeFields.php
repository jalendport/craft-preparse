<?php
namespace besteadfast\preparsefield\migrations;

use besteadfast\preparsefield\PreparseField;
use craft\db\Migration;
use craft\records\Field;

/**
 * Updates field instances to use the new field type class.
 */
class m220909_000000_upgradeFields extends Migration
{

    public function safeUp()
    {
        $this->update(Field::tableName(), ['type' => PreparseField::class], ['type' => 'PreparseFieldType']);
    }

    public function safeDown()
    {
        throw new \Exception("m220909_000000_upgradeFields cannot be reverted.");
    }
}
