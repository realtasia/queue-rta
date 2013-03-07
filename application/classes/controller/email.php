<?php defined('SYSPATH') or die('No direct script access.');
require_once MODPATH . 'kohana-aws/vendor/aws-sdk-for-php/sdk.class.php';
/**
 * PHP Daemon. Original idea inspired/acquired from GitHub
 *
 * @author jonnyjon
 * @link https://github.com/jonnyjon/Task-Queue/blob/master/classes/controller/daemon.php
 * @author Jason Nathan
 * @category RealtAsia
 * @package RT_Controller
 * @subpackage Email
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */

class Controller_Email extends Controller_Daemon{
    /*
     * Forks into background and initializes daemon
     */
    public function action_index($config = 'default')
    {

// fork into background
        $pid = pcntl_fork();

        if ($pid == -1)
        {
// Error - fork failed
            Kohana::$log->add('error', 'Queue. Initial fork failed');
            exit;
        }
        elseif ($pid)
        {
// Fork successful - exit parent (daemon continues in child)
            Kohana::$log->add('debug', 'Queue. Daemon created succesfully at: ' . $pid);
            exit;
        }
        else
        {
// Background process - run daemon
            $this->_config = Kohana::$config->load('daemon')->$config;

            Kohana::$log->add('debug', strtr('Queue. Config :config loaded, max: :max, sleep: :sleep', array(
                     ':config' => $config,
                     ':max' => $this->_config['max'],
                     ':sleep' => $this->_config['sleep']
                  )));

// Write the log to ensure no memory issues
            Kohana::$log->write();

// run daemon
            $this->daemon();
        }
    }

    /*
     * This is the actual daemon process that reads queued items and executes them
     */
    protected function daemon()
    {
        try
        {
            while (!$this->_sigterm)
            {
                // Find first task that is not being executed
                $j = Document::factory('job')->get_next();

                if (($j))
                {
                    // Task found
                    // Write log to prevent memory issues
                    Kohana::$log->write();
                    $pro = new Job_Processor_Immediate($j);

                    if ($j['is_single'])
                        $e = $pro->make_emails('single');
                    else
                        $e = $pro->make_emails();

                    $queue = new CFBatchRequest(100);

                    foreach($e->emails as $email)
                    {
                        $queue->add($email);
                    }

                    try
                    {
                        //$token = Profiler::start('email', 'Send Email');
                        $res = $queue->send();
                        $failure = "";
                        //Profiler::stop($token);
                        if(is_array($res))
                        {
                            foreach($res as $r)
                            {
                                $success = $r->isOK();
                                $failure = $r->body;
                            }
                        }

                        $job = Document::factory('job', $j['_id']);

                        if($success)
                        {
                            $job->update_status($job::SUCCESS);
                        }
                        else
                        {
                            Kohana::$log->add('error', $failure);
                            Kohana::$log->write();
                            $job->update_status($job::FAILED);
                        }

                        $queue = $j = $job = $pro = null;
                    }
                    catch (Exception $e)
                    {
                        Kohana::$log->add('error', 'Could not send email');
                        Kohana::$log->write();
                    }
                }
                else
                {
                    // No task in queue - sleep
                    sleep(10);
                }
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add('error', 'Email. Daemon failed, msg: ' . $e->getMessage());
        }
    }
}