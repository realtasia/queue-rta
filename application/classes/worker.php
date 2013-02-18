<?php defined('SYSPATH') or die('No direct script access.');

/**
 * RTA Queue Class.
 *
 * @author Jason Nathan
 * @package realtasia-beta-0.8
 * @subpackage Queue
 * @copyright 2011 Realtasia Pte Ltd
 */

class Worker
{
    const DELIVERED = 0;
    const NOATTEMPT = 1;

    public static function get_next($tries = self::NOATTEMPT, $limit = 100)
    {
        return Document::factory('queue')->get_next($tries);
    }

    public static function update_delivered($job)
    {
        return self::update_status($job['_id'], self::DELIVERED);
    }

    public static function update_undelivered($job)
    {
        return $this->update_status($job['_id'], $job['status']++);
    }

    protected static function update_status($job_id, $status)
    {
        return Document::factory('queue')->update_status($job_id, $status);
    }
}

?>
