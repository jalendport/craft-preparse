<?php

namespace besteadfast\preparsefield\migrations;

use besteadfast\preparsefield\fields\PreparseFieldType;
use craft\db\Migration;

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
        $this->update('{{%fields}}', ['type' => PreparseFieldType::class], ['type' => 'PreparseField_Preparse']);
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
