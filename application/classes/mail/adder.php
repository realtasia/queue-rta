<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Realtasia Mail Adder. A helper class to add Emails to the queue in a standardised
 * fashion
 *
 * @author Jason Nathan
 * @package Queue
 * @subpackage Modules/Queue/Mail/Add
 * @version 0.9
 * @copyright 2012 Realtasia Pte Ltd
 */
class Mail_Add{

    /** @var string The url to use to build links.
     * @todo move this out to a config file.
     */
    public static $url = 'http://beta.realtasia.com.sg/';

    /** @var array The generated list of emails to add to the db
     */
    public $emails;

    /** @var MongoDocument object the original job from the job queue
     */
    public static $job;

    /** @var MongoDocument object the original document retrieved. normally a post.
     */
    public static $doc;

    /**
     * No __construct really needed as all properties are static but returns a new
     * instance to aid in method chaining. Requires the original job document
     * and a retrieved post document.
     *
     * @param MongoDocument $doc object the original job from the job queue
     * @param MongoDocument $job object the original document retrieved. normally a post.
     * @return Mail_Add
     */
    public static function factory($doc, $job)
    {
        static::$doc = $doc;
        static::$job = $job;
        return new Mail_Add;
    }

    /**
     * This method sets the default template for and then calls each interaction
     * method for the customised templates. Adds generated emails to the static
     * $emails property
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param string $type the interaction type, comment, like etc
     * @param array $items the items by interaction type
     * @return Mail_Add
     */
    public function process($recipient, $type, $items)
    {
        $content  = $this->{$type}($recipient, $items);
        $notification_url = static::_notification_url($recipient['_id']);

        $view = View::factory('emails/email')
              ->set('title', $content['header_plain'])
              ->set('notification_url', $notification_url)
              ->set('teaser', strip_tags($content['message']))
              ->set('type', ucfirst($type))
              ->set('post_link', static::$url . static::_post_uri())
              ->set('content_view', $content['message']);

        $plainview = View::factory('emails/content/plaintext')
              ->set('notification_url', $notification_url)
              ->set('type', ucfirst($type))
              ->set('post_link', static::$url . static::_post_uri())
              ->set('content_header', $content['header_plain'])
              ->set('content_message', $content['message_plain']);

        $this->emails[] = Email::factory($content['header_plain'], $plainview)
              ->message($view, 'text/html')
              //->to($recipient['email'][0], $recipient['display_name'])
              ->to('jason.nathan@realtasia.com', 'Jason Nathan')
              ->from('support@realtasia.com', 'Realtasia Notifications')
              ->raw_message();

        //$this->emails[] = $view . $plainview;
        return $this;

    }

    /**
     * Comments differ from likes and subscribes because the content of the
     * comment is also included.
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function comment($recipient, $items)
    {
        $content_message  = static::_build_interaction_summary($recipient, $items, 'commented on ');
        $comment  = View::factory('emails/content/comment')
                        ->set('name', $items[0]['comment']->author)
                        ->set('comment', $items[0]['comment']->content);

        $content_message = $content_message . "\n" . $comment;

        return static::_generic($content_message, $recipient);
    }

    /**
     * Process likes
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function like($recipient, $items)
    {
        $content_message  = static::_build_interaction_summary($recipient, $items, 'likes');

        return static::_generic($content_message, $recipient);

    }

    /**
     * Process subscribes
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function subscribe($recipient, $items)
    {
        $content_message  = static::_build_interaction_summary($recipient, $items, 'subscribed to ');

        return static::_generic($content_message, $recipient);
    }

    /*****************Single Emails*********************/

    /**
     * Testimonials, like wallposts are sent to the page owner. This creates
     * the content and sets the approval link.
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function testimonial($recipient, $item)
    {
        //echo Debug::vars($recipient);
        $content_message  = static::get_counts($item)
                          ."wrote you a "
                          .static::_post_link('testimonial')
                          ." in your profile page.";
        $content_message .= "\n"."You can approve or remove this testimonial by following the link below.";

        return static::_generic($content_message, $recipient);
    }

    public function wallpost($recipient, $item)
    {
        //echo Debug::vars($recipient);
        $content_message  = static::get_counts($item)
                          ."wrote you a "
                          .static::_post_link('testimonial')
                          ." in your profile page.";

        return static::_generic($content_message, $recipient);
    }


    /**
     * Process likes
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return Mail_Add
     */
    public function add($priority = 1)
    {
        if(empty($this->emails))
              throw new Mail_Processor_Exception('There are no generated emails to add');

        Document::factory('email')->add($this->emails, $priority);

        return $this;
    }

    /**
     * Used by all generic interaction emails. counts the number of items in a
     * message array by interaction type. Builds a sentence by extracting user
     * names depending on the count.
     *
     * @param array $items the items by interaction type
     * @uses static::_extract_user to generate content
     * @return NULL
     */
    public static function get_counts($items)
    {
        $count = count($items);
        switch($count)
        {
            case 0:
                return '';

            case 1:
                return static::_extract_user($items[0]) . ' ';

            case 2:
                return static::_extract_user($items[0]) . ' and '
                     . static::_extract_user($items[1]) . ' also ';

            case 3:
                return static::_extract_user($items[0]) . ', '
                     . static::_extract_user($items[1]) . ' and '
                     . static::_extract_user($items[2]) . ' also ';

            // more than 3. return first three followed by the count.
            default:
                return static::_extract_user($items[0]) . ', '
                     . static::_extract_user($items[1]) . ', '
                     . static::_extract_user($items[2]) . ' and '
                     . $count - 3 . ' other people also ';
        }
    }

    /**
     * Used by all generic interaction emails. Creates a greeting, the plain text
     * version of the content and the HTML version of the content
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_greeting to generate content
     * @return NULL
     */
    protected static function _generic($content_message, $recipient)
    {
        $greeting        = static::_greeting($recipient['first_name']);
        $content_header  = View::factory('emails/header/h4')
                               ->set('header', $greeting);

        $content = View::factory('emails/content/standard')
                       ->set('content_header', $content_header)
                       ->set('content_message', $content_message)
                       ->render();

        return array(
           'header_plain' => $greeting,
           'message_plain' => Inflector::br2nl($content_message),
           'message' => $content,
        );
    }

    /**
     * Completes the sentence summary for emails. Extracts the users, post item
     * and parent page item along with the relevant links.
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::get_counts()
     * @uses static::_adj_or_noun()
     * @uses static::_post_link()
     * @uses static::_page_link()
     * @return NULL
     */
    protected static function _build_interaction_summary($recipient, $items, $action)
    {
        return static::get_counts($items)
              ."$action "
              .static::_adj_or_noun($recipient)
              .static::_post_link()
              .static::_page_link();
    }

    /**
     * Utility to extract username from various documents used in this class
     *
     * @param array $message the item or a user array to fetch data from
     * @param bool $set_owner if set, will return ownership too i.e. User's
     * @return string the retrieved name
     */
    protected static function _extract_user($message, $set_owner = FALSE)
    {
        $message = (array) $message;
        $info = isset($message['user']) ? $message['user'] : $message;
        return $set_owner ? $info['display_name'] . "'s" : $info['display_name'];
    }

    /**
     * Builds the notification (unsubscribe) uri for a recipient
     *
     * @param MongoID $recipient_id the recipient id
     * @return string the generated uri
     */
    protected static function _notification_url($recipient_id)
    {
        return static::$url . Route::get('browse')
                    ->uri(
                          array('action' => 'notifications', 'format' => 'php')
                    ) . '?id=' . $recipient_id;
    }

    /**
     * Builds the post uri for the interacted document in question.
     *
     * @return string URI
     */
    protected static function _post_uri()
    {
        return Route::get('browse')
                    ->uri(array('action' => 'post', 'format' => 'php')) . '?id=' . static::$job->document_id;
    }

    /**
     * Builds the post link for the interacted document in question.
     *
     * @return string the generated HTML link
     */
    protected static function _post_link($type = FALSE)
    {
        $type = ($type) ? $type : static::$job->document_type;
        return static::_anchor(static::_post_uri(), $type);
    }

    /**
     * Builds the parent page link for the interacted document in question.
     *
     * @return string the generated HTML link
     */
    protected static function _page_link($noun = NULL)
    {
        $page = property_exists(static::$doc, 'page_uri')
              ? ' in ' . static::_anchor(static::$doc->page_uri, static::$doc->page_title)
              : '';

        return ($noun) ? $page . " $noun" : $page;
    }

    /**
     * Decides if the recipient is the document owner. returns the doc owner's
     * name if not.
     *
     * @param MongoID $recipient_id the recipient id
     * @param string $adjective the adjective to use
     * @return string the document owner name or an adjective (your)
     */
    protected static function _adj_or_noun($recipient, $adjective = 'your')
    {
        if((string) static::$doc->user_id == (string) $recipient['_id'])
            return "$adjective ";

        return $pre_post = static::$doc->user_full_name . '\'s ';
    }

    /**
     * Builds all email links. Since we need a proper domain and also ensures
     * that the anchor attribute "target" is set to blank
     *
     * @param string the uri
     * @param string the link name
     * @uses HTML::anchor()
     * @return string the generated HTML link
     */
    protected static function _anchor($uri, $name)
    {
        return HTML::anchor(static::$url . $uri, $name, array('target' => '_blank'));
    }

    /**
     * A small utility that randomises greetings in emails
     *
     * @param string $name the user name
     * @return string
     */
    protected static function _greeting($name)
    {
        $greetings = array(
           'Hello ', 'Hey there ', 'Hey ', 'Hi '
        );

        return $greetings[array_rand($greetings)] . $name . ', ';
    }
}