<?php

/* IRON_WORKER_HEADER */

require_once 'phar://application/aws.phar';
require_once 'application/modules/RT/init.php';

use Aws\Ses\SesClient;

$sender = SesClient::factory(array(
    'key'    => RT::AWS_KEY,
    'secret' => RT::AWS_SECRET,
    'region' => 'us-east-1'
));

$queue = getPayload();

foreach ($queue as $email)
{
    //$email = json_decode(json_encode($email), true);
    try
    {
        print_r( (array) $email);

        $res     = $sender->sendEmail( (array) $email);
        $failure = "";
        if (is_array($res))
        {
            foreach ($res as $r)
            {
                $success = $r->isOK();
                $failure = (string) $r->body;
                print_r($failure);
            }
        }

        if (!$success)
        {
            print_r($failure);
        }

        $queue = null;
    }
    catch (MessageRejectedException $e)
    {
        echo $e->getMessage();
    }
}

//function getArgs()
//{
//    global $argv;
//
//    $args = array('task_id' => null, 'dir'     => null, 'payload' => array(), 'config'  => null);
//
//    foreach ($argv as $k => $v)
//    {
//        if (empty($argv[$k + 1]))
//            continue;
//
//        if ($v == '-id')
//            $args['task_id'] = $argv[$k + 1];
//        if ($v == '-d')
//            $args['dir']     = $argv[$k + 1];
//
//        if ($v == '-payload' && file_exists($argv[$k + 1]))
//        {
//            $args['payload'] = file_get_contents($argv[$k + 1]);
//
//            $parsed_payload = json_decode($args['payload']);
//
//            if ($parsed_payload != null)
//            {
//                $args['payload'] = $parsed_payload;
//            }
//        }
//
//        if ($v == '-config' && file_exists($argv[$k + 1]))
//        {
//            $args['config'] = file_get_contents($argv[$k + 1]);
//
//            $parsed_config = json_decode($args['config'], true);
//
//            if ($parsed_config != null)
//            {
//                $args['config'] = $parsed_config;
//            }
//        }
//    }
//    return $args;
//}
//
//function getPayload()
//{
//    $args = getArgs();
//
//    return $args['payload'];
//}
//
//function getConfig()
//{
//    $args = getArgs();
//
//    return $args['config'];
//}