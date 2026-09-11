<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\models;

use craft\elements\Entry;
use craft\elements\User;

/**
 * Results for a single answer of a poll.
 */
class ResultByAnswer
{
    /**
     * @var int number of votes for this answer
     */
    public $count = 0;

    /**
     * @var float|null percentage of the total votes, null if there are no votes yet
     */
    public $percent = null;

    /**
     * @var Entry the answer entry (nested Matrix entry)
     */
    public $answer;

    /**
     * An array of user id's that have voted for this answer
     *
     * @var int[]
     */
    public $userIds = [];

    /**
     * List of users that have voted for this answer
     *
     * @var User[]
     */
    public $users = [];
}
