<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Realtasia Queue Adder. A helper class to add Jobs to the queue in a standardised
 * fashion
 *
 * @author Jason Nathan
 * @package Queue
 * @subpackage Modules/Queue/Queue/Add
 * @version 0.9
 * @copyright 2012 Realtasia Pte Ltd
 */
class Job_Add {

    /** @var string|MongoID the document object's id that an interaction was performed on. */
    public $document_id;

    /** @var string the document object type that an interaction was performed on. */
    public $document_type;

    /** @var string|MongoID  the user id of the person performing the interaction. */
    public $user_id;

    /** @var bool  if false, performs batch operations. */
    public $is_single = FALSE;

    /*
     * Sets the default properties for this class
     *
     * @param string|MongoID $document_id
     * @param string $document_type
     * @param string|MongoID optional $user_id
     * */

    public function __construct($document_id, $document_type, $user_id = NULL)
    {
        $this->document_id   = Model::check_id($document_id);
        $this->user_id       = !is_null($user_id) ? Document::check_id($user_id) : $user_id;
        $this->document_type = $document_type;
    }

    /**
     * Add a comment Job to the queue
     *
     * @uses Job_Add->create()
     * @return bool
     */
    public function comment($comment_id)
    {
        $this->key = 'comment';

        return $this->create(array(
                    'user_id'    => $this->user_id,
                    'date'       => time(),
                    'priority'   => 1,
                    'comment_id' => $comment_id
                ));
    }

    /**
     * Add a like Job to the queue
     *
     * @uses self::generic()
     * @return bool
     */
    public function like()
    {
        $this->key = 'like';
        return $this->generic();
    }

    /**
     * Add a subscribe Job to the queue
     *
     * @uses self::generic()
     * @return bool
     */
    public function subscribe()
    {
        $this->key = 'subscribe';
        return $this->generic();
    }

    /**
     * Mentions can be mentioned in any post type. We'll need some extra params
     * for this type of job
     *
     * @param string|mongoID $mentionee_id the id of the person mentioned
     * @uses Job_Add->create()
     * @return bool
     */
    public function mention($mentionee_id)
    {
        $this->key = 'mention';
        return $this->double_generic($mentionee_id, $parent_id, $parent_type);
    }

    /**
     * Although testimonials can currently only be added to a user's profile.
     * There might be an option to add testimonials to locations in the future
     * Requires the same params as wallposts for this type of job.
     *
     * @param string|mongoID $profile_user_id the id of the user whose profile was written on
     * @uses Job_Add->create()
     * @return bool
     */
    public function testimonial($profile_user_id)
    {
        $this->key       = 'testimonial';
        $this->is_single = TRUE;
        return $this->double_generic($profile_user_id);
    }

    /**
     * Approval of testimonials merely informs the original writer of the approval
     *
     *
     * @param string|mongoID $writer_user_id the id of the user who wrote the testimonial
     * @uses Job_Add->create()
     * @return bool
     */
    public function approve_testimonial($writer_user_id)
    {
        $this->key       = 'approve-testimonial';
        $this->is_single = TRUE;
        $this->user_id = Document::check_id($writer_user_id);
        return $this->generic();
    }

    /**
     * Wallposts like testimonials are written on a user's profile page
     *
     * @param string|mongoID $profile_user_id the id of the user whose profile was written on
     * @uses Job_Add->create()
     * @return bool
     */
    public function wallpost($profile_user_id)
    {
        $this->key       = 'wallpost';
        $this->is_single = TRUE;
        return $this->double_generic($profile_user_id);
    }

    /**
     * Wallposts like testimonials are written on a user's profile page
     *
     * @param string|mongoID $profile_user_id the id of the user whose profile was written on
     * @uses Job_Add->create()
     * @return bool
     */
    public function private_message($comment_id)
    {
        $this->key       = 'private-message';

        return $this->create(array(
                    'user_id'    => $this->user_id,
                    'date'       => time(),
                    'priority'   => 1,
                    'comment_id' => $comment_id
        ));
    }


    /*     * *************** PROTECTED METHODS *********** */

    /**
     * Add a generic Job to the queue
     *
     * 1. It verifies both document id and user id to be MongoIDs.
     * 2. Constructs a message array
     * 3. Inserts the job into the db, checking to see if the document already exists
     *    If it does, it will add to the message array, if not it'll create a new job
     *
     * @uses Job_Add->create()
     * @return bool
     */
    protected function generic()
    {
        return $this->create(array(
                    'user_id' => $this->user_id,
                    'date'    => time(),
                        ), 1);
    }

    /**
     * Add a single generic Job to the queue
     *
     * Single generic jobs are ones where only an email needs to be sent to a
     * single user, normally followed by an admin email. These jobs are simpler
     * by design and generally has a higher priority.
     *
     *
     * @param string $user_email We directly use an email for these jobs
     * @uses Job_Add->create()
     * @return bool
     */
    protected function single_generic($email, $priority = 2)
    {
        return $this->create(array(
                    'user_email' => $email,
                    'date'       => time()
                        ), $priority);
    }

    /**
     * Double generic jobs are like generic jobs except that there are normally only
     * two users in question. This is the case for testimonials and wall posts.
     * They are little more complex than generic jobs because they require
     * additional params => secondary user
     *
     * @param string|mongoID $mentionee_id the id of the person(s) mentioned
     * @uses Job_Add->create()
     * @return bool
     */
    protected function double_generic($mentionee_id)
    {
        if (is_array($mentionee_id))
            $mentionee_id = array_map('Model::check_id', $mentionee_id);
        else
            $mentionee_id = Model::check_id($mentionee_id);

        return $this->create(array(
                    'user_id'       => $this->user_id, //mentioner
                    'mentionee_ids' => $mentionee_id, // mentionees
                    'date'          => time(),
                        ), 1);
    }

    /**
     * The actual method that updates or inserts a job.
     *
     * @return bool or throws MongoCursorException in Model
     */
    protected function create($message, $priority = 1)
    {
        $Job = Document::factory('job');
        // if user id is set then we ought to check for existing jobs and update
        // or create a new one
        // in this case, these are singular emails, and we should create a new job
        if ($this->is_single)
        {
            $new_job = $this->get_job_defaults($priority, TRUE);
        }
        else
        {
            // check if this job exists
            $Job->check_exists($this->document_id, $this->document_type);

            // if it does exist, add the message to the document and return
            if (isset($Job->_id))
                return $Job->add_to_existing_queue($this->key, $message);

            // document doesn't exist so need to create a new job
            // create a new default job array from defaults
            $new_job = $this->get_job_defaults($priority);
        }

        // add to the message array of this job by type
        $new_job['messages'][$this->key][] = $message;

        // insert it into the db and return
        return $Job->add_to_queue($new_job);
    }

    /************************* PROTECTED  METHODS ************************* */

    /**
     * Creates and returns a default schema array for a Job Document in Mongo
     *
     * @param MongoID $document_id
     * @param string $document_type
     * @return array
     */
    protected function get_job_defaults($priority = 1, $single = FALSE, $delivery = 'immediate')
    {
        if ($single)
            $message_array = [$this->key => []];
        else
            $message_array = [
                'like'      => [],
                'comment'   => [],
                'subscribe' => []
            ];

        return [
            'document_id'   => Document::check_id($this->document_id),
            'document_type' => $this->document_type,
            'in_progress'   => FALSE,
            'is_single'     => (bool) $single,
            'status'        => 1,
            'priority'      => $priority,
            'delivery'      => $delivery,
            'messages'      => $message_array
        ];
    }
}
