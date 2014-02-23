<?php

/* IRON_WORKER_HEADER */

include_once APPPATH.'/aws.phar';

use Aws\Ses\SesClient;


class Sendemails {
    public static $debug = true;
	
	public static function send($email) {
		$sender = SesClient::factory(array('key' => RT::AWS_KEY, 'secret' => RT::AWS_SECRET, 'region' => 'us-east-1'));
		$email = json_decode(json_encode($email), true);
		try {
			static::debug("Sending Email");
			$res = $sender->sendEmail($email);
			static::debug(["responseFromSES" => $res->toArray()]);
			$failure = "";
			$queue = null;
		} catch (MessageRejectedException $e) {
			static::debug(['MessageRejectedException' => $e->getMessage()]);
		} catch (Exception $e) {
			static::debug(['Exception' => $e->getMessage()]);
		}

	}

    public static function debug($text)
    {
    	if(!static::$debug)
			return;    	
		
    	if(!is_string($text))
		{
			$text = implode("\n", (array) $text);
		}

        Kohana::$log->add("debug", $text);
        Kohana::$log->write();

        //echo "$text \n";
        //echo "-------------------------------- \n";
        return;
    }
}
