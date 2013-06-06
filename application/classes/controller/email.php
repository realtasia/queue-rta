<?php

defined('SYSPATH') or die('No direct script access.');
require_once MODPATH . 'kohana-aws/vendor/aws-sdk-for-php/sdk.class.php';
require_once 'phar://' . APPPATH . 'iron_worker.phar';

/**
 * PHP Daemon. Original idea inspired/acquired from GitHub
 *
 * @author jonnyjon
 * @link https://github.com/jonnyjon/Task-Queue/blob/master/classes/controller/daemon.php
 * @author Jason Nathan
 * @category RealtAsia
 * @package Queue
 * @subpackage Controller_Email
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */
class Controller_Email extends Controller {
    /*
     * Forks into background and initializes daemon
     */

    public function before()
    {

    }

    public $debug = true;

    public function action_index($config = 'default')
    {
        //echo Debug::vars(new IronWorker());
        static::debug("Running Daemon");
        return $this->daemon();
    }

    /*
     * This is the actual daemon process that reads queued items and executes them
     */

    protected function daemon()
    {
        try
        {
            // Find first task that is not being executed
            $j = Document::factory('job')->get_next();
            if (($j))
            {
                // Task found
                static::debug("Found Task");
                $pro = new Job_Processor_Immediate($j);
                //$this->debug($pro);

                if ($j['is_single'])
                    $e = $pro->make_emails('single');
                else
                    $e = $pro->make_emails();


                $job = Document::factory('job', $j['_id']);
                static::debug("Is emails more than zero" . (string) count($e->emails));
                if (count($e->emails) > 0)
                {
                    // the iron worker
                    //static::debug("Starting IronWorker");
                    $worker = new IronWorker();

                    static::debug("Email Send worker started");

                    $chunks = array_chunk($e->emails, 10);


                    // no of chunks
                    $total = count($chunks);

                    //the CFBatchRequest(s) created
                    $batches = array();

                    $i = 0;

                    while ($i < $total)
                    {

                        static::debug("POSTING TASK");

                        $task_id = $worker->postTask("SendEmails", $chunks[$i]);
                        static::debug("task_id = $task_id");

                        # Wait for task finish
                        $details = $worker->waitFor($task_id);
                        static::debug($details);

                        $log = $worker->getLog($task_id);
                        static::debug("Task log:\n $log");

                        $i++;
                    }
                    $job->update_status($job::SUCCESS);
                }
                else
                {
                    $job->update_status($job::SUCCESS);
                }
            }
            else
            {
                exit();
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add('error', 'Email. Daemon failed, msg: ' . $e->getMessage());
            Kohana::$log->write();
        }
    }

    public static function debug($text)
    {
        echo "$text \n";
        echo "-------------------------------- \n";
        return;
    }

}