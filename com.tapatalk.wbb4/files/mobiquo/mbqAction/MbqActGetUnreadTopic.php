<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActGetUnreadTopic');

/**
 * get_unread_topic action
 * 
 * @since  2012-8-16
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqActGetUnreadTopic extends MbqBaseActGetUnreadTopic {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * action implement
     */
    public function actionImplement() {
        parent::actionImplement();
    }
  
}

?>