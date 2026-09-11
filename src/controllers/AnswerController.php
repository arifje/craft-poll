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
use twentyfourhoursmedia\poll\Poll;
use twentyfourhoursmedia\poll\services\PollService;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Handles front-end poll submissions (`actions/poll/answer/submit`).
 *
 * @author    24hoursmedia
 * @package   Poll
 * @since     1.0.0
 */
class AnswerController extends Controller
{
    protected array|bool|int $allowAnonymous = ['submit'];

    /**
     * Message returned in json if user has already participated
     */
    protected string $alreadyParticipatedMsg = 'Already participated';

    /**
     * Stores a submitted answer.
     *
     * Responds with JSON (`{success: bool, message: string|null}`), or redirects when a
     * (hashed, see `redirectInput()`) `redirect` param is posted.
     *
     * @throws BadRequestHttpException
     */
    public function actionSubmit(): Response
    {
        $this->requirePostRequest();

        $service = Poll::$plugin->pollService;
        $request = $this->request;

        $param = static fn(string $configKey): string => trim((string)$request->getBodyParam($service->getConfigOption($configKey)));
        $pollId = $param(PollService::CFG_FORM_POLLID_FIELDNAME);
        $pollUid = $param(PollService::CFG_FORM_POLLUID_FIELDNAME);
        $siteId = $param(PollService::CFG_FORM_SITEID_FIELDNAME);
        $siteUid = $param(PollService::CFG_FORM_SITEUID_FIELDNAME);
        $answerFieldId = $param(PollService::CFG_FORM_ANSWERFIELDID_FIELDNAME);
        $answerFieldUid = $param(PollService::CFG_FORM_ANSWERFIELDUID_FIELDNAME);

        if ($pollId === '' || $pollUid === '' || $siteId === '' || $siteUid === '' || $answerFieldId === '') {
            throw new BadRequestHttpException('Missing poll identifiers');
        }

        // get and validate the site
        $site = Craft::$app->getSites()->getSiteById((int)$siteId);
        if (!$site || $site->uid !== $siteUid) {
            throw new BadRequestHttpException('Invalid site');
        }

        // get and validate the answer field that contained the answers (i.e. the Matrix field)
        $answerField = Craft::$app->getFields()->getFieldById((int)$answerFieldId);
        if (!$answerField || $answerField->uid !== $answerFieldUid) {
            throw new BadRequestHttpException('Invalid answer field');
        }

        // get and validate the poll (only enabled polls can be submitted)
        $poll = $service->getPoll($pollId, 'enabled', (int)$siteId);
        if (!$poll || $poll->uid !== $pollUid) {
            throw new BadRequestHttpException('Poll disabled or invalid');
        }

        $message = null;
        if (!$service->hasParticipated($poll)) {
            // the selected answer uid
            $submittedAnswers = $request->getBodyParam($service->getConfigOption(PollService::CFG_FORM_POLLANSWER_FIELDNAME));
            $selectedAnswerUids = array_filter([
                is_array($submittedAnswers) ? ($submittedAnswers[$poll->uid] ?? null) : null,
            ]);

            // optional free text for the submitted answer
            $submittedAnswerTexts = $request->getBodyParam($service->getConfigOption(PollService::CFG_FORM_POLLANSWERTEXT_FIELDNAME));
            $answerTexts = is_array($submittedAnswerTexts) ? ($submittedAnswerTexts[$poll->uid] ?? []) : [];

            $success = $service->submit($poll, (int)$siteId, (int)$answerFieldId, $selectedAnswerUids, is_array($answerTexts) ? $answerTexts : []);
        } else {
            $success = false;
            $message = $this->alreadyParticipatedMsg;
        }

        if ($request->getParam('redirect') !== null) {
            return $this->redirectToPostedUrl();
        }

        return $this->asJson([
            'success' => $success,
            'message' => $message,
        ]);
    }
}
