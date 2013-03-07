<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Description of test
 *
 * @author Jason Nathan
 * @category RealtAsia
 * @package RT_
 * @subpackage Auth
 * @version 1.0
 * @copyright 2012 Realtasia Pte Ltd
 */
class Controller_Test extends Controller {
    public function action_index()
    {
                Collection::factory('ticker')->remove([
                        'document_id' => Document::check_id('5135a5996c01991526000011'),
                        'user_id' => ['$ne' => '5135a33e6c0199a341000000']
                ], ['multiple' => true]);
    }
}