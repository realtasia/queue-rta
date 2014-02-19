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
 * @subpackage Controller_Email
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */
class Controller_Email extends Controller {
	
    /** @var boolean Debug flag
     */
	public static $debug = true;
	
	
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
    	while($n = Document::factory('notification')->get_next_email())
    	{
	        try
	        {
	            // Find first task that is not being executed
	            if (($n))
	            {
	                // Task found
	                static::debug("Found Task:");
	                //static::debug($n);

	                $pro = new Mail_Processor($n);
	
					$raw = $pro->process();	                    
					
	                if ($raw->email)
	                {
	                	static::debug("Creating Connection to QUEUE ");
						Sendemails::send($raw->email);

	                    $n->update_emailed_status();
	                }
	                else
	                {
	                    static::debug("Notification found but no emails to process. Changing status to success");
	                    $n->update_emailed_status();
	                }
	            }
	            else
	            {
	                static::debug("No Notification to process.");
					//die;
	            }
	        }
	        catch (Exception $e)
	        {
	            static::debug('error', 'Email. Daemon failed, msg: ' . $e->getMessage());
	        }
			sleep(9);
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