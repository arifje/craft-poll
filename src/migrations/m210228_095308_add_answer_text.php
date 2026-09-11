<?php

namespace twentyfourhoursmedia\poll\migrations;

use craft\db\Migration;

/**
 * m210228_095308_add_answer_text migration.
 */
class m210228_095308_add_answer_text extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%poll_pollanswer}}', 'answerText')) {
            $this->addColumn('{{%poll_pollanswer}}', 'answerText', $this->mediumText()->null());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%poll_pollanswer}}', 'answerText')) {
            $this->dropColumn('{{%poll_pollanswer}}', 'answerText');
        }

        return true;
    }
}
