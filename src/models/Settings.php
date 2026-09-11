<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\models;

use craft\base\Model;

/**
 * Poll Settings Model
 *
 * The handles can be overridden by placing a `poll.php` file inside the /config folder.
 * If any value is empty it reverts to the default.
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 */
class Settings extends Model
{
    /**
     * @var string handle of the section containing polls (default: pollSection)
     */
    public $sectionHandle = '';

    /**
     * @var string handle of the Entries field to select a poll (default: selectedPoll)
     */
    public $selectPollFieldHandle = '';

    /**
     * @var string handle of the Matrix field containing the answers (default: pollAnswerMatrix)
     */
    public $answerMatrixFieldHandle = '';

    /**
     * @var string handle of the entry type of the answers in the Matrix field (default: pollAnswer)
     */
    public $matrixBlockAnswerHandle = '';

    /**
     * Blocks removal of the plugin as a safety measure
     *
     * @var bool
     */
    public $blockPluginRemoval = true;

    /**
     * Max number of participations kept in cookies
     *
     * @var int
     */
    public $numCookieParticipations = 200;

    /**
     * Number of days to keep the participations in cookies
     *
     * @var int
     */
    public $participationsCookieLifetime = 1000;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['sectionHandle', 'selectPollFieldHandle', 'answerMatrixFieldHandle', 'matrixBlockAnswerHandle'], 'string'],
            [['blockPluginRemoval'], 'boolean'],
            [['numCookieParticipations'], 'integer', 'min' => 1, 'max' => 500],
            [['participationsCookieLifetime'], 'integer', 'min' => 1, 'max' => 2000],
        ];
    }
}
