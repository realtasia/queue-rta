<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Realtasia Mail Processor.
 *
 * @author Jason Nathan
 * @package Queue
 * @subpackage Modules/Queue/Mail/Processor
 * @version 0.9
 * @copyright 2012 Realtasia Pte Ltd
 */
class Mail_Processor{

    public function __construct()
    {
        $mailer = Email::mailer();
        //$mail_adder::add();
        $i = 0;
        foreach($mail_adder::$emails as $mail)
        {
            $send = Profiler::start('Send ' . $i, 'Emails ');
            print_r($mailer->send($mail));
            Profiler::stop($send);
            $i++;
        }
    }

}