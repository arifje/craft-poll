<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\records;

use craft\db\ActiveRecord;

/**
 * PollAnswer Record: one row per submitted answer.
 *
 * The table only has `dateCreated` of Craft's audit columns; Craft's `Command::insert()`
 * only fills in the audit columns that actually exist, so no custom insert logic is needed.
 *
 * @property int $id
 * @property string $dateCreated
 * @property int $pollId
 * @property int $siteId
 * @property int $fieldId
 * @property int $answerId
 * @property int|null $userId
 * @property string|null $ip
 * @property string|null $answerText
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 */
class PollAnswer extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%poll_pollanswer}}';
    }
}
