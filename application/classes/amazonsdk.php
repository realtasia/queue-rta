<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Amazon Web Services Utility Helper.
 *
 * @author Jason Nathan
 * @package realtasia-beta-0.8
 * @subpackage Helper
 * @copyright 2011 Realtasia Pte Ltd
 */
require 'AWSSDKforPHP/aws.phar';
use Aws\S3\S3Client;

class AmazonSDK
{

    public static function s3()
    {
        return S3Client::factory([
            'key'      => '[REDACTED]',
            'secret'   => '[REDACTED]',
            //'base_url' => 's3.amazonaws.com',
            'region'   => 'ap-southeast-1'
        ]);
    }

    public static function SES()
    {
        define('AWS_KEY', '[REDACTED]');
        define('AWS_SECRET_KEY', '[REDACTED]');
        require_once MODPATH . 'kohana-aws/vendor/aws-sdk-for-php/sdk.class.php';
        return new AmazonSES();
    }

    public static function sns()
    {
        defined('AWS_KEY') or self::_initialise();
        return new AmazonSNS();
    }

    protected static function _initialise()
    {
        $config = Kohana::$config->load('amazon');
        foreach ($config as $key => $value)
        {
            define($key, $value);
        }
    }

}