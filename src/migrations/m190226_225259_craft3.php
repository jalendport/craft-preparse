<?php

namespace aelvan\preparsefield\migrations;

use aelvan\preparsefield\fields\PreparseFieldType;
use craft\db\Migration;
use craft\db\Table;

/**
 * m190226_225259_craft3 migration.
 */
class m190226_225259_craft3 extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update(Table::FIELDS, ['type' => PreparseFieldType::class], ['type' => 'PreparseField_Preparse']);
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
