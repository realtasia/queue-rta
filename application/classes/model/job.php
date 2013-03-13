<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Mongo Job Model.
 *
 * @author Jason Nathan
 * @category RealtAsia
 * @package Queue
 * @subpackage Document_Job
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */

/**
  Jobs
 * Document ID   -> MongoID,
 * Document Type  -> STRING,
 * In Progress -> BOOL TRUE/FALSE,
 * Status      -> INT 0 = Success, 1 = Pending, 2 = Failed,
  Messages (array)[
  Type (array) [
  (optional) User ID       -> MongoID,
  (optional) User Email    -> STRING,
  (optional) Mentionee Ids -> array,
  Date                     -> INT timestamp,
  Prority                  -> INT 1 = Lowest
  ]
  ]
 */
class Model_Job extends Document {

    /**
     * The job got sent successfully
     */
    const SUCCESS = 0;
    /**
     * The job is pending.
     */
    const PENDING = 1;
    /**
     * The job failed.
     */
    const FAILED = 2;


    /**
     * Get the next job in the line to process...
     *
     * @param string $delivery
     * @param INT $tries
     * @return mixed
     */
    public function get_next($delivery = 'immediate', $tries = self::PENDING)
    {
        $this->collection()->deleteIndexes();
//        $this->collection()->ensureIndex([
//            'in_progress' => 1,
//            'delivery' => 1,
//            'status' => 1
//        ]);
//        $this->collection()->ensureIndex([
//            'document_id' => 1
//        ]);
        try
        {
            return $this->collection()->collection()->findAndModify([
                            'in_progress' => FALSE,
                            'delivery'    => $delivery,
                            'status'      => ['$gte' => $tries]
                        ], [
                            '$set' => ['in_progress' => TRUE]
                        ], null, [
                            'sort'   => ['status' => 1],
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

    /**
     * Update a Job's status
     *
     * @param INT $status
     * @return \Model_Job|boolean
     */
    public function update_status($status = self::SUCCESS)
    {
        $this->in_progress = FALSE;
        $this->status      = $status;
        if($this->save());
            return $this;

        return false;
    }

    /**
     * Check if there is an unsent JOB so we can add to it instead.
     *
     * @param string|MongoID $document_id
     * @return \Model_Job|boolean
     */
    public function check_exists($document_id)
    {

        try
        {
//            $this->load([
//                'document_id'   => parent::check_id($document_id),
//                'in_progress'   => FALSE,
//                'is_single'     => FALSE,
//            ]);
            $doc = $this->collection()->collection()->findAndModify([
                            'document_id'   => parent::check_id($document_id),
                            'in_progress'   => FALSE,
                            'is_single'     => FALSE,
                            'status'        => ['$ne' => 0]
                        ], ['$set' => ['in_progress' => TRUE]]);

            $_id = \Arr::get($doc, '_id');

            if($_id)
                $this->load($_id);

            return $this;
        } catch (MongoCursorException $e)
        {
            Debug::error('Error while checking for existing job :id -> :e', array(
                ':id' => $document_id,
                ':e'  => $e->getMessage()
            ));
        }

        return FALSE;
    }

    /**
     * Add another interaction to an exisitng Job
     *
     * @param string $key the interaction , 'comment', 'like' etc.
     * @param array $message
     * @return boolean
     */
    public function add_to_existing_queue($key, $message)
    {
        try
        {
            $this->in_progress = FALSE;
            $this->addToSet('messages.' . $key, $message);
            if($this->save());
                return this;
        } catch (MongoException $e)
        {
            Debug::error('Error adding to existing queue job:id status -> :e', array(
                ':id' => $job_id,
                ':e'  => $e->getMessage()
            ));

            return FALSE;
        }

        return $job_id;
    }

    /**
     * Create a new Job
     *
     * @param array $document the jobb to add
     * @return boolean
     */
    public function add_to_queue($document)
    {
        $this->clear();
        try
        {
            $this->load_values($document);
            $this->save();
        } catch (MongoException $e)
        {
            Debug::error('Error adding a new job job :id status -> :e', array(
                ':id' => $document,
                ':e'  => $e->getMessage()
            ));

            return FALSE;
        }

        return $this->id;
    }
}