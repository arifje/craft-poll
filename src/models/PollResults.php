<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\models;

use craft\base\Model;
use craft\elements\User;

/**
 * Results of a poll.
 */
class PollResults extends Model
{
    /**
     * @var int total number of votes
     */
    public $count = 0;

    /**
     * @var ResultByAnswer[]
     */
    public $byAnswer = [];

    /**
     * An array of user id's that have participated in the poll
     *
     * @var int[]
     */
    public $userIds = [];

    /**
     * List of users that have participated in the poll
     *
     * @var User[]
     */
    public $users = [];
}
