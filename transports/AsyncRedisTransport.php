<?php
namespace bazilio\async\transports;

use bazilio\async\models\AsyncTask;

/**
 * Class AsyncRedisTransport
 * @package bazilio\async\transports
 *
 *
 */
class AsyncRedisTransport
{
    /**
     * @var \yii\redis\Connection
     */
    protected $connection;

    function __construct(Array $connectionConfig)
    {
        $this->connection = \Yii::$app->{$connectionConfig['connection']};
    }

    public static function getQueueKey($queueName, $progress = false)
    {
        return "queue:$queueName" . ($progress ? ':progress' : null);
    }

    /**
     * @param string $text
     * @param string $queueName
     * @return integer index in queue
     */
    public function send($text, $queueName)
    {
        $return = $this->connection->executeCommand('RPUSH', [self::getQueueKey($queueName), $text]);
        $this->connection->executeCommand('PUBLISH', [$queueName, 'new']);
        return $return;
    }

    /**
     * @param string $queueName
     * @param bool $blocking
     * @return AsyncTask|bool
     */
    public function receive($queueName, $blocking = false)
    {
        $message = $this->connection->executeCommand(
            'RPOPLPUSH',
            [self::getQueueKey($queueName), self::getQueueKey($queueName, true)]
        );

        if (!$message && !$blocking) {
            return false;
        } elseif ($blocking) {
            $this->connection->executeCommand('SUBSCRIBE', [$queueName]);
            while (!$message) {
                $response = $this->connection->parseResponse('');
                if (is_array($response)) {
                    $type = array_shift($reponse);
                    if ($type !== 'message') {
                        continue;
                    }
                    $this->connection->executeCommand('UNSUBSCRIBE', [$queueName]);

                    $message = $this->connection->executeCommand(
                        'RPOPLPUSH',
                        [self::getQueueKey($queueName), self::getQueueKey($queueName, true)]
                    );
                }
            }
        }

        /**
         * @var AsyncTask $task
         */
        $task = unserialize($message);
        $task->message = $message;
        return $task;
    }


    /**
     * @param AsyncTask $task
     * @return bool
     */
    public function acknowledge(AsyncTask $task)
    {
        return $this->connection->executeCommand(
            'LREM',
            [self::getQueueKey($task::$queueName, true), -1, $task->message]
        ) === '1';
    }

    /**
     * @param string $queueName
     * @return bool
     */
    public function purge($queueName)
    {
        $this->connection->executeCommand('DEL', [self::getQueueKey($queueName)]);
        return true;
    }

    /**
     * @return bool
     * @throws Exception
     */
    function disconnect()
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}