<?php
/**
 * Poll plugin for Craft CMS 5.x
 *
 * @link      https://www.24hoursmedia.com
 * @copyright Copyright (c) 2020 24hoursmedia
 */

namespace twentyfourhoursmedia\poll\controllers;

use craft\elements\Entry;
use craft\web\Controller;
use twentyfourhoursmedia\poll\helper\CsvHelper;
use twentyfourhoursmedia\poll\Poll;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Exports raw poll data from the control panel.
 */
class DownloadController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * Gets a poll entry from the request
     *
     * @throws NotFoundHttpException
     */
    private function getPoll(): Entry
    {
        $poll = Poll::$plugin->pollService->getPoll(
            (int)$this->request->getParam('id')
        );
        if (!$poll) {
            throw new NotFoundHttpException('Not a poll');
        }
        return $poll;
    }

    /**
     * Downloads poll data in CSV format
     * action route: poll/download/poll-data
     */
    public function actionPollData(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('accessPlugin-poll');

        $polls = [$this->getPoll()];
        $data = Poll::$plugin->resultService->getData($polls);

        // append answer labels
        $labels = Poll::$plugin->pollService->getAnswerLabelsIndexedById($polls);
        foreach ($data as $k => $row) {
            $data[$k]['answer_label'] = $labels[$row['answer_id']] ?? '(no label)';
        }

        $columns = CsvHelper::getColumns($data);
        // order columns, place answer_text at end
        $idx = array_search('answer_text', $columns, true);
        if (false !== $idx) {
            unset($columns[$idx]);
            $columns[] = 'answer_text';
        }

        $fh = tmpfile();
        CsvHelper::createCsv($fh, $columns, $data);
        fseek($fh, 0);

        return $this->response->sendStreamAsFile($fh, 'polldata-' . date('YmdHis') . '.csv', [
            'mimeType' => 'text/csv',
        ]);
    }
}
