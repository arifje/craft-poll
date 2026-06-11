<?php

namespace twentyfourhoursmedia\poll\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210228_095308_add_answer_text migration.
 */
class m210228_095308_add_answer_text extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->columnExists('{{%poll_pollanswer}}', 'answerText')) {
            return;
        }

        $this->addColumn(
            '{{%poll_pollanswer}}',
            'answerText',
            $this->mediumText()->null()
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        if (!$this->db->columnExists('{{%poll_pollanswer}}', 'answerText')) {
            return;
        }

        $this->dropColumn('{{%poll_pollanswer}}', 'answerText');
    }
}
