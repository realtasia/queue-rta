<?php defined('SYSPATH') or die('No direct script access.');
require_once MODPATH . 'kohana-aws/vendor/aws-sdk-for-php/sdk.class.php';
/**
 * Realtasia Mail Adder. A helper class to add Emails to the queue in a standardised
 * fashion
 *
 * @author Jason Nathan
 * @category RealtAsia
 * @package Queue
 * @subpackage Mail_Add
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */
class Mail_Add{

    /** @var array The generated list of emails to add to the db
     */
    public $emails = [];

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
     * @return \Mail_Add
     */
    public function process($recipient, $type, $items)
    {
        //echo Debug::Vars($items);
        $content  = $this->{str_replace('-', '_', $type)}($recipient, $items);
        $notification_url = static::_notification_url($recipient['_id']);
        $email = ASDK::SES();
        $url = 'http://' . RT::APP_SERVER;

        switch($type)
        {
            case 'feedback': case 'registration': case 'markerting':
            case 'welcome': case 'beta':
                $view = View::factory('emails/generic')
                    ->set('title', $content['header_plain'])
                    ->set('teaser', strip_tags($content['message']))
                    ->set('content_view', $content['message']);

                $plainview = View::factory('emails/content/plaintext')
                    ->set('title', $content['header_plain'])
                    ->set('teaser', strip_tags($content['message']))
                    ->set('content_view', $content['message']);

                $this->emails[] = $email->send_email(
                        RT::NOTIFICATIONS, [
                            'ToAddresses' => [
                                $recipient['name'] . "<" . $recipient['email'] . ">"
                            ]
                        ], [
                            'Subject.Data'   => $content['header_plain'],
                            'Body.Text.Data' => $plainview->render(),
                            'Body.Html.Data' => $view->render(),
                        ], [
                            'returnCurlHandle' => true
                        ]);
                break;
            case 'adminEmail':
                $content = array(
                    'header_plain'  => $content['header_plain'],
                    'message_plain' => $content['message'],
                    'message'       => View::factory('emails/content/standard')
                            ->set('content_header', "<h3>{$content['header_plain']}</h3>")
                            ->set('content_message', "<pre>{$content['message']}</pre>")
                );

                $view = View::factory('emails/admin')
                        ->set('title', $content['header_plain'])
                        ->set('teaser', strip_tags($content['header_plain']))
                        ->set('content_view', $content['message']);

                $plainview = View::factory('emails/content/plaintext_generic')
                        ->set('content_header', $content['header_plain'])
                        ->set('content_message', $content['message_plain']);

                $this->emails[] = $email->send_email(
                        RT::NOTIFICATIONS, [
                            'ToAddresses' => [RT::ADMINS]
                        ], [
                            'Subject.Data'   => $content['header_plain'],
                            'Body.Text.Data' => $plainview->render(),
                            'Body.Html.Data' => $view->render(),
                        ], [
                            'returnCurlHandle' => true
                        ]);
                break;
            default:
                $view = View::factory('emails/email')
                    ->set('title', $content['header_plain'])
                    ->set('notification_url', $notification_url)
                    ->set('teaser', strip_tags($content['message']))
                    ->set('type', ucfirst($type))
                    ->set('post_link', $url . static::_post_uri())
                    ->set('content_view', $content['message']);

                $plainview = View::factory('emails/content/plaintext')
                      ->set('notification_url', $notification_url)
                      ->set('type', ucfirst($type))
                      ->set('post_link', $url . static::_post_uri())
                      ->set('content_header', $content['header_plain'])
                      ->set('content_message', $content['message_plain']);

                $this->emails[] = $email->send_email(
                        RT::NOTIFICATIONS, [
                            'ToAddresses' => [
                                $recipient['name'] . "<" . $recipient['email'] . ">"
                            ]
                        ], [
                            'Subject.Data'   => $content['header_plain'],
                            'Body.Text.Data' => $plainview->render(),
                            'Body.Html.Data' => $view->render(),
                        ], [
                            'returnCurlHandle' => true
                        ]);
                break;
        }

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
        //echo Debug::vars();
        foreach($items as $i)
        {
            if(isset($i['comment']))
            {
                $excerpt = $i['comment']->excerpt;
                $user    = $i['user'];
            }
        }
        //Kohana::$log->add('debug', static::_get_avatar($user));
        $summary  = static::_build_interaction_summary($recipient, $items, 'commented on ');
        $comment  = View::factory('emails/content/comment')
                        ->set('name', $user['name'])
                        ->set('avatar', static::_get_avatar($user))
                        ->set('comment', $excerpt);

        return static::_generic($summary, $comment, $recipient);
    }

    /**
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function feedback($recipient, $item)
    {
        $summary  = "thank you for your feedback.";

        $comment  = "We have received your email and will keep you posted.";

        return static::_generic($summary, $comment, $recipient);
    }

    /**
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function join_beta($recipient, $item)
    {
        //'We received your submission', Kohana::message('user/info', 'welcome_aboard')
        $summary  = "we received your submission!";

        $comment  = Kohana::message('user/info', 'thank_you_beta_email');

        return static::_generic($summary, $comment, $recipient);
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
        $summary  = static::_build_interaction_summary($recipient, $items, 'likes');

        return static::_generic($summary, "", $recipient);

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
        $summary  = static::_build_interaction_summary($recipient, $items, 'subscribed to ');

        return static::_generic($summary, "", $recipient);
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
        $summary  = static::get_counts($item)
                          ."wrote you a "
                          .static::_post_link('testimonial', 'testimonial')
                          ." in your profile page.";
        $content_message = "\n"."You can approve or remove this testimonial by following the link below.";

        if(isset($item['user']))
        {
            $user = $item['user'];
        }
        else
        {
            foreach($item as $i)
            {
                if(isset($i['user']['name']))
                {
                    $user = $i['user'];
                }
            }
        }
        //die(Debug::vars(static::$doc));
        $comment  = View::factory('emails/content/comment')
                        ->set('name', $user['name'])
                        ->set('avatar', static::_get_avatar($user['user']))
                        ->set('comment', static::$doc->excerpt);

        $content_message = $content_message . "\n" . $comment;

        return static::_generic($summary, $content_message, $recipient);
    }

    /**
     * Approval of testimonials. Just to inform the writer that it was approved
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function approve_testimonial($recipient, $item)
    {
        //echo Debug::vars($recipient);
        $summary  = static::get_counts($item)
                          ."approved the "
                          .static::_post_link('testimonial')
                          ." you wrote:";

        $comment  = View::factory('emails/content/comment')
                        ->set('name', 'You')
                        ->set('avatar', "")
                        ->set('comment', static::$doc->excerpt);

        return static::_generic($summary, $comment, $recipient);
    }

    /**
     * Process Wall posts
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $items the items by interaction type
     * @uses static::_generic() to generate content
     * @return NULL
     */
    public function wallpost($recipient, $item)
    {
        //echo Debug::vars($recipient);
        $summary  = static::get_counts($item)
                          ."shared a "
                          .static::_post_link('post')
                          ." on your profile page.";
        if(isset($item['user']))
        {
            $user = $item['user'];
        }
        else
        {
            foreach($item as $i)
            {
                if(isset($i['user']['name']))
                {
                    $user = $i['user'];
                }
            }
        }
        //die(Debug::vars(static::$doc));
        $comment  = View::factory('emails/content/comment')
                        ->set('name', $user['name'])
                        ->set('avatar', static::_get_avatar($user['user']))
                        ->set('comment', static::$doc->excerpt)
                        ->render();

        return static::_generic($summary, $comment, $recipient);
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
     * Used by all notification interaction emails. Creates a greeting, the plain text
     * version of the content and the HTML version of the content
     *
     * @param MongoDocument $recipient the user object for the recipient
     * @param array $item the item by interaction type
     * @uses static::_greeting to generate content
     * @return array
     */
    protected static function _generic($summary, $content, $recipient)
    {
        $greeting        = static::_greeting($recipient['first_name']);
        $content_header  = View::factory('emails/header/h4')
                               ->set('header', $greeting . " $summary");

        $content = View::factory('emails/content/standard')
                       ->set('content_header', $content_header)
                       ->set('content_message', $content)
                       ->render();

        return [
           'header_plain' => $greeting,
           'message_plain' => Inflector::br2nl($content_message),
           'message' => $content,
        ];
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
        $info = \Arr::get($message, 'user', $message);
        return $set_owner ? $info['name'] . "'s" : $info['name'];
    }

    /**
     * Builds the notification (unsubscribe) uri for a recipient
     *
     * @param MongoID $recipient_id the recipient id
     * @return string the generated uri
     */
    protected static function _notification_url($recipient_id)
    {
        return 'http://' . RT::APP_SERVER . '/' . $recipient_id . '/settings';
    }

    /**
     * Builds the post uri for the interacted document in question.
     *
     * @return string URI
     */
    protected static function _post_uri()
    {
        return "/post/?id=" . static::$job->document_id;
    }

    /**
     * Builds the post link for the interacted document in question.
     *
     * @return string the generated HTML link
     */
    protected static function _post_link($type = NULL)
    {
        if(!$type)
        {
            $type = static::$doc->type ? static::$doc->type : 'user';
        }

        $name = $type === 'user' ? 'profile page' : $type;
        $link = Inflector::singularize( (string) static::$doc->collection());

        return static::_anchor("/$link/?id=" . static::$job->document_id, $name);
    }

    /**
     * Builds the parent page link for the interacted document in question.
     *
     * @return string the generated HTML link
     */
    protected static function _page_link($noun = NULL)
    {
        $uri = \Arr::get(static::$doc->page, 'uri');
        $page = $uri
              ? ' in ' . static::_anchor($uri, static::$doc->page['name'])
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
        $user = isset(static::$doc->user) ? static::$doc->user : static::$doc->as_array();

        if((string) $user['_id'] === (string) $recipient['_id'])
            return "$adjective ";

        return $user['name'] . '\'s ';
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
        return HTML::anchor('http://' . RT::APP_SERVER . $uri, $name, ['target' => '_blank']);
    }

    /**
     * Small utility that extracts the avatar for a user.
     *
     * @param mixed Document|array $user
     * @return string the url for the avatar
     */
    protected static function _get_avatar($user)
    {
        if($user instanceof Document)
        {
            $user = $user->as_array();
        }

        $id   = (string) $user['_id'];
        $size = 40;
        $avtr = \Arr::get($user, 'avatar');

        if($avtr)
        {
            return 'http://' . str_replace($id, "$id/$size", $avtr);
        }
        $url = 'http://' . RT::BUCKET . '/avatars/';
        $gender = \Arr::get($user['info'], 'gender', 'male');
        return $url . strtoupper(substr($gender, 0, 1)) . $size . '.png';
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