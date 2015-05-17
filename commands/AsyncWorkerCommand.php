<?php

namespace bazilio\async\commands;

use bazilio\async\models\AsyncTask;

class AsyncWorkerCommand extends \yii\console\Controller
{
    /**
     * @var string Component name to work with
     */
    public $component = 'async';

    /**
     * Processes all tasks in queue and exits.
     * @param string|null $queueName
     */
    public function actionExecute($queueName = null)
    {
        /** @var AsyncTask $task */
        while ($task = \Yii::$app->{$this->component}->receiveTask($queueName ?: AsyncTask::$queueName)) {
            $task->execute();
            \Yii::$app->{$this->component}->acknowledgeTask($task);
        }
    }

    /**
     * Processec all tasks in queue and waits for new.
     * @param string|null $queueName
     */
    public function actionDaemon($queueName = null)
    {
        /** @var AsyncTask $task */
        while ($task = \Yii::$app->{$this->component}->waitAndReceive($queueName ?: AsyncTask::$queueName)) {
            $task->execute();
            \Yii::$app->{$this->component}->acknowledgeTask($task);
        }
    }
}
