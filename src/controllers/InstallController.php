<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\controllers;

use Craft;
use craft\web\Controller;
use twentyfourhoursmedia\poll\models\SetupReport;
use twentyfourhoursmedia\poll\Poll;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Runs the setup (creates the section, fields and entry types) from the Poll utility.
 */
class InstallController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * Handles the setup form of the utility, e.g.: actions/poll/install/setup
     *
     * @throws ForbiddenHttpException
     * @throws \Throwable
     */
    public function actionSetup(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAdmin(false);

        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException('Administrative changes are not allowed in this environment.');
        }

        $report = new SetupReport();
        $success = Poll::getInstance()->installService->setup($report);

        if ($success) {
            $this->setSuccessFlash(Craft::t('poll', 'Poll setup completed.'));
        } else {
            $this->setFailFlash(Craft::t('poll', 'Poll setup did not complete, see the report for details.'));
        }

        return $this->redirectToPostedUrl();
    }
}
