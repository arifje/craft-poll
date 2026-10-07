<?php

declare(strict_types=1);

// Run only against a development/test Craft installation. All fixture tables are temporary.
$craftPath = $argv[1] ?? null;
if (!$craftPath || !is_file($craftPath . '/bootstrap.php')) {
    throw new RuntimeException('Usage: php tests/regression.php /path/to/test-craft');
}
$loader = require $craftPath . '/vendor/autoload.php';
$loader->addPsr4('twentyfourhoursmedia\\poll\\', dirname(__DIR__) . '/src/', true);
require $craftPath . '/bootstrap.php';
$app = require $craftPath . '/vendor/craftcms/cms/bootstrap/console.php';

use craft\elements\Entry;
use craft\elements\User;
use twentyfourhoursmedia\poll\events\PollEvents;
use twentyfourhoursmedia\poll\helper\CsvHelper;
use twentyfourhoursmedia\poll\Poll;
use twentyfourhoursmedia\poll\records\PollAnswer;
use twentyfourhoursmedia\poll\services\PollService;
use twentyfourhoursmedia\poll\services\ResultService;
use yii\base\Event;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

class FixtureEntry extends Entry
{
    public array $fixtureAnswers = [];

    public function getFieldValue(string $fieldHandle): mixed
    {
        return new class($this->fixtureAnswers) {
            public function __construct(private array $answers) {}
            public function all(): array { return $this->answers; }
        };
    }
}

class FixturePollService extends PollService
{
    public Entry $fixturePoll;
    public int $cookies = 0;

    public function getPoll(mixed $pollOrPollId, mixed $status = null, ?int $siteId = null): ?Entry
    {
        return $this->fixturePoll;
    }

    public function getAnswers(mixed $pollOrPollId): array
    {
        return $this->fixturePoll->fixtureAnswers;
    }

    public function addPollIdToCookie(int $pollId): void
    {
        $this->cookies++;
    }
}

$stream = fopen('php://memory', 'w+');
$values = ['=1+1', '+SUM(1)', '-1+1', '@SUM(1)', " \t=1+1", "\tplain", 'plain;"quoted"', 'backslash\\', '', null];
CsvHelper::createCsv($stream, ['text'], array_map(static fn($value) => ['text' => $value], $values), ['write_bom' => false]);
rewind($stream);
fgetcsv($stream, 0, ';', '"', '');
foreach ($values as $i => $value) {
    $expected = $i < 6 ? "'" . $value : ($value ?? '');
    check((fgetcsv($stream, 0, ';', '"', '')[0] ?? '') === $expected, "CSV value $i is safe and round-trips");
}
fclose($stream);

// Initialize framework services before changing the prefix to isolate fixture queries.
$app->getSites()->getCurrentSite();
$mutex = $app->getMutex();
$poll = new FixtureEntry(['id' => 900001, 'uid' => 'poll-test', 'siteId' => 1]);
$answer = new Entry(['id' => 900002, 'uid' => 'answer-test', 'siteId' => 1]);
$poll->fixtureAnswers = [$answer];
$identity = new User(['id' => 900003]);
$app->getUser()->setIdentity($identity);
$plugin = (new ReflectionClass(Poll::class))->newInstanceWithoutConstructor();
Poll::$plugin = $plugin;
$service = new FixturePollService();
$service->fixturePoll = $poll;
$plugin->set('pollService', $service);
$results = new ResultService();
$db = $app->getDb();
$originalPrefix = $db->tablePrefix;
$db->tablePrefix = 'poll_regression_' . bin2hex(random_bytes(6)) . '_';
$tables = ['{{%poll_pollanswer}}', '{{%users}}'];
try {
    check($db->getIsMysql(), 'Regression fixture uses MySQL temporary tables');
    $db->createCommand('CREATE TEMPORARY TABLE {{%poll_pollanswer}} (
        id INT AUTO_INCREMENT PRIMARY KEY, dateCreated DATETIME NOT NULL,
        pollId INT NOT NULL, siteId INT NOT NULL, fieldId INT NOT NULL,
        answerId INT NOT NULL, userId INT NULL, ip VARBINARY(16) NULL, answerText TEXT NULL
    )')->execute();
    $db->createCommand('CREATE TEMPORARY TABLE {{%users}} (id INT PRIMARY KEY, email VARCHAR(255), username VARCHAR(255))')->execute();
    $db->createCommand()->insert('{{%users}}', ['id' => $identity->id, 'email' => 'test@example.invalid', 'username' => 'tester'])->execute();
    $events = 0;
    $poll->on(PollEvents::POLL_SUBMITTED, static function () use (&$events): void { $events++; });
    check($service->submit($poll, 1, 10, [$answer->uid], [$answer->uid => '=1+1']), 'First authenticated submission succeeds');
    check(!$service->submit($poll, 1, 10, [$answer->uid]), 'Repeated submission is rejected inside the service');
    check($events === 1 && $service->cookies === 1, 'Only a saved vote sets the cookie and emits an event');
    $model = $results->getResults($poll, ['with_users' => true, 'user_id_only' => true]);
    check($model->count === 1 && array_map('intval', $model->userIds) === [$identity->id], 'ID-only mode returns poll participants');
    check(array_map('intval', $model->byAnswer[0]->userIds) === [$identity->id] && $model->users === [], 'ID-only mode returns answer participants without user objects');
    $rows = $results->getData([$poll]);
    check(count($rows) === 1 && $rows[0]['username'] === 'tester' && $rows[0]['answer_text'] === '=1+1', 'Export query joins users and preserves stored text');
    $limited = $results->getResults($poll, ['with_users' => true, 'user_id_only' => true, 'limit_users' => 0]);
    check($limited->userIds === [], 'Participant limit is respected');

    $identity->id = 900004;
    $veto = static function (Event $event): void { $event->isValid = false; };
    Event::on(PollAnswer::class, PollAnswer::EVENT_BEFORE_INSERT, $veto);
    try {
        check(!$service->submit($poll, 1, 10, [$answer->uid]), 'Failed save is reported as unsuccessful');
        check($events === 1 && $service->cookies === 1, 'Failed save does not emit an event or set a cookie');
    } finally {
        Event::off(PollAnswer::class, PollAnswer::EVENT_BEFORE_INSERT, $veto);
    }
    check($service->submit($poll, 1, 10, [$answer->uid]), 'Lock is released after a failed save');

    $identity->id = 900005;
    $otherDb = new yii\db\Connection([
        'dsn' => $db->dsn,
        'username' => $db->username,
        'password' => $db->password,
    ]);
    $otherMutex = Craft::createObject(array_merge(craft\helpers\App::dbMutexConfig(), ['db' => $otherDb]));
    $lockName = $mutex->namePrefix . "poll:vote:{$poll->id}:{$identity->id}";
    check($otherMutex->acquire($lockName), 'Independent database connection acquires the vote lock');
    try {
        check(!$service->submit($poll, 1, 10, [$answer->uid]), 'Contending submission times out without storing a vote');
        check(!$service->hasParticipated($poll), 'Lock contention does not mark participation');
    } finally {
        $otherMutex->release($lockName);
        $otherDb->close();
    }


    $app->getUser()->setIdentity(null);
    check($service->submit($poll, 1, 10, [$answer->uid]), 'Anonymous vote can be stored');
    check($service->submit($poll, 1, 10, [$answer->uid]), 'Another anonymous visitor can vote');
    check($results->getResults($poll)->count === 4, 'Authenticated and anonymous totals are correct');
} finally {
    foreach ($tables as $table) {
        $db->createCommand("DROP TEMPORARY TABLE IF EXISTS $table")->execute();
    }
    $db->tablePrefix = $originalPrefix;
}
