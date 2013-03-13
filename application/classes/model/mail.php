<?php defined('SYSPATH') or die('No direct script access.');

/**
 * RTA Queue Model.
 *
 * @author Jason Nathan
 * @category RealtAsia
 * @package Queue
 * @subpackage Model_Mail
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */

class Model_Mail extends Model
{
    public function get_next($tries, $limit = 100)
    {
        $query = array(
            'in_progress' => FALSE,
            'status' => array('$gte' => $tries)
        );

        $result = $this->db->command(
          array(
             'findandmodify' => 'emails',
             'query'         => $query,
             'sort'          => array('priority' => -1, 'status' => 1),
             'update'        => array('$set' => array('in_progress' => TRUE)),
             'upsert'        => FALSE,
             'limit'         => $limit
          ));

        if(isset($result['value']))
            return $result['value'];

        return FALSE;
    }

    public function update_status($job_id, $status)
    {
        return $this->collection->update(
              array('_id'  => parent::check_id($job_id)),
              array('$set' => array('in_progress' => FALSE, 'status' => $status)),
              array('safe' => TRUE)
        );
    }

    public static function is_user_active_now($user_id)
    {
        $t = time();
        $m = Document::factory('user')->findOne(
              array(
                 '_id' => Model::check_id($user_id),
                 'active' => array('$lt' => $t)
              ));

        if(empty($m))
            return TRUE;

        return $t - $m['active'] >= 60;
    }
}

?>