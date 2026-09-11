<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\utilities;

use Craft;
use craft\base\Utility;
use twentyfourhoursmedia\poll\models\SetupReport;
use twentyfourhoursmedia\poll\Poll;
use twentyfourhoursmedia\poll\services\PollService;
use yii\web\ForbiddenHttpException;

/**
 * Poll Utility: checks the setup (section, fields, entry types) and lets admins run it.
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 */
class PollUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('poll', 'Poll');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'poll-poll-utility';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return Craft::getAlias('@twentyfourhoursmedia/poll/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        if (!Craft::$app->getUser()->getIsAdmin()) {
            throw new ForbiddenHttpException('User is not permitted to perform this action.');
        }

        $allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $installService = Poll::getInstance()->installService;
        $pollService = Poll::getInstance()->pollService;
        $setupReport = new SetupReport();
        $setupOk = $installService->check($setupReport);

        return Craft::$app->getView()->renderTemplate(
            'poll/_components/utilities/PollUtility_content',
            [
                'allow_admin_changes' => $allowAdminChanges,
                'setup_ok' => $setupOk,
                'setup_report' => $setupReport,
                'selectPollFieldHandle' => $pollService->getConfigOption(PollService::CFG_FIELD_SELECT_POLL_HANDLE),
            ]
        );
    }
}
