<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\controllers;

use craft\web\Controller;
use twentyfourhoursmedia\poll\Poll;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Shows the results of a poll in the control panel.
 */
class CpResultController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionResult(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('accessPlugin-poll');

        $id = (int)$this->request->getQueryParam('id');
        if (!$id) {
            throw new BadRequestHttpException('Invalid id');
        }

        $facade = Poll::$plugin->facade;
        $poll = $facade->getPoll($id);
        if (!$poll) {
            throw new NotFoundHttpException('Poll not found');
        }

        return $this->renderTemplate('poll/_poll_results', [
            'poll' => $poll,
            'service' => $facade,
            'simple_results' => $facade->getResults($poll),
        ]);
    }
}
