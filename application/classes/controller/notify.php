<?php defined('SYSPATH') or die('No direct script access.');
include_once MODPATH . 'kohana-aws/vendor/aws-sdk-for-php/sdk.class.php';
include_once MODPATH . 'RT/init.php';
include_once APPPATH . 'classes/sendemails.php';

/**
 * PHP Daemon. Original idea inspired/acquired from GitHub
 *
 * @author jonnyjon
 * @link https://github.com/jonnyjon/Task-Queue/blob/master/classes/controller/daemon.php
 * @author Jason Nathan
 * @category RealtAsia
 * @package Queue
 * @subpackage Controller_Notify
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */
class Controller_Notify extends Controller {
	
    /** @var boolean Debug flag
     */
	public static $debug = false;
	
	
    public function action_index($config = 'default')
    {
        //echo Debug::vars(new IronWorker());
        static::debug("Running Daemon");
		while(true)
		{
			$this->daemon();
			sleep(2);
		}
        
    }

    /*
     * This is the actual daemon process that reads queued items and executes them
     */

    protected function daemon()
    {
    	while($j = Document::factory('job')->get_next())
    	{
	        try
	        {
	            // Find first task that is not being executed
	            //$j = Document::factory('job')->get_next();
	            if (($j))
	            {
	                // Task found
	                static::debug("Found Task");
	                $pro = new Job_Processor_Immediate($j);
	
	                if ($j['is_single'])
					{
						static::debug("Making Notifications");
						$e = $pro->make_notifications('single');
					}
	                    
	                else
					{
						static::debug("Making Notifications");
						$e = $pro->make_notifications();
					}
	                    
	
					static::debug("Done with making Notifications");
	                $job = Document::factory('job', $j['_id']);
	                static::debug("Is notifications more than zero: " . (string) count($e->notifications));
	                if (count($e->notifications) > 0)
	                {
	                	static::debug("Creating Connection to QUEUE ");
						$e->addAll();
						$job->update_status($job::SUCCESS);

	                }
	                else
	                {
	                    static::debug("Job found but no emails to process. Changing job status to success");
	                    $job->update_status($job::SUCCESS);
	                }
	            }
	            else
	            {
	                static::debug("No Job to process.");
					//die;
	            }
	        }
	        catch (Exception $e)
	        {
	        	static::debug($e->getMessage());
	            Kohana::$log->add('error', 'Email. Daemon failed, msg: ' . $e->getMessage());
	            Kohana::$log->write();
	        }
			sleep(2);
        }
    }

    public static function debug($text)
    {
    	if(!static::$debug) return;
		
    	if(!is_string($text))
		{
			$text = var_export($text, true);
		}
		
        $STDOUT = fopen("php://stdout", "w");
        fwrite($STDOUT, "$text \n");
        fclose($STDOUT);		
		
		
        //echo "$text \n";
        //echo "-------------------------------- \n";
        //Kohana::$log->add("debug", $text);
        //Kohana::$log->write();

        return;
    }

}