<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\migrations;

use craft\db\Migration;

/**
 * Poll Install Migration
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->createTables()) {
            $this->createIndexes();
            // Refresh the db schema caches
            $this->db->getSchema()->refresh();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%poll_pollanswer}}');

        return true;
    }

    /**
     * Creates the tables needed for the Records used by the plugin
     */
    protected function createTables(): bool
    {
        if ($this->db->tableExists('{{%poll_pollanswer}}')) {
            return false;
        }

        $this->createTable('{{%poll_pollanswer}}', [
            'id' => $this->primaryKey(),
            'dateCreated' => $this->dateTime()->notNull(),
            // custom fields
            'pollId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'fieldId' => $this->integer()->notNull(),
            'answerId' => $this->integer()->notNull(),
            'userId' => $this->integer()->null(),
            'ip' => $this->binary(16)->null(),
            'answerText' => $this->mediumText()->null(),
        ]);

        return true;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, '{{%poll_pollanswer}}', ['pollId', 'answerId'], false);
        $this->createIndex(null, '{{%poll_pollanswer}}', ['userId', 'pollId'], false);
        $this->createIndex(null, '{{%poll_pollanswer}}', ['dateCreated'], false);
    }
}
