<?php
use Vast\Repo\VastUser\VastUserInterface;
use Vast\Repo\VastSession\VastSessionInterface;
use Vast\Repo\Session\SessionInterface;
use Vast\Repo\Like\LikeInterface;
use Vast\Repo\Follow\FollowInterface;
use Vast\Repo\Subscribe\SubscribeInterface;
use Vast\Repo\Channel\ChannelInterface;
use Vast\Repo\Device\DeviceInterface;
use Vast\Repo\Post\PostInterface;
use Vast\Repo\Chapter\ChapterInterface;
use Vast\Repo\ReadMessage\ReadMessageInterface;
use Vast\Repo\ReadNotification\ReadNotificationInterface;
use Vast\Repo\Share\ShareInterface;
use Vast\Repo\Version\VersionInterface;
use Vast\Repo\Manageexplore\ManageexploreInterface;
use Vast\Repo\AdvastShare\AdvastShareInterface;
use Vast\Repo\AdvastView\AdvastViewInterface;
use Vast\Repo\Message\MessageInterface;
use Vast\Repo\User\UserInterface;
use Vast\Repo\EventCheckin\EventCheckinInterface;
use Vast\Repo\EventBouncer\EventBouncerInterface;
use Vast\Repo\Conversion\ConversionInterface;
use Vast\Repo\VastShare\VastShareInterface;
use Vast\Repo\SlideShow\SlideShowInterface;
use Vast\OAuth\OAuthUtil;
use Aws\Common\Credentials\Credentials;
use Aws\Sts\StsClient;
use Aws\S3\S3Client;
use Doctrine\Common\Cache\FilesystemCache;
use Guzzle\Cache\DoctrineCacheAdapter;
use Illuminate\Support\Facades\Log;

class VastController extends BaseController
{
	protected $vastuser, $session, $like, $follow, $subscribe, $channel, $device, $vpost, $chapter, $readmessage, $readnotification, $share, $version, $event, $manageexp, $advastshare, $advastview,$slideshow;

	private $verifyPassword = '6670b3cb498c4abb94ba2c12360d2d79';

    private $verifyReceiptUrl = 'https://buy.itunes.apple.com/verifyReceipt';

	protected $data = array();

	public function __construct(VastUserInterface $vastuser, VastSessionInterface $session, LikeInterface $like, FollowInterface $follow, SubscribeInterface $subscribe, ChannelInterface $channel, DeviceInterface $device, PostInterface $vpost, ChapterInterface $chapter, ReadMessageInterface $readmessage, ReadNotificationInterface $readnotification, ShareInterface $share, VersionInterface $version, EventBouncerInterface $event, ManageexploreInterface $manageexp, AdvastShareInterface $advastshare, AdvastViewInterface $advastview, MessageInterface $message, EventCheckinInterface $eventcheckin, ConversionInterface $conversion, VastShareInterface $vastshare, SlideShowInterface $slideshow)
	{
		$this->vastuser = $vastuser;
		$this->session = $session;
		$this->like = $like;
		$this->follow = $follow;
		$this->subscribe = $subscribe;
		$this->channel = $channel;
		$this->device = $device;
		$this->vpost = $vpost;
		$this->chapter = $chapter;
		$this->readmessage = $readmessage;
		$this->readnotification = $readnotification;
		$this->share = $share;
		$this->version = $version;
		$this->event = $event;
		$this->manageexp = $manageexp;
		$this->advastshare = $advastshare;
		$this->advastview = $advastview;
		$this->message	= $message;
		$this->eventcheckin	= $eventcheckin;
		$this->conversion =	$conversion;
		$this->vastshare = $vastshare;
		$this->slideshow = $slideshow;
	}

	/**
     * Verify itunes store receipt
     * @param string Transaction receipt
     * @return boolean
     */
    private function verifyStoreReceipt($transactionReceipt, $try = 1, $sandbox = false)
    {
        if ($sandbox) {
            $this->verifyReceiptUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';
        }
        $receipt = json_encode(array(
            'receipt-data' => $transactionReceipt,
            'password' => $this->verifyPassword
        ));

        $xmlResponse = Util::sendRequest($this->verifyReceiptUrl, true, $receipt);
        $response    = json_decode($xmlResponse, true);

        // If response is 21007 then check again
        if ($response['status'] == '21007') {
            if ($try == 1) {
                if (!$sandbox) {
                    return $this->verifyStoreReceipt($transactionReceipt, 2, true);
                } else {
                    return $this->verifyStoreReceipt($transactionReceipt, 2, false);
                }
            } else {
                return $response;
            }
            // If response is 21005 then check couple of times
        } else if ($response['status'] == '21005') {
            if ($try == 1 || $try == 2) {
                return $this->verifyStoreReceipt($transactionReceipt, $try++);
            } else {
                return $response;
            }
        } else {
            return $response;
        }
    }

	/* Escape string function
	 * sanitize other languages
	 */
	public function escapeStr($str = '')
	{
		return htmlentities($str, ENT_NOQUOTES, "UTF-8", false);
	}

	/**
     * Error messages
	 * @return json
	 */
	public function failure($errorCode, $errorDesc)
	{
		$response = array(
			'result' => 'failure',
			'error' => array(
				'code' => $errorCode,
				'description' => $errorDesc
			)
		);

		return Response::json($response);
	}

	/**
	 * Check whether the email is valid
	 * @param string $email
	 * @return number
	*/
	public function isValidEmail($email)
	{
		return preg_match('/^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,4})$/', $email);
	}

	/**
	 * User Information
	 * @param string $user
	 * @return string
	*/
	private function userInfo($channelId)
	{
		$usrInfo = array();

		$channel	=	$this->vastuser->getChannelVastUserById($channelId);
		if(!empty($channel)) {
			$channel 				= 	call_user_func_array('array_merge', $channel);
			$usrInfo['id']         	= 	(string) $channel['channel_id'];
			$usrInfo['first_name'] 	= 	$this->escapeStr($channel['first_name']);
			$usrInfo['last_name']  	= 	$this->escapeStr($channel['last_name']);
			$usrInfo['vast_name']  	= 	$channel['username'];
			$usrInfo['bio']        	= 	$channel['biography'];
			$usrInfo['email']       =   $channel['email_id'];
			$usrInfo['birthday']    = 	$channel['birthday'];
			$usrInfo['location']    = 	$channel['location'];
			$usrInfo['phone']       = 	$channel['phone'];
			$usrInfo['tour']       	= 	$channel['tour_url'];
			$usrInfo['store']      	= 	$channel['store_url'];
			$usrInfo['ticket']      = 	$channel['ticket_url'];
			$usrInfo['spotify']    	= 	$channel['spotify_url'];
			$usrInfo['deezer']     	= 	$channel['deezer_url'];
			$usrInfo['tidal']       = 	$channel['tidal_url'];
			$usrInfo['beats']      	= 	$channel['beats_url'];
			$usrInfo['itunes']     	= 	$channel['itunes_url'];
			$usrInfo['twitter']    	= 	$channel['twitter_url'];
			$usrInfo['youtube']    	= 	$channel['youtube_url'];
			$usrInfo['facebook']    = 	$channel['fb_url'];
			$usrInfo['instagram']   = 	$channel['instagram_url'];
			$usrInfo['tumblr']    	= 	$channel['tumblr_url'];
			$usrInfo['website']    	= 	$channel['website_url'];
			$usrInfo['avatar']     	= 	($channel['avatar']=="") ? '' : S3::getS3Url(S3::getChannelAvatarPath("sd/".$channel['avatar']));
			$usrInfo['cover']     	= 	($channel['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover("sd/".$channel['channel_cover']));

			$channelId			 	=	$channel['channel_id'];
			$followNum			 	=	$this->follow->getFollowersCountByChannel($channelId);

			$usrInfo['followers']	 	= 	$followNum;

			$sublId					=	$channel['subscription_id'];
			$subscribeNum		 	=	$this->subscribe->getSubscriptionBySubId($sublId);

			$usrInfo['subscribers'] 	= 	$subscribeNum;

			$followedChannels = $this->follow->getFollowChannelByUser($channel['vast_user_id']);
			$usrInfo['following'] = count($followedChannels);

			$usrInfo['subscription_id'] = $channel['subscription_id'];

			$chapters 				= 	array();

			$chapterQry				=	$this->chapter->getChapterListByChannelId($channelId);
			foreach($chapterQry as $chapter) {
				$chapters[] = array('id' => (string) $chapter['chapter_id'], 'name' => $this->escapeStr($chapter['chapter_name']));
			}

			$usrInfo['chapters'] 		= 	$chapters;

			$liveInfo               =   array();
			$liveCover 				= 	array();
			$liveThumb              =   array();
			$liveThmb               =   array();
			$liveSd                 =   array();

			$liveInfo['name'] 			= 	$channel['livestream_name'];
			$liveInfo['title'] 			= 	$channel['livestream_title'];
			$liveInfo['event'] 			= 	$channel['livestream_event'];
			$liveInfo['subscribers_only'] = 	$channel['live_featured'];

			$liveThumb['width'] 			= 	($channel['live_thumb_width']=="") ? '' : $channel['live_thumb_width'];
			$liveThumb['height'] 			= 	($channel['live_thumb_height']=="") ? '' : $channel['live_thumb_height'];
			$lthumb_cover 				= 	preg_replace("~[\r\n]~", "",$channel['live_thumb_url']);
			$liveThumb['url'] 			= 	($channel['live_thumb_url']=="") ? '' :	S3::getS3Url(S3::getLivestreamThumbPath($lthumb_cover));
			$liveThmb['width'] 			= 	($channel['live_hdthumb_width']=="") ? '' : $channel['live_hdthumb_width'];
			$liveThmb['height'] 			= 	($channel['live_hdthumb_height']=="") ? '' : $channel['live_hdthumb_height'];
			$lhdthumb_cover 			= 	preg_replace("~[\r\n]~", "",$channel['live_hdthumb_url']);
			$liveThmb['url'] 				= 	($channel['live_hdthumb_url']=="") ? '' : S3::getS3Url(S3::getLivestreamHdThumbPath($lhdthumb_cover));
			$liveSd['width'] 				= 	($channel['live_sd_width']=="") ? '' : $channel['live_sd_width'];
			$liveSd['height'] 			= 	($channel['live_sd_height']=="") ? '' : $channel['live_sd_height'];
			$lsd_cover 					= 	preg_replace("~[\r\n]~", "",$channel['live_sd_url']);
			$liveSd['url'] 				= 	($channel['live_sd_url']=="") ? '' : S3::getS3Url(S3::getLivestreamSdPath($lsd_cover));
			$liveCover[] 				= 	$liveThumb;
			$liveCover[] 				= 	$liveThmb;
			$liveCover[] 				= 	$liveSd;

			$liveInfo['cover'] 			= 	$liveCover;

			$usrInfo['livestream'] 		= 	$liveInfo;

			$social 					= 	$this->vastuser->getTokenDetails($channelId);
			$socialAccount 				= 	array();
			if(!empty($social)){
				$social 				= 	call_user_func_array('array_merge', $social);
				if($social['fb_token']!="") {
					$socialAccount[] = 'fb';
				}
				if($social['twitter_token']!="") {
					$socialAccount[] = 'tw';
				}
				if($social['tumblr_token']!="") {
					$socialAccount[] = 'tm';
				}
				$usrInfo['social'] = $socialAccount;
			} else {
				$usrInfo['social'] = $socialAccount;
			}
			$postLike = array();
			$likedPosts = $this->like->getLikesByUser($channel['vast_user_id']);
			foreach($likedPosts as $posts) {
				//liked post id
				$postLike[] = (string) $posts['post_id'];
			}
			$usrInfo['likes'] = $postLike;

			$channelFollow = array();
			$followedChannels = $this->follow->getFollowChannelByUser($channel['vast_user_id']);
			foreach($followedChannels as $follows) {
				//followed channel_id
				$channelFollow[] = (string) $follows['channel_id'];
			}
			$usrInfo['followed'] = $channelFollow;

			$activeSub = array();
			$userSubscriptions = $this->subscribe->getSubscriptionByUser($channel['vast_user_id']);
			foreach($userSubscriptions as $subscribe) {
				$verify = $this->verifyStoreReceipt($subscribe['receipt']);
				$arr = array();
				if($verify['latest_receipt_info']) {
					foreach($verify['latest_receipt_info'] as $item) {
						$arr[$item['product_id']]['item'] = $item;
						$arr[$item['product_id']]['expiry'] = (isset($arr[$item['product_id']]['expiry']) && strtotime($arr[$item['product_id']]['expiry']) > strtotime($item['expires_date']))	?	$arr[$item['product_id']]['expiry']	:	$item['expires_date'];
					}
					foreach($arr as $key => $value) {
						$expiry = $value['expiry'];
						if(strtotime($expiry) < strtotime($verify['receipt']['request_date'])) {
							$this->subscribe->deleteSubscription($key, $channel['vast_user_id']);
						} else {
							$num = $this->subscribe->getNumSubscriptionByUser($key, $channel['vast_user_id']);
							if($num == 0) {
								$this->subscribe->create(array(
									'active_subscription' => $key,
									'user_id' => $channel['vast_user_id'],
									'receipt' => $verify['latest_receipt']
								));
							}
						}
					}
				}
				if($verify['status']==21002) {
					$this->subscribe->deleteSubscriptionByReceipt($subscribe['receipt'], $channel['vast_user_id']);
				}
				if($verify['status']==21006) {
					$this->subscribe->deleteSubscription($key, $channel['vast_user_id']);
				}
				$sub = $this->subscribe->getSubscriptionByUser($channel['vast_user_id']);
				if(count($sub) > 0) {
					//active subscription ids
					$activeSub[] = $sub[0]['active_subscription'];
				}
			}
			$usrInfo['active_subscription_ids'] = $activeSub;
			$usrInfo['private'] = (string) $channel['inactive'];

			return $usrInfo;
		}
	}

	/**
	 * UserFeed Post Info
	 * @return json
	 */
	private function userFeedPostInfo($post, $type)
	{
		$postInfo 		= 	array();
		$postThumb 		= 	array();
		$postThmb 		= 	array();
		$postSd 		= 	array();
		$postContents	=	array();
		$postImage 		= 	array();
		$postImg 		= 	array();
		$location 		= 	array();
		$thumb 			= 	array();
		$image 			= 	array();
		$content        =   array();

		$postInfo['id'] 	= 	(string) $post['post_id'];
		$postInfo['type']   = 	$post['type'];
		$postThumb['width']	=	($post['thumb_width']=="") ? '256' : (string) $post['thumb_width'];
		$postThumb['height']= 	($post['thumb_height']=="") ? '192' : (string) $post['thumb_height'];
		$thumb_url 			= 	preg_replace("~[\r\n]~", "",$post['thumb_url']);
		$postThumb['url'] 	= 	($post['thumb_url']=="") ? URL::asset('images/no_image.png') : S3::getS3Url(S3::getPostPhotoPath($thumb_url));
		$postThmb['width'] 	= 	($post['hdthumb_width']=="") ? '512' : (string) $post['hdthumb_width'];
		$postThmb['height'] = 	($post['hdthumb_height']=="") ? '384' : (string) $post['hdthumb_height'];
		$hdthumb_url 		= 	preg_replace("~[\r\n]~", "",$post['hdthumb_url']);
		$postThmb['url'] 	= 	($post['hdthumb_url']=="") ? URL::asset('images/no_image.png') : S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url));
		$postSd['width'] 	= 	($post['sd_width']=="") ? '1024' : (string) $post['sd_width'];
		$postSd['height'] 	= 	($post['sd_height']=="") ? '768' : (string) $post['sd_height'];
		$sd_url 			= 	preg_replace("~[\r\n]~", "",$post['sd_url']);
		$postSd['url'] 		= 	($post['sd_url']=="") ? URL::asset('images/no_image.png') : S3::getS3Url(S3::getPostSdPath($sd_url));
		$thumb[] 			= 	$postThumb;
		$thumb[] 			= 	$postThmb;
		$thumb[] 			= 	$postSd;
		$postInfo['thumbnail']	=	$thumb;
		// unset($postThumb);
		// unset($postThmb);
		// unset($postSd);
		// unset($thumb);

		if($post['type'] == 'image') {
			if($post['audio_url']!="") {
				$audio_url 				= 	preg_replace("~[\r\n]~", "",$post['audio_url']);
				$postContents['audio'] 	= 	S3::getS3Url(S3::getSecAudioPath($audio_url));
			}
			if($post['second_video']!="") {
				$secvideo_url 			= 	preg_replace("~[\r\n]~", "",$post['second_video']);
				$postContents['video'] 	= 	S3::getS3Url(S3::getSecVideoPath($secvideo_url));
			}
			$postImage['width'] 	= 	($post['retina_width']=="") ? '' : (string) $post['retina_width'];
			$postImage['height'] 	= 	($post['retina_height']=="") ? '' : (string) $post['retina_height'];
			$retina_url 		= 	preg_replace("~[\r\n]~", "",$post['retina_url']);
			$postImage['url'] 	= 	($post['retina_url']=="") ? '' : S3::getS3Url(S3::getPostRetinaPath($retina_url));
			$postImg['width'] 	= 	($post['nonretina_width']=="") ? '' : (string) $post['nonretina_width'];
			$postImg['height'] 	= 	($post['nonretina_height']=="") ? '' : (string) $post['nonretina_height'];
			$nonretina_url 		= 	preg_replace("~[\r\n]~", "",$post['nonretina_url']);
			$postImg['url'] 		= 	($post['nonretina_url']=="") ? '' : S3::getS3Url(S3::getPostNonretinaPath($nonretina_url));
			$image[] 			= 	$postImage;
			$image[] 			=	$postImg;
			$postContents['image']	=	$image;
			//unset($image);
		}
		if($post['type'] == 'video') {
			$video_url 			=	preg_replace("~[\r\n]~", "",$post['video_url']);
			$postContents['url']	=	($post['video_url']=="") ? '' : S3::getS3Url(S3::getPostVideoPath($video_url));
			if($post['audio_url']!="") {
				$audio_url 				= 	preg_replace("~[\r\n]~", "",$post['audio_url']);
				$postContents['audio'] 	= 	S3::getS3Url(S3::getSecAudioPath($audio_url));
			}
			if($post['second_video']!="") {
				$secvideo_url 			= 	preg_replace("~[\r\n]~", "",$post['second_video']);
				$postContents['video'] 	= 	S3::getS3Url(S3::getSecVideoPath($secvideo_url));
			}
		}
		if($post['type'] == 'music') {
			$music_url 				=	preg_replace("~[\r\n]~", "",$post['music_url']);
			$postContents['url'] 		= 	($post['music_url']=="") ? '' : S3::getS3Url(S3::getPostMusicPath($music_url));
			$postContents['song'] 	= 	($post['song']=="") ? '' : $post['song'];
			$postContents['author']	= 	($post['author']=="") ? '' : $post['author'];
			$postContents['album'] 	= 	($post['album']=="") ? '' : $post['album'];
			$postContents['itunes_link']	=	($post['itunes_link']=="") ? '' : $post['itunes_link'];
			if($post['second_video']!="") {
				$secvideo_url 			= 	preg_replace("~[\r\n]~", "",$post['second_video']);
				$postContents['video'] 	= 	S3::getS3Url(S3::getSecVideoPath($secvideo_url));
			}
		}
		if($type == 'userFeed' || $type == 'explore' || $type == 'homepage' || $type == 'homepages' || $type == 'contents' || $type == 'editChapter')  {
			if($post['type'] == 'event') {
				$evt	=	$this->event->getUpdateTicket($post['post_id']);
				if(!empty($evt))
					$evt	=	call_user_func_array('array_merge', $evt);
				$postContents['type'] = ($evt['event_type']==0) ? 'vip' : 'merch';
				$postContents['code'] = $evt['event_code'];
				$postContents['name'] = $post['title'];
				$postContents['date'] = $evt['expire_date'];

				$postLoc	=	explode(',', $evt['location']);

				$location['country']  =	(!isset($postLoc[0]) || $postLoc[0]=="undefined" || $postLoc[0]=="") ? '' : $postLoc[0];
				$location['region'] = 	(!isset($postLoc[1]) || $postLoc[1]=="undefined" || $postLoc[1]=="") ? '' : $postLoc[1];
				$location['city'] 	= 	(!isset($postLoc[2]) || $postLoc[2]=="undefined" || $postLoc[2]=="") ? '' : $postLoc[2];
				$location['place'] 	= 	(!isset($postLoc[3]) || $postLoc[3]=="undefined" || $postLoc[3]=="") ? '' : $postLoc[3];
				$location['latitude'] = 	(!isset($postLoc[4]) || $postLoc[4]=="undefined" || $postLoc[4]=="") ? '' : $postLoc[4];
				$location['longitude']= 	(!isset($postLoc[5]) || $postLoc[5]=="undefined" || $postLoc[5]=="") ? '' : $postLoc[5];

				$postContents['location'] 	= 	$location;
				$postContents['tickets_num'] = 	(string) $evt['avail_tickets'];
			}
		}



		$postInfo['contents']	=	$postContents;
		//unset($postContents);
		$postInfo['date'] 	= 	($post['date']==0) ? time() : $post['date'];

		//$content			=	new stdClass();

		//channelInfo
		if($type == 'contents' || $type == 'homepage' || $type == 'homepages' )  {
			if ($type == 'contents') {
				$postInfo['channel_id'] 	= (string) $post['channel_id'];
				$content['channelID'] 	= (string) $post['channel_Id'];
			} else {
				$postInfo['channel_id'] 	= (string) $post['channel_id'];
			}
		}
		if($type == 'userFeed' || $type == 'explore' || $type == 'notifications' || $type == 'userFav' || $type == 'getChannelGroup' || $type == 'editChapter' || $type== 'vip_content') {
			$postInfo['channel_info']	= $this->channelInfo($post, $type);
		}

		/* $cptrData		=	$this->chapter->getChapterDetailsById($post['chapter_id']);
		if(!empty($cptrData))
			$cptrData	=	call_user_func_array('array_merge', $cptrData);
		//$content['channelID'] = $cptrData['channel_id'];
		$content['id']	=	(string) $cptrData['chapter_id'];
		$content['name'] 	= 	$cptrData['chapter_name']; */

		if($type	==	'getChannelGroup' || $type == 'explore' || $type == 'homepage' || $type == 'notifications' || $type == 'editChapter' || $type == 'schedulePost')	{
			$cptrData		=	$this->chapter->getChapterDetailsById($post['chapter_id']);
			if(!empty($cptrData))
				$cptrData	=	call_user_func_array('array_merge', $cptrData);
			$content['id']	=	(string) $cptrData['chapter_id'];
			$content['name'] 	= 	$cptrData['chapter_name'];
		} else {
			if($type == 'contents' || $type == 'homepages') {
				$content['id'] 	= (string) $post['chapter_Id'];
				$content['name']= $post['chapter_Name'];
			} else {
				$content['id'] 	= (string) $post['chapterId'];
				$content['name']= $post['chapterName'];
			}
		}

		$postInfo['chapter']		=	$content;
		//unset($content);
		$postInfo['aspect_ratio']	=	($post['aspect_ratio']=="") ? '' : $post['aspect_ratio'];

		$postInfo['poi'] 			= 	($post['poix']=="" || $post['poix']==0) ? '{0.5, 0.5}' : '{'.$post['poix'].', '.$post['poiy'].'}';
		$postInfo['post_url'] 	    = 	($post['short_url']=="") ? '' : $post['short_url'];

		if($type	==	'getChannelGroup' || $type == 'explore' || $type == 'contents' || $type == 'homepage' || $type == 'homepages' || $type == 'notifications' || $type == 'schedulePost' || $type == 'editChapter')	{
			$likesCount 		= 	$this->like->getLikesByPostId($post['post_id']);
			$postInfo['likes']	=	$likesCount;
		} else {
			$postInfo['likes'] 	= 	(string) $post['num'];
		}

		if($post['title']!='') {
			$postInfo['title'] 	= 	preg_replace("~[\r\n]~", "",$post['title']);
		}
		if($post['story']!='') {
			$descr 				= 	(preg_replace("~[\r\n]~", "",$post['story']));
			$postInfo['text'] 	= 	($post['story']=="") ? '' : html_entity_decode($descr);

		}
		if($type == 'explore' || $type == 'contents' || $type == 'homepage' || $type == 'homepages' || $type == 'notifications' || $type == 'schedulePost' || $type == 'editChapter') {
			$post['subscribeId']	=	$post['subscription_id'];
		}

		if(is_null($post['subscribeId']) || ($post['subscribeId']=="")) {
			$postInfo['required_subscription']	=	'';
		} else {
			if ($type	==	'getChannelGroup' || $type == 'explore' || $type == 'contents' || $type == 'homepage' || $type == 'homepages' || $type == 'notifications' || $type == 'editChapter' || $type == 'schedulePost') {
				$ch	=	$this->channel->getChannelDetailsByChannelId($post['channel_id']);
				if(!empty($ch))
					$ch	=	call_user_func_array('array_merge', $ch);
				$postInfo['required_subscription'] = (string) $ch['subscription_id'];
			} else {
				$postInfo['required_subscription'] = (string) $post['subId'];
			}
		}


		return $postInfo;
	}

	/**
	 * Channel Information
	 * @return json
	 */
	private function channelInfo($chnl, $type)
	{

		$channelInfo 	= array();
		$channelSocial 	= array();
		$channelLive 	= array();
		$coverThumb 	= array();
		$coverThmb 		= array();
		$coverSd 		= array();
		$liveThumb 		= array();
		$liveThmb 		= array();
		$liveSd 		= array();
		$profileThumb 	= array();
		$profileThmb 	= array();
		$profileSd 		= array();
		$cover 			= array();
		$liveCover 		= array();
		$profile 		= array();

		if($type	==	'getChnlGroup' || $type == 'explore' || $type == 'notifications' || $type == 'myPosts' || $type == 'editChapter' || $type == 'vip_content') {
			$chanlData 	= 	$this->channel->getChannelDetailsByChannelId($chnl['channel_id']);
			if(!empty($chanlData))
				$chnl	=	call_user_func_array('array_merge', $chanlData);
		}
		$channelInfo['id'] 			= (string) $chnl['channel_id'];
		$channelInfo['first_name']	= $chnl['first_name'];
		$channelInfo['last_name'] 	= $chnl['last_name'];
		$channelInfo['vast_name']   = $chnl['vast_name'];
		$avatar 					= preg_replace("~[\r\n]~", "",$chnl['avatar']);
		$channelInfo['avatar'] 		= ($chnl['avatar']=="") ? '' : S3::getS3Url(S3::getTwitterAvatar($avatar)); //AVATAR_SD_PATH . $avatar;
		$channelInfo['bio']         = $chnl['biography'];
		$profileThumb['width'] 		= ($chnl['channel_cover_thumbW']=="" || $chnl['channel_cover_thumbW']==0) ? '' : (string) $chnl['channel_cover_thumbW'];
		$profileThumb['height'] 		= ($chnl['channel_cover_thumbH']=="" || $chnl['channel_cover_thumbH']==0) ? '' : (string) $chnl['channel_cover_thumbH'];
		$thumb_profile 				= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
		$profileThumb['url'] 		= ($chnl['channel_cover']=="") ? '' :S3::getS3Url(S3::getAppCover('thumb/'.$thumb_profile ));//PROFILE_THUMB_PATH .$thumb_profile;
		$profileThmb['width'] 		= ($chnl['channel_cover_hd_thumbW']=="" || $chnl['channel_cover_hd_thumbW']==0) ? '' : (string) $chnl['channel_cover_hd_thumbW'];
		$profileThmb['height'] 		= ($chnl['channel_cover_hd_thumbH']=="" || $chnl['channel_cover_hd_thumbH']==0) ? '' : (string) $chnl['channel_cover_hd_thumbH'];
		$hdthumb_profile 			= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
		$profileThmb['url'] 		= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('hdthumb/'.$hdthumb_profile)); //PROFILE_HDTHUMB_PATH . $hdthumb_profile;
		$profileSd['width'] 		= ($chnl['channel_cover_sdW']=="" || $chnl['channel_cover_sdW']==0) ? '' : (string) $chnl['channel_cover_sdW'];
		$profileSd['height'] 		= ($chnl['channel_cover_sdH']=="" || $chnl['channel_cover_sdH']==0) ? '' : (string) $chnl['channel_cover_sdH'];
		$sd_profile 				= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
		$profileSd['url'] 			= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('sd/'.$sd_profile)); //PROFILE_SD_PATH . $sd_profile;
		$profile[] 					= $profileThumb;
		$profile[] 					= $profileThmb;
		$profile[] 					= $profileSd;

		$channelInfo['profile_image'] = $profile;
		unset($profileThumb);
		unset($profileThmb);
		unset($profileSd);
		unset($profile);
		if($type == 'userFeed' || $type == 'userFav') {
			if($chnl['channel_cover'] != "") {
				$coverThumb['width'] 		= ($chnl['channel_cover_thumbW']=="" || $chnl['channel_cover_thumbW']==0) ? '' : (string) $chnl['channel_cover_thumbW'];
				$coverThumb['height'] 		= ($chnl['channel_cover_thumbH']=="" || $chnl['channel_cover_thumbH']==0) ? '' : (string) $chnl['channel_cover_thumbH'];
				$thumb_profile 				= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
				$coverThumb['url'] 		= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('thumb/'.$thumb_profile )); //PROFILE_THUMB_PATH .$thumb_profile;
				$coverThmb['width'] 		= ($chnl['channel_cover_hd_thumbW']=="" || $chnl['channel_cover_hd_thumbW']==0) ? '' : (string) $chnl['channel_cover_hd_thumbW'];
				$coverThmb['height'] 		= ($chnl['channel_cover_hd_thumbH']=="" || $chnl['channel_cover_hd_thumbH']==0) ? '' : (string) $chnl['channel_cover_hd_thumbH'];
				$hdthumb_profile 			= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
				$coverThmb['url'] 		= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('hdthumb/'.$hdthumb_profile)); //PROFILE_HDTHUMB_PATH . $hdthumb_profile;
				$coverSd['width'] 		= ($chnl['channel_cover_sdW']=="" || $chnl['channel_cover_sdW']==0) ? '' : (string) $chnl['channel_cover_sdW'];
				$coverSd['height'] 		= ($chnl['channel_cover_sdH']=="" || $chnl['channel_cover_sdH']==0) ? '' : (string) $chnl['channel_cover_sdH'];
				$sd_profile 				= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
				$coverSd['url'] 			= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('sd/'.$sd_profile)); //PROFILE_SD_PATH . $sd_profile;
				$cover[] 					= $coverThumb;
				$cover[] 					= $coverThmb;
				$cover[] 					= $coverSd;

				$channelInfo['cover'] = $cover;
				unset($coverThumb);
				unset($coverThmb);
				unset($coverSd);
				unset($cover);
				$channelInfo['poi'] = '{0.5, 0.5}';
			} else {
				$post	=	$chnl;
				$coverThumb['width'] = ($post['thmbWidth']=="") ? '' : (string) $post['thmbWidth'];
				$coverThumb['height'] = ($post['thmbHeight']=="") ? '' : (string) $post['thmbHeight'];
				$thumb_cover = preg_replace("~[\r\n]~", "",$post['thmbUrl']);
				$coverThumb['url'] = ($post['thmbUrl']=="") ? '' : S3::getS3Url(S3::getPostPhotoPath($thumb_cover));
				$coverThmb['width'] = ($post['hdWidth']=="") ? '' : (string) $post['hdWidth'];
				$coverThmb['height'] = ($post['hdHeight']=="") ? '' : (string) $post['hdHeight'];
				$hdthumb_cover = preg_replace("~[\r\n]~", "",$post['hdUrl']);
				$coverThmb['url'] = ($post['hdUrl']=="") ? '' : S3::getS3Url(S3::getPostHdthumbPath($hdthumb_cover));
				$coverSd['width'] = ($post['sdWidth']=="") ? '' : (string) $post['sdWidth'];
				$coverSd['height'] = ($post['sdHeight']=="") ? '' : (string) $post['sdHeight'];
				$sd_cover = preg_replace("~[\r\n]~", "",$post['sdUrl']);
				$coverSd['url'] = ($post['sdUrl']=="") ? '' : S3::getS3Url(S3::getPostSdPath($sd_cover));
				$cover[] = $coverThumb;
				$cover[] = $coverThmb;
				$cover[] = $coverSd;

				$channelInfo['cover'] = $cover;
				unset($coverThumb);
				unset($coverThmb);
				unset($coverSd);
				unset($cover);
				$channelInfo['poi'] = ($post['lPoix']=="" || $post['lPoix']==0) ? '{0.5, 0.5}' : '{'.$post['lPoix'].', '.$post['lPoiy'].'}';
			}

		} else {
			if($chnl['channel_cover'] != "") {
				$coverThumb['width'] 		= ($chnl['channel_cover_thumbW']=="" || $chnl['channel_cover_thumbW']==0) ? '' : (string) $chnl['channel_cover_thumbW'];
				$coverThumb['height'] 		= ($chnl['channel_cover_thumbH']=="" || $chnl['channel_cover_thumbH']==0) ? '' : (string) $chnl['channel_cover_thumbH'];
				$thumb_profile 				= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
				$coverThumb['url'] 		= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('thumb/'.$thumb_profile )); //PROFILE_THUMB_PATH .$thumb_profile;
				$coverThmb['width'] 		= ($chnl['channel_cover_hd_thumbW']=="" || $chnl['channel_cover_hd_thumbW']==0) ? '' : (string) $chnl['channel_cover_hd_thumbW'];
				$coverThmb['height'] 		= ($chnl['channel_cover_hd_thumbH']=="" || $chnl['channel_cover_hd_thumbH']==0) ? '' : (string) $chnl['channel_cover_hd_thumbH'];
				$hdthumb_profile 			= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
				$coverThmb['url'] 		= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('hdthumb/'.$hdthumb_profile)); //PROFILE_HDTHUMB_PATH . $hdthumb_profile;
				$coverSd['width'] 		= ($chnl['channel_cover_sdW']=="" || $chnl['channel_cover_sdW']==0) ? '' : (string) $chnl['channel_cover_sdW'];
				$coverSd['height'] 		= ($chnl['channel_cover_sdH']=="" || $chnl['channel_cover_sdH']==0) ? '' : (string) $chnl['channel_cover_sdH'];
				$sd_profile 				= preg_replace("~[\r\n]~", "",$chnl['channel_cover']);
				$coverSd['url'] 			= ($chnl['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('sd/'.$sd_profile)); //PROFILE_SD_PATH . $sd_profile;
				$cover[] 					= $coverThumb;
				$cover[] 					= $coverThmb;
				$cover[] 					= $coverSd;

				$channelInfo['cover'] = $cover;
				unset($coverThumb);
				unset($coverThmb);
				unset($coverSd);
				unset($cover);

				$channelInfo['poi'] = '{0.5, 0.5}';
			} else {
				if ($type == 'getMsg' || $type == 'getChnlGroup') {
					$cvr	=	$chnl;
				} else{
					$cvr = $this->vpost->getOnePostByChannel($chnl['channel_id'], time());
					if(!empty($cvr))
						$cvr	=	call_user_func_array('array_merge', $cvr);
				}
				$coverThumb['width']  = (isset($cvr['thumb_width'])) ? $cvr['thumb_width'] : '' ;
				$coverThumb['height'] = (isset($cvr['thumb_height'])) ? $cvr['thumb_height'] : '';
				$thumb_cover 		= preg_replace("~[\r\n]~", "", (isset($cvr['thumb_url'])) ? $cvr['thumb_url'] : '');
				$coverThumb['url'] 	= (isset($cvr['thumb_url'])) ? S3::getS3Url(S3::getPostPhotoPath($thumb_cover)) : ''; //THUMB_PATH .$thumb_cover;
				$coverThmb['width'] 	= (isset($cvr['hdthumb_width'])) ? $cvr['hdthumb_width'] : '';
				$coverThmb['height'] 	= (isset($cvr['hdthumb_height'])) ? $cvr['hdthumb_height'] : '';
				$hdthumb_cover 		= preg_replace("~[\r\n]~", "",(isset($cvr['hdthumb_url'])) ? $cvr['hdthumb_url'] : '');
				$coverThmb['url'] 	= (isset($cvr['hdthumb_url'])) ? S3::getS3Url(S3::getPostHdthumbPath($hdthumb_cover)) : ''; //HD_THUMB_PATH . $hdthumb_cover;
				$coverSd['width'] 	= (isset($cvr['sd_width'])) ? $cvr['sd_width'] : '';
				$coverSd['height'] 	= (isset($cvr['sd_height'])) ? $cvr['sd_height'] : '';
				$sd_cover 			= preg_replace("~[\r\n]~", "", (isset($cvr['sd_url'])) ? $cvr['sd_url'] : '');
				$coverSd['url'] 		= (isset($cvr['sd_url'])) ? S3::getS3Url(S3::getPostSdPath($sd_cover)) : ''; //SD_PATH . $sd_cover;
				$cover[] 			= $coverThumb;
				$cover[] 			= $coverThmb;
				$cover[] 			= $coverSd;

				$channelInfo['cover'] = $cover;

				unset($coverThumb);
				unset($coverThmb);
				unset($coverSd);
				unset($cover);

				$cvr['poix']		= (isset($cvr['poix']))	? $cvr['poix'] : '' ;
				$channelInfo['poi'] 	= ($cvr['poix']=="" || $cvr['poix']==0) ? '{0.5, 0.5}' : '{'.$cvr['poix'].', '.$cvr['poiy'].'}';
			}

		}

		$channelSocial['facebook'] 	= $chnl['fb_url'];
		$channelSocial['twitter'] 	= $chnl['twitter_url'];
		$channelSocial['instagram'] 	= $chnl['instagram_url'];
		$channelSocial['youtube'] 	= $chnl['youtube_url'];
		$channelSocial['tumblr'] 		= $chnl['tumblr_url'];
		$channelInfo['social'] 		= $channelSocial;

		$totalFollowers = $this->follow->getFollowersCountByChannel($chnl['channel_id']);
		$channelInfo['followers'] = $totalFollowers;
		$followedChannels = $this->follow->getFollowChannelByUser($chnl['channel_id']);
		$channelInfo['following'] = count($followedChannels);

		/* $channelLive['name'] 				= $chnl['livestream_name'];
		$channelLive['title'] 			= $chnl['livestream_title'];
		$channelLive['event'] 			= ($chnl['livestream_event']=="") ? '' : $chnl['livestream_event'];
		$channelLive['subscribers_only'] 	= $chnl['live_featured'];

		$liveThumb['width'] 	= ($chnl['live_thumb_width']=="") ? '' : $chnl['live_thumb_width'];
		$liveThumb['height'] 	= ($chnl['live_thumb_height']=="") ? '' : $chnl['live_thumb_height'];
		$lthumb_cover 		= preg_replace("~[\r\n]~", "",$chnl['live_thumb_url']);
		$liveThumb['url'] 	= ($chnl['live_thumb_url']=="") ? '' : S3::getS3Url(S3::getLivestreamThumbPath($lthumb_cover)); //LIVE_THUMB_PATH .$lthumb_cover;
		$liveThmb['width'] 	= ($chnl['live_hdthumb_width']=="") ? '' : $chnl['live_hdthumb_width'];
		$liveThmb['height'] 	= ($chnl['live_hdthumb_height']=="") ? '' : $chnl['live_hdthumb_height'];
		$lhdthumb_cover 	= preg_replace("~[\r\n]~", "",$chnl['live_hdthumb_url']);
		$liveThmb['url'] 		= ($chnl['live_hdthumb_url']=="") ? '' : S3::getS3Url(S3::getLivestreamHdThumbPath($lhdthumb_cover)); //LIVE_HD_THUMB_PATH . $lhdthumb_cover;
		$liveSd['width'] 		= ($chnl['live_sd_width']=="") ? '' : $chnl['live_sd_width'];
		$liveSd['height'] 	= ($chnl['live_sd_height']=="") ? '' : $chnl['live_sd_height'];
		$lsd_cover 			= preg_replace("~[\r\n]~", "",$chnl['live_sd_url']);
		$liveSd['url'] 		= ($chnl['live_sd_url']=="") ? '' : S3::getS3Url(S3::getLivestreamSdPath($lsd_cover)); // LIVE_SD_PATH . $lsd_cover;
		$liveCover[] 		= $liveThumb;
		$liveCover[] 		= $liveThmb;
		$liveCover[] 		= $liveSd;

		$channelLive['cover'] = $liveCover;
		unset($liveThumb);
		unset($liveThmb);
		unset($liveSd);
		unset($liveCover);

		$channelInfo['livestream'] = $channelLive; */

		$channelInfo['tour'] 	= $chnl['tour_url'];
		$channelInfo['store'] 	= $chnl['store_url'];
		$channelInfo['ticket'] 	= $chnl['ticket_url'];
		$channelInfo['website'] = $chnl['website_url'];
		$channelInfo['spotify'] = $chnl['spotify_url'];
		$channelInfo['deezer'] 	= $chnl['deezer_url'];
		$channelInfo['tidal'] 	= $chnl['tidal_url'];
		$channelInfo['beats'] 	= $chnl['beats_url'];
		$channelInfo['itunes'] 	= $chnl['itunes_url'];
		$channelInfo['content_subscription_id'] = $chnl['subscription_id'];

		return $channelInfo;

	}


	/**
	 * Login User
	 * @return json
	*/
	public function doLogin()
	{
		$authScheme = Input::get('auth_scheme');

		if(!$authScheme) {
			return self::failure('2', 'Missing required parameter authorization scheme');
		} else if($authScheme=="fb"){
			$fbUID = Input::get('fb-uid');
			$fbAccessToken = Input::get('fb-access-token');
			if(!$fbUID) {
				return self::failure('2', 'Missing required parameter fb userid');
			} else if(!$fbAccessToken) {
				return self::failure('2', 'Missing required parameter fb access token');
			} else {
				$fb = new Facebook();
				$fbUserValid = $fb->validateAccessToken($fbAccessToken);
				if(!$fbUserValid) {
					return self::failure('23', 'Invalid access token');
				} else {
					$user = $this->vastuser->getUserByFacebookId($fbUID);
					if(!empty($user))
						$user	=	call_user_func_array('array_merge', $user);
					if(count($user) > 0) {
						if($user['channel_id']==0) {
							$channelArr = array(
								'channel_id' => $user['vast_user_id'],
								'avatar' => '',
								'first_name' => $user['first_name'],
								'last_name' => $user['last_name'],
								'username' => ''
							);
							$this->channel->insertUserAvatar($channelArr);
							$userArr = array(
								'vast_user_id' => $user['vast_user_id'],
								'channel_id' =>$user['vast_user_id']
							);
							$this->vastuser->update($userArr);
						}
						$sessionId = hash_hmac('md5', uniqid($user['vast_user_id']), $user['vast_user_id'].time());
						$this->session->create(array(
							'session_id' => $sessionId,
							'vast_user_id' => $user['vast_user_id']
						));
					} else {
						$fbUser = $fb->getFacebookUserInfo($fbAccessToken);
						$hometown = isset($fbUser['hometown']['name']) ? $fbUser['hometown']['name'] : '';
						$fbImg  = $fb->getFacebookProfilePicture($fbUser['id']);
						$date = time();
						$id =	$this->vastuser->create(array(
									'role_id' => 4,
									'first_name' => $fbUser['first_name'],
									'last_name' => $fbUser['last_name'],
									'username' => '',
									'email_id' => $fbUser['email'],
									//'photo' => $fbImg['data']['url'],
									'location' => $hometown,
									'phone' => '',
									'birthday' => '',
									'fb_uid' => $fbUID,
									'fb_access_token' => $fbAccessToken,
									'join_date' => $date,
									'status' => '1',
									'channel_group_id' => 0
								));
						/* $channelArr = array(
							'channel_id' => $id,
							'avatar' => '',
							'first_name' => $fbUser['first_name'],
							'last_name' => $fbUser['last_name'],
							'username' => ''
						);
						$this->channel->insertUserAvatar($channelArr); */
						$userArr = array(
							'vast_user_id' => $id,
							'channel_id' =>$id
						);
						$this->vastuser->update($userArr);

						$sessionId = hash_hmac('md5', uniqid($id), $id.time());
						$this->session->create(array(
							'session_id' => $sessionId,
							'vast_user_id' => $id
						));
						/* $this->follow->create(array(
							'channel_id' => 96,
							'user_id' => $id
						)); */
						if($fbUser['email']!='') {
							$html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
									<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
									<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
									<title>VAST</title>
									<style type="text/css">
										table td {
										border-collapse:collapse;
										}
										body {
										margin:0 !important;
										padding:0 !important;
										width:100% !important;
										background-color: #ffffff !important;
										}

									</style>
								</head>
								<body>
									<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
										<tbody>
											<tr>
												<td valign="top" style="padding-left: 10px;">
													<table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
														<tbody>
															<tr>
																<td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="https://prod.thefutureisvast.us/images/vast_app_logo.png" alt=""  /></td>
															</tr>
															<tr>
																<td class="text_content" valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding: 50px 15px;">
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">Welcome to the future epicenter of the Internet.</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">We\'ll bring you closer to the people and things you love,</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">and introduce you to culture you\'d never find anywhere else.</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">Prepare to get lost in what you find.</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">We\'ll be in touch.</p>
																	<h1 style="text-align:center;">
																	<img style="margin:auto; " src="https://prod.thefutureisvast.us/images/vast_logo_mail.png" />
																	</h1>
																</td>
															</tr>
															<tr>
																<td valign="middle" align="center" bgcolor="#ffffff">
																	<hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" />
																</td>
															</tr>
															<tr>
																<td valign="middle" align="center" bgcolor="#ffffff">
																	<p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; 2016 by Vast
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table>
								</body>
							</html>';
							/*$html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
											<head>
											<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
											<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
											<title>VAST</title>
											<style type="text/css">
											table td {
												border-collapse:collapse;
											}
											body {
												margin:0 !important;
												padding:0 !important;
												width:100% !important;
												background-color: #ffffff !important;
											}
											</style>
											</head>
											<body>
											<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
											  <tbody>
												<tr>
												  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
													  <tbody>
														<tr>
														  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="https://prod.thefutureisvast.us/images/vast_app_logo.png" alt=""  /></td>
														</tr>
														<tr>
														  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;">Hey '. $fbUser['first_name'] . ' ' . $fbUser['last_name'] .',</span></p>
															<p>Welcome to VAST - we are super excited to have you!</p>
															<p>As the ultimate fan experience, our goal is to deliver the very best ways for you to be close to the artists you love.  To that end, below are a few ways to make the most out of our platform. </p>
															<p>
																<ul>
																	<li>Use EXPLORE to find interesting artists and content</li>
																	<li>FOLLOW artists to create your personal MY VAST feed</li>
																	<li>SUBSCRIBE to your favorite artists to get exclusive content, VIP access to events and more!</li>
																</ul>
															</p>
															<p>If you have any questions, reply to this email or holla at us on Twitter. (@GetVASTnow).</p>
															<p>Hugs,<br />
														  Team VAST</p></td>
														</tr>
														<tr>
														  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
														</tr>
														<tr>
														  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; ' . date('Y') . ' by Vast</td>
														</tr>
													  </tbody>
													</table></td>
												</tr>
											  </tbody>
											</table>
											</body>
											</html>';*/
							Util::sendSESMail('admin@thefutureisvast.us', $fbUser['email'], 'WELCOME TO VAST', null, $html_body);
						}
					}
					$user = $this->vastuser->getUserByFacebookId($fbUID);
					if(!empty($user))
						$user	=	call_user_func_array('array_merge', $user);

					$user_info = $this->userInfo($user['channel_id']);

					$response = array(
									'result' => 'success',
									'session_id' => $user['session_id'],
									'user_info' => $user_info
								);

					return Response::json($response);
				}
			}
		} else if($authScheme == "email") {
			$userEmail = Input::get('email');

			$userPwd = Input::get('password');

			if($userEmail == '' || $userPwd == '') {
				return self::failure('2', 'Missing required parameter email or password');
			} else {
				$pwdQry		=	$this->vastuser->checkPassword($userEmail, $userPwd);
				if(!empty($pwdQry)) {
					$pwdQry 		= 	call_user_func_array('array_merge', $pwdQry);
					if($pwdQry['vast_user_id'] > 0) {
						$salt 		= 	'2144758762534858898eef20.73371324';
						$usrPwd 	= 	sha1($salt . $userPwd);
						$this->vastuser->updatePasswordByEmail($userEmail, $usrPwd);
					}
				}

				$user = $this->vastuser->getUserByEmailId($userEmail, $userPwd);

				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);

				if(count($user) > 0) {
					if($user['channel_id']==0) {
						$channelArr = array(
							'channel_id' => $user['vast_user_id'],
							'avatar' => '',
							'first_name' => $user['first_name'],
							'last_name' => $user['last_name'],
							'vast_name' => $user['username']
						);
						$this->channel->insertUserAvatar($channelArr);
						$userArr = array(
							'vast_user_id' => $user['vast_user_id'],
							'channel_id' =>$user['vast_user_id']
						);
						$this->vastuser->update($userArr);
					}
					$sessionId = hash_hmac('md5', uniqid($user['vast_user_id']), $user['vast_user_id'].time());
					$this->session->create(array(
						'session_id' => $sessionId,
						'vast_user_id' => $user['vast_user_id']
					));
					$user = $this->vastuser->getUserByEmailId($userEmail, $userPwd);
					if(!empty($user))
						$user	=	call_user_func_array('array_merge', $user);

					$user_info = $this->userInfo($user['channel_id']);

					$response = array(
									'result' => 'success',
									'session_id' => $user['session_id'],
									'user_info' => $user_info
								);

					return Response::json($response);
				} else {
					return self::failure('1011', 'Wrong email or password combination');
				}
			}
		} else {
			return self::failure('23', 'Invalid authorization scheme');
		}
	}

	/**
	 * Logout User
	 * @return json
	*/
	public function doLogout()
	{
		$sessionId	=	Input::get('session_id');

		if(!$sessionId) {
			return self::failure('2', 'Missing required parameter session_id');
		} else {
			$this->session->deleteSession($sessionId);
			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * Forget password
	 * @return json
	*/
	public function forgotPassword()
	{
		$email = Input::get('email');

		if(!$email) {
			return self::failure(2, 'Missing required parameter email');
		} else {
			$user = $this->vastuser->checkValidUser($email);
			if(!empty($user)) {
				$user	=	call_user_func_array('array_merge', $user);
				$token = sha1(uniqid(time(), true));
				$this->vastuser->update(array(
					'reset_token' => $token,
					'vast_user_id' => $user['vast_user_id']
				));
				$message = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>VAST</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="https://prod.thefutureisvast.us/images/vast_app_logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;">Hi,</span></p>
												<p>You recently requested a password reset for your Vast account!. To create a new password, click on the link below:

  													<a href="https://prod.thefutureisvast.us/client-api/'.$token.'/reset-apppwd">Reset My Password </a> </p>
												<p>Thanks,<br />
												  <br />
											  The Vast Team</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; 2016 by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';
				Util::sendSESMail('admin@thefutureisvast.us', $email, 'Password Recovery Link', null, $message);

				$response = array(
					'result' => 'success',
					'description' => 'Password recovery link sent successfully'
				);
				return Response::json($response);
			} else {
				return self::failure(1010, 'User does not exist');
			}
		}
	}

	/**
	 * Get own info
	 * @return json
	*/
	public function getOwnInfo()
	{
		$sessionId = Input::get('session_id');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else  {
			$user 				=	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);
			$user_info = $this->userInfo($user['channel_id']);

			$response = array(
				'result' => 'success',
				'user_info' => $user_info
			);

			return Response::json($response);
		}
	}

	/**
	 * Update user info
	 * @retrn json
	*/
	public function updateUserInfo()
	{
		$sessionId	=	Input::get('session_id');
		$params 	= 	Input::file('params');
		$image      =   Input::file('image');
		$profileImage = Input::file('profile_image');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
				$userSession	=	$this->vastuser->getUserBySession($sessionId);
				if(!empty($userSession)) {
					$userSession	=	call_user_func_array('array_merge', $userSession);
				}
				$channelDet = $this->vastuser->getChannelVastUserById($userSession['channel_id']);
				if(!empty($channelDet)) {
					$channelDet	=	call_user_func_array('array_merge', $channelDet);
				}
				$paramArr = array();
				if(!empty($params)) {
					$orgName		=	$params->getClientOriginalName();
					$strJson		= 	File::get(Input::file('params')->getRealPath());
					$updateJson 	= 	json_decode($strJson);

					$firstName 		= 	isset($updateJson->first_name) ? $updateJson->first_name : $userSession['first_name'];
					$lastName 		= 	isset($updateJson->last_name) ? $updateJson->last_name : $userSession['last_name'];
					$location       =   isset($updateJson->location) ? $updateJson->location : $userSession['location'];
					$phone          =   isset($updateJson->phone) ? $updateJson->phone : $userSession['phone'];
					$birthday       =   isset($updateJson->birthday) ? $updateJson->birthday : $userSession['birthday'];
					$bio 			= 	isset($updateJson->bio) ? $updateJson->bio : $channelDet['biography'];
					$tour 			= 	isset($updateJson->tour) ? $updateJson->tour : $channelDet['tour_url'];
					$store 			= 	isset($updateJson->store) ? $updateJson->store : $channelDet['store_url'] ;
					$ticket         =   isset($updateJson->tickets) ? $updateJson->tickets : $channelDet['ticket_url'];
					$spotify 		= 	isset($updateJson->spotify) ? $updateJson->spotify : $channelDet['spotify_url'];
					$deezer 		= 	isset($updateJson->deezer) ? $updateJson->deezer : $channelDet['deezer_url'];
					$tidal          =   isset($updateJson->tidal) ? $updateJson->tidal : $channelDet['tidal_url'];
					$beats 			= 	isset($updateJson->beats) ? $updateJson->beats : $channelDet['beats_url'];
					$itunes 		= 	isset($updateJson->itunes) ? $updateJson->itunes : $channelDet['itunes_url'];
					$website 		= 	isset($updateJson->website) ? $updateJson->website : $channelDet['website_url'];
					$subscriptionId = 	isset($updateJson->subscription_id) ? $updateJson->subscription_id : $channelDet['subscription_id'];
					$private        =   isset($updateJson->private) ? $updateJson->private : $channelDet['inactive'];

					$paramArr['first_name'] = $firstName;
					$paramArr['last_name'] = $lastName;
					$paramArr['biography'] = $bio;
					$paramArr['tour_url'] = $tour;
					$paramArr['store_url'] = $store;
					$paramArr['ticket_url'] = $ticket;
					$paramArr['spotify_url'] = $spotify;
					$paramArr['deezer_url'] = $deezer;
					$paramArr['tidal_url'] = $tidal;
					$paramArr['beats_url'] = $beats;
					$paramArr['itunes_url'] = $itunes;
					$paramArr['website_url'] = $website;
					$paramArr['subscription_id'] = $subscriptionId;
					$paramArr['inactive'] = $private;

					if(isset($updateJson->private)) {
						$private = ($updateJson->private==1) ? '0' : '1';
					}

					$updateArr = array(
						'vast_user_id' => $userSession['vast_user_id'],
						'first_name' => $firstName,
						'last_name' => $lastName,
						'location' => $location,
						'phone' => $phone,
						'birthday' => $birthday,
						'status' => $private
					);
					$this->vastuser->update($updateArr);
				}
				if(isset($updateJson->private)) {
					$private = ($updateJson->private==1) ? '1' : '0';
				}
				if(!empty($image)) {
					$image 	= $image->getClientOriginalName();
					$rand = rand(00000, 99999);
					$uploadfilename = $rand . $image;
					$photo = $rand . $image;
					S3::uploadVastUserAvatar('image', $photo);
				} else {
					$photo = "";
				}
				if(!empty($profileImage)) {
					$profileImage 	= $profileImage->getClientOriginalName();
					$rand = rand(00000, 99999);
					$uploadfilename = $rand . $profileImage;
					$profilePhoto = $rand . $profileImage;
					$prPhoto = S3::uploadVastUserCover('profile_image', $profilePhoto);
					$prPhoto = json_decode($prPhoto);

					$sdW	=	$prPhoto->sdw;
					$sdH	=	$prPhoto->sdh;
					$sdUrl	=	$prPhoto->photo;
					$hdthumbW	=	$prPhoto->hdthumbw;
					$hdthumbH	=	$prPhoto->hdthumbh;
					$thumbW		=	$prPhoto->thumbw;
					$thumbH		=	$prPhoto->thumbh;
				} else {
					$sdUrl = "";
					$sdW = 0;
					$sdH = 0;
					$hdthumbW =	0;
					$hdthumbH =	0;
					$thumbW	= 0;
					$thumbH	= 0;
				}
				$avatar         =   ($photo!="") ? $photo : $channelDet['avatar'];
				$cover          =   ($sdUrl!="") ? $sdUrl : $channelDet['channel_cover'];
				$sdW 			= 	($sdW!=0) ? $sdW : $channelDet['channel_cover_sdW'];
				$sdH            =   ($sdH!=0) ? $sdH : $channelDet['channel_cover_sdH'];
				$hdthumbW       =	($hdthumbW!=0) ? $hdthumbW : $channelDet['channel_cover_hd_thumbW'];
				$hdthumbH       =	($hdthumbH!=0) ? $hdthumbH : $channelDet['channel_cover_hd_thumbH'];
				$thumbW			=   ($thumbW!=0) ? $thumbW : $channelDet['channel_cover_thumbW'];
				$thumbH			= 	($thumbH!=0) ? $thumbH : $channelDet['channel_cover_thumbH'];

				$paramArr['avatar'] = $avatar;
				$paramArr['channel_cover_sdW'] =  $sdW;
				$paramArr['channel_cover_sdH'] = $sdH;
				$paramArr['channel_cover_thumbW'] = $thumbW;
				$paramArr['channel_cover_thumbH'] = $thumbH;
				$paramArr['channel_cover_hd_thumbW'] = $hdthumbW;
				$paramArr['channel_cover_hd_thumbH'] = $hdthumbH;
				$paramArr['channel_cover'] = $cover;

				$rr	=	$this->channel->update($userSession['channel_id'], $paramArr);

				$user =	$this->vastuser->getUserBySession($sessionId);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);
				$user_info = $this->userInfo($user['channel_id']);

				$response = array(
					'result' => 'success',
					'user_info' => $user_info
				);

				return Response::json($response);
		}
	}

	/**
	 * User feed
	 * @return json
	*/
	public function userFeed()
	{
		$sessionId = Input::get('session_id');
		$postId    = Input::get('post_id');
		$count     = Input::get('count');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$count) {
			return self::failure(2, 'Missing required parameter count');
		} else {
				$response = new stdClass();
				$response->result = "success";
				if($postId) {
					$response->post_id = $postId;
				}

				$user = $this->vastuser->getUserBySession($sessionId);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);
				$followedChannel = $this->follow->getFollowChannelByUser($user['channel_id']);
				$channelFollow = array();
				foreach($followedChannel as $follows) {
					$channelFollow[] = (string) $follows['channel_id'];
				}

				if(empty($channelFollow)){
					array_push($channelFollow, '0');
				}

				$total = $this->vpost->getPostsCountByChannels($channelFollow, 1);

				if($total < $count) {
					$response->count = $total;
				} else {
					$response->count = $count;
				}

				$response->total = (string) $total;

				//$content  = new stdClass();
				$feedInfo = array();
				$posts = array();
				//$feed  = array();
				if(!$postId) {
					//$postQry = $this->vpost->getPostsByChannelIdForFeed(96, 0, $count);
					$postQry = $this->vpost->getPostsByChannelsForFeed($channelFollow, 0, $count);
				} else {
					$postQry = $this->vpost->getPostsByChannelsForFeed($channelFollow, ($postId-1), $count);
				}

				foreach($postQry as $post) {
					$postIn		=	$this->userFeedPostInfo($post, 'userFeed');
					$posts[] 	=	$postIn;
					unset($postIn);
				}

				$feedInfo['posts'] = $posts;
				$response->feed = $feedInfo;
				/* $response = array(
					'result' => 'success',
					'count' => (string) $rescount,
					'total' => (string) $total,
					'feed' => $feedInfo
				); */

				return Response::json($response);
		}
	}

	/**
	 * Publish Post
	 * @return json
	*/
	public function publishPost()
	{
		$sessionId		=	Input::get('session_id');
		$scheduleDate 	= 	Input::get('schedule_date');
		$social 		=	Input::get('social');
		$post			=	Input::file('post');
		$image 			= 	Input::file('image');
		$video 			= 	Input::file('video');
		$music 			= 	Input::file('music');
		$extraVideo 	= 	Input::file('extra_video');
		$extraAudio 	= 	Input::file('extra_audio');

		$tw_token = '';
		$tw_secret = '';
		$fb_token = '';
		$tb_secret = '';
		$tb_username = '';
		$tb_token = '';

		$socialShare 	= 	json_decode($social);

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!isset($post)) {
			return self::failure(2, 'Missing required parameter post');
		} else if(!$scheduleDate) {
			return self::failure(2, 'Missing required parameter schedule_date');
		} else {
			if(!empty($post)){
				$strJson	=	File::get(Input::file('post')->getRealPath());
				$postJson 	= 	json_decode($strJson);
				if(isset($postJson->id)) {
					$postId 	= 	$postJson->id;
				} else {
					$postId 	= 	'';
				}
				if(isset($postJson->poi)) {
					$poi1 		= 	str_replace("{", "", $postJson->poi);
					$poi2 		= 	str_replace("}", "", $poi1);
					$postPoi 	= 	explode( ',', $poi2 );
				} else {
					$postPoi[0] = '0.5';
					$postPoi[1] = '0.5';
				}

				if(isset($postJson->sharing_text)) {
					$shareText 	= 	$postJson->sharing_text;
				} else {
					$shareText  = "";
				}

				if($postJson->type == "music") {
					$songName 	= 	isset($postJson->song_name) ? $postJson->song_name : "";
					$songAuthor =	isset($postJson->song_author) ? $postJson->song_author : "";
				} else {
					$songName = "";
					$songAuthor = "";
				}
				$channel 	= 	$this->session->getSessionBySessionId($sessionId);
				if(!empty($channel)) //{
					$channel	=	call_user_func_array('array_merge', $channel);

					$channelId 		= 	$channel['vast_user_id'];
					$channelDetails	=	$this->channel->getChannelById($channelId);

					if($extraVideo) {
						$extra = $channelDetails->vast_name . time();
						S3::uploadSecVideoPostNew('extra_video', $extra);
					}
					if($extraAudio) {
						$extraSong = $channelDetails->vast_name . time();
						S3::uploadSecMusicPostNew('extra_audio', $extraSong);
						$filename = $extraAudio->getClientOriginalName();
						$ext = File::extension($filename);
						$extraAudio = strtolower($extraSong) . '.' . $ext;
					}
					if(!$postId) {

						if($image) {
							if($postJson->type == 'image') {
								$PhotoPost	=	S3::uploadPhotoPostNew('image', $channelDetails);
								$PhotoPost	=	json_decode($PhotoPost);

								$retinaW	=	$PhotoPost->retina->w;
								$retinaH	=	$PhotoPost->retina->h;
								$retina_url	=	$PhotoPost->retina->photo;

								$nonRetinaW		=	$PhotoPost->nonretina->w;
								$nonRetinaH		=	$PhotoPost->nonretina->h;
								$nonretina_url	=	$PhotoPost->nonretina->photo;

								$sdW	=	$PhotoPost->sd->w;
								$sdH	=	$PhotoPost->sd->h;
								$sd_url	=	$PhotoPost->sd->photo;

								$hdthumbW		=	$PhotoPost->hdthumb->w;
								$hdthumbH		=	$PhotoPost->hdthumb->h;
								$hdthumb_url	=	$PhotoPost->hdthumb->photo;

								$thumbW		=	$PhotoPost->thumb->w;
								$thumbH		=	$PhotoPost->thumb->h;
								$thumb_url	=	$PhotoPost->thumb->photo;
							} else {
								if($video) {
									$name = $channelDetails->vast_name . time();
									S3::uploadVideoPostNew('video', $name);
								}
								if($music) {
									$sname = $channelDetails->vast_name . time();
									S3::uploadMusicPostNew('music', $sname);
									$filename = $music->getClientOriginalName();
									$ext = File::extension($filename);
									$music = strtolower($sname) . '.' . $ext;
								}
								$PhotoSecPost	=	S3::uploadPhotoSecPostNew('image', $channelDetails);
								$PhotoSecPost	=	json_decode($PhotoSecPost);

								$retinaW	=	"";
								$retinaH	=	"";
								$retina_url	=	"";

								$nonRetinaW		=	"";
								$nonRetinaH		=	"";
								$nonretina_url	=	"";

								$sdW	=	$PhotoSecPost->sd->w;
								$sdH	=	$PhotoSecPost->sd->h;
								$sd_url	=	$PhotoSecPost->sd->photo;

								$hdthumbW		=	$PhotoSecPost->hdthumb->w;
								$hdthumbH		=	$PhotoSecPost->hdthumb->h;
								$hdthumb_url	=	$PhotoSecPost->hdthumb->photo;

								$thumbW		=	$PhotoSecPost->thumb->w;
								$thumbH		=	$PhotoSecPost->thumb->h;
								$thumb_url	=	$PhotoSecPost->thumb->photo;
							}
						} else {
							$retina_url 	= "";
							$nonretina_url	= "";
							$sd_url 		= "";
							$hdthumb_url 	= "";
							$thumb_url 		= "";
						}
						$user 		= 	$this->vastuser->getUserBySession($sessionId);
						if(!empty($user))
							$user	=	call_user_func_array('array_merge', $user);
						$chapterId 	= 	(isset($postJson->chapter->id)) ? $postJson->chapter->id : '';

						if($chapterId == '') {
							$chapterName	=	isset($postJson->chapter->name) ? $postJson->chapter->name : '';
							$time			=	time();
							$chapterId 		= 	$this->chapter->insertChapterName(array('channel_id' => $user['channel_id'], 'chapter_name' => $chapterName, 'date' => $time));
						}
						$postStatus = ($video!="" || $extraVideo!="") ? 0 : 1;
						if($video!="") {
							$video = strtolower($name) . '.tmp';
						}
						if($extraVideo!="") {
							$extraVideo = strtolower($extra) . '.tmp';
						}
						$post_type = (isset($postJson->type) ? $postJson->type : '');
						$story = (isset($postJson->text) ? $postJson->text : '');
						$post_title = (isset($postJson->title) ? $postJson->title : '');
						$subscription_id = (isset($postJson->required_subscription) ? $postJson->required_subscription : '');
						$post_id	=	$this->vpost->insertPostDetails(array(
							'chapter_id' => $chapterId,
							'channel_id' => $user['channel_id'],
							'type' => $post_type,
							'date' => $scheduleDate,
							'story' => $story,
							'title' => $post_title,
							'subscription_id' => $subscription_id,
							'audio_url' => $extraAudio,
							'video_url' => $video,
							'second_video' => $extraVideo,
							'thumb_width' => $thumbW,
							'thumb_height' => $thumbH,
							'thumb_url' => $thumb_url,
							'hdthumb_width' => $hdthumbW,
							'hdthumb_height' => $hdthumbH,
							'hdthumb_url' => $hdthumb_url,
							'retina_width' => $retinaW,
							'retina_height' => $retinaH,
							'retina_url' => $retina_url,
							'nonretina_width' => $nonRetinaW,
							'nonretina_height' => $nonRetinaH,
							'nonretina_url' => $nonretina_url,
							'sd_width' => $sdW,
							'sd_height' => $sdH,
							'sd_url' => $sd_url,
							'music_url' => $music,
							'song' => $songName,
							'author' => $songAuthor,
							'poix' => $postPoi[0],
							'poiy' => $postPoi[1],
							'post_status' => $postStatus));
						$chapterDet = $this->chapter->getChapterByIdAndChannel($user['channel_id'], $chapterId);
						if(!empty($chapterDet))
							$chapterDet	=	call_user_func_array('array_merge', $chapterDet);
						$postOrderArr = explode(',', $chapterDet['post_order']);
						$postSum = array_sum($postOrderArr);
						if($postSum != 0) {
							array_push($postOrderArr, $post_id);
							$postList = implode(",", $postOrderArr);
							$upArr =  array(
								'chapter_id' => $chapterId,
								'post_order' => $postList
							);
							$this->chapter->update($upArr);
						}
						$post_hash = md5($post_id);
						$surl	= Util::shortenUrl('https://prod.thefutureisvast.us/cms/post/'.$post_hash);
						$updateArr = array(
							'post_id' => $post_id,
							'short_url' => $surl,
							'post_hash' => $post_hash
						);
						$this->vpost->updateShortUrlByPostId($updateArr);
						if($postStatus==0){
							if(count($socialShare) > 0) {
								$tw = in_array("tw", $socialShare) ? 1 : 0;
								$fb = in_array("fb", $socialShare) ? 1 : 0;
								$tb = in_array("tm", $socialShare) ? 1 : 0;
							} else {
								$tw = 0;
								$fb = 0;
								$tb = 0;
							}

							if($tw==1) {
								$token	=	$this->vastuser->getSocialShareDetails($user['channel_id']);
								$token	=	call_user_func_array('array_merge', $token);
								$tw_token = ($token['twitter_token'] == '' ) ? '0' : $token['twitter_token'];
								$tw_secret = ($token['twitter_token_secret'] == '' ) ? '0' : $token['twitter_token_secret'];
							}
							if($fb==1) {
								$token	=	$this->vastuser->getSocialShareDetails($user['channel_id']);
								$token	=	call_user_func_array('array_merge', $token);
								$fb_token = ($token['fb_token'] == '' ) ? '0' : $token['fb_token'];
							}
							if($tb==1) {
								$token	=	$this->vastuser->getSocialShareDetails($user['channel_id']);
								$token	=	call_user_func_array('array_merge', $token);
								$tb_token = ($token['tumblr_token'] == '' ) ? '0' : $token['tumblr_token'];
								$tb_username = ($token['tumblr_username'] == '' ) ? '0' : $token['tumblr_username'];
								$tb_secret = ($token['tumblr_oauth_token_secret'] == '' ) ? '0' : $token['tumblr_oauth_token_secret'];
							}
							$this->conversion->insertPost(array('post_id' => $post_id,
								'videourl' => $video,
								'secvideourl' => $extraVideo,
								'channel_id' => $user['channel_id'],
								'percent' => 0,
								'post_title' => (isset($postJson->title) ? $postJson->title : ''),
								'share_text' => $shareText,
								'tw' => $tw,
								'fb' => $fb,
								'tb' => $tb,
								'short_url' => $surl,
								'tw_token' => $tw_token,
								'tw_secret' => $tw_secret,
								'fb_token' => $fb_token,
								'tb_token' => $tb_token,
								'tb_username' => $tb_username,
								'tb_secret' => $tb_secret));
						}

						$pst =	$this->vpost->getPostsListByPostId($post_id);

						if(!empty($pst)) //{
							$pst	=	call_user_func_array('array_merge', $pst);

							$manage	=	$this->manageexp->getMappingRowsByLimit($pst['channel_id']);

							if(!empty($manage)) {
								$manage	=	call_user_func_array('array_merge', $manage);

								$nonfeaturedLists 		= 	explode(',', $manage['non_featured_list']);
								$nonfeaturedpostLists 	= 	explode(',', $manage['non_featured_post_list']);

								/* if(in_array($pst['chapter_id'], $nonfeaturedLists)){
									$offset 			= 	array_search($pst['chapter_id'], $nonfeaturedLists);
									unset($nonfeaturedLists[$offset]);
									array_unshift($nonfeaturedLists, $pst['chapter_id']);
									$nonFeatured = implode(',', $nonfeaturedLists);
									unset($nonfeaturedpostLists[$offset]);
									array_unshift($nonfeaturedpostLists, $pst['post_id']);
									$nonFeaturedPosts = implode(',', $nonfeaturedpostLists);

									$this->manageexp->updateFeaturedListById(array('type' => 1,
										'non_featured_list' => $nonFeatured,
										'non_featured_post_list' => $nonFeaturedPosts,
										'latestEntry' => $pst['chapter_id'],
										'id' => $manage['id']));
								} else {
									$present = array_search(0, $nonfeaturedLists);
									if($present==false){
										array_unshift($nonfeaturedLists, $pst['chapter_id']);
										array_unshift($nonfeaturedpostLists, $pst['post_id']);
									} else {
										unset($nonfeaturedLists[$present]);
										array_unshift($nonfeaturedLists, $pst['chapter_id']);
										unset($nonfeaturedpostLists[$present]);
										array_unshift($nonfeaturedpostLists, $pst['post_id']);
									}
									$nonFeatured 	  = implode(',', $nonfeaturedLists);
									$nonFeaturedPosts = implode(',', $nonfeaturedpostLists);

									$this->manageexp->updateFeaturedListById(array('type' => 1,
										'non_featured_list' => $nonFeatured,
										'non_featured_post_list' => $nonFeaturedPosts,
										'latestEntry' => $pst['chapter_id'],
										'id' => $manage['id']));
								} */

								if($manage['network_id'] > 0) {
									$network = $this->manageexp->getById($manage['network_id']);
									if(!empty($network)) {
										$network	=	call_user_func_array('array_merge', $network);

										$nonFeaturedLists = explode(',', $network['non_featured_list']);
										if(in_array($pst['channel_id'], $nonFeaturedLists)) {
											$key = array_search($pst['channel_id'], $nonFeaturedLists);
											unset($nonFeaturedLists[$key]);
											array_unshift($nonFeaturedLists, $pst['channel_id']);
											$nonFeatured = implode(',', $nonFeaturedLists);

											$this->manageexp->updateNonFeaturedListById(array('type' => 0,
											'non_featured_list' => $nonFeatured,
											'latestEntry' => $pst['channel_id'],
											'id' => $network['id']));
										}
									}
								}
							}
						//}
					} else {

						if($image) {

							if($extraVideo) {
								S3::uploadSecVideoPostNew('extra_video', $channelDetails);
							}
							if($extraAudio) {
								S3::uploadSecMusicPostNew('extra_audio', $channelDetails);
							}

							if($postJson->type == 'image') {
								$PhotoPost	=	S3::uploadPhotoPostNew('image', $channelDetails);
								$PhotoPost	=	json_decode($PhotoPost);

								$retinaW	=	$PhotoPost->retina->w;
								$retinaH	=	$PhotoPost->retina->h;
								$retina_url	=	$PhotoPost->retina->photo;

								$nonRetinaW		=	$PhotoPost->nonretina->w;
								$nonRetinaH		=	$PhotoPost->nonretina->h;
								$nonretina_url	=	$PhotoPost->nonretina->photo;

								$sdW	=	$PhotoPost->sd->w;
								$sdH	=	$PhotoPost->sd->h;
								$sd_url	=	$PhotoPost->sd->photo;

								$hdthumbW		=	$PhotoPost->hdthumb->w;
								$hdthumbH		=	$PhotoPost->hdthumb->h;
								$hdthumb_url	=	$PhotoPost->hdthumb->photo;

								$thumbW		=	$PhotoPost->thumb->w;
								$thumbH		=	$PhotoPost->thumb->h;
								$thumb_url	=	$PhotoPost->thumb->photo;

							} else {
								if($video) {
									S3::uploadVideoPostNew('video', $channelDetails);
								}
								if($music) {
									S3::uploadMusicPostNew('music', $channelDetails);
								}
								$PhotoSecPost	=	S3::uploadPhotoSecPostNew('image', $channelDetails);
								$PhotoSecPost	=	json_decode($PhotoSecPost);

								$retinaW	=	"";
								$retinaH	=	"";
								$retina_url	=	"";

								$nonRetinaW		=	"";
								$nonRetinaH		=	"";
								$nonretina_url	=	"";

								$sdW	=	$PhotoSecPost->sd->w;
								$sdH	=	$PhotoSecPost->sd->h;
								$sd_url	=	$PhotoSecPost->sd->photo;

								$hdthumbW		=	$PhotoSecPost->hdthumb->w;
								$hdthumbH		=	$PhotoSecPost->hdthumb->h;
								$hdthumb_url	=	$PhotoSecPost->hdthumb->photo;

								$thumbW		=	$PhotoSecPost->thumb->w;
								$thumbH		=	$PhotoSecPost->thumb->h;
								$thumb_url	=	$PhotoSecPost->thumb->photo;
							}

						} else {
							$retinaW	= "";
							$retinaH	= "";
							$retina_url = "";
							$nonRetinaW	= "";
							$nonRetinaH	= "";
							$nonretina_url	= "";
							$sdW	=	"";
							$sdH	=   "";
							$sd_url 	= "";
							$hdthumbW	= "";
							$hdthumbH	= "";
							$hdthumb_url = "";
							$thumbW		= "";
							$thumbH		= "";
							$thumb_url 	= "";
						}
						$pst = $this->vpost->getPostsListByPostId($postId);
						if(!empty($pst)) //{
							$pst	=	call_user_func_array('array_merge', $pst);

							$chapterId = isset($postJson->chapter->id) ? $postJson->chapter->id : '';

							if($chapterId == '') {
								$chapterName	=	isset($postJson->chapter->name) ? $postJson->chapter->name : '';
								$time			=	time();
								$chapterId 		= 	$this->chapter->insertChapterName(array('channel_id' => $pst['channel_id'], 'chapter_name' => $chapterName, 'date' => $time));
							}

							$type 				= (!isset($postJson->type)) ? $pst['type'] : $postJson->type;
							$text 				= (!isset($postJson->text)) ? $this->escapeStr($pst['story']) : $postJson->text;
							$title 				= (!isset($postJson->title)) ? $this->escapeStr($pst['title']) : $postJson->title;
							$subscriptionId 	= (!isset($postJson->required_subscription)) ? $pst['subscription_id'] : $postJson->required_subscription;
							$scDate 			= is_null($scheduleDate) ? $pst['date'] : $scheduleDate;
							if(isset($postJson->second_audio)) {
								$audioUrl 			= ($postJson->second_audio==1) ? '' : (is_null($extraAudio) ? $pst['audio_url'] : $extraAudio);
							} else {
								$audioUrl = is_null($extraAudio) ? $pst['audio_url'] : $extraAudio;
							}
							$videoUrl 			= is_null($video) ? $pst['video_url'] : $video;
							if(isset($postJson->second_video)) {
								$secVideo 			= ($postJson->second_video==1) ? '' : (is_null($extraVideo) ? $pst['second_video'] : $extraVideo);
							} else {
								$secVideo = is_null($extraVideo) ? $pst['second_video'] : $extraVideo;
							}
							$thumbWidth 		= (is_null($thumbW) || ($thumbW=="")) ? $pst['thumb_width'] : $thumbW;
							$thumbHeight 		= (is_null($thumbH) || ($thumbH=="")) ? $pst['thumb_height'] : $thumbH;
							$thumbUrl 			= (is_null($thumb_url) || ($thumb_url=="")) ? $pst['thumb_url'] : $thumb_url;
							$hdthumbWidth 		= (is_null($hdthumbW) || ($hdthumbW=="")) ? $pst['hdthumb_width'] : $hdthumbW;
							$hdthumbHeight 		= (is_null($hdthumbH) || ($hdthumbH=="")) ? $pst['hdthumb_height'] : $hdthumbH;
							$hdthumbUrl 		= (is_null($hdthumb_url) || ($hdthumb_url=="")) ? $pst['hdthumb_url'] : $hdthumb_url;
							$retinaWidth 		= (is_null($retinaW) || ($retinaW=="")) ? $pst['retina_width'] : $retinaW;
							$retinaHeight 		= (is_null($retinaH) || ($retinaH=="")) ? $pst['retina_height'] : $retinaH;
							$retinaUrl 			= (is_null($retina_url) || ($retina_url=="")) ? $pst['retina_url'] : $retina_url;
							$nonretinaWidth 	= (is_null($nonRetinaW) || ($nonRetinaW=="")) ? $pst['nonretina_width'] : $nonRetinaW;
							$nonretinaHeight	= (is_null($nonRetinaH) || ($nonRetinaH=="")) ? $pst['nonretina_height'] : $nonRetinaH;
							$nonretinaUrl 		= (is_null($nonretina_url) || ($nonretina_url=="")) ? $pst['nonretina_url'] : $nonretina_url;
							$sdWidth 			= (is_null($sdW) || ($sdW=="")) ? $pst['sd_width'] : $sdW;
							$sdHeight 			= (is_null($sdH) || ($sdH=="")) ? $pst['sd_height'] : $sdH;
							$sdUrl 				= (is_null($sd_url) || ($sd_url=="")) ? $pst['sd_url'] : $sd_url;
							$musicUrl 			= (is_null($music) || ($music=="")) ? $pst['music_url'] : $music; //$pst['music_url'] : $music;
							$song 				= (is_null($songName) || ($songName=="")) ? $pst['song'] : $songName;
							$author 			= (is_null($songAuthor) || ($songAuthor=="")) ? $pst['author'] : $songAuthor;
							$poix 				= (is_null($postPoi[0]) || ($postPoi[0]=="")) ? $pst['poix'] : $postPoi[0];
							$poiy 				= (is_null($postPoi[1]) || ($postPoi[1]=="")) ? $pst['poiy'] : $postPoi[1];

							$user = $this->vastuser->getUserBySession($sessionId);
							if(!empty($user))
								$user	=	call_user_func_array('array_merge', $user);
							$postStatus = ($video!="" || $extraVideo!="") ? 0 : 1;
							$surl = $pst['short_url'];

							$this->vpost->insertPostDetails(array(
								'chapter_id' => $chapterId,
								'channel_id' => $user['channel_id'],
								'type' => $type,
								'date' => $scDate,
								'story' => $text,
								'title' => $title,
								'subscription_id' => $subscriptionId,
								'audio_url' => $audioUrl,
								'video_url' => $videoUrl,
								'second_video' => $secVideo,
								'thumb_width' => $thumbWidth,
								'thumb_height' => $thumbHeight,
								'thumb_url' => $thumbUrl,
								'hdthumb_width' => $hdthumbWidth,
								'hdthumb_height' => $hdthumbHeight,
								'hdthumb_url' => $hdthumbUrl,
								'retina_width' => $retinaWidth,
								'retina_height' => $retinaHeight,
								'retina_url' => $retinaUrl,
								'nonretina_width' => $nonretinaWidth,
								'nonretina_height' => $nonretinaHeight,
								'nonretina_url' => $nonretinaUrl,
								'sd_width' => $sdWidth,
								'sd_height' => $sdHeight,
								'sd_url' => $sdUrl,
								'music_url' => $musicUrl,
								'song' => $song,
								'author' => $author,
								'poix' => $poix,
								'poiy' => $poiy,
								'post_status' => $postStatus,
								'post_edit' => 1,
								'post_id' => $postId));

							$post_id = $postId;

							if($postStatus==0){
								$tw = in_array("tw", $socialShare) ? 1 : 0;
								$fb = in_array("fb", $socialShare) ? 1 : 0;
								$tb = in_array("tm", $socialShare) ? 1 : 0;
								if($tw==1) {
									$token	=	$this->vastuser->getSocialShareDetails($user['channel_id']);
									$token	=	call_user_func_array('array_merge', $token);
									$tw_token = ($token['twitter_token'] == '' ) ? '0' : $token['twitter_token'];
									$tw_secret = ($token['twitter_token_secret'] == '' ) ? '0' : $token['twitter_token_secret'];
								}
								if($fb==1) {
									$token	=	$this->vastuser->getSocialShareDetails($user['channel_id']);
									$token	=	call_user_func_array('array_merge', $token);
									$fb_token = ($token['fb_token'] == '' ) ? '0' : $token['fb_token'];
								}
								if($tb==1) {
									$token	=	$this->vastuser->getSocialShareDetails($user['channel_id']);
									$token	=	call_user_func_array('array_merge', $token);
									$tb_token = ($token['tumblr_token'] == '' ) ? '0' : $token['tumblr_token'];
									$tb_username = ($token['tumblr_username'] == '' ) ? '0' : $token['tumblr_username'];
									$tb_secret = ($token['tumblr_oauth_token_secret'] == '' ) ? '0' : $token['tumblr_oauth_token_secret'];
								}
								$this->conversion->insertPost(array('post_id' => $post_id,
									'videourl' => $videoUrl,
									'secvideourl' => $secVideo,
									'channel_id' => $user['channel_id'],
									'percent' => 0,
									'post_title' => $title,
									'share_text' => $shareText,
									'tw' => $tw,
									'fb' => $fb,
									'tb' => $tb,
									'short_url' => $surl,
									'tw_token' => $tw_token,
									'tw_secret' => $tw_secret,
									'fb_token' => $fb_token,
									'tb_token' => $tb_token,
									'tb_username' => $tb_username,
									'tb_secret' => $tb_secret));
							}
						// $pst =	$this->vpost->getPostsListByPostId($post_id);

						// if(!empty($pst))
							// $pst	=	call_user_func_array('array_merge', $pst);

							// $manage	=	$this->manageexp->getMappingRowsByLimit($pst['channel_id']);

							// if(!empty($manage)) {
								// $manage	=	call_user_func_array('array_merge', $manage);

								// $nonfeaturedLists 		= 	explode(',', $manage['non_featured_list']);
								// $nonfeaturedpostLists 	= 	explode(',', $manage['non_featured_post_list']);
								// $i = 0;
								// foreach ($nonfeaturedpostLists as $nonfeaturedpostList) {
									// if ($nonfeaturedpostList == $post_id) {
										// $nonfeaturedLists[$i] = $chapterId;
									// }
									// $i++;
								// }
								// $nonFeatured = implode(',', $nonfeaturedLists);
								// $this->manageexp->updateNonFeaturedListById(array('type' => 1,
									// 'non_featured_list' => $nonFeatured,
									// 'latestEntry' => 0,
									// 'id' => $manage['id']));
							// }
						//}

						$cp = $this->chapter->getChapterByCover($post_id);
						if(!empty($cp)) {
							$cp	=	call_user_func_array('array_merge', $cp);

							if($cp['chapter_id'] != $chapterId) {
								$this->chapter->updateCover($post_id);
							}
						}

					}
				//}

				$posts = $this->vpost->getPostsListByPostId($post_id);
				if(!empty($posts)) {
					$posts	=	call_user_func_array('array_merge', $posts);
					if(count($socialShare) > 0) {
						if(($video == "") || ($postJson->type != 'video' && $secVideo == "")) {
							if(in_array("tw", $socialShare)) {
								$token	=	$this->vastuser->getSocialShareDetails($posts['channel_id']);
								if(!empty($token))
									$token	=	call_user_func_array('array_merge', $token);

								$twitterMsg	=	$shareText . ' - ' . $posts['short_url'];
								$connection	=	new Twitter($token['twitter_token'], $token['twitter_token_secret']);

								$connection->tweet($twitterMsg);
							}

							if(in_array("fb", $socialShare)){
								$token	=	$this->vastuser->getSocialShareDetails($posts['channel_id']);
								if(!empty($token)) {
									$token	=	call_user_func_array('array_merge', $token);

									$share_array	=	array(
										'access_token' => $token['fb_token'],
										'link' => $posts['short_url'],
										'caption' => 'VAST - The ultimate fan experience',
										'message' => $shareText,
										'scrap' => true
									);

									$fb = new Facebook();
									$fb->shareLink($share_array);
								}
							}

							if(in_array("tm", $socialShare)) {

								switch ($posts['type']) {
									case 'music':
										$caption 	= 	$shareText . ', Song : ' . $posts['song'] . ', Artist : ' . $posts['author'];
										$caption 	= 	($shareText == '' ) ? $posts['title'] : $shareText;
										$music_url 	= 	preg_replace("~[\r\n]~", "", $posts['music_url']);
										$source_url = 	S3::getS3Url(S3::getPostMusicPath($music_url));//MUSIC_PATH . $music_url;

										$post_data = array('type' => 'audio', 'caption' => '<a href="' . $posts['short_url'] . '">' . $caption . '<a>', 'external_url' => $source_url);
										break;

									case 'video':
										$caption 	= 	$shareText;
										$video_url 	= 	preg_replace("~[\r\n]~", "", $posts['video_url']);
										$source_url = 	S3::getS3Url(S3::getPostVideoPath($video_url));//VIDEO_PATH . $video_url;
										$embed 		= 	' <video controls>
																<source src="' . $source_url . '"></source>
															</video>';
										$post_data 	= 	array('type' => 'video', 'caption' => '<a href="' . $posts['short_url'] . '">' . $caption . '<a>', 'embed' => $embed);
										break;

									case 'image':
										$caption 	= 	$shareText;
										$sd_url 	= 	preg_replace("~[\r\n]~", "", $posts['sd_url']);
										$source_url = 	S3::getS3Url(S3::getPostRetinaPath($sd_url));//RETINA_PATH . $sd_url;

										$post_data 	= 	array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $posts['short_url']);
										break;
									default:
										break;
								}

								if($post_data) {
									$token_details	=	$this->vastuser->getSocialShareDetails($posts['channel_id']);
									if(!empty($token_details)) {
										$token_details	=	call_user_func_array('array_merge', $token_details);

										$token 			= 	$token_details['tumblr_token'];
										$tokenSecret 	= 	$token_details['tumblr_oauth_token_secret'];
										$token_name 	= 	$token_details['tumblr_username'] . '.tumblr.com';

										$connection 	= 	new Tumblr($token, $tokenSecret);
										$connection->createPost($token_name, $post_data);
									}
								}
							}
						}
					}


					$postInfo 		= array();
					$postThumb 		= array();
					$postThmb 		= array();
					$postSd 		= array();
					$postContents	= array();
					$postImage 		= array();
					$postImg 		= array();
					$content		= array();
					$cover 			= array();
					$thumb 			= array();
					$image 			= array();
					$liveCover 		= array();

					$postInfo['id'] 	= (string) $posts['post_id'];
					$postInfo['type'] = $posts['type'];
					$postThumb['width'] 	= ($posts['thumb_width']=="") ? '' : $posts['thumb_width'];
					$postThumb['height'] 	= ($posts['thumb_height']=="") ? '' : $posts['thumb_height'];
					$thumb_url 			= preg_replace("~[\r\n]~", "",$posts['thumb_url']);
					$postThumb['url'] 	= ($posts['thumb_url']=="") ? '' : S3::getS3Url(S3::getPostPhotoPath($thumb_url));//THUMB_PATH . $thumb_url;
					$postThmb['width'] 	= ($posts['hdthumb_width']=="") ? '' : $posts['hdthumb_width'];
					$postThmb['height']	= ($posts['hdthumb_height']=="") ? '' : $posts['hdthumb_height'];
					$hdthumb_url 		= preg_replace("~[\r\n]~", "",$posts['hdthumb_url']);
					$postThmb['url'] 		= ($posts['hdthumb_url']=="") ? '' : S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url));//HD_THUMB_PATH . $hdthumb_url;
					$postSd['width'] 		= ($posts['sd_width']=="") ? '' : $posts['sd_width'];
					$postSd['height'] 	= ($posts['sd_height']=="") ? '' : $posts['sd_height'];
					$sd_url 			= preg_replace("~[\r\n]~", "",$posts['sd_url']);
					$postSd['url'] 		= ($posts['sd_url']=="") ? '' : S3::getS3Url(S3::getPostSdPath($sd_url));// SD_PATH . $sd_url;
					$thumb[] = $postThumb;
					$thumb[] = $postThmb;
					$thumb[] = $postSd;
					$postInfo['thumbnail'] = $thumb;
					// unset($postThumb);
					// unset($postThmb);
					// unset($postSd);
					// unset($thumb);

					if($posts['type'] == 'image') {
						if($posts['audio_url']!="") {
							$audio_url 	= preg_replace("~[\r\n]~", "", $posts['audio_url']);
							$postContents['audio'] = S3::getS3Url(S3::getSecAudioPath($audio_url));// AUDIO_PATH . $audio_url;
						}
						if($posts['second_video']!="") {
							$secvideo_url = preg_replace("~[\r\n]~", "", $posts['second_video']);
							$postContents['video'] = S3::getS3Url(S3::getSecVideoPath($secvideo_url));// SECOND_VIDEO_PATH . $secvideo_url;
						}
						$postImage['width'] = ($posts['retina_width']=="") ? '' : $posts['retina_width'];
						$postImage['height'] = ($posts['retina_height']=="") ? '' : $posts['retina_height'];
						$retina_url = preg_replace("~[\r\n]~", "",$posts['retina_url']);
						$postImage['url'] = ($posts['retina_url']=="") ? '' : S3::getS3Url(S3::getPostRetinaPath($retina_url));// RETINA_PATH . $retina_url;
						$postImg['width'] = ($posts['nonretina_width']=="") ? '' : $posts['nonretina_width'];
						$postImg['height'] = ($posts['nonretina_height']=="") ? '' : $posts['nonretina_height'];
						$nonretina_url = preg_replace("~[\r\n]~", "",$posts['nonretina_url']);
						$postImg['url'] = ($posts['nonretina_url']=="") ? '' : S3::getS3Url(S3::getPostNonretinaPath($nonretina_url));// NONRETINA_PATH . $nonretina_url;
						$image[] = $postImage;
						$image[] = $postImg;
						$postContents['image'] = $image;
						//unset($image);
					}
					if($posts['type'] == 'video') {
						$video_url = preg_replace("~[\r\n]~", "",$posts['video_url']);
						$postContents['url'] = ($posts['video_url']=="") ? '' : S3::getS3Url(S3::getPostVideoPath($video_url));// VIDEO_PATH . $video_url;
						if($posts['audio_url']!="") {
							$audio_url = preg_replace("~[\r\n]~", "",$posts['audio_url']);
							$postContents['audio'] = S3::getS3Url(S3::getSecAudioPath($audio_url));// AUDIO_PATH . $audio_url;
						}
					}
					if($posts['type'] == 'link') {
						$postContents['url'] = ($posts['link_url']=="") ? '' : $posts['link_url'];
						$link_thumb = preg_replace("~[\r\n]~", "",$posts['link_thumb']);
						$postContents['thumbnail'] = ($posts['link_thumb']=="") ? '' : S3::getS3Url(S3::getPostlinkthumbPath($link_thumb));// LINK_THUMB_PATH . $link_thumb;
					}
					if($posts['type'] == 'text') {
						$postContents = "";
					}
					if($posts['type'] == 'music') {
						$music_url = preg_replace("~[\r\n]~", "",$posts['music_url']);
						$postContents['url'] = ($posts['music_url']=="") ? '' : S3::getS3Url(S3::getPostMusicPath($music_url));//MUSIC_PATH . $music_url;
						$postContents['song'] = ($posts['song']=="") ? '' : $posts['song'];
						$postContents['author'] = ($posts['author']=="") ? '' : $posts['author'];
						$postContents['album'] = ($posts['album']=="") ? '' : $posts['album'];
						$postContents['itunes_link'] = ($posts['itunes_link']=="") ? '' : $posts['itunes_link'];
						if($posts['second_video']!="") {
							$secvideo_url = preg_replace("~[\r\n]~", "",$posts['second_video']);
							$postContents['video'] = S3::getS3Url(S3::getSecVideoPath($secvideo_url));// SECOND_VIDEO_PATH . $secvideo_url;
						}
					}

					$postInfo['contents'] = $postContents;
					//unset($postContents);
					$postInfo['date'] = $posts['date'];

					$ch	=	$this->channel->getChannelDetailsByChannelId($posts['channel_id']);
					if(!empty($ch))
						$ch	=	call_user_func_array('array_merge', $ch);
					$postInfo['channel_info'] = $this->channelInfo($ch, 'publishPost');

					$chapter =	$this->chapter->getChapterDetailsById($posts['chapter_id']);
					if(!empty($chapter)) {
						$chapter					=	call_user_func_array('array_merge', $chapter);

						$chapter['channel_id']		=	(isset($chapter['channel_id']) && $chapter['channel_id'] != '')	?	$chapter['channel_id']	:	'';
						$chapter['chapter_id']		=	(isset($chapter['chapter_id']) && $chapter['chapter_id'] != '')	?	$chapter['chapter_id']	:	'';
						$chapter['chapter_name']	=	(isset($chapter['chapter_name']) && $chapter['chapter_name'] != '')	?	$chapter['chapter_name']	:	'';
						$content['channelID'] = (string) $chapter['channel_id'];
						$content['id'] = (string) $chapter['chapter_id'];
						$content['name'] = $this->escapeStr($chapter['chapter_name']);

						$postInfo['chapter'] = $content;
						//unset($content);
						$postInfo['aspect_ratio'] = ($posts['aspect_ratio']=="") ? '' : $posts['aspect_ratio'];
						$postInfo['poi'] = ($posts['poix']=="" || $posts['poix']==0) ? '{0.5, 0.5}' : '{'.$posts['poix'].', '.$posts['poiy'].'}';
						$postInfo['post_url'] = ($posts['short_url']=="") ? '' : $posts['short_url'];

						$num = $this->like->getLikesByPostId($posts['post_id']);
						$postInfo['likes'] = $num;
						if($posts['title']!='') {
							$postInfo['title'] = preg_replace("~[\r\n]~", "",$posts['title']);
						}
						if($posts['story']!='') {
							$postInfo['text'] = $this->escapeStr(preg_replace("~[\r\n]~", "", html_entity_decode($posts['story'])));
						}
						$postInfo['required_subscription'] = ($posts['subscription_id']=="") ? '' : $posts['subscription_id'];

						Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$chapter['channel_id']);
					}

					$response = array(
						'result' => 'success',
						'post' => $postInfo
					);
					return Response::json($response);
				}
			}
		}
	}

	/**
	 * Delete post
	 * @return json
	*/
	public function deletePost()
	{
		$sessionId	=	Input::get('session_id');
		$postId		=	Input::get('post_id');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!isset($postId)) {
			return self::failure(2, 'Missing required parameter post_id');
		} else {
			$postsDet = $this->vpost->getPostsByPostId($postId);
			$postsDet = $postsDet[0];
			$postCount = count($this->vpost->getPostsbyChapter($postsDet->chapter_id));
			$hasMorePost = ($postCount == 1) ? 0:1;
			if(Input::get('debugg') == 'anoop') {
				echo $hasMorePost;
			}
			$postChapter = $this->chapter->getChapterDetailsById($postsDet->chapter_id);
			if(!empty($postChapter)) {
				$postChapter = call_user_func_array('array_merge', $postChapter);
			}
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);
			$this->vpost->delete($postId, $user['channel_id']);
			$this->chapter->updateCover($postId);
			$postArr = explode(',', $postChapter['post_order']);
			if (($key = array_search($postId, $postArr)) !== false) {
				unset($postArr[$key]);
			}
			$postOrder = implode(",", $postArr);
			$updateCptrArr =  array(
					'chapter_id' => $postsDet->chapter_id,
					'post_order' => $postOrder
			);
			$this->chapter->update($updateCptrArr);

			$manage	=	$this->manageexp->getRowsByChannelId($user['channel_id']);
			if(!empty($manage)) {
				$manage	=	call_user_func_array('array_merge', $manage);

				$featuredId 	= 	explode(',', $manage['featured_list']);
				$nonfeaturedId 	= 	explode(',', $manage['non_featured_post_list']);
				$nonfeaturedChapterId = explode(',', $manage['non_featured_list']);

				$network		=	$this->manageexp->getByNetworkId($manage['network_id']);
				//$networkQry 	= 	$this->selectRows($this->_groupTable, '*', 'where id = \'' . addslashes($manage['network_id']) . '\'');
				if(!empty($network)) {
					$network	=	call_user_func_array('array_merge', $network);

					$featuredNet 	= 	explode(',', $network['featured_list']);
					$featuredNet 	= 	explode(',', $network['featured_list']);

					foreach($featuredNet as &$v){
						if($v == $postId){
							$v = 0;
						}
					}

					$featuredNetwork 	= 	implode(",", $featuredNet);

					$this->manageexp->updateDetailsByNetworkId($featuredNetwork, $network['id']);
					//$this->updateRows($this->_groupTable, 'featured_list = \'' . addslashes($featuredNetwork) . '\'', 'where id = \'' . addslashes($network['id']) .'\'');

				}

				foreach($featuredId as &$value) {
					if($value == $postId){
						$value = 0;
					}
				}
				foreach($nonfeaturedId as $key => &$val){
					if($val == $postId && $hasMorePost == 0) {
						$nonfeaturedChapterId[$key] = 0;
					}
					if($val == $postId){
						$val = 0;
					}
				}

				$featuredList 		= 	implode(",", $featuredId);
				$nonfeaturedPosts 	= 	implode(",", $nonfeaturedId);
				$nonfeaturedChapters = implode(",", $nonfeaturedChapterId);

				$this->manageexp->updateDetailsByChannelId($featuredList, $nonfeaturedChapters, $nonfeaturedPosts, $user['channel_id']);
				//$this->updateRows($this->_groupTable, 'featured_list = \'' . addslashes($featuredList) . '\' , non_featured_post_list = \'' . addslashes($nonfeaturedPosts) . '\'', 'where mapping_id = \'' . addslashes($user['channel_id']) . '\'');

			}
			Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);
			// $response 				= 	new stdClass();
			//result : success
			// $response->result 		= 	"success";
			// $response->description 	= 	"Post deleted";
			$response = array(
				'result' => 'success',
				'description' => 'Post deleted'
			);
			return Response::json($response);
		}
	}

	/**
	 * Scheduled Posts
	 * @return json
	*/
	public function scheduledPost()
	{
		$sessionId = Input::get('session_id');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user)) {
				$user	=	call_user_func_array('array_merge', $user);
			} else {
				return self::failure(2, 'Invalid session_id');
			}

			$posts = array();
			$postQrys 	= 	$this->vpost->getPostsByChannelIdAndDate($user['channel_id'], time(), 'date');
			if(count($postQrys)>0) {
				foreach ($postQrys as $postQry) {
					$post = $this->userFeedPostInfo($postQry, 'schedulePost');
					$posts[] = array(
					'schedule_date' => $postQry['date'],
					'post' => $post
					);
				}
			}
			$response = array(
				'result' => 'success',
				'posts' => $posts
			);
			return Response::json($response);
		}
	}

	/**
	 * Publish Message
	 * @return json
	*/
	public function publishMessage()
	{
		$sessionId	=	Input::get('session_id');
		$message 	=	Input::get('message');
		$image 		= 	Input::file('image');

		$msgJson 	= 	json_decode($message);

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!isset($message)) {
			return self::failure(2, 'Missing required parameter message');
		} else {
			if(!empty($image)) {
				$date		=	time();

				$channel 	= 	$this->session->getSessionBySessionId($sessionId);
				if(!empty($channel))
					$channel	=	call_user_func_array('array_merge', $channel);

				$channelId 		= 	$channel['vast_user_id'];
				$channelDetails	=	$this->channel->getChannelById($channelId);
				if($image) {
					$msgPhotoUpload = S3::getretinaImage('image', $channelDetails);

					$msgPhotoUpload	=	json_decode($msgPhotoUpload);

					$retinaW	=	$msgPhotoUpload->retina->w;
					$retinaH	=	$msgPhotoUpload->retina->h;
					$photo		=	$msgPhotoUpload->retina->photo;

				}
				else {
					$photo = "";
					$retinaW = "";
					$retinaH = "";
				}

				$msgId	=	$this->message->getMessageId(array(
					'channel_id' => $channelId,
					'type' => $msgJson->options,
					'subject' => $msgJson->subject,
					'message' => $msgJson->body,
					'image' => $photo,
					'date' => $date,
					'image_width' => $retinaW,
					'image_height' => $retinaH,
					'action' => 'reply',
					'ref_id' => 0 ));
				if($msgId) {
					$ch	=	$this->channel->getChannelDetailsByChannelId($channelId);

					if(!empty($ch))
						$ch	=	call_user_func_array('array_merge', $ch);
					$msg	=	$this->message->getMessageDetailsById($msgId);

					$msgText	=	"Holla! ". $ch['first_name']. " ". $ch['last_name'] . " sent you a message.";
					$sound 		=	"default";

					if($msg['type']==1) {
						$usrQry = $this->follow->getFollowersByChannelId($msg['channel_id']);
						foreach($usrQry as $usr) {
							$tknDetail	=	$this->device->getDeviceByUserID($usr['vast_user_id']);
							if(!empty($tknDetail)) {
								foreach ($tknDetail as $tkn) {
									if($tkn['device_token']!="") {
										$apnsToken	=	$tkn['device_token'];
										$this->message->_sendPushNotification($apnsToken, $msgText, $msgId, $sound, $action = '');
									}
								}
							}
						}
					} else if($msg['type']==2) {
						$usrQry = 	$this->subscribe->getSubscribersByChannel($msg['channel_id']);
						foreach($usrQry as $usr) {
							$tknDetail = $this->device->getDeviceByUserID($usr['vast_user_id']);
							if(!empty($tknDetail)) {
								foreach ($tknDetail as $tkn) {
									if($tkn['device_token']!="") {
										$apnsToken	=	$tkn['device_token'];
										$this->message->_sendPushNotification($apnsToken, $msgText, $msgId, $sound, $action = '');
									}
								}
							}
						}
					}
					$msgInfo 	= 	array();
					$msgImg 	=	array();
					$msgs 		=	array();

					$msgInfo['id'] 		= 	(string) $msg['message_id'];
					$subject 			= 	preg_replace("~[\r\n]~", "",strip_tags($msg['subject']));
					$msgInfo['subject']	= 	$this->escapeStr($subject);
					$body 				= 	preg_replace("~[\r\n]~", "",strip_tags($msg['message']));
					$msgInfo['body'] 	= 	$this->escapeStr($body);

					$image_url 			= 	preg_replace("~[\r\n]~", "",$msg['image']);

					$msgImg['width'] 	= 	($msg['image_width'] == 0) ? '' : (string) $msg['image_width'];
					$msgImg['height'] 	= 	($msg['image_height'] == 0) ? '' : (string) $msg['image_height'];
					$msgImg['url'] 		= 	($msg['image']=='') ? '' : S3::getS3Url(S3::getMessagePhotoPath($image_url)); //MESSAGE_IMAGE_PATH . $image_url;
					$msgs[] 			= 	$msgImg;

					$msgInfo['image'] 	= 	$msgs;
					//unset($msgImg);
					//unset($msgs);

					$msgInfo['timestamp']   = 	(string) $msg['date'];
					$msgInfo['access_type'] = 	$msg['type'];

					$response = array(
						'result' => 'success',
						'message' => $msgInfo
					);
					return Response::json($response);
				} else {
					return self::failure(12, 'Something is wrong');
				}
			}
		}
	}

	/**
	 * Get messages
	 * @return json
	*/
	public function getMessages()
	{
		$sessionId = Input::get('session_id');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
			$messages = array();
			$channel	=	$this->session->getSessionBySessionId($sessionId);
			if(!empty($channel)) {
				$channel	=	call_user_func_array('array_merge', $channel);

				$channelId 	= 	$channel['vast_user_id'];

				$msgQry = $this->message->getMessageByChannelId($channelId);
				foreach($msgQry as $msg) {
					$msgInfo	=	array();
					$msgImg 	= 	array();
					$msgs 		= 	array();

					$msgInfo['id'] 		= 	(string) $msg['message_id'];
					$subject 			= 	preg_replace("~[\r\n]~", "",strip_tags($msg['subject']));
					$msgInfo['subject'] = 	$this->escapeStr($subject);
					$body 				= 	preg_replace("~[\r\n]~", "",strip_tags($msg['message']));
					$msgInfo['body'] 	= 	$this->escapeStr($body);

					$image_url 			=	preg_replace("~[\r\n]~", "",$msg['image']);

					$msgImg['width'] 	= 	($msg['image_width'] == 0) ? '' : (string) $msg['image_width'];
					$msgImg['height'] 	= 	($msg['image_height'] == 0) ? '' : (string) $msg['image_height'];
					$msgImg['url'] 		= 	($msg['image']=='') ? '' : S3::getS3Url(S3::getMessagePhotoPath($image_url)); //MESSAGE_IMAGE_PATH . $image_url;
					$msgs[] 			= 	$msgImg;

					$msgInfo['image'] 	=	$msgs;
					//unset($msgImg);
					//unset($msgs);

					$msgInfo['timestamp'] = 	(string) $msg['date'];
					$msgInfo['access_type']	=	$msg['type'];

					$messages[]	=	$msgInfo;
					//unset($msgInfo);
				}
			}
			$response = array(
				'result' => 'success',
				'messages' => $messages
			);
			return Response::json($response);
		}
	}

	/**
	 * Edit Livestream
	 * @return json
	*/
	public function editLivestream()
	{
		$sessionId	=	Input::get('session_id');
		$livestream = 	Input::get('livestream');
		$cover 		= 	Input::file('cover');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!isset($livestream)) {
			return self::failure(2, 'Missing required parameter livestream');
		} else if(!isset($cover)) {
			return self::failure(2, 'Missing required parameter cover');
		} else {
			$channel 	= 	$this->session->getSessionBySessionId($sessionId);
			if(!empty($channel))
				$channel	=	call_user_func_array('array_merge', $channel);

			$channelId 		= 	$channel['vast_user_id'];
			$channelDetails	=	$this->channel->getChannelById($channelId);
			if(!empty($cover)) {
				$liveJson 	= 	json_decode($livestream);

				$LivestreamUpload 	= 	S3::getLivestreamImage('cover', $channelDetails);

				$LivestreamUpload	=	json_decode($LivestreamUpload);

				$sdW	=	$LivestreamUpload->sd->w;
				$sdH	=	$LivestreamUpload->sd->h;
				$sd_url	=	$LivestreamUpload->sd->photo;

				$hdthumbW		=	$LivestreamUpload->hdthumb->w;
				$hdthumbH		=	$LivestreamUpload->hdthumb->h;
				$hdthumb_url	=	$LivestreamUpload->hdthumb->photo;

				$thumbW		=	$LivestreamUpload->thumb->w;
				$thumbH		=	$LivestreamUpload->thumb->h;
				$thumb_url	=	$LivestreamUpload->thumb->photo;

				$res = $this->channel->update($channelId, array(
		        	'livestream_name' => $liveJson->name,
					'livestream_title' => $liveJson->title,
					'live_thumb_width' => $thumbW,
					'live_thumb_height' => $thumbH,
					'live_thumb_url' => $thumb_url,
					'live_hdthumb_width' => $hdthumbW,
					'live_hdthumb_height' => $hdthumbH,
					'live_hdthumb_url' => $hdthumb_url,
					'live_sd_width' => $sdW,
					'live_sd_height' => $sdH,
					'live_sd_url' => $sd_url,
					'live_featured' => $liveJson->subscribers_only
		    	));

				$chl		=	$this->channel->getChannelDetailsByChannelId($channelId);
				if(!empty($chl)) {
					$chl	=	call_user_func_array('array_merge', $chl);

					$chId 		= 	$chl['channel_id'];
					$chName 	= 	$chl['first_name'] . $chl['last_name'];
					$evName 	= 	$chl['livestream_event'];
					$msgText	= 	$chName. " just went live. See what is happening now!.";
					$sound 		= 	"default";

					if($liveJson->subscribers_only==0) {
						$userQry = $this->user->getDeviceDetailsOrderById();

						foreach($userQry as $usr) {
							$tknDetail = $this->device->getDeviceByUserID($usr['vast_user_id']);
							if(!empty($tknDetail)) {
								foreach ($tknDetail as $tkn) {
									if($tkn['device_token']!="") {
										$apnsToken	=	$tkn['device_token'];
										$this->channel->_sendPushNotificationLive($apnsToken, $msgText, $chId, $chName, $evName, $sound, $action='');
									}
								}
							}
						}
					} else if($liveJson->subscribers_only==1) {
						$usrQry = 	$this->subscribe->getSubscribersByChannel($chl['channel_id']);
						foreach($usrQry as $usr) {
							$tknDetail = $this->device->getDeviceByUserID($usr['vast_user_id']);
							if(!empty($tknDetail)) {
								foreach ($tknDetail as $tkn) {
									if($tkn['device_token']!="") {
										$apnsToken	=	$tkn['device_token'];
										$this->channel->_sendPushNotificationLive($apnsToken, $msgText, $chId, $chName, $evName, $sound, $action='');
									}
								}
							}
						}
					}
				}
				$user 				=	$this->vastuser->getUserBySession($sessionId);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);
				$user_info = $this->userInfo($user['channel_id']);

				$response = array(
					'result' => 'success',
					'user_info' => $user_info
				);

				return Response::json($response);
			}
		}
	}

	/**
	 * Remove livestream
	 * @return json
	*/
	public function removeLivestream()
	{
		$sessionId = Input::get('session_id');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
			$ch	=	$this->session->getSessionBySessionId($sessionId);
			if(!empty($ch)){
				$ch			=	call_user_func_array('array_merge', $ch);
		 		$channelId 	= 	$ch['user_id'];
			 	$res = $this->channel->update($channelId, array(
			        	'livestream_name' => "",
						'livestream_title' => "",
						'live_thumb_width' => "",
						'live_thumb_height' => "",
						'live_thumb_url' => "",
						'live_hdthumb_width' => "",
						'live_hdthumb_height' => "",
						'live_hdthumb_url' => "",
						'live_sd_width' => "",
						'live_sd_height' => "",
						'live_sd_url' => "",
						'live_featured' => 0
			    	));
			}
			$user 				=	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);
			$user_info = $this->userInfo($user['channel_id']);

			$response = array(
				'result' => 'success',
				'user_info' => $user_info
			);

			return Response::json($response);
		}
	}

	/** old app calls start here **/

	/**
	 * Register User
	 * @return json
	*/
	public function doRegister()
	{
		$params = Input::file('params');
		$image 	= Input::file('image');
		$date  	= time();
		if(!isset($params)) {
			return self::failure(2, 'Missing required json file');
		} else {
			$orgName		=	$params->getClientOriginalName();
			$strJson		= 	File::get(Input::file('params')->getRealPath());
			$registerJson 	= json_decode($strJson);
			$regEmail		= (isset($registerJson->email)) ? $registerJson->email : '' ;
			$emailId 		= $this->vastuser->checkValidUser($regEmail);
			if(!empty($emailId))
				$emailId	=	call_user_func_array('array_merge', $emailId);

			if(isset($emailId['vast_user_id']) && $emailId['vast_user_id'] != "") {
				$response = array(
					'result' => 'success',
					'valid' => '0',
					'description' => 'Email is already taken'
				);
				return Response::json($response);
			} else {
				if($image) {
					$image 	= $image->getClientOriginalName();
					$rand = rand(00000, 99999);
					$uploadfilename = $rand . $image;
					$photo = $rand . $image;
					S3::uploadVastUserAvatar('image', $photo);
				} else {
					$photo = "";
				}
				$salt 		= 	'2144758762534858898eef20.73371324';
				$userId = $this->vastuser->create(array(
							'role_id' => 4,
							'first_name' => (isset($registerJson->first_name)) ? $registerJson->first_name : '',
							'last_name' => (isset($registerJson->last_name)) ? $registerJson->last_name : '',
							'username' => (isset($registerJson->username)) ? $registerJson->username : '',
							'email_id' => (isset($registerJson->email)) ? $registerJson->email : '',
							'password' => (isset($registerJson->pwd)) ? sha1($salt . $registerJson->pwd) : '',
							'photo' => $photo,
							'location' => (isset($registerJson->location)) ? $registerJson->location : '',
							'phone' => (isset($registerJson->phone)) ? $registerJson->phone : '',
							'birthday' => (isset($registerJson->birthday)) ? $registerJson->birthday : '',
							'join_date' => $date,
							'status' => '1',
							'channel_group_id' => 0
						  ));

				$sessionId = hash_hmac('md5', uniqid($userId), $userId.time());
				$this->session->create(array(
					'session_id' => $sessionId,
					'vast_user_id' => $userId
				));
				/* $this->follow->create(array(
					'channel_id' => 96,
					'user_id' => $userId
				)); */
				$firstname = isset($registerJson->first_name) ? $registerJson->first_name : '';
				$lastname = isset($registerJson->last_name) ? $registerJson->last_name : '';
				$name = $firstname . ' ' . $lastname;
				if($registerJson->email!='') {
					$message = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
									<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
									<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
									<title>VAST</title>
									<style type="text/css">
										table td {
										border-collapse:collapse;
										}
										body {
										margin:0 !important;
										padding:0 !important;
										width:100% !important;
										background-color: #ffffff !important;
										}

									</style>
								</head>
								<body>
									<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
										<tbody>
											<tr>
												<td valign="top" style="padding-left: 10px;">
													<table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
														<tbody>
															<tr>
																<td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="https://prod.thefutureisvast.us/images/vast_app_logo.png" alt=""  /></td>
															</tr>
															<tr>
																<td class="text_content" valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding: 50px 15px;">
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">Welcome to the future epicenter of the Internet.</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">We\'ll bring you closer to the people and things you love,</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">and introduce you to culture you\'d never find anywhere else.</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">Prepare to get lost in what you find.</p>
																	<p style="padding: 0;text-transform: uppercase;
										font-size: .8em;letter-spacing: 1px;line-height: 2em;text-align: center;
										font-weight: bold;">We\'ll be in touch.</p>
																	<h1 style="text-align:center;">
																	<img style="margin:auto; " src="https://prod.thefutureisvast.us/images/vast_logo_mail.png" />
																	</h1>
																</td>
															</tr>
															<tr>
																<td valign="middle" align="center" bgcolor="#ffffff">
																	<hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" />
																</td>
															</tr>
															<tr>
																<td valign="middle" align="center" bgcolor="#ffffff">
																	<p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; 2016 by Vast
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table>
								</body>
							</html>';
					/*$message = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>VAST</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="https://prod.thefutureisvast.us/images/vast_app_logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;">Hey '. $name .',</span></p>
												<p>Welcome to VAST - we are super excited to have you!</p>
												<p>As the ultimate fan experience, our goal is to deliver the very best ways for you to be close to the artists you love.  To that end, below are a few ways to make the most out of our platform. </p>
												<p>
													<ul>
														<li>Use EXPLORE to find interesting artists and content</li>
														<li>FOLLOW artists to create your personal MY VAST feed</li>
														<li>SUBSCRIBE to your favorite artists to get exclusive content, VIP access to events and more!</li>
													</ul>
												</p>
												<p>If you have any questions, reply to this email or holla at us on Twitter. (@GetVASTnow).</p>
												<p>Hugs,<br />
											  Team VAST</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; 2016 by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';*/


					Util::sendSESMail('noreply@thefutureisvast.us', $registerJson->email, 'WELCOME TO VAST', null, $message);
				}
				$user = $this->vastuser->getUserBySession($sessionId);
				if(!empty($user))
                		Log::info('Calling doRegister not empty user');
				$user	=	call_user_func_array('array_merge', $user);
				$user_info = $this->userInfo($user['channel_id']);
				$response = array(
								'result' => 'success',
								'session_id' => $user['session_id'],
								'user_info' => $user_info
							);
				#Log::info(print_r($response,true));
				Log::info(json_encode($response));
				return Response::json($response);
			}
		}
	}

	/**
	 * Data Validation
	 * @return json
	*/
	public function doValidateParameter()
	{
		$paramName = Input::get('param_name');
		$paramValue = Input::get('param_value');

		if(!$paramName) {
			return self::failure(2, 'Missing required parameter name');
		} else if($paramName == "password") {
			if($paramValue != "") {
				$response = array(
					'result' => 'success',
					'valid' => '1'
				);
				return Response::json($response);
			} else {
				return self::failure(4, 'Password cannot be null');
			}
		} else if($paramName == "email") {
			if(!preg_match('/^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,4})$/', $paramValue)) {
				return self::failure(6, 'Invalid email');
			} else {
				$user = $this->vastuser->checkValidUser($paramValue);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);

				if(isset($user) && !empty($user)) {
					$response = array(
						'result' => 'success',
						'valid' => '0',
						'description' => 'Email is already taken'
					);
					return Response::json($response);
				} else {
					$response = array(
						'result' => 'success',
						'valid' => '1'
					);
					return Response::json($response);
				}
			}
		} else if($paramName == "own-password") {
			$num = $this->vastuser->verifyUserPassword($paramValue);
			if($num != 0) {
				$response = array(
					'result' => 'success',
					'valid' => '1'
				);
				return Response::json($response);
			} else {
				return self::failure(7, 'Wrong password');
			}
		} else if($paramName == "vastname") {
			if($paramValue != "") {
				$user = $this->vastuser->checkValidUserName($paramValue);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);

				if(isset($user) && !empty($user)) {
					$response = array(
						'result' => 'success',
						'valid' => '0',
						'description' => 'vastname is already taken'
					);
					return Response::json($response);
				} else {
					$response = array(
						'result' => 'success',
						'valid' => '1'
					);
					return Response::json($response);
				}
			} else {
				return self::failure(4, 'parameter value cannot be null');
			}
		} else {
			return self::failure(5, 'Malformed data or other error');
		}
	}

	/**
	 * Change password
	 * @return json
	*/
	public function changePassword()
	{
		$sessionId = Input::get('session_id');
		$oldPassword = Input::get('old_password');
		$newPassword = Input::get('new_password');
		$salt 		= 	'2144758762534858898eef20.73371324';

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		}  else {
			$user = $this->vastuser->getUserBySession($sessionId);

			if(!empty($user)) {
				$user	=	call_user_func_array('array_merge', $user);
				if(!$user['fb_uid']) {
					if(!trim($oldPassword)) {
						return self::failure(12, 'Old password can\'t be blank');
					} else if($user['password'] != sha1($salt . $oldPassword)) {
						return self::failure(13, 'Incorrect old password');
					} else if(!trim($newPassword)) {
						return self::failure(12, 'New password can\'t be blank');
					} else if($oldPassword == $newPassword) {
						return self::failure(13, 'New password should not be same as old password');
					}else if($user['password'] == sha1($salt . $newPassword)) {
						return self::failure(13, 'Password should be different from the existing password');
					} else {
						$this->vastuser->updateUserPassword($user['vast_user_id'],$newPassword,$oldPassword);
						$response = array(
							'result' => 'success',
							'description' => 'Password updated successfully'
						);
						return Response::json($response);
					}
				} else {
					return self::failure(14, 'Facebook user can\'t change password here');
				}
			} else {
				return self::failure(1010, 'User does not exist');
			}
		}
	}

	/**
	 * Follow channel
	 * @return json
	*/
	public function followChannel()
	{
		$sessionId = Input::get('session_id');
		$channelId = Input::get('channel_id');
		$follow    = Input::get('follow');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$channelId) {
			return self::failure(2, 'Missing required parameter channel_id');
		} else if($follow=='') {
			return self::failure(2, 'Missing required parameter follow');
		} else {
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);
			$channels = array();
			if($follow == 1) {
				$count = $this->follow->getUserFollowedChannelById($user['vast_user_id'], $channelId);
				if($count == 0) {
					if($channelId == $user['vast_user_id']) {
						return self::failure(20, 'User can\'t follow own channel');
						exit;
					} else {
						$this->follow->create(array(
							'channel_id' => $channelId,
							'user_id' => $user['vast_user_id'],
							'status' => 1,
							'refdate' => time()
						));
					}
				} else {
					return self::failure(20, 'User is already following the channel');
					exit;
				}
			} else {
				$followers = $this->follow->getFollowersByChannelUser($channelId, $user['vast_user_id']);
				if(!empty($followers))
					$followers	=	call_user_func_array('array_merge', $followers);
				$updateArr = array(
					'follow_id' => $followers['follow_id'],
					'status' => 0,
					'refdate' => time()
				);
				$this->follow->update($updateArr);
			}
			$followQry = $this->follow->getFollowChannelByUser($user['vast_user_id']);
			foreach($followQry as $followed) {
				$channels[] = (string) $followed['channel_id'];
			}
			$response = array(
				'result' => 'success',
				'followed' => $channels
			);
			return Response::json($response);
		}
	}

	/**
	 * Add to favorites
	 * @return json
	*/
	public function addFavorite()
	{
		$sessionId = Input::get('session_id');
		$postId    = Input::get('post_id');
		$like      = Input::get('like');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$postId) {
			return self::failure(2, 'Missing required parameter post_id');
		} else if($like == '') {
			return self::failure(2, 'Missing required parameter like');
		} else {
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);
			if($like==1) {
				$check = $this->like->getUserLikedPostById($user['vast_user_id'], $postId);
				if($check == 0) {
					$chnl = $this->vpost->getPostsListByPostId($postId);
					if(!empty($chnl)) {
						$chnl =	call_user_func_array('array_merge', $chnl);
					}
					$this->like->create(array(
						'post_id' => $postId,
						'user_id' => $user['vast_user_id'],
						'channel_id' => $chnl['channel_id'],
						'refdate' => time()
					));
				} else {
					return self::failure(20, 'User is already likes the post');
					exit;
				}
			} else {
				$this->like->delete($postId, $user['vast_user_id']);
			}
			$likes = $this->like->getLikesByPostId($postId);

			$response = array(
				'result' => 'success',
				'likes' => $likes
			);
			return Response::json($response);
		}
	}

	/**
	 * Subscribe
	 * @return json
	*/
	public function doSubscribe()
	{
		$sessionId = Input::get('session_id');
		$subscribeId = Input::get('subscription_id');
		$receipt = Input::get('receipt');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$subscribeId) {
			return self::failure(2, 'Missing required parameter subscription_id');
		} else if(!$receipt) {
			return self::failure(2, 'Missing required parameter receipt');
		} else {
			$verify = $this->verifyStoreReceipt($receipt);
			if($verify['status'] == 0 || $verify['status'] == 21006) {
				$user = $this->vastuser->getUserBySession($sessionId);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);
				$num = $this->subscribe->getNumSubscriptionByUser($subscribeId, $user['vast_user_id']);
				$fnum = $this->follow->getFollowChannelByUser($user['vast_user_id']);
				if(count($fnum) ==0) {
					$chnl = $this->channel->getChannelBySubscribeId($subscribeId);
					if(!empty($chnl))
						$chnl	=	call_user_func_array('array_merge', $chnl);
					$this->follow->create(array(
						'channel_id' => $chnl['channel_id'],
						'user_id' => $user['vast_user_id']
					));
				}
				if($num != 0) {
					$sel = $this->subscribe->getSubscriptionBySubUser($subscribeId, $user['vast_user_id']);
					if(!empty($sel))
						$sel	=	call_user_func_array('array_merge', $sel);
					$this->subscribe->update(array(
						'ID' => $sel['ID'],
						'active_subscription' => $subscribeId,
						'user_id' => $user['vast_user_id'],
						'receipt' => $verify['latest_receipt']
					));
				} else {
					$this->subscribe->create(array(
						'active_subscription' => $subscribeId,
						'user_id' => $user['vast_user_id'],
						'receipt' => $verify['latest_receipt']
					));
				}
				$response = array(
					'result' => 'success'
				);
				return Response::json($response);
			} else if($verify['status'] == 21007) {
				return self::failure(21007, 'The receipt is a sandbox receipt, but it was sent to the production service for verification');
			} else if($verify['status'] == 21005) {
				return self::failure(21005, 'The receipt server is not currently available');
			} else {
				$response = array(
					'result' => 'success',
					'status' => $verify['status']
				);
				return Response::json($response);
			}
		}
	}

	/**
	 * Notifications
	 * @return json
	**/
	public function getNotifications()
	{
		$sessionId = Input::get('session_id');
		$referenceDate = Input::get('reference_date');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$referenceDate) {
			return self::failure(2, 'Missing required parameter reference_date');
		} else {
			$notposts = array();
			$loopedItems = array();
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user)) {
				$user	=	call_user_func_array('array_merge', $user);
			} else {
				return self::failure(2, 'Invalid session_id');
			}
			$time = time();
			$followQry = $this->follow->getFollowChannelByUser($user['vast_user_id']);
			$i = 1;
			foreach($followQry as $followed) {
				//print_r($followed);
				$postQry = $this->vpost->getPostByDate($followed['channel_id'], $referenceDate, $time);

				$notInfo = array();
				foreach($postQry as $post) {
					$notInfo['id'] = (string) $i;
					$notInfo['type'] = 'new';
					$postInfo	=	$this->userFeedPostInfo($post, 'notifications');
					$notInfo['post'] = $postInfo;
					$usrQry = $this->channel->getChannelDetailsByChannelId($post['channel_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => ($usrQry['vast_name'] != "") ? $usrQry['vast_name'] : $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$notInfo['user'] = $usrInfo;
					$chInfo = array();
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $post['date'];
					$num = $this->readnotification->getNumReadNotification($post['post_id'], $user['vast_user_id'], 'new');
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}
					$notposts[] = $notInfo;
					$i++;
					//unset($notInfo);
				}

			}




			$followUserQry = $this->follow->getFollowersByDate($user['vast_user_id'], $referenceDate, $time);
			foreach($followUserQry as $followuser) {
				//echo 1;
				$notInfo['id'] = (string) $i;
				if($followuser['status']=='1') {
					$notInfo['type'] = 'follow';
					$typo = 'follow';
				} else {
					$notInfo['type'] = 'unfollow';
					$typo = 'unfollow';
				}
				$postInfo	=	array();
				$notInfo['post'] = $postInfo;
				$usrQry = $this->channel->getChannelDetailsByChannelId($followuser['user_id']);
				if(!empty($usrQry))
					$usrQry	=	call_user_func_array('array_merge', $usrQry);
				$usrInfo	= array(
					'id'           => (string) $usrQry['channel_id'],
					'channel_name' => ($usrQry['vast_name'] != "") ? $usrQry['vast_name'] : $usrQry['first_name']. ' '.$usrQry['last_name'],
					'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
				);
				$notInfo['user'] = $usrInfo;
				$chQry = $this->channel->getChannelDetailsByChannelId($followuser['channel_id']);
				if(!empty($chQry))
					$chQry	=	call_user_func_array('array_merge', $chQry);
				$chInfo	= array(
					'id'           => (string) $chQry['channel_id'],
					'channel_name' => ($chQry['vast_name'] != "") ? $chQry['vast_name'] : $chQry['first_name']. ' '.$chQry['last_name'],
					'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$chQry['avatar']))
				);
				$notInfo['channel'] = $chInfo;
				array_push($loopedItems,(string) $usrQry['channel_id'].','.(string) $chQry['channel_id'].',follow');
				//unset($postInfo);
				$notInfo['timestamp'] = (string) $followuser['refdate'];
				$num = $this->readnotification->getNumFollowNotification($user['vast_user_id'], $usrQry['channel_id'], $typo);
				if($num==0) {
					$notInfo['read'] = 0;
				} else {
					$notInfo['read'] = 1;
				}
				$notposts[] = $notInfo;
				$i++;
				//unset($notInfo);
			}


			$followerUserQry = $this->follow->getChannelFollowersByDate($user['vast_user_id'], $referenceDate, $time);
			foreach($followerUserQry as $followeruser) {
				//print_r($followeruser);
				$followUserQry = $this->follow->getChannelFollowersByDate($followeruser['channel_id'], $referenceDate, $time);
				foreach($followUserQry as $followuser) {

					//echo 2;
					$notInfo['id'] = (string) $i;
					if($followuser['status']=='1') {
						$notInfo['type'] = 'follow';
						$typo = 'follow';
					} else {
						$notInfo['type'] = 'unfollow';
						$typo = 'unfollow';
					}
					$postInfo	=	array();
					$notInfo['post'] = $postInfo;
					//print_r($followed);
					$usrQry = $this->channel->getChannelDetailsByChannelId($followed['channel_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => ($usrQry['vast_name'] != "") ? $usrQry['vast_name'] : $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$notInfo['user'] = $usrInfo;
					//print_r($followuser);
					$chQry = $this->channel->getChannelDetailsByChannelId($followuser['channel_id']);
					if(!empty($chQry))
						$chQry	=	call_user_func_array('array_merge', $chQry);
					$chInfo	= array(
						'id'           => (string) $chQry['channel_id'],
						'channel_name' => ($chQry['vast_name'] != "") ? $chQry['vast_name'] : $chQry['first_name']. ' '.$chQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$chQry['avatar']))
					);
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $followuser['refdate'];
					$num = $this->readnotification->getNumFollowNotification($user['vast_user_id'], $usrQry['channel_id'], $typo);
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}
					$conditionText = (string)$usrQry['channel_id'].','.(string) $chQry['channel_id'].',follow';
					if(!in_array($conditionText,$loopedItems)){
						array_push($loopedItems,(string) $usrQry['channel_id'].','.(string) $chQry['channel_id'].',follow');
						$notposts[] = $notInfo;
						$i++;
					}


					//unset($notInfo);
				}
			}

			$likeUsrQry = $this->like->getUserLikesByDate($user['vast_user_id'], $referenceDate, $time);
			foreach($likeUsrQry as $liked) {
				$notInfo['id'] = (string) $i;
				$notInfo['type'] = 'like';
				$post = $this->vpost->getPostsListByPostId($liked['post_id']);
				if(!empty($post))
					$post	=	call_user_func_array('array_merge', $post);
				$postInfo	=	$this->userFeedPostInfo($post, 'notifications');
				$notInfo['post'] = $postInfo;
				$usrQry = $this->channel->getChannelDetailsByChannelId($liked['user_id']);
				if(!empty($usrQry))
					$usrQry	=	call_user_func_array('array_merge', $usrQry);
				$usrInfo	= array(
					'id'           => (string) $usrQry['channel_id'],
					'channel_name' => ($usrQry['vast_name'] != "") ? $usrQry['vast_name'] : $usrQry['first_name']. ' '.$usrQry['last_name'],
					'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
				);
				$conditionText = (string) $usrQry['channel_id'].',like';
				array_push($loopedItems,$conditionText);
				$notInfo['user'] = $usrInfo;
				$chInfo = array();
				$notInfo['channel'] = $chInfo;
				//unset($postInfo);
				$notInfo['timestamp'] = (string) $liked['refdate'];
				$num = $this->readnotification->getNumLikeNotification($post['post_id'], $user['vast_user_id'], $usrQry['channel_id'], 'like');
				if($num==0) {
					$notInfo['read'] = 0;
				} else {
					$notInfo['read'] = 1;
				}
				$notposts[] = $notInfo;
				$i++;
				//unset($notInfo);
			}

			$followerUserQry = $this->follow->getChannelFollowersByDate($user['vast_user_id'], $referenceDate, $time);
			foreach($followerUserQry as $followeruser) {
				$likeFollowQry = $this->like->getFollowLikesByDate($followeruser['channel_id'], $referenceDate, $time);
				foreach($likeFollowQry as $liked) {
					$notInfo['id'] = (string) $i;
					$notInfo['type'] = 'like';
					$post = $this->vpost->getPostsListByPostId($liked['post_id']);
					if(!empty($post))
						$post	=	call_user_func_array('array_merge', $post);
					$postInfo	=	$this->userFeedPostInfo($post, 'notifications');
					$notInfo['post'] = $postInfo;
					$usrQry = $this->channel->getChannelDetailsByChannelId($liked['user_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => ($usrQry['vast_name'] != "") ? $usrQry['vast_name'] : $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$notInfo['user'] = $usrInfo;
					$chInfo = array();
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $liked['refdate'];
					$num = $this->readnotification->getNumLikeNotification($post['post_id'], $user['vast_user_id'], $usrQry['channel_id'], 'like');
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}

					$conditionText = (string) $usrQry['channel_id'].',like';
					if(!in_array($conditionText,$loopedItems)){
						array_push($loopedItems,(string) $usrQry['channel_id'].',like');
						$notposts[] = $notInfo;
						$i++;
					}

					//unset($notInfo);
				}
			}


			$shareQry = $this->vastshare->getShareByDate($user['vast_user_id'], $referenceDate, $time);
			foreach($shareQry as $shared) {
				if($shared['user_id'] != $shared['channel_id']) {
					$notInfo['id'] = (string) $i;
					$notInfo['type'] = 'share';
					$post = $this->vpost->getPostsListByPostId($shared['post_id']);
					if(!empty($post))
						$post	=	call_user_func_array('array_merge', $post);
					$postInfo	=	$this->userFeedPostInfo($post, 'notifications');
					$notInfo['post'] = $postInfo;
					$usrQry = $this->channel->getChannelDetailsByChannelId($shared['user_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => ($usrQry['vast_name'] != "") ? $usrQry['vast_name'] : $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$conditionText = (string) $usrQry['channel_id'].',share';
					array_push($loopedItems,$conditionText);
					$notInfo['user'] = $usrInfo;
					$chInfo = array();
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $shared['refdate'];
					$num = $this->readnotification->getNumShareNotification($post['post_id'], $user['vast_user_id'], $usrQry['channel_id'], 'share');
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}
					$notposts[] = $notInfo;
					$i++;
					//unset($notInfo);
				}
			}

			$followerUserQry = $this->follow->getChannelFollowersByDate($user['vast_user_id'], $referenceDate, $time);
			foreach($followerUserQry as $followeruser) {
				$shareQry = $this->vastshare->getFollowShareByDate($followeruser['channel_id'], $referenceDate, $time);
				foreach($shareQry as $shared) {
					$notInfo['id'] = (string) $i;
					$notInfo['type'] = 'share';
					$post = $this->vpost->getPostsListByPostId($shared['post_id']);
					if(!empty($post))
						$post	=	call_user_func_array('array_merge', $post);
					$postInfo	=	$this->userFeedPostInfo($post, 'notifications');
					$notInfo['post'] = $postInfo;
					$usrQry = $this->channel->getChannelDetailsByChannelId($shared['user_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => ($usrQry['vast_name'] != "") ? $usrQry['vast_name'] : $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$notInfo['user'] = $usrInfo;
					$chInfo = array();
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $shared['refdate'];
					$num = $this->readnotification->getNumShareNotification($post['post_id'], $user['vast_user_id'], $usrQry['channel_id'], 'share');
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}
					$conditionText = (string) $usrQry['channel_id'].',share';
					if(!in_array($conditionText,$loopedItems)){
						array_push($loopedItems,(string) $usrQry['channel_id'].',share');
						$notposts[] = $notInfo;
					}
					$i++;
					//unset($notInfo);
				}

			}

			$response = array(
				'result' => 'success',
				'notifications' => $notposts
			);
			return Response::json($response);
		}
	}

	/**
	 * Push Notifications
	 * @return json
	*/
	public function pushNotifications()
	{
		$sessionId = Input::get('session_id');
		$deviceId = Input::get('device_id');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$deviceId) {
			return self::failure(2, 'Missing required parameter device_id');
		} else {
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);
			$num = $this->device->getDeviceById($deviceId);
			if($num==0) {
				$this->device->create(array(
					'device_token' => $deviceId,
					'user_id' => $user['vast_user_id'],
					'bind_date' => time()
				));
			}

			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * Unsubscribe push notifications
	 * @return json
	**/
	public function unsubscribeApns()
	{
		$sessionId = Input::get('session_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user)) {
				$user	=	call_user_func_array('array_merge', $user);
				$deviceQry = $this->device->getDeviceByUserID($user['vast_user_id']);
				foreach($deviceQry as $device) {
					$unsubscribeQry = $this->device->deleteDevice($device['token_id']);
				}
			}
			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * Read flag for messages
	 * @return json
	 */
	public function readMessage()
	{
		$sessionId = Input::get('session_id');
		$messageId = Input::get('message_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$messageId) {
			return self::failure(2, 'Missing required parameter message_id');
		} else {
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user)){
				$user	=	call_user_func_array('array_merge', $user);
				$this->readmessage->create(array(
					'message_id' => $messageId,
					'user_id' => $user['vast_user_id']
				));
			}
			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * Read flag for notifications
	 * @return json
	 */
	public function readNotification()
	{
		$sessionId = Input::get('session_id');
		$notificationId = Input::get('notification_id');
		$postId = Input::get('post_id');
		$type = Input::get('type');
		$channelId = Input::get('channel_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$notificationId) {
			return self::failure(2, 'Missing required parameter notification_id');
		} else if(!$type) {
			return self::failure(2, 'Missing required parameter type');
		} else {
			if(!$postId) {
				$postId = 1;
			}
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user)) {
				$user	=	call_user_func_array('array_merge', $user);
				$this->readnotification->create(array(
					'notification_id' => $notificationId,
					'user_id' => $user['vast_user_id'],
					'post_id' => $postId,
					'channel_id' => $channelId,
					'type' => $type
				));
			}
			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * Share tracking
	 * @return json
	 */
	public function incrementShareCount()
	{
		$sessionId = Input::get('session_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user)){
				$user	=	call_user_func_array('array_merge', $user);
				$this->share->create(array(
					'channel_id' => 96,
					'user_id' => $user['vast_user_id']
				));
			}
			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * App version
	 * @returm json
	 */
	public function appVersion()
	{
		$ver = $this->version->getVersion();
		$response = array(
			'result' => 'success',
			'version_num' => $ver['version_number'],
			'build_num' => $ver['build_number'],
			'update_link' => $ver['update_link']
		);
		return Response::json($response);
	}

	/**
	 * User favorites
	 * @return json
	*/
	public function userFavorite()
	{
		$sessionId	=	Input::get('session_id');
		$postId    	= 	Input::get('post_id');
		$count     	= 	Input::get('count');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!isset($count)) {
			return self::failure(2, 'Missing required parameter count');
		} else {
			$response = new stdClass();
			$response->result = "success";
			if($postId) {
				$response->post_id = $postId;
			}
			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user)) {
				$user	=	call_user_func_array('array_merge', $user);
				$favDetails	=	$this->like->getNumOfFavorites($postId, $user['vast_user_id'], $count);
				$num	=	count($favDetails);

				if($num < $count) {
					$response->count = $num;
				} else {
					$response->count = $count;
				}
				$response->total = $num;
				$favorites = array();

				foreach ($favDetails as $key => $value) {
					$favPostId 		=	$value['post_id'];

					$postInfo			=	$this->userFeedPostInfo($value, 'userFav');
					$favorites[] 		=	$postInfo;
					//unset($postInfo);
				}
				$response->favorites	=	$favorites;
				/* if($postId) {
					$response = array('post_id' => $postId);
				}
				$response = array(
					'result' => 'success',
					'count' => (string) $rescount,
					'total' => (string) $total,
					'favorites' => $favorites
				); */

				return Response::json($response);

			}

		}
	}

	/**
	 * Channel group request
	 * @return json
	*/
	public function getChannelGroup()
	{
		$groupId	=	Input::get('group_id');
		if(!$groupId) {
			return self::failure(2, 'Missing required parameter group_id');
		} else {
			//$response	=	new stdClass();
			// success
			//$response->result	=	"success";
			$group	=	$this->manageexp->getByIdAndType($groupId, 0);
			if(!empty($group)) {
				$group				=	call_user_func_array('array_merge', $group);
				$channelGroup		=	array();
				$groupImgRetina 	= 	array();
				$groupImgNonretina	=	array();
				$groupImgSd			=	array();
				$groupCover 		= 	array();

				$channelGroup['id']	=	(string) $group['id'];
				$channelGroup['name'] = 	$group['screen_name'];
				$image_url 			=	preg_replace("~[\r\n]~", "",$group['cover']);

				$groupImgRetina['width'] 		= ($group['retinaW'] == "" || $group['retinaW'] == 0) ? '' : (string) $group['retinaW'];
				$groupImgRetina['height'] 	= ($group['retinaH'] == "" || $group['retinaH'] == 0) ? '' : (string) $group['retinaH'];
				$groupImgRetina['url'] 		= ($group['cover']=='') ? '' : S3::getS3Url(S3::getChannelGroupRetinaPath($image_url)); //CHANNEL_GROUP_RETINA_PATH . $image_url;
				$groupImgNonretina['width'] 	= ($group['nonretinaW'] == "" || $group['nonretinaW'] == 0) ? '' : (string) $group['nonretinaW'];
				$groupImgNonretina['height']	= ($group['nonretinaH'] == "" || $group['nonretinaH'] == 0) ? '' : (string) $group['nonretinaH'];
				$groupImgNonretina['url'] 	= ($group['cover']=='') ? '' : S3::getS3Url(S3::getChannelGroupNonRetinaPath($image_url)); //CHANNEL_GROUP_NONRETINA_PATH . $image_url;
				$groupImgSd['width'] 			= ($group['sdW'] == "" || $group['sdW'] == 0) ? '' : (string) $group['sdW'];
				$groupImgSd['height'] 		= ($group['sdH'] == "" || $group['sdH'] == 0) ? '' : (string) $group['sdH'];
				$groupImgSd['url'] 			= ($group['cover']=='') ? '' : S3::getS3Url(S3::getChannelGroupSdPath($image_url)); //CHANNEL_GROUP_SD_PATH . $image_url;
				$groupCover[] 				= $groupImgRetina;
				$groupCover[] 				= $groupImgNonretina;
				$groupCover[] 				= $groupImgSd;
				$channelGroup['cover'] 		= $groupCover;
				// unset($groupImgRetina);
				// unset($groupImgNonretina);
				// unset($groupImgSd);
				// unset($groupCover);

				$featuredId 	= explode(',', $group['featured_list']);
				$GroupFeatured 	= array();
				$post 			= array();
				foreach( $featuredId as $value) {
					$posts			= $this->vpost->getPostsListByPostId($value);

					if($value!=0) {
						$postInfo	=	$this->userFeedPostInfo($posts, 'getChannelGroup');

						$post['type']	=	"post";
						$post['data']   = 	$postInfo;
						$GroupFeatured[]	=	$post;
						// unset($postInfo);
						// unset($post);
					}
				}
				$channelGroup->featured	=	$GroupFeatured;

				if($group['disabled']=='0') {
					$channelGroup->disabled_text = '';
				} else {
					$channelGroup->disabled_text = $group['alert_text'];
				}

				$nonfeaturedId	=	explode(',', $group['non_featured_list']);
				$otherChannel 	= 	array();
				foreach( $nonfeaturedId as $value) {
					$channels	=	$this->channel->getChannelPostDetails($value);
					if(!empty($channels))
						$channels	=	call_user_func_array('array_merge', $channels);
					if($value!=0) {
						$details	=	$this->channelInfo($channels, 'getChnlGroup');
						$otherChannel[]	= $details;
						//unset($details);
					}
				}
				$channelGroup->channels = $otherChannel;
				//$response->channel_group = $channelGroup;
				$response = array(
					'result' => 'success',
					'channel_group' => $channelGroup
				);
			}
			return Response::json($response);
		}
	}

	/**
	 * Get popular channels
	 * @return json
	**/
	public function getPopularChannels()
	{
		$popularCh 			= array();

		$popularQry = $this->channel->getPopChannelDetails('tot', 30);
		foreach($popularQry as $chanl) {
			$channelInfo	=	$this->channelInfo($chanl, 'getPopularChannels');
			$popularCh[]	= 	$channelInfo;
			unset($channelInfo);
		}
		$response = array(
			'result' => 'success',
			'channels' => $popularCh
		);
		return Response::json($response);
	}

	/**
	 * Explore
	 * @return json
	*/
	public function explore()
	{
		$response	=	new stdClass();
		//success
		$response->result 	= "success";
		$featured 			= new stdClass();
		$featureList 		= array();

		$manageGroupQry = $this->manageexp->getDiscoverByOrder();
		if(Input::get('debugs')=='abhi2'){
			print_r($manageGroupQry);
		}
		foreach($manageGroupQry as $group) {
			if($group['type']==0) {
				$groupImgRetina 	= new stdClass();
				$groupImgNonretina 	= new stdClass();
				$groupImgSd 		= new stdClass();
				$groupCover 		= array();
				$latestId 			= array();
				$channelGroup 		= new stdClass();
				$channelg 			= new stdClass();
				$channelGroup->id 	= (string) $group['id'];
				$channelGroup->name = $group['screen_name'];
				$image_url 			= preg_replace("~[\r\n]~", "",$group['cover']);

				$groupImgRetina->width 		= ($group['retinaW'] == "" || $group['retinaW'] == 0) ? '' : (string) $group['retinaW'];
				$groupImgRetina->height 	= ($group['retinaH'] == "" || $group['retinaH'] == 0) ? '' : (string) $group['retinaH'];
				$groupImgRetina->url 		= ($group['cover']=='') ? '' : S3::getS3Url(S3::getChannelGroupRetinaPath($image_url)); //CHANNEL_GROUP_RETINA_PATH . $image_url;
				$groupImgNonretina->width 	= ($group['nonretinaW'] == "" || $group['nonretinaW'] == 0) ? '' : (string) $group['nonretinaW'];
				$groupImgNonretina->height	= ($group['nonretinaH'] == "" || $group['nonretinaH'] == 0) ? '' : (string) $group['nonretinaH'];
				$groupImgNonretina->url 	= ($group['cover']=='') ? '' : S3::getS3Url(S3::getChannelGroupNonRetinaPath($image_url)); //CHANNEL_GROUP_NONRETINA_PATH . $image_url;
				$groupImgSd->width 			= ($group['sdW'] == "" || $group['sdW'] == 0) ? '' : (string) $group['sdW'];
				$groupImgSd->height 		= ($group['sdH'] == "" || $group['sdH'] == 0) ? '' : (string) $group['sdH'];
				$groupImgSd->url 			= ($group['cover']=='') ? '' : S3::getS3Url(S3::getChannelGroupSdPath($image_url)); //CHANNEL_GROUP_SD_PATH . $image_url;
				$groupCover[] 				= $groupImgRetina;
				$groupCover[] 				= $groupImgNonretina;
				$groupCover[] 				= $groupImgSd;

				$channelGroup->cover 	= 	$groupCover;
				unset($groupImgRetina);
				unset($groupImgNonretina);
				unset($groupImgSd);
				unset($groupCover);

				$featuredId 	= explode(',', $group['featured_list']);
				$nonfeature 	= explode(',', $group['non_featured_list']);
				$GroupFeatured 	= array();

				$features	=	array_filter($nonfeature);

				$pstQry		=	$this->vpost->getPostByChannelIdAndDate($features, time(), 'post_id', 5);
				foreach($pstQry as $pst) {
					$latestId[] = $pst['post_id'];
				}
				$featureSum = array_sum($featuredId);

				if($group['disabled']=='0') {
					$channelGroup->disabled_text = '';
				} else {
					$channelGroup->disabled_text = $group['alert_text'];
				}

				$nonfeaturedId 	= explode(',', $group['non_featured_list']);
				$otherChannel 	= array();
				foreach( $nonfeaturedId as $value ) {
					$channels	=	$this->channel->getChannelFlagDetails($value);
					if(Input::get('debugs')=='anoop1'){
						print_r($channels); exit;
					}
					if(!empty($channels))
						$channels	=	call_user_func_array('array_merge', $channels);
					if($value!=0 && isset($channels['channel_id']) && $channels['channel_id']!= "") {
						$details = new stdClass();
						$social  = new stdClass();
						$livestream = new stdClass();
						$coverThumb = new stdClass();
						$coverThmb = new stdClass();
						$coverSd = new stdClass();
						$liveThumb = new stdClass();
						$liveThmb = new stdClass();
						$liveSd = new stdClass();
						$profileThumb = new stdClass();
						$profileThmb = new stdClass();
						$profileSd = new stdClass();
						$cover = array();
						$liveCover = array();
						$profile = array();

						$details->id = (string) $channels['channel_id'];
						$details->first_name = $channels['first_name'];
						$details->last_name = $channels['last_name'];
						$details->vast_name = $channels['vast_name'];
						$avatar = preg_replace("~[\r\n]~", "",$channels['avatar']);
						$details->avatar = ($channels['avatar']=="") ? '' : S3::getS3Url(S3::getTwitterAvatar($avatar)); //AVATAR_SD_PATH . $avatar;
						$details->bio = $channels['biography'];
						$profileThumb->width 		= ($channels['channel_cover_thumbW']=="" || $channels['channel_cover_thumbW']==0) ? '' : (string) $channels['channel_cover_thumbW'];
						$profileThumb->height 		= ($channels['channel_cover_thumbH']=="" || $channels['channel_cover_thumbH']==0) ? '' : (string) $channels['channel_cover_thumbH'];
						$thumb_profile 				= preg_replace("~[\r\n]~", "",$channels['channel_cover']);
						$profileThumb->url 			= ($channels['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('thumb/'.$thumb_profile)); //PROFILE_THUMB_PATH .$thumb_profile;
						$profileThmb->width 		= ($channels['channel_cover_hd_thumbW']=="" || $channels['channel_cover_hd_thumbW']==0) ? '' : (string) $channels['channel_cover_hd_thumbW'];
						$profileThmb->height 		= ($channels['channel_cover_hd_thumbH']=="" || $channels['channel_cover_hd_thumbH']==0) ? '' : (string) $channels['channel_cover_hd_thumbH'];
						$hdthumb_profile 			= preg_replace("~[\r\n]~", "",$channels['channel_cover']);
						$profileThmb->url 			= ($channels['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('hdthumb/'.$hdthumb_profile)); //PROFILE_HDTHUMB_PATH . $hdthumb_profile;
						$profileSd->width 			= ($channels['channel_cover_sdW']=="" || $channels['channel_cover_sdW']==0) ? '' : (string) $channels['channel_cover_sdW'];
						$profileSd->height 			= ($channels['channel_cover_sdH']=="" || $channels['channel_cover_sdH']==0) ? '' : (string) $channels['channel_cover_sdH'];
						$sd_profile 				= preg_replace("~[\r\n]~", "",$channels['channel_cover']);
						$profileSd->url 			= ($channels['channel_cover']=="") ? '' : S3::getS3Url(S3::getAppCover('sd/'.$sd_profile)); //PROFILE_SD_PATH . $sd_profile;
						$profile[] 					= $profileThumb;
						$profile[] 					= $profileThmb;
						$profile[] 					= $profileSd;

						$details->profile_image = $profile;
						unset($profileThumb);
						unset($profileThmb);
						unset($profileSd);
						unset($profile);

						$manage	=	$this->manageexp->getBYFlagAndType($value, 1, 1);
						 if(!empty($manage)){
							 $manage	=	call_user_func_array('array_merge', $manage);
						 }
						if(Input::get('debugs')=='abhi'){
							print_r($manage);
						}

						if(isset($channels['channel_cover'])) {
							$coverSd->width = ($channels['channel_cover_thumbW'] == "") ? '' : (string) $channels['channel_cover_thumbW'];
							$coverSd->height = ($channels['channel_cover_thumbH'] == "") ? '' : (string) $channels['channel_cover_thumbH'];
							$sd_cover = preg_replace("~[\r\n]~", "",$channels['channel_cover']);
							$coverSd->url = ($channels['channel_cover'] == "") ? '' : S3::getS3Url(S3::getAppCover('thumb/'.$sd_cover)); // CHANNEL_RETINA_PATH . $sd_cover;
							$coverThmb->width = ($channels['channel_cover_hd_thumbW'] == "") ? '' : (string) $channels['channel_cover_hd_thumbW'];
							$coverThmb->height = ($channels['channel_cover_hd_thumbH'] == "") ? '' : (string) $channels['channel_cover_hd_thumbH'];
							$hdthumb_cover = preg_replace("~[\r\n]~", "",$channels['channel_cover']);
							$coverThmb->url = ($channels['channel_cover'] == "") ? '' : S3::getS3Url(S3::getAppCover('hdthumb/'.$hdthumb_cover)); //CHANNEL_NONRETINA_PATH . $hdthumb_cover;
							$coverThumb->width = ($channels['channel_cover_sdW'] == "") ? '' : (string) $channels['channel_cover_sdW'];
							$coverThumb->height = ($channels['channel_cover_sdH'] == "") ? '' : (string) $channels['channel_cover_sdH'];
							$thumb_cover = preg_replace("~[\r\n]~", "",$channels['channel_cover']);
							$coverThumb->url = ($channels['channel_cover'] == "") ? '' : S3::getS3Url(S3::getAppCover('sd/'.$thumb_cover)); //CHANNEL_SD_PATH .$thumb_cover;
							$cover[] = $coverThumb;
							$cover[] = $coverThmb;
							$cover[] = $coverSd;

							$details->cover = $cover;
							unset($coverThumb);
							unset($coverThmb);
							unset($coverSd);
							unset($cover);

							$details->poi = '{0.5, 0.5}';
						} else {
							$coverThumb->width = ($channels['thumbW']=="") ? '' : (string) $channels['thumbW'];
							$coverThumb->height = ($channels['thumbH']=="") ? '' : (string) $channels['thumbH'];
							$thumb_cover = preg_replace("~[\r\n]~", "",$channels['avatar']);
							$coverThumb->url = ($channels['avatar']=="") ? '' : S3::getS3Url(S3::getChannelAvatarThumb($thumb_cover)); //AVATAR_THUMB_PATH .$thumb_cover;
							$coverThmb->width = ($channels['hdthumbW']=="") ? '' : (string) $channels['hdthumbW'];
							$coverThmb->height = ($channels['hdthumbH']=="") ? '' : (string) $channels['hdthumbH'];
							$hdthumb_cover = preg_replace("~[\r\n]~", "",$channels['avatar']);
							$coverThmb->url = ($channels['avatar']=="") ? '' :  S3::getS3Url(S3::getChannelAvatarHdThumb($hdthumb_cover)); //AVATAR_HDTHUMB_PATH . $hdthumb_cover;
							$coverSd->width = ($channels['sdW']=="") ? '' : (string) $channels['sdW'];
							$coverSd->height = ($channels['sdH']=="") ? '' : (string) $channels['sdH'];
							$sd_cover = preg_replace("~[\r\n]~", "",$channels['avatar']);
							$coverSd->url = ($channels['avatar']=="") ? '' : S3::getS3Url(S3::getTwitterAvatar($sd_cover)); //AVATAR_SD_PATH . $sd_cover;
							$cover[] = $coverThumb;
							$cover[] = $coverThmb;
							$cover[] = $coverSd;

							$details->cover = $cover;
							unset($coverThumb);
							unset($coverThmb);
							unset($coverSd);
							unset($cover);

							$details->poi = ($channels['poix']=="" || $channels['poix']==0) ? '{0.5, 0.5}' : '{'.$channels['poix'].', '.$channels['poiy'].'}';
						}

						$social->facebook = $channels['fb_url'];
						$social->twitter = $channels['twitter_url'];
						$social->instagram = $channels['instagram_url'];
						$social->youtube = $channels['youtube_url'];
						$social->tumblr = $channels['tumblr_url'];
						$details->social = $social;

						$totalFollowers = $this->follow->getFollowersCountByChannel($channels['channel_id']);
						$details->followers = $totalFollowers;
						$followedChannels = $this->follow->getFollowChannelByUser($channels['channel_id']);
						$details->following = count($followedChannels);
						$details->tour = $channels['tour_url'];
						$details->store = $channels['store_url'];
						$details->ticket = $channels['ticket_url'];
						$details->website = $channels['website_url'];
						$details->spotify = $channels['spotify_url'];
						$details->deezer = $channels['deezer_url'];
						$details->beats = $channels['beats_url'];
						$details->itunes = $channels['itunes_url'];
						$details->tidal = $channels['tidal_url'];
						$details->content_subscription_id = (string) $channels['subscription_id'];

						$otherChannel[] = $details;
						unset($livestream);
						unset($social);
						unset($details);
						unset($channels);
					}
				}
				$channelGroup->channels = $otherChannel;
				$channelg->type = "channel_group";
				$channelg->data = $channelGroup;
				$featureList[] = $channelg;
			} else if($group['type']==1) {
				$channelQry	=	$this->channel->getChannelDetailsByChannelId($group['mapping_id']);
				foreach($channelQry as $channels) {
					$chanl = new stdClass();
					$details = new stdClass();
					$social  = new stdClass();
					$livestream = new stdClass();
					$coverThumb = new stdClass();
					$coverThmb = new stdClass();
					$coverSd = new stdClass();
					$liveThumb = new stdClass();
					$liveThmb = new stdClass();
					$liveSd = new stdClass();
					$profileThumb = new stdClass();
					$profileThmb = new stdClass();
					$profileSd = new stdClass();
					$cover = array();
					$liveCover = array();
					$profile = array();

					$details->id = (string) $channels['channel_id'];
					$details->first_name = $channels['first_name'];
					$details->last_name = $channels['last_name'];
					$details->vast_name = $channels['vast_name'];
					$avatar = preg_replace("~[\r\n]~", "",$channels['avatar']);
					$details->avatar = ($channels['avatar']=="") ? '' : S3::getS3Url(S3::getTwitterAvatar($avatar)); //AVATAR_SD_PATH . $avatar;
					$details->bio = $channels['biography'];
					$profileThumb->width 		= ($channels['thumbW']=="" || $channels['thumbW']==0) ? '' : (string) $channels['thumbW'];
					$profileThumb->height 		= ($channels['thumbH']=="" || $channels['thumbH']==0) ? '' : (string) $channels['thumbH'];
					$thumb_profile 				= preg_replace("~[\r\n]~", "",$channels['cover']);
					$profileThumb->url 			= ($channels['cover']=="") ? '' : S3::getS3Url(S3::getProfileThumbPath($thumb_profile)); //PROFILE_THUMB_PATH .$thumb_profile;
					$profileThmb->width 		= ($channels['hdthumbW']=="" || $channels['hdthumbW']==0) ? '' : (string) $channels['hdthumbW'];
					$profileThmb->height 		= ($channels['hdthumbH']=="" || $channels['hdthumbH']==0) ? '' : (string) $channels['hdthumbH'];
					$hdthumb_profile 			= preg_replace("~[\r\n]~", "",$channels['cover']);
					$profileThmb->url 			= ($channels['cover']=="") ? '' : S3::getS3Url(S3::getProfileHdThumbPath($hdthumb_profile)); //PROFILE_HDTHUMB_PATH . $hdthumb_profile;
					$profileSd->width 			= ($channels['sdW']=="" || $channels['sdW']==0) ? '' : (string) $channels['sdW'];
					$profileSd->height 			= ($channels['sdH']=="" || $channels['sdH']==0) ? '' : (string) $channels['sdH'];
					$sd_profile 				= preg_replace("~[\r\n]~", "",$channels['cover']);
					$profileSd->url 			= ($channels['cover']=="") ? '' : S3::getS3Url(S3::getProfileSdThumbPath($sd_profile)); //PROFILE_SD_PATH . $sd_profile;
					$profile[] 					= $profileThumb;
					$profile[] 					= $profileThmb;
					$profile[] 					= $profileSd;

					$details->profile_image = $profile;
					unset($profileThumb);
					unset($profileThmb);
					unset($profileSd);
					unset($profile);
					if(Input::get('debugs')=='abhi3'){
							print_r($channels);
					}

					if($group['cover'] == "") {
						$coverThumb->width 		= ($channels['thumbW']=="" || $channels['thumbW']==0) ? '' : (string) $channels['thumbW'];
						$coverThumb->height 		= ($channels['thumbH']=="" || $channels['thumbH']==0) ? '' : (string) $channels['thumbH'];
						$thumb_profile 				= preg_replace("~[\r\n]~", "",$channels['avatar']);
						$coverThumb->url 			= ($channels['avatar']=="") ? '' : S3::getS3Url(S3::getChannelAvatarPath('thumb/'.$thumb_profile)); //PROFILE_THUMB_PATH .$thumb_profile;
						$coverThmb->width 		= ($channels['hdthumbW']=="" || $channels['hdthumbW']==0) ? '' : (string) $channels['hdthumbW'];
						$coverThmb->height 		= ($channels['hdthumbH']=="" || $channels['hdthumbH']==0) ? '' : (string) $channels['hdthumbH'];
						$hdthumb_profile 			= preg_replace("~[\r\n]~", "",$channels['avatar']);
						$coverThmb->url 			= ($channels['avatar']=="") ? '' : S3::getS3Url(S3::getChannelAvatarPath('hdthumb/'.$hdthumb_profile)); //PROFILE_HDTHUMB_PATH . $hdthumb_profile;
						$coverSd->width 			= ($channels['sdW']=="" || $channels['sdW']==0) ? '' : (string) $channels['sdW'];
						$coverSd->height 			= ($channels['sdH']=="" || $channels['sdH']==0) ? '' : (string) $channels['sdH'];
						$sd_profile 				= preg_replace("~[\r\n]~", "",$channels['avatar']);
						$coverSd->url 			= ($channels['avatar']=="") ? '' : S3::getS3Url(S3::getChannelAvatarPath('sd/'.$sd_profile)); //PROFILE_SD_PATH . $sd_profile;
						$cover[] 					= $coverThumb;
						$cover[] 					= $coverThmb;
						$cover[] 					= $coverSd;

						$details->cover = $cover;
						unset($coverThumb);
						unset($coverThmb);
						unset($coverSd);
						unset($cover);
					} else {
						$cvr	=	$this->vpost->getOnePostByChannel($channels['channel_id'], time());
						if(!empty($cvr))
							$cvr	=	call_user_func_array('array_merge', $cvr);

						$coverThumb->width = ($group['retinaW']=="") ? '' : (string) $group['retinaW'];
						$coverThumb->height = ($group['retinaH']=="") ? '' : (string) $group['retinaH'];
						$thumb_cover = preg_replace("~[\r\n]~", "",$group['cover']);
						$coverThumb->url = ($group['cover']=="") ? '' : S3::getS3Url(S3::getChannelRetinaPath($thumb_cover)); //CHANNEL_RETINA_PATH .$thumb_cover;
						$coverThmb->width = ($group['nonretinaW']=="") ? '' : (string) $group['nonretinaW'];
						$coverThmb->height = ($group['nonretinaH']=="") ? '' : (string) $group['nonretinaH'];
						$hdthumb_cover = preg_replace("~[\r\n]~", "",$group['cover']);
						$coverThmb->url = ($group['cover']=="") ? '' : S3::getS3Url(S3::getChannelNonRetinaPath($hdthumb_cover)); //CHANNEL_NONRETINA_PATH . $hdthumb_cover;
						$coverSd->width = ($group['sdW']=="") ? '' : (string) $group['sdW'];
						$coverSd->height = ($group['sdH']=="") ? '' : (string) $group['sdH'];
						$sd_cover = preg_replace("~[\r\n]~", "",$group['cover']);
						$coverSd->url = ($group['cover']=="") ? '' : S3::getS3Url(S3::getChannelSdPath($sd_cover)); // CHANNEL_SD_PATH . $sd_cover;
						$cover[] = $coverThumb;
						$cover[] = $coverThmb;
						$cover[] = $coverSd;

						$details->cover = $cover;
						unset($coverThumb);
						unset($coverThmb);
						unset($coverSd);
						unset($cover);

						$details->poi = ($cvr['poix']=="" || $cvr['poix']==0) ? '{0.5, 0.5}' : '{'.$cvr['poix'].', '.$cvr['poiy'].'}';
					}

					$social->facebook = $channels['fb_url'];
					$social->twitter = $channels['twitter_url'];
					$social->instagram = $channels['instagram_url'];
					$social->youtube = $channels['youtube_url'];
					$social->tumblr = $channels['tumblr_url'];
					$details->social = $social;

					$totalFollowers = $this->follow->getFollowersCountByChannel($channels['channel_id']);
					$details->followers = $totalFollowers;
					$followedChannels = $this->follow->getFollowChannelByUser($channels['channel_id']);
					$details->following = count($followedChannels);
					$details->tour = $channels['tour_url'];
					$details->store = $channels['store_url'];
					$details->ticket = $channels['ticket_url'];
					$details->website = $channels['website_url'];
					$details->spotify = $channels['spotify_url'];
					$details->deezer = $channels['deezer_url'];
					$details->beats = $channels['beats_url'];
					$details->itunes = $channels['itunes_url'];
					$details->tidal = $channels['tidal_url'];
					$details->content_subscription_id = $channels['subscription_id'];

					$chanl->type = "channel";
					$chanl->data = $details;
					$featureList[] = $chanl;
					unset($livestream);
					unset($social);
					unset($details);
					unset($chanl);
				}
			}
		}

		$response->content = $featureList;
		return Response::json($response);
	}

	/**
	 * Channel request
	 * @return json
	*/
	public function getChannel()
	{

		$channelId		=	Input::get('channel_id');
		$channelData 	=	Input::get('channel_data');

		if(!$channelId) {
			return self::failure(2, 'Missing required parameter channel_id');
		} else if(!$channelData) {
			return self::failure(2, 'Missing required parameter channel_data');
		} else {
			$channelSplit 	= explode(',', $channelData);
			$response 		= new stdClass();
			$response->result = "success";
			for($i=0; $i<3 ; $i++) {
				//channel_info
				if(isset($channelSplit[$i]) && $channelSplit[$i] == 'channel_info') {
					$chnl	=	$this->channel->getChannelDetailsByChannelId($channelId);
					if(!empty($chnl))
						$chnl	=	call_user_func_array('array_merge', $chnl);

					$response->channel_info	=	$this->channelInfo($chnl, 'getChannel');
				//contents
				} else if(isset($channelSplit[$i]) && $channelSplit[$i] == 'contents') {
					$contents	=	array();
					$cptrId     =	array();
					$chapterInserted  = array();
					$nonSum     = 0;

					$manage 	= 	$this->manageexp->getGroupByChannelAndType($channelId, 1);
					if(!empty($manage)) {
						$manage	=	call_user_func_array('array_merge', $manage);
						$nonfeaturedChapterId	=	explode(',', $manage['non_featured_list']);
						$nonfeaturedId	=	explode(',', $manage['non_featured_post_list']);
						$nonSum 	= 	array_sum($nonfeaturedChapterId);
					}
					$cptrQry = $this->chapter->getAllChaptersByChannelId($channelId);
					foreach($cptrQry as $cpt) {
						$cptrId[] = $cpt['chapter_id'];
					}
					if($nonSum != 0) {
						/* foreach($cptrId as $chapterid) {
							if($chapterid!="") {
								if(!in_array($chapterid, $nonfeaturedChapterId)) {
									$key	=	in_array(0, $nonfeaturedChapterId);
									if($key == '1') {
										$nonfeaturedChapterId[$key]	=	$chapterid;
									}
									$key	=	array_search(0, $nonfeaturedChapterId);
									if($key>=0) {
										$nonfeaturedChapterId[$key]	=	$latestPost;
									}
								}
							}
						} */
						if(Input::get('debugs') == 'anoop') {
							/* foreach($cptrId as $chapterid) {
								if($chapterid!="") {
									if(!in_array($chapterid, $nonfeaturedChapterId)) {
										$key	=	array_search(0, $nonfeaturedChapterId);
										if($key) {
											$nonfeaturedChapterId[$key]	=	$chapterid;
										}
									}
								}
							} */
							print_r($nonfeaturedId); exit;
						}
						foreach($nonfeaturedChapterId as $keys => $vals) {
							if($vals!=0) {
								$chapterQry = 	$this->chapter->getChapterDetailsById($vals);
								foreach($chapterQry as $chapter) {
									$chapterInfo		=	new stdClass();
									$posts 				= 	array();
									$chapterInfo->id 	= 	(string) $chapter['chapter_id'];
									$chapterInfo->name 	= 	$chapter['chapter_name'];
									$chapterInfo->forceTextDisplaying	=	0;
									$postThumb	=	array();
									$postThmb	=	array();
									$postSd		=	array();
									$thumb 		= 	array();
									//echo $nonfeaturedId[$keys] . "<br />";
									//if($nonfeaturedId[$keys] == '' || $nonfeaturedId[$keys] == 0) {
									if($chapter['cover'] == 0) {
										$post = $this->vpost->getLastPostByChapter($chapter['chapter_id']);
										$postThumb['width'] = 	($post['thumb_width']=="")	?	'256'	:	$post['thumb_width'];
										$postThumb['height']= 	($post['thumb_height']=="") ?	'192'	:	$post['thumb_height'];
										$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
										$postThumb['url'] 	= 	($post['thumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
										$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	'512'	:	$post['hdthumb_width'];
										$postThmb['height'] = 	($post['hdthumb_height']=="")	?	'384'	:	$post['hdthumb_height'];
										$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
										$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
										$postSd['width'] 	= 	($post['sd_width']=="")	?	'1024'	:	$post['sd_width'];
										$postSd['height'] 	= 	($post['sd_height']=="")	?	'768'	:	$post['sd_height'];
										$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
										$postSd['url'] 		= 	($post['sd_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
										$thumb[] 			= 	$postThumb;
										$thumb[] 			= 	$postThmb;
										$thumb[] 				= $postSd;
									} else {
										//$post = $this->vpost->getPostsListByPostId($nonfeaturedId[$keys]);
										$post = $this->vpost->getPostsListByPostId($chapter['cover']);
										if(!empty($post))
											$post	=	call_user_func_array('array_merge', $post);
										$postThumb['width'] = 	($post['thumb_width']=="")	?	'256'	:	$post['thumb_width'];
										$postThumb['height']= 	($post['thumb_height']=="") ?	'192'	:	$post['thumb_height'];
										$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
										$postThumb['url'] 	= 	($post['thumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
										$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	'512'	:	$post['hdthumb_width'];
										$postThmb['height'] = 	($post['hdthumb_height']=="")	?	'384'	:	$post['hdthumb_height'];
										$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
										$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
										$postSd['width'] 	= 	($post['sd_width']=="")	?	'1024'	:	$post['sd_width'];
										$postSd['height'] 	= 	($post['sd_height']=="")	?	'768'	:	$post['sd_height'];
										$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
										$postSd['url'] 		= 	($post['sd_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
										$thumb[] 			= 	$postThumb;
										$thumb[] 			= 	$postThmb;
										$thumb[] 				= $postSd;
									}
									$chapterInfo->cover	=	$thumb;
									$chapterInfo->published = ($chapter['inactive']==1) ? '0' : '1';
									array_push($chapterInserted,$chapter['chapter_id']);
									$chapterPostOrder = explode(',', $chapter['post_order']);
									$postOrderSum = array_sum($chapterPostOrder);
									if($postOrderSum != 0) {
										foreach($chapterPostOrder as $chapterPost) {
											$post = $this->vpost->getPostByStatus($chapterPost, 1);
											if(!empty($post))
												$post	=	call_user_func_array('array_merge', $post);
											$post['channel_Id']	  	=	$chapter['channel_id'];
											$post['chapter_Id']	  	=	$chapter['chapter_id'];
											$post['chapter_Name']	=	$chapter['chapter_name'];
											$postInfo				=	$this->userFeedPostInfo($post, 'contents');
											$posts[] 			= 	$postInfo;
											unset($postInfo);
										}
									} else {
										if(strcasecmp('vip', $chapter['chapter_name']) == 0){
											$postQry = $this->vpost->getEventPostChapter($chapter['chapter_id']);
										} else {
											$postQry = $this->vpost->getPostByChapterStatus($chapter['chapter_id'], time());
										}
										foreach($postQry as $post) {
											$post['channel_Id']	  	=	$chapter['channel_id'];
											$post['chapter_Id']	  	=	$chapter['chapter_id'];
											$post['chapter_Name']	=	$chapter['chapter_name'];
											$postInfo				=	$this->userFeedPostInfo($post, 'contents');
											// if($post['post_id'] == $nonfeaturedId[$keys]) {
												// array_unshift($posts,$postInfo);
											// } else {
												// $posts[] 			= 	$postInfo;
											// }
											$posts[] 			= 	$postInfo;
											unset($postInfo);
										}
									}
									$chapterInfo->posts	=	$posts;
									$contents[] 		=	$chapterInfo;
									unset($posts);
									unset($chapterInfo);
								}
							} else {
								//print_r($cptrId); exit;
								foreach($cptrId as $chapterid) {
									if($chapterid!="") {
										if(!in_array($chapterid, $nonfeaturedChapterId)) {
											$psts = $this->vpost->getPostsbyChapter($chapterid);
											if(count($psts) > 0) {
												$pos = array_search($vals, $nonfeaturedChapterId);
												if ($pos !== FALSE) {
												   $nonfeaturedChapterId[$pos] = $chapterid;
												}
												$vals = $chapterid;
											}
										}
									}
									if($vals != 0) {
										break;
									}
								}
								//echo $vals; exit;
								if(!in_array($vals, $chapterInserted)) {
									array_push($chapterInserted,$vals);
									$chapterQry = 	$this->chapter->getChapterDetailsById($vals);
									foreach($chapterQry as $chapter) {
										$chapterInfo		=	new stdClass();
										$posts 				= 	array();
										$chapterInfo->id 	= 	(string) $chapter['chapter_id'];
										$chapterInfo->name 	= 	$chapter['chapter_name'];
										$chapterInfo->forceTextDisplaying	=	0;
										$postThumb	=	array();
										$postThmb	=	array();
										$postSd		=	array();
										$thumb 		= 	array();
										if($chapter['cover'] != '' || $chapter['cover'] != 0) {
											$post = $this->vpost->getPostsListByPostId($chapter['cover']);
											if(!empty($post))
												$post	=	call_user_func_array('array_merge', $post);
											$postThumb['width'] = 	($post['thumb_width']=="")	?	'256'	:	$post['thumb_width'];
											$postThumb['height']= 	($post['thumb_height']=="") ?	'192'	:	$post['thumb_height'];
											$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
											$postThumb['url'] 	= 	($post['thumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
											$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	'512'	:	$post['hdthumb_width'];
											$postThmb['height'] = 	($post['hdthumb_height']=="")	?	'384'	:	$post['hdthumb_height'];
											$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
											$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
											$postSd['width'] 	= 	($post['sd_width']=="")	?	'1024'	:	$post['sd_width'];
											$postSd['height'] 	= 	($post['sd_height']=="")	?	'768'	:	$post['sd_height'];
											$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
											$postSd['url'] 		= 	($post['sd_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
											$thumb[] 			= 	$postThumb;
											$thumb[] 			= 	$postThmb;
											$thumb[] 				= $postSd;
										} else {
											$post = $this->vpost->getLastPostByChapter($chapter['chapter_id']);
											$postThumb['width'] = 	($post['thumb_width']=="")	?	'256'	:	$post['thumb_width'];
											$postThumb['height']= 	($post['thumb_height']=="") ?	'192'	:	$post['thumb_height'];
											$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
											$postThumb['url'] 	= 	($post['thumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
											$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	'512'	:	$post['hdthumb_width'];
											$postThmb['height'] = 	($post['hdthumb_height']=="")	?	'384'	:	$post['hdthumb_height'];
											$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
											$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
											$postSd['width'] 	= 	($post['sd_width']=="")	?	'1024'	:	$post['sd_width'];
											$postSd['height'] 	= 	($post['sd_height']=="")	?	'768'	:	$post['sd_height'];
											$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
											$postSd['url'] 		= 	($post['sd_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
											$thumb[] 			= 	$postThumb;
											$thumb[] 			= 	$postThmb;
											$thumb[] 				= $postSd;
										}
										$chapterInfo->cover	=	$thumb;
										$chapterInfo->published = ($chapter['inactive']==1) ? '0' : '1';
										$chapterPostOrder = explode(',', $chapter['post_order']);
										$postOrderSum = array_sum($chapterPostOrder);
										if($postOrderSum != 0) {
											foreach($chapterPostOrder as $chapterPost) {
												$post = $this->vpost->getPostByStatus($chapterPost, 1);
												if(!empty($post))
													$post	=	call_user_func_array('array_merge', $post);
												$post['channel_Id']	  	=	$chapter['channel_id'];
												$post['chapter_Id']	  	=	$chapter['chapter_id'];
												$post['chapter_Name']	=	$chapter['chapter_name'];
												$postInfo				=	$this->userFeedPostInfo($post, 'contents');
												$posts[] 			= 	$postInfo;
												unset($postInfo);
											}
										} else {
											if(strcasecmp('vip', $chapter['chapter_name']) == 0){
												$postQry = $this->vpost->getEventPostChapter($chapter['chapter_id']);
											} else {
												$postQry = $this->vpost->getPostByChapterStatus($chapter['chapter_id'], time());
											}
											foreach($postQry as $post) {
												$post['channel_Id']	  	=	$chapter['channel_id'];
												$post['chapter_Id']	  	=	$chapter['chapter_id'];
												$post['chapter_Name']	=	$chapter['chapter_name'];
												$postInfo				=	$this->userFeedPostInfo($post, 'contents');
												$posts[] 				= 	$postInfo;
												unset($postInfo);
											}
										}
										$chapterInfo->posts	=	$posts;
										$contents[] 		=	$chapterInfo;
										unset($posts);
										unset($chapterInfo);
									}
								}
							}

						}
					} else {
						$chapterQry = 	$this->chapter->getChapterListByChannelId($channelId);
						foreach($chapterQry as $chapter) {
							$chapterInfo		=	new stdClass();
							$posts 				= 	array();
							$chapterInfo->id 	= 	(string) $chapter['chapter_id'];
							$chapterInfo->name 	= 	$chapter['chapter_name'];
							$chapterInfo->forceTextDisplaying	=	0;
							$postThumb	=	array();
							$postThmb	=	array();
							$postSd		=	array();
							$thumb 		= 	array();
							if($chapter['cover']!='' || $chapter['cover'] != 0) {
								$post = $this->vpost->getPostsListByPostId($chapter['cover']);
								if(!empty($post))
									$post	=	call_user_func_array('array_merge', $post);
								$postThumb['width'] = 	($post['thumb_width']=="")	?	'256'	:	$post['thumb_width'];
								$postThumb['height']= 	($post['thumb_height']=="") ?	'192'	:	$post['thumb_height'];
								$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
								$postThumb['url'] 	= 	($post['thumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
								$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	'512'	:	$post['hdthumb_width'];
								$postThmb['height'] = 	($post['hdthumb_height']=="")	?	'384'	:	$post['hdthumb_height'];
								$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
								$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
								$postSd['width'] 	= 	($post['sd_width']=="")	?	'1024'	:	$post['sd_width'];
								$postSd['height'] 	= 	($post['sd_height']=="")	?	'768'	:	$post['sd_height'];
								$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
								$postSd['url'] 		= 	($post['sd_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
								$thumb[] 			= 	$postThumb;
								$thumb[] 			= 	$postThmb;
								$thumb[] 				= $postSd;
							} else {
								$post = $this->vpost->getLastPostByChapter($chapter['chapter_id']);
								$postThumb['width'] = 	($post['thumb_width']=="")	?	'256'	:	$post['thumb_width'];
								$postThumb['height']= 	($post['thumb_height']=="") ?	'192'	:	$post['thumb_height'];
								$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
								$postThumb['url'] 	= 	($post['thumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
								$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	'512'	:	$post['hdthumb_width'];
								$postThmb['height'] = 	($post['hdthumb_height']=="")	?	'384'	:	$post['hdthumb_height'];
								$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
								$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
								$postSd['width'] 	= 	($post['sd_width']=="")	?	'1024'	:	$post['sd_width'];
								$postSd['height'] 	= 	($post['sd_height']=="")	?	'768'	:	$post['sd_height'];
								$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
								$postSd['url'] 		= 	($post['sd_url']=="")	?	URL::asset('images/no_image.png')	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
								$thumb[] 			= 	$postThumb;
								$thumb[] 			= 	$postThmb;
								$thumb[] 				= $postSd;
							}
							$chapterInfo->cover	=	$thumb;
							$chapterInfo->published = ($chapter['inactive']==1) ? '0' : '1';
							$chapterPostOrder = explode(',', $chapter['post_order']);
							$postOrderSum = array_sum($chapterPostOrder);
							if($postOrderSum != 0) {
								foreach($chapterPostOrder as $chapterPost) {
									$post = $this->vpost->getPostByStatus($chapterPost, 1);
									if(!empty($post)) {
										$post	=	call_user_func_array('array_merge', $post);
									} else  {
										continue;
									}

									$post['channel_Id']	  	=	$chapter['channel_id'];
									$post['chapter_Id']	  	=	$chapter['chapter_id'];
									$post['chapter_Name']	=	$chapter['chapter_name'];
									$postInfo				=	$this->userFeedPostInfo($post, 'contents');
									$posts[] 			= 	$postInfo;
									unset($postInfo);
								}
							} else {
								if(strcasecmp('vip', $chapter['chapter_name']) == 0){
									$postQry = $this->vpost->getEventPostChapter($chapter['chapter_id']);
								} else {
									$postQry = $this->vpost->getPostByChapterStatus($chapter['chapter_id'], time());
								}
								foreach($postQry as $post) {
									$post['channel_Id']	  	=	$chapter['channel_id'];
									$post['chapter_Id']	  	=	$chapter['chapter_id'];
									$post['chapter_Name']	=	$chapter['chapter_name'];
									$postInfo				=	$this->userFeedPostInfo($post, 'contents');
									$posts[] 				= 	$postInfo;
									unset($postInfo);
								}
							}
							$chapterInfo->posts	=	$posts;
							$contents[] 		=	$chapterInfo;
							unset($posts);
							unset($chapterInfo);
						}
					}
					$response->contents	=	$contents;
				//homepage
				} else if(isset($channelSplit[$i]) && $channelSplit[$i] == 'homepage') {

					$homeInfo	=	new stdClass();
					// $location = new stdClass();
					// $thumb = array();
					// $image = array();
					$posts 		= 	array();
					$latestId 	= 	array();
					$cptrId     =   array();
					$featureSum = 0;
					$nonSum     = 0;

					$manage 	= 	$this->manageexp->getGroupByChannelAndType($channelId, 1);
					if(!empty($manage)) {
						$manage	=	call_user_func_array('array_merge', $manage);
						$featuredId = 	explode(',', $manage['featured_list']);
						$nonfeaturedId	=	explode(',', $manage['non_featured_post_list']);
						$nonfeaturedChapterId	=	explode(',', $manage['non_featured_list']);
						$featureSum = 	array_sum($featuredId);
						$nonSum 	= 	array_sum($nonfeaturedChapterId);
					}

					$pstQry 	= 	$this->vpost->getPostByChannelAndDate($channelId, time());
					foreach($pstQry as $pst) {
						$latestId[]	=	$pst['post_id'];
					}
					$cptrQry = $this->chapter->getAllChaptersByChannelId($channelId);
					foreach($cptrQry as $cpt) {
						$cptrId[] = $cpt['chapter_id'];
					}
					if($featureSum!=0 || $nonSum!=0) {
						foreach($featuredId as $value) {
							if($value == 0) {
								foreach($latestId as $lid) {
									if($lid!=""){
										if(!in_array($lid, $featuredId)) {
											$key	=	array_search(0, $featuredId);
											$featuredId[$key]	=	$lid;
											$latest = 	$this->vpost->getPostByStatus($lid, 1);
											if(!empty($latest))
												$latest	=	call_user_func_array('array_merge', $latest);
											$cptr	=	$this->chapter->getChapterDetailsById($latest['chapter_id']);
											if(!empty($cptr))
												$cptr	=	call_user_func_array('array_merge', $cptr);
											if($cptr['inactive'] != '1') {
												$latestInfo	=	$this->userFeedPostInfo($latest, 'homepage');
											}
											$posts[] = $latestInfo;
											// unset($latestContents);
											// unset($content);
											unset($latestInfo);
											break;
										}
									}
								}
							} else {
								$latest = $this->vpost->getPostByStatus($value, 1);
								if(!empty($latest))
									$latest = call_user_func_array('array_merge', $latest);
								$latestInfo	=	$this->userFeedPostInfo($latest, 'homepage');
								$posts[] = $latestInfo;
								// unset($latestContents);
								// unset($content);
								unset($latestInfo);
							}
						}

						$homeInfo->latest_posts	=	$posts;

						$contentInfo 		=	new stdClass();
						$chapterInfo 		=	new stdClass();
						$chapterPosts 		=	array();
						$chapterContents	=	array();
						$chapterInserted    =   array();

						foreach($nonfeaturedChapterId as $val) {
							if($val != 0) {
								//$post = $this->vpost->getPostByStatus($val, 1);
								$post = $this->vpost->getLastestPostByChapter($val);
								// if(!empty($post))
									// $post = call_user_func_array('array_merge', $post);
								$chapterContent	=	new stdClass();
								$chapterInfo 	=	new stdClass();
								$cptr	=	$this->chapter->getChapterDetailsById($val);
								if(!empty($cptr))
									$cptr	=	call_user_func_array('array_merge', $cptr);
								$chapterInfo->id	=	(string) $cptr['chapter_id'];
								$chapterInfo->name 	= 	$cptr['chapter_name'];
								$chapterInfo->forceTextDisplaying	=	0;
								$postThumb	=	array();
								$postThmb	=	array();
								$postSd		=	array();
								$thumb 		= 	array();
								if($cptr['cover']!='' || $cptr['cover'] != 0) {
									$post = $this->vpost->getPostsListByPostId($cptr['cover']);
									if(!empty($post))
										$post	=	call_user_func_array('array_merge', $post);
									$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
									$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
									$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
									$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
									$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
									$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
									$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
									$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
									$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
									$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
									$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
									$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
									$thumb[] 			= 	$postThumb;
									$thumb[] 			= 	$postThmb;
									$thumb[] 				= $postSd;
								} else {
									//$post = $this->vpost->getPostsListByPostId($val);
									$post = $this->vpost->getLastPostByChapter($cptr['chapter_id']);
									// if(!empty($post))
										// $post	=	call_user_func_array('array_merge', $post);
									//$post = $this->vpost->getLastPostByChapter($cptr['chapter_id']);
									$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
									$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
									$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
									$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
									$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
									$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
									$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
									$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
									$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
									$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
									$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
									$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
									$thumb[] 			= 	$postThumb;
									$thumb[] 			= 	$postThmb;
									$thumb[] 				= $postSd;
								}
								$chapterInfo->cover	=	$thumb;
								$chapterInfo->published = ($cptr['inactive']==1) ? '0' : '1';
								array_push($chapterInserted,$cptr['chapter_id']);
								if($post['post_id']!="") {

									$post['chapter_Id']	  =	$cptr['chapter_id'];
									$post['chapter_Name'] =	$cptr['chapter_name'];
									$postInfo	=	$this->userFeedPostInfo($post, 'homepages');
									$chapterPosts[] = $postInfo;
									unset($postInfo);
									$chapterInfo->posts = $chapterPosts;
								} else {
									$chapterInfo->posts = array();
								}
								$chapterContents[] = $chapterInfo;
								unset($chapterInfo);
								unset($chapterPosts);
							} else {
								foreach($cptrId as $latestPost) {
									if($latestPost!="") {
										if(!in_array($latestPost, $nonfeaturedChapterId)) {
											// $key	=	array_search(0, $nonfeaturedChapterId);
											// if($key>=0) {
												// $nonfeaturedChapterId[$key]	=	$latestPost;
											// }
											$psts =  $this->vpost->getPostsbyChapter($latestPost);
											if(count($psts) > 0) {
												$pos = array_search($val, $nonfeaturedChapterId);
												if ($pos !== FALSE) {
												   $nonfeaturedChapterId[$pos] = $latestPost;
												}
												$val = $latestPost;
											}
										}
									}
									if($val != 0) {
										break;
									}
								}

								if(!in_array($val, $chapterInserted)) {
									array_push($chapterInserted,$val);
									$chapterQry = 	$this->chapter->getChapterDetailsById($val);
									foreach($chapterQry as $chapter) {
										$chapterInfo		=	new stdClass();
										$posts 				= 	array();
										$chapterInfo->id 	= 	(string) $chapter['chapter_id'];
										$chapterInfo->name 	= 	$chapter['chapter_name'];
										$chapterInfo->forceTextDisplaying	=	0;
										$postThumb	=	array();
										$postThmb	=	array();
										$postSd		=	array();
										$thumb 		= 	array();
										if($chapter['cover']!='' || $chapter['cover'] != 0) {
											$post = $this->vpost->getPostsListByPostId($chapter['cover']);
											if(!empty($post))
												$post	=	call_user_func_array('array_merge', $post);
											$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
											$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
											$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
											$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
											$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
											$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
											$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
											$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
											$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
											$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
											$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
											$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
											$thumb[] 			= 	$postThumb;
											$thumb[] 			= 	$postThmb;
											$thumb[] 				= $postSd;
										} else {
											$post = $this->vpost->getLastPostByChapter($chapter['chapter_id']);
											$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
											$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
											$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
											$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
											$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
											$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
											$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
											$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
											$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
											$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
											$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
											$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
											$thumb[] 			= 	$postThumb;
											$thumb[] 			= 	$postThmb;
											$thumb[] 				= $postSd;
										}
										$chapterInfo->cover	=	$thumb;
										$chapterInfo->published = ($chapter['inactive']==1) ? '0' : '1';
										if(strcasecmp('vip', $chapter['chapter_name']) == 0){
											$postQry = $this->vpost->getEventPostChapter($chapter['chapter_id']);
										} else {
											$postQry = $this->vpost->getPostByChapterStatus($chapter['chapter_id'], time());
										}
										foreach($postQry as $post) {


											$post['channel_Id']	  	=	$chapter['channel_id'];
											$post['chapter_Id']	  	=	$chapter['chapter_id'];
											$post['chapter_Name']	=	$chapter['chapter_name'];
											$postInfo				=	$this->userFeedPostInfo($post, 'contents');
											$posts[] 				= 	$postInfo;
											unset($postInfo);
										}
										$chapterInfo->posts	=	$posts;
										$chapterContents[] = $chapterInfo;
										unset($posts);
										unset($chapterInfo);
									}
								}
							}
						}
						//print_r($nonfeaturedChapterId); exit;

						$homeInfo->chapters = $chapterContents;

						$response->homepage = $homeInfo;
					} else {

						$postQry = $this->vpost->getPostByChannelAndDate($channelId, time());
						foreach($postQry as $latest) {

							$latestInfo	=	$this->userFeedPostInfo($latest, 'homepage');
							$posts[] = $latestInfo;
							// unset($latestContents);
							// unset($content);
							unset($latestInfo);
						}
						$homeInfo->latest_posts = $posts;

						$contentInfo = new stdClass();
						$chapterInfo = new stdClass();

						$chapterContent = new stdClass();

						$chapterPosts = array();
						$chapterContents = array();

						$postQry = $this->vpost->getPostOrderByMax($channelId, time());

						foreach($postQry as $latestPost) {

							$chapterQry = $this->chapter->getChapterDetailsById($latestPost['chapter_id']);

							foreach($chapterQry as $cptr) {
								$chapterInfo = new stdClass();

								$chapterInfo->id = (string) $cptr['chapter_id'];
								$chapterInfo->name = $cptr['chapter_name'];
								$chapterInfo->forceTextDisplaying = 0;

								$postThumb	=	array();
								$postThmb	=	array();
								$postSd		=	array();
								$thumb 		= 	array();
								if($cptr['cover']!='' || $cptr['cover'] != 0) {
									$post = $this->vpost->getPostsListByPostId($cptr['cover']);
									if(!empty($post))
										$post	=	call_user_func_array('array_merge', $post);
									$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
									$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
									$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
									$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
									$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
									$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
									$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
									$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
									$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
									$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
									$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
									$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
									$thumb[] 			= 	$postThumb;
									$thumb[] 			= 	$postThmb;
									$thumb[] 				= $postSd;
								} else {
									$post = $this->vpost->getLastPostByChapter($cptr['chapter_id']);
									$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
									$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
									$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
									$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
									$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
									$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
									$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
									$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
									$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
									$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
									$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
									$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
									$thumb[] 			= 	$postThumb;
									$thumb[] 			= 	$postThmb;
									$thumb[] 				= $postSd;
								}
								$chapterInfo->cover	=	$thumb;
								$chapterInfo->published = ($cptr['inactive']==1) ? '0' : '1';

								$post = $this->vpost->getPostByChapterAndChannel($cptr['channel_id'], $cptr['chapter_id'], time());
								if(!empty($post))
									$post = call_user_func_array('array_merge', $post);
								if($post['post_id']!="") {

									$post['chapter_Id']	  =	$cptr['chapter_id'];
									$post['chapter_Name'] =	$cptr['chapter_name'];
									$postInfo		=	$this->userFeedPostInfo($post, 'homepages');
									$chapterPosts[] = 	$postInfo;
									unset($postInfo);
									$chapterInfo->posts = $chapterPosts;
								} else {
									$chapterInfo->posts = array();
								}
								$chapterContents[] = $chapterInfo;
								unset($chapterInfo);
								unset($chapterPosts);
							}
						}
						$homeInfo->chapters = $chapterContents;

						$response->homepage = $homeInfo;
					}
				}
			}
			return Response::json($response);
		}
	}

	 //Forgot password code from app starts here
    public function resetUsrpwd($token)
    {
        $this->data['token'] = $token;
        $token               = VastUser::where('reset_token', $token)->first();

        if (sizeof($token) > 0) {
            return View::make('default.reset_apppwd')->with('data', $this->data);
        } else {
            return Redirect::to('cms/login')->with('error', 'The account recovery information has expired and is no longer valid.!');
        }
    }

    public function doResetUsrpwd()
    {

        $rules = array(
            'password' => 'required|alphaNum|min:3',
            'confirm_pass' => 'required|alphaNum|min:3|same:password'

        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('client-api/' . Input::get('token') . '/reset-apppwd')->with('error', '<strong>The following errors occurred!</strong>')->withErrors($validator);
        } else {

            $user = VastUser::where('reset_token', Input::get('token'))->first();

            if ($user && $user->email_id != '') {

                $this->vastuser->resetUsrpwd(array(
                    'password' => Input::get('password'),
                    'vast_user_id' => $user['vast_user_id']
                ));


                //$name 		= $user['firstname'].' ' . $user['lastname'];
                //$username 	=  $user['username'];
                $html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>VAST</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="' . URL::to("images") . '/vast_app_logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;">Dear User,</span></p>
                                                <p>The password for your VAST  has been successfully reset. </p>
												<p>Thanks,<br />
												  <br />
											  The Vast Team</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; ' . date('Y') . ' by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';

                Util::sendSESMail('admin@thefutureisvast.us', $user['email'], 'Your VAST password has been reset.', null, $html_body);

                return Redirect::to('client-api/resetmsg')->with('success', 'Your password has been updated. Please be sure to use this new password when logging in with the Vast app');
            } else {
                return Redirect::to('client-api/resetmsg')->with('error', 'The account recovery information has expired and is no longer valid.!');
            }
        }

    }

	public function resetmsg()
    {
        return View::make('default.reset_msg')->with('data', $this->data);
    }
    //Forgot password code from app ends here

	/**
	 * User feed
	 * @return json
	*/
	public function getMyPosts()
	{
		$sessionId = Input::get('session_id');
		$postId    = Input::get('post_id');
		$count     = Input::get('count');

		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$count) {
			return self::failure(2, 'Missing required parameter count');
		} else {
			$response			=	new stdClass();
			//success
			$response->result 	= 	"success";
			//post_id
			if($postId) {
				$response->post_id	=	$postId;
			}

			$user	=	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);
			$num	=	$this->vpost->getPostsCountByChannelId($user['channel_id'], 1);

			if($num < $count) {
				$response->count = $num;
			} else {
				$response->count = $count;
			}
			$response->total 	 = $num;

			$content  	=	array();
			$posts 		= 	array();
			$postInfo	=	array();
			$postThumb	=	array();
			$postThmb	=	array();
			$postSd		=	array();
			$thumb 		= 	array();
			$postContents=	array();
			$postImage  =	array();
			$postImg	=	array();
			$image 		= 	array();
			if(!$postId) {
				$postQry	=	$this->vpost->getPostsLimitByChannelId($user['channel_id'], 0, 'date', $count );
			} else {
				$pId		=	$postId-1;
				$postQry 	=	$this->vpost->getPostIdLimitByChannelId($user['channel_id'], 0, $pId, 'date', $count);
			}
			foreach($postQry as $post) {
				//$postInfo		=	new stdClass();
				//$postDate		=	new stdClass();
				//$postThumb		=	new stdClass();
				//$postThmb		=	new stdClass();
				//$postSd			=	new stdClass();
				//$postContents	=	new stdClass();
				//$postImage		=	new stdClass();
				//$postImg		=	new stdClass();
				$coverThumb 	= 	new stdClass();
				$coverThmb 		=	new stdClass();
				$coverSd 		= 	new stdClass();
				$liveThumb 		= 	new stdClass();
				$liveThmb 		= 	new stdClass();
				$liveSd 		= 	new stdClass();
				//$content  		=	new stdClass();
				$cover 			= 	array();
				$liveCover 		= 	array();

				$postInfo['id'] 	=	(string) $post['post_id'];
				$postInfo['type'] 	= 	$post['type'];
				$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
				$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
				$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
				$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
				$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
				$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
				$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
				$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
				$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
				$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
				$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
				$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
				$thumb[] 			= 	$postThumb;
				$thumb[] 			= 	$postThmb;
				$thumb[] 				= $postSd;
				$postInfo['thumbnail'] 	= $thumb;
				// unset($postThumb);
				// unset($postThmb);
				// unset($postSd);
				// unset($thumb);

				if($post['type'] == 'image') {
					if($post['audio_url']!="") {
						$audio_url				=	preg_replace("~[\r\n]~", "", $post['audio_url']);
						$postContents['audio']	=	S3::getS3Url(S3::getSecAudioPath($audio_url)); //AUDIO_PATH . $audio_url;
					}
					if($post['second_video']!="") {
						$secvideo_url			=	preg_replace("~[\r\n]~", "", $post['second_video']);
						$postContents['video']	=	S3::getS3Url(S3::getSecVideoPath($secvideo_url)); //SECOND_VIDEO_PATH . $secvideo_url;
					}
					$postImage['width']		=	($post['retina_width']=="")	?	''	:	$post['retina_width'];
					$postImage['height']    = 	($post['retina_height']=="")	?	''	:	$post['retina_height'];
					$retina_url 			= 	preg_replace("~[\r\n]~", "", $post['retina_url']);
					$postImage['url'] 		= 	($post['retina_url']=="")	?	''	:	S3::getS3Url(S3::getPostRetinaPath($retina_url)); //RETINA_PATH . $retina_url;
					$postImg['width']		=	($post['nonretina_width']=="")	?	''	:	$post['nonretina_width'];
					$postImg['height'] 		= 	($post['nonretina_height']=="")	?	''	:	$post['nonretina_height'];
					$nonretina_url 			= 	preg_replace("~[\r\n]~", "", $post['nonretina_url']);
					$postImg['url'] 		= 	($post['nonretina_url']=="")	?	''	:	S3::getS3Url(S3::getPostNonretinaPath($nonretina_url)); //NONRETINA_PATH . $nonretina_url;
					$image[] 				= 	$postImage;
					$image[] 				= 	$postImg;
					$postContents['image'] 	= 	$image;
					//unset($image);
				}
				if($post['type'] == 'video') {
					$video_url			=	preg_replace("~[\r\n]~", "", $post['video_url']);
					$postContents['url']	=	($post['video_url']=="")	?	''	:	S3::getS3Url(S3::getPostVideoPath($video_url)); //VIDEO_PATH . $video_url;
				}
				if($post['type'] == 'music') {
					$music_url					=	preg_replace("~[\r\n]~", "", $post['music_url']);
					$postContents['url'] 		= 	($post['music_url']=="")	?	''	:	S3::getS3Url(S3::getPostMusicPath($music_url)); //MUSIC_PATH . $music_url;
					$postContents['song'] 		= 	($post['song']=="")	?	''	:	$post['song'];
					$postContents['author'] 	= 	($post['author']=="")	?	''	:	$post['author'];
					$postContents['album'] 		= 	($post['album']=="")	?	''	:	$post['album'];
					$postContents['itunes_link']= 	($post['itunes_link']=="")	?	''	:	$post['itunes_link'];
				}
				// if($post['type'] == 'link') {
				// 	$postContents->url			=	($post['link_url']=="")	?	''	:	$post['link_url'];
				// 	$link_thumb					= 	preg_replace("~[\r\n]~", "",$post['link_thumb']);
				// 	$postContents->thumbnail	= 	($post['link_thumb']=="")	?	''	:	S3::getS3Url(S3::getPostlinkthumbPath($link_thumb)); //LINK_THUMB_PATH . $link_thumb;
				// }

				// if($post['type'] == 'text') {
				// 	$postContents	=	"";
				// }

				// if($post['type'] == 'touchcast_video') {
				// 	$postContents->video_url	=	($post['touchcast_url']=="")	?	''	:	$post['touchcast_url'];
				// 	$postContents->xml_url		=	($post['xml_url']=="")	?	''	:	$post['xml_url'];
				// }
				$postInfo['contents']	=	$postContents;
				//unset($postContents);
				$postInfo['date']		=	$post['date'];

				$postInfo['channel_info']   = $this->channelInfo($post, 'myPosts');

				$chapter			=	$this->chapter->getChapterDetailsById($post['chapter_id']);
				if(!empty($chapter)){
					$chapter			=	call_user_func_array('array_merge', $chapter);

					$content['channelID'] 	=	(string) $chapter['channel_id'];
					$content['id'] 			= 	(string) $chapter['chapter_id'];
					$content['name'] 	    = 	$this->escapeStr($chapter['chapter_name']);
					$postInfo['chapter']    = 	$content;
					//unset($content);
				}

				$postInfo['aspect_ratio']	=	($post['aspect_ratio']=="")	?	''	:	$post['aspect_ratio'];
				$postInfo['poi'] 			= 	($post['poix']=="" || $post['poix']==0)	?	'{0.5, 0.5}'	:	'{'.$post['poix'].', '.$post['poiy'].'}';
				$postInfo['post_url'] 	= 	($post['short_url']=="")	?	''	:	$post['short_url'];

				$num				=	$this->like->getLikesByPostId($post['post_id']);
				$postInfo['likes'] 	= 	$num;
				if($post['title']!='') {
					$postInfo['title'] 	= 	preg_replace("~[\r\n]~", "", $post['title']);
				}
				if($post['story']!='') {
					$postInfo['text'] 	= 	$this->escapeStr(preg_replace("~[\r\n]~", "", html_entity_decode($post['story'])));
				}
				$postInfo['required_subscription']	=	($post['subscription_id']=="")	?	''	:	$post['subscription_id'];
				$posts[] 				= 	$postInfo;
				//unset($postInfo);
				//unset($postDate);
			}

			//$this->_postInfo($response, $postQry);
			$response->posts 	=	$posts;

			return Response::json($response);
		}
	}

	/**
	 * Messages
	 * @return json
	**/
	public function getFollowedMessages()
	{
		$sessionId	=	Input::get('session_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
			// $response	=	new stdClass();
			// success
			// $response->result	=	"success";
			$user		=	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user)) {
				$user		=	call_user_func_array('array_merge', $user);
				$messages	=	array();
				$msgContent =   array();
				$msgImg 	= 	array();
				$msg 		= 	array();
				$followQry	=	$this->follow->getMessage($user['vast_user_id']);
				foreach($followQry as $followed) {
					//$msgContent 	= new stdClass();
					$msgContent['id'] = (string) $followed['message_id'];
					$msgContent['channel_info']	=	$this->channelInfo($followed, 'getMsg');

					$msgContent['subject']		=	$followed['subject'];
					$body	=	preg_replace("~[\r\n]~", "",strip_tags($followed['message']));
					$msgContent['body']		=	$body;
					$msgContent['timestamp']	=	(string) $followed['date'];

					$image_url	=	preg_replace("~[\r\n]~", "",$followed['image']);

					$msgImg['width']  =	($followed['image_width']==0) ? '' : (string) $followed['image_width'];
					$msgImg['height'] = ($followed['image_height']==0) ? '' : (string) $followed['image_height'];
					$msgImg['url'] 	  = ($followed['image']=='') ? '' : S3::getS3Url(S3::getMessagePhotoPath($image_url)); //MESSAGE_IMAGE_PATH . $image_url;
					$msg[]	=	$msgImg;
					$msgContent['image'] =	$msg;
					// unset($msgImg);
					// unset($msg);
					if($followed['num']==0) {
						$msgContent['read']	=	0;
					} else {
						$msgContent['read'] = 	1;
					}

					$messages[]	=	$msgContent;
					// unset($msgContent);
				}
				$response = array(
					'result' => 'success',
					'messages' => $messages
				);
				//$response->messages = $messages;
				return Response::json($response);
			}
		}
	}

	/**
	 * Edit chapter
	 * @return json
	**/
	public function editChapter()
	{
		$sessionId = Input::get('session_id');
		$chapterId = Input::get('chapter_id');
		$chapterName = Input::get('name');
		$cover = Input::get('cover');
		$publish = Input::get('publish');
		$delete = Input::get('delete');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$chapterId) {
			return self::failure(2, 'Missing required parameter chapter_id');
		} else {
			if($delete) {
				$user	=	$this->vastuser->getUserBySession($sessionId);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);
				$manageExpDetails = $this->manageexp->getRowsByChannel($user['channel_id']);
				$featuredArray = explode(',',$manageExpDetails['featured_list']);
				foreach($featuredArray as $key => $value){
					if($value != 0){
						$postDetails =  $this->vpost->getPostsByPostId($value);
						if($postDetails[0]['chapter_id'] == $chapterId){
							$featuredArray[$key] = 0;
						}
					}
				}
				$nonFeaturedChapterArray = explode(',',$manageExpDetails['non_featured_list']);
				$nonFeaturedPostArray = explode(',',$manageExpDetails['non_featured_post_list']);
				$key = array_search($chapterId, $nonFeaturedChapterArray);
				if($key >= 0){
					$nonFeaturedChapterArray[$key] = 0;
					$nonFeaturedPostArray[$key] = 0;
				}
				$featuredList = implode(',',$featuredArray);
				$nonFeaturedChapterList = implode(',',$nonFeaturedChapterArray);
				$nonFeaturedPostList = implode(',',$nonFeaturedPostArray);
				$this->chapter->deleteChapter($chapterId, $user['channel_id']);
				$updateDetails = array(
					'id' => $manageExpDetails['id'],
					'type' => 1,
					'featured_list'  => $featuredList,
					'non_featured_list' => $nonFeaturedChapterList,
					'non_featured_post_list' =>  $nonFeaturedPostList
				);
				$this->manageexp->update($updateDetails);
				Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);
				$response = array(
					'result' => 'success'
				);
			} else {
				$cptrData		=	$this->chapter->getChapterDetailsById($chapterId);
				if(!empty($cptrData))
					$cptrData	=	call_user_func_array('array_merge', $cptrData);
				if(isset($publish)) {
					$pub = ($publish==0) ? '1' : '0';
				} else {
					$pub = '0';
				}

				$user	=	$this->vastuser->getUserBySession($sessionId);
				if(!empty($user))
					$user	=	call_user_func_array('array_merge', $user);
				$manageExpDetails = $this->manageexp->getRowsByChannel($user['channel_id']);
				$nonFeaturedChapterArray = explode(',',$manageExpDetails['non_featured_list']);
				$nonFeaturedPostArray = explode(',',$manageExpDetails['non_featured_post_list']);
				$cover = ($cover!='') ? $cover : $cptrData['cover'];
				$key = array_search($chapterId, $nonFeaturedChapterArray);
				if($key !== false){
					$nonFeaturedChapterArray[$key] = $chapterId;
					$nonFeaturedPostArray[$key] = $cover;
				} else {
					$num = count($nonFeaturedChapterArray);
					for($key=0; $key<$num; $key++) {
						if($nonFeaturedChapterArray[$key] == 0) {
							$nonFeaturedChapterArray[$key] = $chapterId;
							$nonFeaturedPostArray[$key] = $cover;
							break;
						}
					}
				}
				$nonFeaturedPostList = implode(',',$nonFeaturedPostArray);
				$nonFeaturedChapterList = implode(',',$nonFeaturedChapterArray);

				$updateArr = array(
					'chapter_name' => ($chapterName!='') ? $chapterName : $cptrData['chapter_name'],
					'cover' => ($cover!='') ? $cover : $cptrData['cover'],
					'inactive' => $pub,
					'chapter_id' => $chapterId
				);
				$this->chapter->update($updateArr);

				$updateDetails = array(
					'id' => $manageExpDetails['id'],
					'non_featured_list' => $nonFeaturedChapterList,
					'non_featured_post_list' =>  $nonFeaturedPostList,
					'type' => 1
				);
				$this->manageexp->update($updateDetails);
				Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);

				$cptrData		=	$this->chapter->getChapterDetailsById($chapterId);
				if(!empty($cptrData))
					$cptrData	=	call_user_func_array('array_merge', $cptrData);
				$chapterPostOrder = explode(',', $cptrData['post_order']);
				$postOrderSum = array_sum($chapterPostOrder);
				if($postOrderSum != 0) {
					foreach($chapterPostOrder as $chapterPost) {
						$post = $this->vpost->getPostByStatus($chapterPost, 1);
						if(!empty($post))
							$post	=	call_user_func_array('array_merge', $post);
						$postIn				=	$this->userFeedPostInfo($post, 'editChapter');
						$posts[] 			= 	$postIn;
						//unset($postIn);
					}
				} else {
					$postQry = $this->vpost->getPostByChapterId($chapterId);
					foreach($postQry as $post) {
						$postIn		=	$this->userFeedPostInfo($post, 'editChapter');
						$posts[] 	=	$postIn;
						//unset($postIn);
					}
				}
				$postThumb	=	array();
				$postThmb	=	array();
				$postSd		=	array();
				$thumb 		= 	array();
				if($cover!='' || $cover != 0) {
					$post = $this->vpost->getPostsListByPostId($cover);
					if(!empty($post))
						$post	=	call_user_func_array('array_merge', $post);
					$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
					$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
					$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
					$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
					$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
					$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
					$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
					$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
					$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
					$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
					$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
					$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
					$thumb[] 			= 	$postThumb;
					$thumb[] 			= 	$postThmb;
					$thumb[] 				= $postSd;
				} else {
					$post = $this->vpost->getLastPostByChapter($chapterId);
					$postThumb['width'] = 	($post['thumb_width']=="")	?	''	:	$post['thumb_width'];
					$postThumb['height']= 	($post['thumb_height']=="") ?	''	:	$post['thumb_height'];
					$thumb_url 			= 	preg_replace("~[\r\n]~", "", $post['thumb_url']);
					$postThumb['url'] 	= 	($post['thumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostPhotoPath($thumb_url)); //THUMB_PATH . $thumb_url;
					$postThmb['width'] 	= 	($post['hdthumb_width']=="")	?	''	:	$post['hdthumb_width'];
					$postThmb['height'] = 	($post['hdthumb_height']=="")	?	''	:	$post['hdthumb_height'];
					$hdthumb_url 		= 	preg_replace("~[\r\n]~", "", $post['hdthumb_url']);
					$postThmb['url'] 	= 	($post['hdthumb_url']=="")	?	''	:	S3::getS3Url(S3::getPostHdthumbPath($hdthumb_url)); //HD_THUMB_PATH . $hdthumb_url;
					$postSd['width'] 	= 	($post['sd_width']=="")	?	''	:	$post['sd_width'];
					$postSd['height'] 	= 	($post['sd_height']=="")	?	''	:	$post['sd_height'];
					$sd_url 			= 	preg_replace("~[\r\n]~", "", $post['sd_url']);
					$postSd['url'] 		= 	($post['sd_url']=="")	?	''	:	S3::getS3Url(S3::getPostSdPath($sd_url)); //SD_PATH . $sd_url;
					$thumb[] 			= 	$postThumb;
					$thumb[] 			= 	$postThmb;
					$thumb[] 				= $postSd;
				}
				$contents = array(
					array(
						'id' => (string)$cptrData['chapter_id'],
						'name' => $cptrData['chapter_name'],
						'forceTextDisplaying' => '0',
						'cover' => $thumb,
						'published' => ($cptrData['inactive']==1) ? '0' : '1',
						'posts' => array($posts)
					)
				);

				$response = array(
					'result' => 'success',
					'contents' => $contents
				);
			}
			return Response::json($response);
		}
	}


	/**
	* Delete Message
 	* @return json
	**/
	public function deleteMessage()
	{
		$sessionId = Input::get('session_id');
		$messageId = Input::get('message_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		}else {
			if(!$messageId) {
				return self::failure(2, 'Missing required parameter message_id');
			}else{
				$user		 = $this->vastuser->getUserBySession($sessionId);
				$user		 = call_user_func_array('array_merge', $user);
				$res 		 = $this->message->deleteMessageByUser($user['vast_user_id'],$messageId);
				$messages = $this->message->getMessageByChannelId($user['vast_user_id']);
			}

			$messageList  = array();
			foreach($messages as $message){
				$tempArray = array('subject' => $message['subject'],
								   'body'    => $message['message'],
								   'options' => $message['type']
								   );
				array_push($messageList, $tempArray);

			}

			$response = array(
				'result' => 'success',
				'messages' => $messageList
			);
			return Response::json($response);
		}
	}

	/**
	 * Edit featured
	 * @return json
	**/
	public function editFeatured()
	{
		$sessionId = Input::get('session_id');
		$featured = Input::get('featured');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$featured) {
			return self::failure(2, 'Missing required parameter featured');
		} else {
			$user =	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user =	call_user_func_array('array_merge', $user);
			$network = $this->manageexp->getRowsByChannel($user['channel_id']);
			if(count($network) > 0) {
				$feat1 = str_replace("[", "", $featured);
				$feat2 = str_replace("]", "", $feat1);
				$featured = explode( ',', $feat2 );
				$feature = implode(",", $featured);
				$updateArr = array(
					'id' => $network['id'],
					'featured_list' => $feature,
					'type' => 1
				);
				$this->manageexp->update($updateArr);
				$response = array(
					'result' => 'success'
				);
				Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);
				return Response::json($response);
			} else {
				$feat1 = str_replace("[", "", $featured);
				$feat2 = str_replace("]", "", $feat1);
				$featured = explode( ',', $feat2 );
				$feature = implode(",", $featured);
				$createArr = array(
					'featured_list' => $feature,
					'non_featured_list' => '0,0,0,0,0,0,0,0',
					'non_featured_post_list' => '0,0,0,0,0,0,0,0',
					'type' => 1,
					'mapping_id' => $user['channel_id'],
					'flag' => 1,
					'isexplore' => 1
				);
				$this->manageexp->featuredCreate($createArr);
				Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);
				$response = array(
					'result' => 'success'
				);
				return Response::json($response);
				//return self::failure(77, 'The channel is not listed in Vast networks');
			}
		}
	}

	/**
	 * Edit Chapters order
	 * @return json
	**/
	public function editChaptersOrder()
	{
		$sessionId = Input::get('session_id');
		$chapters = Input::get('chapters');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$chapters) {
			return self::failure(2, 'Missing required parameter chapters');
		} else {
			$user =	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user =	call_user_func_array('array_merge', $user);
			$network = $this->manageexp->getRowsByChannel($user['channel_id']);
			if(count($network) > 0) {
				$cptr1 = str_replace("[", "", $chapters);
				$cptr2 = str_replace("]", "", $cptr1);
				$chapters = explode( ',', $cptr2 );
				$chapterList = implode(",", $chapters);
				$i = 0;
				foreach ($chapters as $nonFeaturedList) {
					if ($nonFeaturedList == 0)
						$nonFeaturedPostList[$i] = 0;
					else {
						$lastPost                = $this->vpost->getLastPostByChapter($nonFeaturedList);
						$nonFeaturedPostList[$i] = $lastPost['post_id'];
					}
					$i++;
				}
				$postList = implode(",", $nonFeaturedPostList);
				$updateArr = array(
					'id' => $network['id'],
					'non_featured_list' => $chapterList,
					'non_featured_post_list' => $postList,
					'type' => 1
				);
				$this->manageexp->update($updateArr);
				Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);
				$response = array(
					'result' => 'success'
				);
				return Response::json($response);
			} else {
				$cptr1 = str_replace("[", "", $chapters);
				$cptr2 = str_replace("]", "", $cptr1);
				$chapters = explode( ',', $cptr2 );
				$chapterList = implode(",", $chapters);
				$i = 0;
				foreach ($chapters as $nonFeaturedList) {
					if ($nonFeaturedList == 0)
						$nonFeaturedPostList[$i] = 0;
					else {
						$lastPost                = $this->vpost->getLastPostByChapter($nonFeaturedList);
						$nonFeaturedPostList[$i] = $lastPost['post_id'];
					}
					$i++;
				}
				$postList = implode(",", $nonFeaturedPostList);
				$createArr = array(
					'featured_list' => '0,0,0,0,0',
					'non_featured_list' => $chapterList,
					'non_featured_post_list' => $postList,
					'type' => 1,
					'mapping_id' => $user['channel_id'],
					'flag' => 1,
					'isexplore' => 1
				);
				$this->manageexp->featuredCreate($createArr);
				Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);
				$response = array(
					'result' => 'success'
				);
				return Response::json($response);
				//return self::failure(77, 'The channel is not listed in Vast networks');
			}
		}
	}

	/**
	 * Edit social link
	 * @return json
	**/
	public function editSocialLink()
	{
		$sessionId = Input::get('session_id');
		$socialName = Input::get('social_name');
		$link = Input::get('link');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$socialName) {
			return self::failure(2, 'Missing required parameter social_name');
		} else if(!$link) {
			return self::failure(2, 'Missing required parameter link');
		} else {
			$user =	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user =	call_user_func_array('array_merge', $user);
			if($socialName == 'twitter') {
				$updateArr = array(
					'twitter_url' => $link
				);
			} else if($socialName == 'youtube') {
				$updateArr = array(
					'youtube_url' => $link
				);
			} else if($socialName == 'facebook') {
				$updateArr = array(
					'fb_url' => $link
				);
			} else if($socialName == 'instagram') {
				$updateArr = array(
					'instagram_url' => $link
				);
			} else if($socialName == 'tumblr') {
				$updateArr = array(
					'tumblr_url' => $link
				);
			}
			$this->channel->update($user['channel_id'], $updateArr);
			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * channel search
	 * @return json
	**/
	public function channelSearch()
	{
		$searchStr = Input::get('search_str');
		if(!$searchStr) {
			return self::failure(2, 'Missing required parameter search_str');
		} else {
			$channels = $this->channel->channelSearch($searchStr);
			$channelDetails = array();
			foreach($channels as $channel){
				$postInfo	= array(
					'id'           => $channel['channel_id'],
					'channel_name' => ($channel['vast_name'] != "") ? $channel['vast_name'] : $channel['first_name']. ' '.$channel['last_name'],
					'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$channel['avatar']))
				);
				array_push($channelDetails,$postInfo);
			}
			$response = array(
				'result' 	=> 'success',
				'channels' 	=> $channelDetails
			);
			return Response::json($response);
		}
	}

	/**
	 * vip content
	 * @return json
	**/
	public function vipContent()
	{
		$sessionId = Input::get('session_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else {
			$postsDetails = array();
			$user  =	$this->vastuser->getUserBySession($sessionId);
			$sub = $this->subscribe->getSubscriptionByUser($user[0]['channel_id']);
			if(count($sub) > 0) {
				$followedChannels =  $this->follow->getFollowChannelByUser($user[0]['channel_id']);
				$followChannel = array();
				foreach($followedChannels as $chnl){
					array_push($followChannel,$chnl->channel_id);
				}
				//array_push($followChannel, $user[0]['channel_id']);
				$posts =	$this->vpost->getVIPPostsByChannelId($followChannel);
				//echo count($posts);
				//exit;

				foreach($posts as $post) {
					$postInfo	=	$this->userFeedPostInfo($post, 'vip_content');
					array_push($postsDetails,$postInfo);
				}
			}

			$response = array(
				'result' 	=> 'success',
				'posts'     => $postsDetails

			);
			return Response::json($response);
		}
	}

	/**
	 * Track share
	 * @return json
	**/
	public function trackShare()
	{
		$sessionId = Input::get('session_id');
		$postId = Input::get('post_id');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$postId) {
			return self::failure(2, 'Missing required parameter post_id');
		} else {
			$user =	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user =	call_user_func_array('array_merge', $user);
			$chnl = $this->vpost->getPostsListByPostId($postId);
			if(!empty($chnl))
				$chnl =	call_user_func_array('array_merge', $chnl);
			$shareArr = array(
				'user_id' => $user['vast_user_id'],
				'post_id' => $postId,
				'channel_id' => $chnl['channel_id'],
				'refdate' => time()
			);
			$this->vastshare->create($shareArr);
			$response = array(
				'result' => 'success'
			);
			return Response::json($response);
		}
	}

	/**
	 * Activities
	 * @return json
	**/
	public function getActivities()
	{
		$sessionId = Input::get('session_id');
		$referenceDate = Input::get('reference_date');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$referenceDate) {
			return self::failure(2, 'Missing required parameter reference_date');
		} else {
			$notposts = array();

			$user = $this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user	=	call_user_func_array('array_merge', $user);

			$followQry = $this->follow->getFollowChannelByUser($user['vast_user_id']);

			foreach($followQry as $followed) {

				$time = time();

				$i = 1;
				$notInfo = array();

				$likeUsrQry = $this->like->getUserLikesByDate($user['vast_user_id'], $referenceDate, $time);
				foreach($likeUsrQry as $liked) {
					$notInfo['id'] = (string) $i;
					$notInfo['type'] = 'like';
					$post = $this->vpost->getPostsListByPostId($liked['post_id']);
					if(!empty($post))
						$post	=	call_user_func_array('array_merge', $post);
					$postInfo	=	$this->userFeedPostInfo($post, 'notifications');
					$notInfo['post'] = $postInfo;
					$usrQry = $this->channel->getChannelDetailsByChannelId($liked['user_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$notInfo['user'] = $usrInfo;
					$chInfo = array();
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $liked['refdate'];
					$this->readnotification->getNumLikeNotification($post['post_id'], $user['vast_user_id'], $usrQry['channel_id'], 'like');
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}
					$notposts[] = $notInfo;
					$i++;
					//unset($notInfo);
				}

				$shareQry = $this->vastshare->getShareByDate($user['vast_user_id'], $referenceDate, $time);
				foreach($shareQry as $shared) {
					$notInfo['id'] = (string) $i;
					$notInfo['type'] = 'share';
					$post = $this->vpost->getPostsListByPostId($shared['post_id']);
					if(!empty($post))
						$post	=	call_user_func_array('array_merge', $post);
					$postInfo	=	$this->userFeedPostInfo($post, 'notifications');
					$notInfo['post'] = $postInfo;
					$usrQry = $this->channel->getChannelDetailsByChannelId($shared['user_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$notInfo['user'] = $usrInfo;
					$chInfo = array();
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $shared['refdate'];
					$num = $this->readnotification->getNumShareNotification($post['post_id'], $user['vast_user_id'], $usrQry['channel_id'], 'share');
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}
					$notposts[] = $notInfo;
					$i++;
					//unset($notInfo);
				}

				$followUserQry = $this->follow->getFollowersByDate($user['vast_user_id'], $referenceDate, $time);
				foreach($followUserQry as $followuser) {
					$notInfo['id'] = (string) $i;
					if($followuser['status']=='1') {
						$notInfo['type'] = 'follow';
					} else {
						$notInfo['type'] = 'unfollow';
					}
					$postInfo	=	array();
					$notInfo['post'] = $postInfo;
					$usrQry = $this->channel->getChannelDetailsByChannelId($followuser['user_id']);
					if(!empty($usrQry))
						$usrQry	=	call_user_func_array('array_merge', $usrQry);
					$usrInfo	= array(
						'id'           => (string) $usrQry['channel_id'],
						'channel_name' => $usrQry['first_name']. ' '.$usrQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$usrQry['avatar']))
					);
					$notInfo['user'] = $usrInfo;
					$chQry = $this->channel->getChannelDetailsByChannelId($followuser['channel_id']);
					if(!empty($chQry))
						$chQry	=	call_user_func_array('array_merge', $chQry);
					$chInfo	= array(
						'id'           => (string) $chQry['channel_id'],
						'channel_name' => $chQry['first_name']. ' '.$chQry['last_name'],
						'avatar'       => S3::getS3Url(S3::getChannelAvatarPath("sd/".$chQry['avatar']))
					);
					$notInfo['channel'] = $chInfo;
					//unset($postInfo);
					$notInfo['timestamp'] = (string) $followuser['refdate'];
					$num = $this->readnotification->getNumFollowNotification($user['vast_user_id'], $usrQry['channel_id'], $typo);
					if($num==0) {
						$notInfo['read'] = 0;
					} else {
						$notInfo['read'] = 1;
					}
					$notposts[] = $notInfo;
					$i++;
					//unset($notInfo);
				}
			}
			$response = array(
				'result' => 'success',
				'activities' => $notposts
			);
			return Response::json($response);
		}
	}

	/**
	 * Getting welcome sildes for app
	 * @return json
	**/
	public function welcomeSlide()
	{
		$slides = $this->slideshow->getSlides();
		$slideData = array();
		foreach($slides as $slide){
			if( $slide->slide_title != ""){
				$imageDetails  =  array();
				array_push($imageDetails,array(
					'width' => $slide->sdW,
					'height' => $slide->sdH,
					'url'   => S3::getS3Url(S3::getSlideShowImagePath('sd/'.$slide->slide_img))
				));
				array_push($imageDetails,array(
					'width' => $slide->hdW,
					'height' => $slide->hdH,
					'url'   => S3::getS3Url(S3::getSlideShowImagePath('hdthumb/'.$slide->slide_img))
				));
				array_push($imageDetails,array(
					'width' => $slide->thumbW,
					'height' => $slide->thumbH,
					'url'   => S3::getS3Url(S3::getSlideShowImagePath('thumb/'.$slide->slide_img))
				));
				array_push($slideData, array(
					'image' => $imageDetails,
					'title' => $slide->slide_title,
					'descr' => $slide->slide_description
				));
			}
		}
		$response = array(
			'result' => 'success',
			'slides' => $slideData
		);

		return Response::json($response);
	}

	/**
	 * Edit posts order of chapter
	 * params session_id, chapter_id, posts
	 * @return json
	**/
	public function editPostsOrder()
	{
		$sessionId = Input::get('session_id');
		$chapterId = Input::get('chapter_id');
		$editPosts = Input::get('posts');
		if(!$sessionId) {
			return self::failure(2, 'Missing required parameter session_id');
		} else if(!$chapterId) {
			return self::failure(2, 'Missing required parameter chapter_id');
		} else if(!$editPosts) {
			return self::failure(2, 'Missing required parameter posts');
		} else {
			$user =	$this->vastuser->getUserBySession($sessionId);
			if(!empty($user))
				$user =	call_user_func_array('array_merge', $user);
			$chapterDet = $this->chapter->getChapterByIdAndChannel($user['channel_id'], $chapterId);
			if(!empty($chapterDet)) {
				$pst1 = str_replace("[", "", $editPosts);
				$pst2 = str_replace("]", "", $pst1);
				$editPosts = explode( ',', $pst2 );
				foreach($editPosts as $key => $value) {
					$postQry = $this->vpost->getPostsListByPostId($value);
					if(empty($postQry)) {
						unset($editPosts[$key]);
					}
				}

				$postList = implode(",", $editPosts);

				$updateArr =  array(
					'chapter_id' => $chapterId,
					'post_order' => $postList
				);
				$this->chapter->update($updateArr);
				Util::sendRequest('https://prod.thefutureisvast.us/cms/createjson/'.$user['channel_id']);
				$response = array(
					'result' => 'success'
				);
				return Response::json($response);
			} else {
				return self::failure(2, 'No such chapter found');
			}
		}
	}
}
?>
