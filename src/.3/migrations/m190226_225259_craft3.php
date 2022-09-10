<?php

namespace besteadfast\preparsefield\migrations;

use besteadfast\preparsefield\fields\PreparseFieldType;
use craft\db\Migration;
use craft\records\Field;

/**
 * Updates field instances to use the new field type class.
 */
class m220909_000000_craft4 extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update(Field::tableName(), ['type' => PreparseFieldType::class], ['type' => 'PreparseField_Preparse']);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190226_225259_craft3 cannot be reverted.\n";
        return false;
    }
}
