<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * Description of ticker
 *
 * @author Jason Nathan
 * @category RealtAsia
 * @package RT_Controller
 * @subpackage Ticker
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */
class Controller_Ticker extends Controller_Daemon {
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

            Kohana::$log->add('debug', strtr('Queue. Config :config loaded, max: :max, sleep: :sleep', [
                        ':config' => $config,
                        ':max'    => $this->_config['max'],
                        ':sleep'  => $this->_config['sleep']
                    ]));

            // Write the log to ensure no memory issues
            Kohana::$log->write();

            // run daemon
            $this->daemon();
        }
    }

    /*
     * This is the actual daemon process does batch ticker operations
     * -- for ticker_type "new"
     *      -- get post's page_id && user_id
     *      -- get subscribers of user and insert new doc
     *      -- get subscribers of page and insert new doc
     * -- for ticker_type "delete", remove all by post type
     * -- for ticker_type "update"
     *      -- check privacy
     *      -- get a ticker by post id, and check if privacy was changed
     *          -- if changed ==> PRIVATE
     *              -- delete all non participating users' tickers with post_id
     *          -- if changed ==> PUBLIC
     *              -- update all remaining tickers, no updates to relevancy or modified
     *          -- if no change
     *              -- update existing docs by doc_id (inc, modified)
     */
    protected function daemon()
    {
        try
        {
            while (!$this->_sigterm)
            {
                // Find first task that is not being executed
                $j = static::get_next();

                if (($j))
                {
                    // Task found
                    // Write log to prevent memory issues
                    Kohana::$log->add("debug", "Found a job");
                    Kohana::$log->write();
                    $jid = $j['_id'];
                    switch ($j['ticker_type'])
                    {
                        case 'delete':
                            Collection::factory('ticker')
                                    ->remove([
                                        'document_id' => Document::check_id($j['document_id'])
                                    ], ['multiple' => true]);
                            break;
                        case 'new':
                            $t    = Ticker::factory($j['document_id']);
                            $docs = [];
                            // if user wrote on his own wall, only his subscribers are concerned
                            if ($t->is_page_user())
                            {
                                $s = $t->subscribers($t->user_id);
                                while ($s->hasNext())
                                {
                                    $uid    = (string) \Arr::get($s->getNext()->user, '_id');
                                    if($uid !== (string) $t->user_id)
                                    {
                                        $docs[] = ['user_id' => Document::check_id($uid)];
                                    }
                                }
                                if (!empty($docs))
                                {
                                    $t->bulk_create(array_filter($docs));
                                }
                            }
                            // update the page's subscribers too
                            else
                            {
                                $u = $t->subscribers($t->user_id);
                                while ($u->hasNext())
                                {
                                    $uid    = (string) \Arr::get($u->getNext()->user, '_id');
                                    if($uid !== (string) $t->user_id)
                                    {
                                        $docs[] = ['user_id' => Document::check_id($uid)];
                                    }
                                }
                                if (!empty($docs))
                                {
                                    $t->bulk_create(array_filter($docs));
                                }
                                $docs = [];
                                $p    = $t->subscribers($t->page_id);
                                while ($p->hasNext())
                                {
                                    $uid    = (string) \Arr::get($p->getNext()->user, '_id');
                                    if($uid !== (string) $t->user_id)
                                    {
                                        $docs[] = ['user_id' => Document::check_id($uid)];
                                    }
                                }
                                if (!empty($docs))
                                {
                                    $t->bulk_create(array_filter($docs));
                                }
                            }
                            break;
                        case 'update':
                            $t       = Ticker::factory($j['document_id']);
                            $privacy = $t->document['privacy']['is'];
                            $changed = false;
                            $get_one = Document::factory('ticker');
                            if ($get_one->load([
                                        'document_id' => Document::check_id($j['document_id'])
                                            ], ['privacy' => 1]))
                            {
                                $t_privacy = \Arr::get($get_one->privacy, 'is');
                                $changed   = $t_privacy === $privacy;
                            }

                            if ($changed)
                            {
                                if ($t_privacy)
                                {
                                    if ($t_privacy === 'PRIVATE')
                                    {
                                        $uids = [];
                                        // get all users currently subsribed to this post
                                        $p    = $t->subscribers($j['document_id']);
                                        while ($p->hasNext())
                                        {
                                            $uids[]    = Document::check_id(\Arr::get($p->getNext()->user, '_id'));
                                        }
                                        // delete the ticker for all other users
                                        $t->delete([ 'user_id' => ['$nin' => array_filter($uids)]]);
                                    }
                                }
                            }
                            $t->update();
                            $t= $docs= $privacy= $changed= $get_one= $t_privacy=null;
                            break;
                    }
                    $j = Document::factory('job', $jid);
                    $j->in_progress = false;
                    $j->status = 0;
                    $j->save();
                    $p=$t=$u=$j=$docs=$uid=$s=null;
                    Kohana::$log->write();
                }
                else
                {
                    // No task in queue - sleep
                    sleep(1);
                }
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add('error', 'Ticker. Daemon failed, msg: ' . print_r($e, true));
            Kohana::$log->write();
        }
    }

    /**
     * Get the next job in the line to process...
     *
     * @param string $delivery
     * @param INT $tries
     * @return mixed
     */
    public static function get_next()
    {
//        $this->collection()->deleteIndexes();
//        Collection::factory('job')->ensureIndex([
//            'in_progress' => 1,
//            'delivery'    => 1,
//            'status'      => 1
//        ]);
//        $this->collection()->ensureIndex([ticker_type
//            'document_id' => 1
//        ]);
        try
        {
            return Collection::factory('job')->collection()->findAndModify([
                        'in_progress' => FALSE,
                        'delivery'    => 'ticker',
                        'status'      => ['$gte' => 1]
                            ], [
                        '$set' => ['in_progress' => TRUE]
                            ], null, [
                        'sort' => ['status' => 1]
                    ]);
        }
        catch (MongoCursorException $e)
        {
            Debug::error('Error retrieving next job to process -> :e', array(
                ':e' => $e->getMessage()
            ));
        }

        return FALSE;
    }

}