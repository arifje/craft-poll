<?php

namespace twentyfourhoursmedia\poll\migrations;

use craft\db\Migration;

/**
 * m200209_122402_add_indices migration.
 */
class m200209_122402_add_indices extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndexIfMissing('{{%poll_pollanswer}}', ['userId', 'pollId'], false);
        $this->createIndexIfMissing('{{%poll_pollanswer}}', ['dateCreated'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropIndexIfExists('{{%poll_pollanswer}}', ['dateCreated'], false);
        $this->dropIndexIfExists('{{%poll_pollanswer}}', ['userId', 'pollId'], false);

        return true;
    }
}
