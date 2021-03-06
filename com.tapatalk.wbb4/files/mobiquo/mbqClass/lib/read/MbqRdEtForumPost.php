<?php

use wbb\data\post\ThreadPostList;
use wbb\data\post\ViewablePost;
use wbb\data\post\Post;
use wbb\data\post\ViewablePostList;

use wcf\data\object\type\ObjectTypeCache;
use wcf\data\IMessage;
use wcf\system\application\ApplicationHandler;
use wcf\system\exception\SystemException;
use wcf\system\SingletonFactory;
use wcf\system\WCF;
use wcf\util\ArrayUtil;
use wcf\util\StringUtil;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtForumPost');

/**
 * forum post read class
 * 
 * @since  2012-8-13
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqRdEtForumPost extends MbqBaseRdEtForumPost {
    
    public function __construct() {
    }
    
    public function makeProperty(&$oMbqEtForumPost, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }
    
    /**
     * get forum post position
     *
     * @param  Object  $oMbqEtForumPost
     *
     * @return  Integer
     */
    public function exttGetForumPostPosition($oMbqEtForumPost) {
        $oThreadPostList = new ThreadPostList($oMbqEtForumPost->oMbqEtForumTopic->mbqBind['oViewableThread']->getDecoratedObject());
        $oThreadPostList->sqlOffset = 0;
        $oThreadPostList->sqlLimit = 1000000;   //get all the posts to use
        $oThreadPostList->readObjects();
        $ret = 1;
        foreach ($oThreadPostList->getObjects() as $oViewablePost) {
            if ($oViewablePost->getDecoratedObject()->postID == $oMbqEtForumPost->postId->oriValue) {
                return $ret;
            }
            $ret ++;
        }
        //not found
        return 1;
    }
    
    /**
     * get forum post objs
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byTopic' means get data by forum topic obj.$var is the forum topic obj.
     * $mbqOpt['case'] = 'byPostIds' means get data by post ids.$var is the ids.
     * $mbqOpt['case'] = 'byObjsViewablePost' means get data by objsViewablePost.$var is the objsViewablePost.
     * $mbqOpt['case'] = 'byReplyUser' means get data by reply user.$var is the MbqEtUser obj.
     * $mbqOpt['notGetAttachment'] = true means do not get attachment of forum post.
     * @return  Mixed
     */
    public function getObjsMbqEtForumPost($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'byTopic') {
            $oMbqEtForumTopic = $var;
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                //ref wbb\page\ThreadPage::initObjectList()
                $oThreadPostList = new ThreadPostList($var->mbqBind['oViewableThread']->getDecoratedObject());
                $oThreadPostList->sqlOffset = $oMbqDataPage->startNum;
                $oThreadPostList->sqlLimit = $oMbqDataPage->numPerPage;
                $oThreadPostList->readObjects();
                $oMbqDataPage->totalNum = $oThreadPostList->countObjects();
                /* common begin */
                $mbqOpt['case'] = 'byObjsViewablePost';
                $mbqOpt['oMbqDataPage'] = $oMbqDataPage;
                return $this->getObjsMbqEtForumPost($oThreadPostList->getObjects(), $mbqOpt);
                /* common end */
            }
        } elseif ($mbqOpt['case'] == 'byPostIds') {
            $objsViewablePost = array();
            $oViewablePostList = new ViewablePostList();
    		$oViewablePostList->setObjectIDs($var);
    		$oViewablePostList->readObjects();
            /* common begin */
            $mbqOpt['case'] = 'byObjsViewablePost';
            return $this->getObjsMbqEtForumPost($oViewablePostList->getObjects(), $mbqOpt);
            /* common end */
        } elseif ($mbqOpt['case'] == 'byReplyUser') {
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                $oViewablePostList = new ViewablePostList();
                $oViewablePostList->sqlJoins .= 'INNER JOIN wbb'.WCF_N.'_thread thread ON (post.threadID = thread.threadID AND thread.isAnnouncement = 0 AND post.postID != thread.firstPostID)'; //!!!
                $oViewablePostList->getConditionBuilder()->add('post.userID = ?', array($var->userId->oriValue));
        		$oViewablePostList->readObjects();
        		$oMbqDataPage->totalNum = $oViewablePostList->countObjects();
                /* common begin */
                $mbqOpt['case'] = 'byObjsViewablePost';
                $mbqOpt['oMbqDataPage'] = $oMbqDataPage;
                return $this->getObjsMbqEtForumPost($oViewablePostList->getObjects(), $mbqOpt);
                /* common end */
            }
        } elseif ($mbqOpt['case'] == 'byObjsViewablePost') {
            $objsViewablePost = $var;
            /* common begin */
            $objsMbqEtForumPost = array();
            $authorUserIds = array();
            $topicIds = array();
            foreach ($objsViewablePost as $oViewablePost) {
                $objsMbqEtForumPost[] = $this->initOMbqEtForumPost($oViewablePost, array('case' => 'oViewablePost'));
            }
            foreach ($objsMbqEtForumPost as $oMbqEtForumPost) {
                $authorUserIds[$oMbqEtForumPost->postAuthorId->oriValue] = $oMbqEtForumPost->postAuthorId->oriValue;
                //$topicIds[$oMbqEtForumPost->topicId->oriValue] = $oMbqEtForumPost->topicId->oriValue;
                //must use empty array key,otherwise can cause wbb systemException error when doing the following call:$oMbqRdEtForumTopic->getObjsMbqEtForumTopic($topicIds, array('case' => 'byTopicIds'));
                $topicIds[] = $oMbqEtForumPost->topicId->oriValue;  
            }
            /* load oMbqEtForumTopic property and oMbqEtForum property */
            $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
            $objsMbqEtFroumTopic = $oMbqRdEtForumTopic->getObjsMbqEtForumTopic($topicIds, array('case' => 'byTopicIds'));
            foreach ($objsMbqEtFroumTopic as $oNewMbqEtFroumTopic) {
                foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                    if ($oNewMbqEtFroumTopic->topicId->oriValue == $oMbqEtForumPost->topicId->oriValue) {
                        $oMbqEtForumPost->oMbqEtForumTopic = $oNewMbqEtFroumTopic;
                        if ($oMbqEtForumPost->oMbqEtForumTopic->oMbqEtForum) {
                            $oMbqEtForumPost->oMbqEtForum = $oMbqEtForumPost->oMbqEtForumTopic->oMbqEtForum;
                        }
                    }
                }
            }
            /* load post author */
            $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
            $objsAuthorMbqEtUser = $oMbqRdEtUser->getObjsMbqEtUser($authorUserIds, array('case' => 'byUserIds'));
            $postIds = array();
            foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                $postIds[] = $oMbqEtForumPost->postId->oriValue;
                foreach ($objsAuthorMbqEtUser as $oAuthorMbqEtUser) {
                    if ($oMbqEtForumPost->postAuthorId->oriValue == $oAuthorMbqEtUser->userId->oriValue) {
                        $oMbqEtForumPost->oAuthorMbqEtUser = $oAuthorMbqEtUser;
                        if ($oMbqEtForumPost->oAuthorMbqEtUser->isOnline->hasSetOriValue()) {
                            $oMbqEtForumPost->isOnline->setOriValue($oMbqEtForumPost->oAuthorMbqEtUser->isOnline->oriValue ? MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.isOnline.range.yes') : MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.isOnline.range.no'));
                        }
                        if ($oMbqEtForumPost->oAuthorMbqEtUser->iconUrl->hasSetOriValue()) {
                            $oMbqEtForumPost->authorIconUrl->setOriValue($oMbqEtForumPost->oAuthorMbqEtUser->iconUrl->oriValue);
                        }
                        break;
                    }
                }
            }
            /* load attachment */
            $oMbqRdEtAtt =  MbqMain::$oClk->newObj('MbqRdEtAtt');
            if (!$mbqOpt['notGetAttachment']) {
                $objsMbqEtAtt = $oMbqRdEtAtt->getObjsMbqEtAtt($postIds, array('case' => 'byForumPostIds'));
                foreach ($objsMbqEtAtt as $oMbqEtAtt) {
                    foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                        if ($oMbqEtForumPost->postId->oriValue == $oMbqEtAtt->postId->oriValue) {
                            $oMbqEtForumPost->objsMbqEtAtt[] = $oMbqEtAtt;
                        }
                    }
                }
            }
            /* load objsNotInContentMbqEtAtt */
            if (!$mbqOpt['notGetAttachment']) {
                foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                    $filedataids = MbqMain::$oMbqCm->getAttIdsFromContent($oMbqEtForumPost->postContent->oriValue);
                    foreach ($oMbqEtForumPost->objsMbqEtAtt as $oMbqEtAtt) {
                        if (!in_array($oMbqEtAtt->attId->oriValue, $filedataids)) {
                            $oMbqEtForumPost->objsNotInContentMbqEtAtt[] = $oMbqEtAtt;
                        }
                    }
                }
            }
            /* load objsMbqEtThank property and make related properties/flags */
            //
            /* make other properties */
            $oMbqAclEtForumPost = MbqMain::$oClk->newObj('MbqAclEtForumPost');
            foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                if ($oMbqAclEtForumPost->canAclSaveRawPost($oMbqEtForumPost)) {
                    $oMbqEtForumPost->canEdit->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.canEdit.range.yes'));
                } else {
                    $oMbqEtForumPost->canEdit->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.canEdit.range.no'));
                }
            }
            /* common end */
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                $oMbqDataPage->datas = $objsMbqEtForumPost;
                return $oMbqDataPage;
            } else {
                return $objsMbqEtForumPost;
            }
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * init one forum post by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'oViewablePost' means init forum post by oViewablePost
     * $mbqOpt['case'] = 'byPostId' means init forum post by post id
     * @return  Mixed
     */
    public function initOMbqEtForumPost($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'oViewablePost') {
            $oPost = $var->getDecoratedObject();
            $oMbqEtForumPost = MbqMain::$oClk->newObj('MbqEtForumPost');
            $oMbqEtForumPost->postId->setOriValue($oPost->postID);
            $oMbqEtForumPost->forumId->setOriValue($oPost->getThread()->boardID);
            $oMbqEtForumPost->topicId->setOriValue($oPost->threadID);
            $oMbqEtForumPost->postTitle->setOriValue($oPost->subject);
            $oMbqEtForumPost->postContent->setOriValue($oPost->getMessage());
            $oMbqEtForumPost->postContent->setAppDisplayValue($oPost->getFormattedMessage());
            $oMbqEtForumPost->postContent->setTmlDisplayValue($this->processContentForDisplay($var, true));
            $oMbqEtForumPost->postContent->setTmlDisplayValueNoHtml($this->processContentForDisplay($var, false));
            $oMbqEtForumPost->shortContent->setOriValue(MbqMain::$oMbqCm->getShortContent($oMbqEtForumPost->postContent->tmlDisplayValue));
            $oMbqEtForumPost->postAuthorId->setOriValue($oPost->userID);
            if ($oPost->isDisabled) {
                $oMbqEtForumPost->state->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.state.range.postOkNeedModeration'));
            } else {
                $oMbqEtForumPost->state->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.state.range.postOk'));
            }
            $oMbqEtForumPost->postTime->setOriValue($oPost->time);
            $oMbqEtForumPost->mbqBind['oViewablePost'] = $var;
            return $oMbqEtForumPost;
        } elseif ($mbqOpt['case'] == 'byPostId') {
            $objsMbqEtForumPost = $this->getObjsMbqEtForumPost(array($var), array('case' => 'byPostIds'));
            if ($objsMbqEtForumPost) {
                return $objsMbqEtForumPost[0];
            } else {
                return false;
            }
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * process content for display in mobile app
     *
     * @params  Object  $var $var is $oViewablePost or $oViewableConversationMessage
     * @params  Boolean  $returnHtml
     * @return  String
     */
    public function processContentForDisplay($var, $returnHtml) {
        /*
        support bbcode:url/img/quote
        support html:br/i/b/u/font+color(red/blue)
        <strong> -> <b>
        attention input param:return_html
        attention output param:post_content
        */
        $post = $var->getDecoratedObject()->getFormattedMessage();
        if ($returnHtml) {
            //handle quote
            $post = preg_replace('/<blockquote class="container containerPadding quoteBox"[^>]*?>.*?<header>.*?<h3>.*?<a href="[^>]*?">(.*?) wrote:<\/a>.*?<\/h3>.*?<\/header>.*?(.*?)<\/blockquote>/is', '$1 wrote:[quote]$2[/quote]', $post);
            //handle smilies
            //...
            // b/i/u
    	    $post = preg_replace('/<span style="text-decoration: underline">(.*?)<\/span>/i', '<u>$1</u>', $post);
            // ol/li ul/li
    	    $post = str_ireplace('<li>', "\t\t<li>", $post);
    	    $post = str_ireplace('</li>', "</li><br />", $post);
            //font color
    	    $post = preg_replace_callback('/<span style="color: (\#.*?)">(.*?)<\/span>/is', create_function('$matches','return MbqMain::$oMbqCm->mbColorConvert($matches[1], $matches[2]);'), $post);
    	    //link image email
	        $post = preg_replace('/<img [^>]*?src="([^>]*?)"[^>]*?\/>/i', '[img]$1[/img]', $post);
	        $post = preg_replace('/<a [^>]*?href="mailto:([^>]*?)"[^>]*?>([^>]*?)<\/a>/i', '[url=$1]$2[/url]', $post);
	        $post = preg_replace('/<a [^>]*?href="([^>]*?)"[^>]*?>([^>]*?)<\/a>/i', '[url=$1]$2[/url]', $post);
            
    	    //table
    	    $post = str_ireplace('</tr>', '</tr><br />', $post);
    	    $post = str_ireplace('</td>', "</td>\t\t", $post);
    	    //other
        	$post = str_ireplace('<strong>', '<b>', $post);
        	$post = str_ireplace('</strong>', '</b>', $post);
            $post = str_ireplace('<hr />', '<br />____________________________________<br />', $post);
    	    $post = preg_replace('/<object .*?>.*?<embed src="(.*?)".*?><\/embed><\/object>/is', '[url=$1]$1[/url]', $post); /* for youtube content etc. */
	        $post = str_ireplace('</div>', '</div><br />', $post);
	        $post = str_ireplace('&nbsp;', ' ', $post);
    	    $post = strip_tags($post, '<br><i><b><u><font>');
        } else {
    	    $post = strip_tags($post);
        }
    	$post = trim($post);
    	return $post;
    }
    
    /**
     * return quote post content
     *
     * @param  Object  $oMbqEtForumPost
     * @return  String
     */
    public function getQuotePostContent($oMbqEtForumPost) {
        //ref wcf\system\message\quote\MessageQuoteManager::renderQuote()
        $oPost = $oMbqEtForumPost->mbqBind['oViewablePost']->getDecoratedObject();
		$escapedUsername = StringUtil::replace(array("\\", "'"), array("\\\\", "\'"), $oPost->getUsername());
		$escapedLink = StringUtil::replace(array("\\", "'"), array("\\\\", "\'"), $oPost->getLink());
		return "[quote='".$escapedUsername."','".$escapedLink."']".$oMbqEtForumPost->postContent->oriValue."[/quote]";
    }
  
}

?>