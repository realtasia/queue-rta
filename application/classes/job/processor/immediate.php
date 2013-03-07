<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Realtasia Queue Processor.
 *
 * @author Jason Nathan
 * @package Queue
 * @subpackage Modules/Queue/Queue/Processor
 * @version 0.9
 * @copyright 2012 Realtasia Pte Ltd
 */
class Job_Processor_Immediate {

    public $job;
    public $recipients = [];
    public $interactors = [];
    public $emails = [];
    public $original_document;
    public $document_owner;

    /** @var bool  if false, performs batch operations. */
    public $is_single = FALSE;
    public static $types = array(
        'single' => [
            'mention', 'testimonial', 'wallpost', 'approve-testimonial',
            'private-message'
        ],
        'group' => [
            'comment', 'like', 'subscribe'
        ]
    );

    /**
     *
     * @param MongoDocument $job
     * @return class Queue_Processer
     */
    public function __construct($job)
    {
        $this->job      = (object) $job;
        $this->messages = $this->job->messages;
        return $this->process();
    }

    /**
     * Retrieves all users that are involved in this job after retrieving the
     * original document. Subsequently loops through the messages and calls
     * the respective methods to process them.
     *
     * @return Queue_Processer
     */
    public function process()
    {
        // fetch the document
        $this->original_document = static::get_original_document($this->job);
        //die(Debug::vars($this->original_document));

        // set the author
        $od = $this->original_document;
        $this->document_owner = isset($od->user) ? (string) $od->user['_id'] : (string) $this->job->document_id;


        //echo Debug::vars($this->messages['comment']);

        if ($this->job->is_single)
        {
            $this->is_single = TRUE;
            foreach (self::$types['single'] as $type)
            {
                $m = \Arr::get($this->messages, $type);

                if ($m)
                {
                    $this->{str_replace('-', '_', $type)}($m[0]);
                }

            }

            return $this;
        }


        $this->set_users();


        // loop through the message types.
        foreach (self::$types['group'] as $type)
        {
            $m = \Arr::get($this->messages, $type);
            if ($m)
            {
                foreach ($this->recipients as $recipient_id => $recipient)
                {
                    //print_r([$this->messages[$type], array_reverse($this->messages[$type])]);
                    // check notifications before building emails.
                    $notifications = Notifications::factory($recipient);
                    $this->{$type}($m, $recipient_id, $notifications);
                }
            }
        }

        return $this;
    }

    /**
     * This method consolidates all recipients and interactors and makes a
     * single db call to retrieve user information. Then it sets the recipient
     * and interactor class properties.
     *
     * @return Queue_Processer
     */
    public function set_users($recipients = [], $interactors = [])
    {
        $r = empty($recipients) ? $this->extract_recipients($this->original_document) : (array) $recipients;

        $i = empty($interactors) ? $this->extract_interactors($this->messages) : (array) $interactors;

        $user_ids = array_unique(Arr::merge( $r, $i ));

        $cursor = static::_get_users($user_ids);
        // we put the retrieved users back into the recipients and interactor
        // properties
        if($cursor)
        {
            while ($cursor->hasNext())
            {
                $u = $cursor->getNext()->as_array();
                $uid = (string) $u['_id'];
                if (in_array($uid, $r))
                    $this->recipients[$uid] = $u;

                if (in_array($uid, $i))
                    $this->interactors[$uid] = $u;
            }
        }

        return $this;
    }

    /**
     * sets possible 'comment' email messages for each recipient. Checks their
     * notifications and adds accordingly
     *
     * @param array $items all comment items in this job
     * @param array $recipient the retrived recipient array
     * @return Queue_Processer
     */
    public function comment($items, $receip_id, $notifications)
    {
        //print_r([$items, $receip_id, $notifications]);
        // check if recipient is document owner and would like notifications
        // from his own post
        if ((string) $this->document_owner === (string) $receip_id)
        {
            if (!$notifications->own_post_comments())
                return;
        }

        // check if this recipient wants notifications from other user's posts
        if ((string) $this->document_owner !== (string) $receip_id)
        {
            if (!$notifications->other_post_comments())
                return;
        }
        //echo Debug::vars(Model::factory('comment')->get($items[0]['comment_id']));
        return $this->recipient_emails($items, $receip_id, 'comment');
    }

    /**
     * sets possible 'like' email messages for each recipient. Checks their
     * notifications and adds accordingly
     *
     * @param array $items all like items in this job
     * @param array $recipient the retrived recipient array
     * @return Queue_Processer
     */
    public function like($items, $receip_id, $notifications)
    {
        // check if recipient is document owner and would like notifications
        // from his own post
        if ($this->document_owner === $receip_id)
        {
            if (!$notifications->own_post_likes())
                return;
        }

        // check if this recipient wants notifications from other user's posts
        if ($this->document_owner !== $receip_id)
        {
            if (!$notifications->other_post_likes())
                return;
        }

        // check if this is a profile like and see if user wants to be notified
        if ((string) $this->original_document->_id === $receip_id)
        {
            if (!$notifications->profile_likes())
                return;
        }

        return $this->recipient_emails($items, $receip_id, 'like');
    }

    /**
     * sets possible 'subscribe' email messages for each recipient. Checks their
     * notifications and adds accordingly
     *
     * @param array $items all like items in this job
     * @param array $recipient the retrived recipient array
     * @return Queue_Processer
     */
    public function subscribe($items, $receip_id, $notifications)
    {
        // check if recipient is document owner and would like notifications
        // from his own post
        if ($this->document_owner === $receip_id)
        {
            if (!$notifications->own_post_subscribes())
                return;
        }

        // check if this recipient wants notifications from other user's posts
        if ($this->document_owner !== $receip_id)
        {
            if (!$notifications->profile_subscribes())
                return;
        }

        // check if profile subscription and see if user wants to be notified
        if ((string) $this->original_document->_id === $receip_id)
        {
            if (!$notifications->profile_subscribes())
                return;
        }

        return $this->recipient_emails($items, $receip_id, 'subscribe');
    }

    /**
     * sets possible 'mention' email messages for each recipient. Checks their
     * notifications and adds accordingly
     *
     * @param array $items all like items in this job
     * @param array $recipient the retrived recipient array
     * @return Queue_Processer
     */
    public function mention($items, $receip_id, $notifications)
    {
        // check if recipient wants to receive emails for his mentions
        if (!$notifications->mention())
            return;

        return $this->recipient_emails($items, $receip_id, 'mention');
    }

    /**
     * sets possible 'private-message' email messages for each recipient. This is a
     * little different because we send an email to the person's page where the
     * post is written, not to the writer. As usual, only if their
     * notifications settings allow it.
     *
     * @param array $items this job message
     * @return Queue_Processer
     */
    public function private_message($item)
    {
        $notifications = Notifications::factory($item['mentionee_ids']);
        // check if recipient wants to receive emails for posts written on his wall
        if (!$notifications->messages())
            return;

        $r = (string) $item['mentionee_ids'];
        $i = (string) $item['user_id'];
        $this->set_users($r, $i);

        return $this->single_recipient_emails( $item, $r, 'private-message');
    }

    /**
     * sets possible 'wallpost' email messages for each recipient. This is a
     * little different because we send an email to the person's page where the
     * post is written, not to the writer. As usual, only if their
     * notifications settings allow it.
     *
     * @param array $items this job message
     * @return Queue_Processer
     */
    public function wallpost($item)
    {
        $notifications = Notifications::factory($item['mentionee_ids']);

        if (!$notifications->wallpost())
            return;
        $r = (string) $item['mentionee_ids'];
        $i = (string) $item['user_id'];
        $this->set_users($r, $i);

        return $this->single_recipient_emails($item, $r, 'wallpost');
    }

    /**
     * sets possible 'testimonial' email messages for each recipient. This is a
     * little different because we send an email to the person whose page the
     * testimonial was written on, not to the writer. As usual, only if their
     * notifications settings allow it.
     *
     * @param array $item this job message
     * @return Queue_Processer
     */
    public function testimonial($item)
    {
        $notifications = Notifications::factory($item['mentionee_ids']);
        // check if recipient wants to receive emails for testimonials written
        // about him
        if (!$notifications->testimonial())
            return;

        $r = (string) $item['mentionee_ids'];
        $i = (string) $item['user_id'];
        $this->set_users($r, $i);

        return $this->single_recipient_emails($item, $r, 'testimonial');
    }

    /**
     * approval 'testimonial' email messages for testimonial writer. This is
     * automatically sent. In this case the recipient is as per normal, the
     * original post creator.
     *
     * @param array $item this job message
     * @return Queue_Processer
     */
    public function approve_testimonial($item)
    {
        $r = (string) $item['user_id'];
        $i = (string) $item['mentionee_ids'];
        $this->set_users($r, $i);

        return $this->single_recipient_emails($item, $r, 'approve-testimonial');
    }

    /**
     * Utility method that adds qualified recipient emails to the email property
     * after all the individual checks
     *
     * @param array $items
     * @param string|mongoID $recipient
     * @return Queue_Processer
     */
    protected function recipient_emails($items, $receip_id, $type)
    {
        echo Debug::vars([$items, $receip_id]);
        // loops through all the items.
        foreach ($items as $item)
        {
            //echo Debug::vars($item);
            $item_uid = (string) $item['user_id'];
            // this is the decision making process. Document owner does not
            // get emails for his own interactions. And each recipient does
            // not get his own interaction emails.
            if ($item_uid !== $receip_id)
            {
                $item['user']    = $this->interactors[$item_uid];
                //just for comments, retrieve the comment
                if ($type === 'comment')
                {
                    $c = Document::factory('comment', $item['comment_id']);
                    $c->load();
                    $item['comment'] = $c;
                }

                unset($item['comment_id'], $item['user_id']);

                // add to the final array and build templates.
                                //echo Debug::vars($item);
                $this->emails[(string) $receip_id][$type][] = $item;
            }
        }

        return $this;
    }

    /**
     * Utility method that adds qualified recipient emails to the email property
     * for single emails
     *
     * @param array $item
     * @param string|mongoID $recipient
     * @return Queue_Processer
     */
    protected function single_recipient_emails($item, $receip_id, $type)
    {
        $item['user'] = $this->interactors[(string) $item['user_id']];
//        echo Debug::vars([
//            'r' => $this->recipients,
//            'i' => $this->interactors
//        ]);
        unset($item['user_id']);

        // add to the final array and build templates.
        $this->emails[(string) $receip_id][$type][] = $item;

        return $this;
    }

    /**
     * extracts all subscribers from the original document.
     *
     * @param MongoDocument $document the extracted original document
     * @return array of user_ids
     */
    protected static function extract_recipients($document)
    {
        $object_type = Inflector::singular((string) $document->collection());
        $args = [
            'object_id'   => $document->_id,
            'object_type' => $object_type,
            'limit'       => 100,
            'skip'        => 0,
        ];

        $users = Collection::factory('interaction')->subscribers($args);

        if($object_type === 'user')
        {
            $users[] = [
              '_id' => (string) $document->_id
            ];
        }

        if(!empty($users))
        {
            //Kohana::$log->add('debug', print_r(Arr::path($users, '*._id', []), true));
            return Arr::path($users, '*._id', []);
        }
        return [];
    }

    /**
     * A method that loops through all messages and extracts user_ids of the
     * respective interactors.
     *
     * @param array $messages the list of messages pertaining to this job
     * @return array of interactor ids
     */
    protected static function extract_interactors($messages)
    {
        foreach ($messages as $types)
        {
            foreach ($types as $message)
            {
                $ids[] = (string) $message['user_id'];
            }
        }

        return array_unique(array_values($ids));
    }

    /**
     * Retrieves the original document that was interacted upon from the database
     * uses the job document to fetch the doc id and model
     *
     * @param MongoDocument $job the job currently being processed
     * @return MongoDocument the extracted original document
     */
    protected static function get_original_document($job)
    {
        $d = Document::factory($job->document_type, $job->document_id);
        $d->load();
        return $d;
    }

    /**
     * Retrieves users from database only with the fields needed for Queue
     *
     * @param array|MongoID $user_ids an array of user_ids or a single id
     * @return MongoCursor
     */
    protected static function _get_users($user_ids)
    {
        $query['status'] = 'active';
        $query['_id']    = is_array($user_ids)
                            ? array('$in' => array_map('Document::check_id', $user_ids))
                            : Document::check_id((string)$user_ids);

        $users = Collection::factory('user')
                ->find($query)
                ->fields(array_fill_keys([
                    '_id','email','first_name','name','avatar','settings.notifications', 'info.gender'
                ],1));

        if($users->hasNext())
        {
            return $users;
        }

        return null;
    }

    /**
     *
     * @return type
     */
    public function make_emails($type_key = 'group')
    {
        $mail_adder = Mail_Add::factory($this->original_document, $this->job);

        //echo debug::vars($this);
        //echo "<pre>";
        //print_r($this->emails);
        foreach ($this->emails as $r => $messages)
        {
            //print_r($t);
            // loop through the message types.
            foreach (self::$types[$type_key] as $type)
            {
                $t = \Arr::get($messages, $type);
                //die(Debug::vars($t));
                if ($t)
                {
                    //echo Debug::vars($t);
                    $mail_adder->process($this->recipients[$r], $type, $t);
                }
            }
        }
        //Kohana::$log->write();
        return $mail_adder;
    }

}