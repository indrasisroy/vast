<?php

use Vast\Repo\Admin\AdminInterface;
use Vast\Repo\Channel\ChannelInterface;
use Vast\Repo\Chapter\ChapterInterface;
use Vast\Repo\Favorite\FavoriteInterface;
use Vast\Repo\Follow\FollowInterface;
use Vast\Repo\Like\LikeInterface;
use Vast\Repo\Post\PostInterface;
use Vast\Repo\Session\SessionInterface;
use Vast\Repo\Subscribe\SubscribeInterface;
use Vast\Repo\User\UserInterface;
use Vast\Repo\Conversion\ConversionInterface;
use Vast\Repo\Channelgroup\ChannelgroupInterface;
use Vast\Repo\Manageexplore\ManageexploreInterface;
use Vast\Repo\EventBouncer\EventBouncerInterface;
use Vast\Repo\Channelgrid\ChannelgridInterface;
use Vast\Repo\SlideShow\SlideShowInterface;
use Illuminate\Support\Facades\Log;

class AjaxController extends BaseController {

    protected $layout = 'layouts.ajax';
    protected $admin, $channel, $chapter, $favorite, $follow, $like, $post, $session, $subscribe, $user, $conversion, $channelgroup, $manageexplore, $event,$channelgrid,$slideshow;
    protected $data = array();

    public function __construct(AdminInterface $admin, ChannelInterface $channel, ChapterInterface $chapter, FavoriteInterface $favorite, FollowInterface $follow, LikeInterface $like, PostInterface $post, SessionInterface $session, SubscribeInterface $subscribe, UserInterface $user, ConversionInterface $conversion, ChannelgroupInterface $channelgroup, ManageexploreInterface $manageexplore, EventBouncerInterface $event,  ChannelgridInterface $channelgrid, SlideShowInterface $slideshow) {

        $this->admin = $admin;
        $this->channel = $channel;
        $this->chapter = $chapter;
        $this->favorite = $favorite;
        $this->follow = $follow;
        $this->like = $like;
        $this->post = $post;
        $this->session = $session;
        $this->subscribe = $subscribe;
        $this->user = $user;
        $this->conversion = $conversion;
        $this->channelgroup = $channelgroup;
        $this->manageexplore = $manageexplore;
        $this->event = $event;
		$this->channelgrid = $channelgrid;
        $this->slideshow = $slideshow;

        if (Auth::user() && Auth::user()->role_id != 1)
            $this->data['userChannel'] = $this->channel->getChannelById(Auth::user()->channel->channel_id);
    }

    public function updateVastName() {
        $this->channel->updateVastName(Auth::user()->channel->channel_id, Input::get('vastName'));
        echo 1;
    }

    public function uploadChannelAvatar() {
        $fileUpload = S3::uploadChannelAvatar('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadUserAvatar() {
        $fileUpload = S3::uploadChannelAvatar('Filedata');
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadVideoPost() {
        $fileUpload = S3::uploadVideoPost('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }
	
	public function uploadVideoPostNew(Request $request)
	{
		 $file = $request->file('post_video');
		 echo "<pre>";
		 print_r($file);
		 die();
		
	}

    public function uploadMusicPost() {
        $fileUpload = S3::uploadMusicPost('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadMessageImage() {
        $fileUpload = S3::uploadMessageImage('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadPhotoPost() {
        $fileUpload = S3::uploadPhotoPost('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadLivestreamPhoto() {
        $fileUpload = S3::uploadLivestreamPhoto('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadVIPImage() {
        $fileUpload = S3::uploadVIPImage('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadPhotoMessage() {
        $fileUpload = S3::uploadPhotoMessage('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadSettingsChannelCover() {
        $fileUpload = S3::uploadSettingsChannelCover('Filedata', Auth::user()->channel);
        $imageData = json_decode($fileUpload);
        $this->channel->update(Auth::user()->channel->channel_id,array('cover' => $imageData->photo));
        echo $fileUpload ? $fileUpload : '';
    }
	
	public function uploadSettingsAppCover() {
        $fileUpload = S3::uploadSettingsAppCover('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function getFeeds() {
        $feeds = $this->post->getPostsByChannelId(Auth::user()->channel->channel_id, Input::get('offset'), Input::get('order'));
        $this->data['feeds'] = $feeds;
        $this->data['chapter'] = Input::get('last_chapter');
        $this->data['order'] = Input::get('order');

        return View::make('ajax.post.feed')->with('data', $this->data);
    }

    public function deletePost($id) {
        $postsDet = $this->post->getPostsByPostId($id);
        $postsDet = $postsDet[0];
        $postCount = count($this->post->getPostsbyChapter($postsDet->chapter_id));
       /* if ($postCount == 1) {
			$this->chapter->update(array('chapter_id' => $postsDet->chapter_id, 'inactive' => 1, 'featured' => 0));
		}*/
		$hasMorePost = $postCount == 1 ? 0:1;
        $this->manageexplore->updateEntriesOnPostDeletion($id, $postsDet->chapter_id, Auth::user()->channel->channel_id,$hasMorePost);
		$this->chapter->updateCover($id);
        if ($postsDet->audio_url != "")
            S3::delete_file($folderName = "audios", $fileName = $postsDet->audio_url);
        if ($postsDet->video_url != "")
            S3::delete_file($folderName = "videos", $fileName = $postsDet->video_url);
        if ($postsDet->second_video != "")
            S3::delete_file($folderName = "secvideo", $fileName = $postsDet->second_video);
        if ($postsDet->music_url != "")
            S3::delete_file($folderName = "music", $fileName = $postsDet->music_url);
        if ($postsDet->thumb_url != "")
            S3::delete_file($folderName = "postimages/thumb", $fileName = $postsDet->thumb_url);
        if ($postsDet->hdthumb_url != "")
            S3::delete_file($folderName = "postimages/hdthumb", $fileName = $postsDet->hdthumb_url);
        if ($postsDet->retina_url != "")
            S3::delete_file($folderName = "postimages/retina", $fileName = $postsDet->retina_url);
        if ($postsDet->nonretina_url != "")
            S3::delete_file($folderName = "postimages/nonretina", $fileName = $postsDet->nonretina_url);
        if ($postsDet->sd_url != "")
            S3::delete_file($folderName = "postimages/sd", $fileName = $postsDet->sd_url);
        $this->post->delete($id, Auth::user()->channel->channel_id);
		$jsonData = self::getAdvastBannerJson(Auth::user()->channel->channel_id);
		$jsonUrl = S3::createAdvastBannerJson($jsonData,Auth::user()->channel->channel_id);
        echo 1;
    }

    public function addPost() {
        //var_dump(Input::all());exit;
        $validator = Validator::make(Input::all(), Post::$rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();
            //var_dump($messages);exit;
            if ($validator->getMessageBag()->has('publish-date') || $validator->getMessageBag()->has('publish-time')) {
                if ($messages['publish-date'][0] == "The publish-date format is invalid.")
                    $messages['publish-date'][0] = 'Schedule date should be in MM/DD/YYYY format.';
                else
                    $messages['publish-date'][0] = 'Schedule date and time is required';

                unset($messages['publish-time']);
            }
            return Response::json(array(
                        'success' => false,
                        'errors' => $messages
            )); // 400 being the HTTP code for an invalid request.
        }
        else {
            
            $secondVideo = Input::get('second_video');
            $secondAudio = Input::get('second_audio');
            $secondImage = Input::get('second_image');
            $secondVideoThumb = Input::get('second_video_thumb');
            $poix = 0.5;
            $poiy = 0.5;
            if (Input::get('poi_coords') != "") {
                $poi = explode(",", Input::get('poi_coords'));
                $poix = $poi[0];
                $poiy = $poi[1];
            }
            $second_image = Input::get('second_image_name');
            $secthumb_width = Input::get('secthumb_width');
            $secthumb_height = Input::get('secthumb_height');
            $sechdthumb_width = Input::get('sechdthumb_width');
            $sechdthumb_height = Input::get('sechdthumb_height');
            $secsd_width = Input::get('secsd_width');
            $secsd_height = Input::get('secsd_height');
            $dt = new DateTime();
            $cdate = strtotime($dt->format('Y-m-d H:i:s'));
            $submitData = array(
                'channel_id' => Auth::user()->channel->channel_id,
                'type' => Input::get('type'),
                'post_image' => Input::get('post_image'),
                'thumb_width' => Input::get('thumb_width'),
                'thumb_height' => Input::get('thumb_height'),
                'retina_width' => Input::get('retina_width'),
                'retina_height' => Input::get('retina_height'),
                'nonretina_width' => Input::get('nonretina_width'),
                'nonretina_height' => Input::get('nonretina_height'),
                'hdthumb_width' => Input::get('hdthumb_width'),
                'hdthumb_height' => Input::get('hdthumb_height'),
                'sd_width' => Input::get('sd_width'),
                'sd_height' => Input::get('sd_height'),
                'retina_url' => Input::get('post_retina'),
                'nonretina_url' => Input::get('post_nonretina'),
                'hdthumb_url' => Input::get('post_hdthumb'),
                'sd_url' => Input::get('post_sd'),
                'post_video' => Input::get('post_video'),
                'post_music' => Input::get('post_music'),
                'second_video' => $secondVideo,
                'second_audio' => $secondAudio,
                'second_image' => $second_image,
                'secthumb_width' => $secthumb_width,
                'secthumb_height' => $secthumb_height,
                'sechdthumb_width' => $sechdthumb_width,
                'sechdthumb_height' => $sechdthumb_height,
                'secsd_width' => $secsd_width,
                'secsd_height' => $secsd_height,
                'title' => htmlspecialchars(Input::get('title'),ENT_QUOTES),
                'song' => Input::get('song_name'),
                'author' => Input::get('artist_name'),
                'description' => htmlspecialchars(Input::get('description'),ENT_QUOTES),
                'chapter' => Input::get('chapter'),
                'date' => Input::get('publish-date'),
                'time' => Input::get('publish-time'),
                'subscriber_only' => Input::get('subscriber_only'),
                'story' => htmlspecialchars(Input::get('story'),ENT_QUOTES),
                'poix' => $poix,
                'poiy' => $poiy,
                'schedule' => Input::get('schedules'),
                'cdate' => $cdate,
                'youtube_url' => Input::get('youtube_url'),
				'share_text' => Input::get('share_text'),
				'tw' => Input::get('twiiter_share'),
				'fb' => Input::get('facebook_share'),
				'tb' => Input::get('tumblr_share'),
				'tw_token' => Auth::user()->twitter_token,
				'tw_secret' => Auth::user()->twitter_token_secret,
				'fb_token' => Auth::user()->fb_token,
				'tb_token' => Auth::user()->tumblr_token,
				'tb_username' => Auth::user()->tumblr_username,
				'tb_secret' => Auth::user()->tumblr_oauth_token_secret,
            );
            if (Input::get('type') == 'event') {
                date_default_timezone_set('America/New_York');
                $submitData['chapter'] = $this->chapter->getChapterVipBasedOnChannel(Auth::user()->channel->channel_id);
                $submitData['event_code'] = Input::get('event_code');
                $submitData['event_type'] = Input::get('event_type');
                $submitData['event_date'] = strtotime(Input::get('event_date') . Input::get('event_time'));
                $submitData['num_tickets'] = Input::get('event_passes');
                $submitData['expire_date'] = strtotime(str_replace(".", "-", Input::get('event_end_date')) . Input::get('event_end_time'));
                $submitData['location'] = Input::get('event_location');
                $qrImage = S3::createQrImage(Input::get('event_qr_image_data'));
                $submitData['qr_image'] = $qrImage;
            }
            $res = $this->post->create($submitData);

			// Below is commented intentionally to avoid push notification error.
			/* if(Input::get('type') == 'event') {
				$channelId = Auth::user()->channel->channel_id;
				$channels = $this->channel->getChannelById(Auth::user()->channel->channel_id);
				$channelName = $channels['first_name']. " " .$channels['last_name'];
				$messagetext = $channels['first_name']. " " .$channels['last_name'] ." just created a secret event and you are invited!.";
				$sound = "default";
				switch(Input::get('subscriber_only')) {
					case '0':
						$users = $this->follow->getFollowersByChannel(Auth::user()->channel->channel_id);
						foreach ($users as $user) {
							$apnsToken = $this->user->getDeviceToken($user['user_id']);
							if($apnsToken!="")
							$this->channel->_sendPushNotification($apnsToken, $messagetext, $channelId, $channelName, $sound, $action='');
						}			
						break;
					case '1':
						$users = $this->subscribe->getSubscribersByChannel(Auth::user()->channel->channel_id);
						foreach ($users as $user) {
							$apnsToken = $this->user->getDeviceToken($user['user_id']);
							if($apnsToken!="")
							$this->channel->_sendPushNotification($apnsToken, $messagetext, $channelId, $channelName, $sound, $action='');
						}
						break;
					default :
						break;
				}
			} */
			$pstVideo = Input::get('post_video');
			if(($pstVideo == "") || (Input::get('type') != 'video' && $secondVideo == "")) {
				if($res->post_id > 0){
                    $this->updateChannelPostOrderSettings('add',Auth::user()->channel->channel_id,$res->chapter_id,$res->post_id);
					self::updateAdvastJsonData(Auth::user()->channel->channel_id);
				}
				$shortUrl = $res->short_url;
				/* Sharing To Twitter */
				if (Input::get('twiiter_share') != '0') {
					$title = Input::get('share_text');
					if (Input::get('type') == 'text')
						$title = Input::get('share_text');
				
					$title = Str::limit($title, 135 - 18 - 3);
					$twitter = new Twitter(Auth::user()->twitter_token, Auth::user()->twitter_token_secret);
					$twitter->tweet($title . ' ' . $shortUrl);
				}

				/* Sharing To Facebook */
				if (Input::get('facebook_share') == '1') {
					$title = Input::get('title');
					if ($title == '')
						$title = Input::get('description');

					$title = ( $title == '' ) ? 'Vast' : $title;
					$title = Str::limit($title, 55);

					$fb = new Facebook();
					$share_array = array(
						'access_token' => Auth::user()->fb_token,
						'link' => $shortUrl,
						'caption' => 'VAST - The ultimate fan experience',
						'message' => Input::get('share_text'),
						'scrap' => true
					);
					$fb->shareLink($share_array, Input::get('fb_page_data'));
				}

				/* Sharing To Tumblr */
				if (Input::get('tumblr_share') != '0' && ( Auth::user()->tumblr_token != '' && Auth::user()->tumblr_username != '' )) {
					switch (Input::get('type')) {
						case 'text':
							$caption = Input::get('share_text');
							$path = S3::getPostSdPath(Input::get('post_message_image'));
							$source_url = S3::getS3Url($path);
							$post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);
							break;

						case 'music':
							$caption = Input::get('share_text') . ', Song : ' . Input::get('song_name') . ', Artist : ' . Input::get('artist_name');
							$caption = ( Input::get('share_text') == '' ) ? Input::get('title') : Input::get('share_text');
							$path = S3::getPostMusicPath(Input::get('post_music'));
							$source_url = S3::getS3Url($path);

							$post_data = array('type' => 'audio', 'caption' => '<a href="' . $shortUrl . '">' . $caption . '<a>', 'external_url' => $source_url);
							break;

						/* case 'video':
							$caption = Input::get('share_text');
							$path = S3::getPostVideoPath(Input::get('post_video'));
							$source_url = S3::getS3Url($path);
							$embed = ' <video controls><source src="' . $source_url . '"></source></video>';
							$post_data = array('type' => 'video', 'caption' => '<a href="' . $shortUrl . '">' . $caption . '<a>', 'embed' => $embed);
							break; */

						case 'image':
							$caption = Input::get('share_text');
							$path = S3::getPostRetinaPath(Input::get('post_image'));
							$source_url = S3::getS3Url($path);

							$post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);
							break;
						
						 case 'event':
							$caption = Input::get('share_text');
							$path = S3::getPostSdPath(Input::get('post_image'));
							$source_url = S3::getS3Url($path);

							$post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);
							break;

						default:
							break;
					}

					if ($post_data) {
						$token = Auth::user()->tumblr_token;
						$tokenSecret = Auth::user()->tumblr_oauth_token_secret;
						$blog_name = Auth::user()->tumblr_username . '.tumblr.com';
						$tumblr = new Tumblr($token, $tokenSecret);
						$tumblr->createPost($blog_name, $post_data);
					}
				}
			}
      			
            if ($res->post_id > 0) {				
                /*
                commented to lock manageexplore nonfeatured lists
                $this->manageexplore->updateNonFeaturedList(array(
                    'channel_id' => Auth::user()->channel->channel_id,
                    'postId' => $res->post_id,
                    'chapter' => Input::get('chapter')
                ));*/
                if (Input::get('type') == "event")
                    Session::flash('success', 'Event Added Successfully!');
                else
                    Session::flash('success', 'Post Added Successfully!');
                echo 1;
            }else {
                if (Input::get('type') == "event")
                    Session::flash('error', 'Event Adding Failed!');
                else
                    Session::flash('error', 'Post Adding Failed!');
                echo 0;
            }
        }
    }

    public function doPostEdit() {
        $messages = array(
            'url' => 'Kindly enter a valid Url.'
        );
        $validator = Validator::make(Input::all(), Post::$editrules, $messages);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();
            return Response::json(array(
                        'success' => false,
                        'errors' => $messages
            ));
        } else {
            $poix = '';
            $poiy = '';
            $dt = new DateTime();
            $cdate = strtotime($dt->format('Y-m-d H:i:s'));
            switch (Input::get('post_type')) {
                case 'music' :
                    $updateData = array(
                        'post_id' => (int) Input::get('post_id'),
                        'channel_id' => Auth::user()->channel->channel_id,
                        'music_url' => Input::get('post_music'),
                        'thumb_url' => Input::get('post_music_image'),
                        'hdthumb_url' => Input::get('post_music_image'),
                        'sd_url' => Input::get('post_music_image'),
                        'second_video' => Input::get('post_music_video'),
                        'description' => Input::get('write'),
                        'chapter_id' => Input::get('chapter'),
                        'featured' => Input::get('post_paying_subscriber'),
                        'thumb_width' => Input::get('thumb_width'),
                        'thumb_height' => Input::get('thumb_height'),
                        'hdthumb_width' => Input::get('hdthumb_width'),
                        'hdthumb_height' => Input::get('hdthumb_height'),
                        'sd_width' => Input::get('sd_width'),
                        'sd_height' => Input::get('sd_height'),
                        'song' => Input::get('song'),
                        'author' => Input::get('artist'),
                        'title' => Input::get('title'),
                        'story' => Input::get('story')
                    );
                    break;

                case 'image' :
                    $updateData = array(
                        'post_id' => (int) Input::get('post_id'),
                        'channel_id' => Auth::user()->channel->channel_id,
                        'audio_url' => Input::get('post_image_audio'),
                        'retina_url' => Input::get('post_photo'),
                        'nonretina_url' => Input::get('post_photo'),
                        'thumb_url' => Input::get('post_photo'),
                        'hdthumb_url' => Input::get('post_photo'),
                        'sd_url' => Input::get('post_photo'),
                        'thumb_width' => Input::get('thumb_width'),
                        'thumb_height' => Input::get('thumb_height'),
                        'hdthumb_width' => Input::get('hdthumb_width'),
                        'hdthumb_height' => Input::get('hdthumb_height'),
                        'sd_width' => Input::get('sd_width'),
                        'sd_height' => Input::get('sd_height'),
                        'retina_width' => Input::get('retina_width'),
                        'retina_height' => Input::get('retina_height'),
                        'nonretina_width' => Input::get('nonretina_width'),
                        'nonretina_height' => Input::get('nonretina_height'),
                        'second_video' => Input::get('post_image_video'),
                        'description' => Input::get('write'),
                        'chapter_id' => Input::get('chapter'),
                        'featured' => Input::get('post_paying_subscriber'),
                        'title' => Input::get('title'),
                        'story' => Input::get('story')
                    );
                    break;

                case 'video' :
                    $updateData = array(
                        'post_id' => (int) Input::get('post_id'),
                        'channel_id' => Auth::user()->channel->channel_id,
                        'audio_url' => Input::get('post_video_audio'),
                        'second_video' => Input::get('post_video_video'),
                        'video_url' => Input::get('post_video'),
                        'description' => Input::get('write'),
                        'chapter_id' => Input::get('chapter'),
                        'featured' => Input::get('post_paying_subscriber'),
                        'thumb_url' => Input::get('post_video_thumb'),
                        'hdthumb_url' => Input::get('post_video_thumb'),
                        'sd_url' => Input::get('post_video_thumb'),
                        'thumb_width' => Input::get('thumb_width'),
                        'thumb_height' => Input::get('thumb_height'),
                        'hdthumb_width' => Input::get('hdthumb_width'),
                        'hdthumb_height' => Input::get('hdthumb_height'),
                        'sd_width' => Input::get('sd_width'),
                        'sd_height' => Input::get('sd_height'),
                        'title' => Input::get('title'),
                        'story' => Input::get('story'),
                        'youtube_url' => Input::get('youtube_url')
                    );
                    break;

                case 'event' :
                    $qrImage = S3::createQrImage(Input::get('event_qr_image_data'));
                    $updateData = array(
                        'post_id' => (int) Input::get('post_id'),
                        'event_id' => (int) Input::get('event_id'),
                        'channel_id' => Auth::user()->channel->channel_id,
						'chapter_id' => $this->chapter->getChapterVipBasedOnChannel(Auth::user()->channel->channel_id),
                        'post_type' => 'event',
                        'description' => Input::get('description'),
                        'featured' => Input::get('subscriber_only'),
                        'thumb_url' => Input::get('post_image'),
                        'hdthumb_url' => Input::get('post_image'),
                        'sd_url' => Input::get('post_image'),
                        'thumb_width' => Input::get('thumb_width'),
                        'thumb_height' => Input::get('thumb_height'),
                        'hdthumb_width' => Input::get('hdthumb_width'),
                        'hdthumb_height' => Input::get('hdthumb_height'),
                        'sd_width' => Input::get('sd_width'),
                        'sd_height' => Input::get('sd_height'),
                        'title' => Input::get('title'),
                        'story' => Input::get('description'),
                        'event_date' => strtotime(Input::get('event_date') . Input::get('event_time')),
                        'location' => Input::get('event_location'),
                        'num_tickets' => Input::get('event_passes'),
                        'expire_date' => strtotime(Input::get('event_end_date') . Input::get('event_end_time')),
                        'qr_image' => $qrImage
                    );
                    break;
            }

            if (Input::get('poi_coords') != "") {
                $poi = explode(",", Input::get('poi_coords'));
                $updateData['poix'] = $poi[0];
                $updateData['poiy'] = $poi[1];
            }
            if (Input::get('publishTime') == 1) {
                date_default_timezone_set('America/New_York');
                $updateData['date'] = strtotime(Input::get('publish-date') . Input::get('publish-time'));
            } else
                $updateData['date'] = $cdate;
            //var_dump($updateData);
            $videoval = "";
            $secvideoval = "";
            $vStatus = 0;
            if (isset($updateData['video_url']) && $updateData['video_url'] != "") {
                $videoval = explode('.', $updateData['video_url']);
                $videoval = $videoval[1];
            }
            if (isset($updateData['second_video']) && $updateData['second_video'] != "") {
                $secvideoval = explode('.', $updateData['second_video']);
                $secvideoval = $secvideoval[1];
            }
            $videoUpdateData = array(
                'post_id' => (int) Input::get('post_id')
            );
            if ($videoval != "" && $videoval == "tmp") {
                $videoUpdateData['videourl'] = $updateData['video_url'];
                $vStatus = 1;
            }

            if ($secvideoval != "" && $secvideoval == "tmp") {
                $videoUpdateData['secvideourl'] = $updateData['second_video'];
                $vStatus = 1;
            }
            if ($vStatus == 1) {
                $videoUpdateData['channel_id'] = Auth::user()->channel->channel_id;
                $videoUpdateData['percent'] = 0;
                $videoUpdateData['post_title'] = Input::get('title');
                $updateData['post_status'] = 0;
				$videoUpdateData['share_text'] = '';
				$videoUpdateData['tw'] = 0;
				$videoUpdateData['fb'] = 0;
				$videoUpdateData['tb'] = 0;
				$videoUpdateData['short_url'] = Input::get('short_url');
				$videoUpdateData['tw_token'] = (Auth::user()->twitter_token == '' ) ? '0' : Auth::user()->twitter_token;
				$videoUpdateData['tw_secret'] = (Auth::user()->twitter_token_secret == '' ) ? '0' : Auth::user()->twitter_token_secret;
				$videoUpdateData['fb_token'] = (Auth::user()->fb_token == '' ) ? '0' : Auth::user()->fb_token;
				$videoUpdateData['tb_token'] = (Auth::user()->tumblr_token == '' ) ? '0' : Auth::user()->tumblr_token;
				$videoUpdateData['tb_username'] = (Auth::user()->tumblr_username == '' ) ? '0' : Auth::user()->tumblr_username;
				$videoUpdateData['tb_secret'] = (Auth::user()->tumblr_oauth_token_secret == '' ) ? '0' : Auth::user()->tumblr_oauth_token_secret;
                $resp = $this->conversion->create($videoUpdateData);
            } else
                $updateData['post_status'] = 1;

            $updateData['featured_post'] = Input::get('featured_post');
            /* Sharing To Facebook */

            if (Input::get('facebook_share') == '1') {

                $title = Input::get('title');
                if ($title == '')
                    $title = Input::get('write');
                $title = ( $title == '' ) ? 'Vast' : $title;
                $title = Str::limit($title, 55);

                $fb = new Facebook();
                if (Input::get('post_type') == 'image') {
                    if (Input::get('subscription') != "")
                        $image = URL::to('/') . '/cms/do-water-marking?path=' . S3::getS3Url(S3::getPostSdPath($updateData['sd_url'])) . '&type=1&fb=0';
                    else
                        $image = URL::to('/') . '/cms/do-water-marking?path=' . S3::getS3Url(S3::getPostSdPath($updateData['sd_url'])) . '&type=0&fb=0';
                } else
                    $image = S3::getS3Url(S3::getPostSdPath($updateData['sd_url']));


                $share_array = array(
                    'access_token' => Auth::user()->fb_token,
                    'link' => Input::get('short_url'),
                    'caption' => 'VAST - The ultimate fan experience',
                    'picture' => $image,
					'message' => Input::get('share_text'),
                    'scrap' => true
                );

                $fb->shareLink($share_array, Input::get('fb_page_data'));
            }

            /* Sharing To Tumblr */
            if (Input::get('tumblr_share') == '1') {
                /* Message Sharing to Tumblr */
                $shortUrl = Input::get('short_url');
                switch (Input::get('post_type')) {
                    case 'text':
                        $caption = Input::get('share_text');
                        $path = S3::getPostSdPath(Input::get('post_message_image'));
                        $source_url = S3::getS3Url($path);
                        $post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);
                        break;

                    case 'music':
                        $caption = Input::get('share_text') . ', Song : ' . Input::get('song_name') . ', Artist : ' . Input::get('artist_name');
                        $caption = ( Input::get('share_text') == '' ) ? Input::get('title') : Input::get('share_text');
                        $path = S3::getPostMusicPath(Input::get('post_music'));
                        $source_url = S3::getS3Url($path);

                        $post_data = array('type' => 'audio', 'caption' => '<a href="' . $shortUrl . '">' . $caption . '<a>', 'external_url' => $source_url);
                        break;

                    case 'video':
                        $caption = Input::get('share_text');
                        $path = S3::getPostVideoPath(Input::get('post_video'));
                        $source_url = S3::getS3Url($path);
                        $embed = ' <video controls><source src="' . $source_url . '"></source></video>';
                        $post_data = array('type' => 'video', 'caption' => '<a href="' . $shortUrl . '">' . $caption . '<a>', 'embed' => $embed);

                        break;

                    case 'image':
                        $caption = Input::get('share_text');
                        $path = S3::getPostRetinaPath(Input::get('post_photo'));
                        $source_url = S3::getS3Url($path);
                        $post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);
                        break;
					
					 case 'event':
                        $caption = Input::get('share_text');
                        $path = S3::getPostSdPath(Input::get('post_photo'));
                        $source_url = S3::getS3Url($path);
                        $post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);
                        break;

                    default:
                        break;
                }

                if ($post_data) {
                    $token = Auth::user()->tumblr_token;
                    $tokenSecret = Auth::user()->tumblr_oauth_token_secret;
                    $blog_name = Auth::user()->tumblr_username . '.tumblr.com';
                    $tumblr = new Tumblr($token, $tokenSecret);
                    $tumblr->createPost($blog_name, $post_data);
                }
            }

            /* Sharing To Twitter */
            if (Input::get('twiiter_share') != '0') {
                $title = Input::get('share_text');
                if (Input::get('type') == 'text')
                    $title = Input::get('share_text');

                $title = Str::limit($title, 135 - 18 - 3);

                $twitter = new Twitter(Auth::user()->twitter_token, Auth::user()->twitter_token_secret);
                $twitter->tweet($title . ' - ' . Input::get('short_url'));
            }

            $res = $this->post->updatePost($updateData);
            if ($res > 0) {
                
				//$jsonData = self::getAdvastBannerJson(Auth::user()->channel->channel_id);
				//$jsonUrl = S3::createAdvastBannerJson($jsonData,Auth::user()->channel->channel_id);
                Session::flash('success', 'Post Edited Successfully!');
               //commented to prevent manageexplore featured update on postedit
                //$fLists = $this->manageexplore->updatedFeatured(Auth::user()->channel->channel_id, Input::get('chapter'), (int) Input::get('post_id'), Input::get('featured_post'));
                //$this->post->updateChannelFeaturedPosts(Auth::user()->channel->channel_id, $fLists);
                self::updateAdvastJsonData(Auth::user()->channel->channel_id);
                echo 1;
            } else {
                Session::flash('error', 'Post Editing Failed!');
                echo 0;
            }
        }
    }

    public function updateGPlusToken() {
        if (Request::isMethod('post')) {
            $this->admin->saveGPlusToken(Auth::user()->vast_user_id, array(
                'gplus_token' => Input::get('token'),
                'gplus_uid' => Input::get('id')
            ));
            echo '1';
        } else {
            echo '0';
        }
    }

    public function uploadSecVideo() {
        $fileUpload = S3::uploadSecVideo('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadSecAudio() {
        $fileUpload = S3::uploadSecAudio('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadSecImage() {
        $fileUpload = S3::uploadSecImage('Filedata', Auth::user()->channel);
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadChannelGroupCoverImage() {
        $fileUpload = S3::coverImageUploadAndResize('Filedata', 'network');
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadChannelCoverImage() {
        $fileUpload = S3::coverImageUploadAndResize('Filedata', 'channel');
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadStoryCoverImage() {
        $fileUpload = S3::coverImageUploadAndResize('Filedata', 'story');
        echo $fileUpload ? $fileUpload : '';
    }

    public function uploadChapterStoryCoverImage() {
        $chapterID = json_decode($_POST['chapterId']);
        $res = $this->post->getLastPostByChapter($chapterID);
        if ($res['type'] == 'image')
            $fileUpload = S3::uploadPhotoPost('Filedata', Auth::user()->channel);
        else
            $fileUpload = S3::uploadSecImage('Filedata', Auth::user()->channel);
        $tempArray = json_decode($fileUpload, true);
        $tempArray['post_id'] = $res['post_id'];
        $tempArray['post_type'] = $res['type'];
        $fileUpload = json_encode($tempArray);
        echo $fileUpload ? $fileUpload : '';
    }

    public function deleteMusic() {
        // deleting code
        //echo 1;
    }

    public function addChapter() {
        $validator = Validator::make(Input::all(), Chapter::$rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();
            return Response::json(array(
                        'success' => false,
                        'errors' => $messages
            )); // 400 being the HTTP code for an invalid request.
        } else {
            $res = $this->chapter->create(array(
                'channel_id' => Auth::user()->channel->channel_id,
                'chapter_name' => Input::get('chapterName'),
				'post_order' => '0,0'
            ));

            return Response::json(array(
                        'success' => true,
                        'chapter_id' => $res
            ));
        }
    }

    public function cropImage() {
        $data = array(
            'cropX' => Input::get('cropX'),
            'cropY' => Input::get('cropY'),
            'cropW' => Input::get('cropW'),
            'cropH' => Input::get('cropH'),
            'cropUrl' => Input::get('ipadCropImage'),
            'cropDevice' => Input::get('device'),
            'modWidth' => Input::get('modWidth'),
            'modHeight' => Input::get('modHeight')
        );
        $cropImage = S3::cropImage($data);
        echo $cropImage;
    }

    public function deleteUser() {
        $userId = Input::get('userId');
        $this->manageexplore->updateManageExploreEntryOnChannelDelete($userId);
        $res = $this->admin->deleteAdminUser($userId);
		$cres= $this->channel->delete($userId);
        if ($res == 1)
            Session::flash('success', 'User Deleted Successfully!');
        else
            Session::flash('error', 'User Deletion Failed!');
        echo $res;
    }

    public function getChapters() {

        $channel = array('channel_id' => (int) Input::get('chapterId'));
        $chapters = $this->chapter->getChaptersByChannel((object) $channel);
        $data = "";
        foreach ($chapters as $chapter) {
           // $status = $chapter->featured == 1 ? "<span class='featured_span'>Featured</span>" : "<span class='non_featured_span'>Non Featured</span>";
		   $status = '';
            $active = $chapter->inactive == 0 ? "<span class='featured_span'>&nbsp;Active&nbsp;&nbsp;</span>" : "<span class='in_active_span '>Inactive</span>";
            $data .= "<tr><td align='center'>" . $chapter->chapter_id . "</td><td align='center' id='chapter_td_" . $chapter->chapter_id . "'class='chaptername_td'>" . $chapter->chapter_name . "</td><td align='center'><div class='chapter_edit_actions'><span id='featured_span_" . $chapter->chapter_id . "'>" . $status . "</span><span class='newline_span' id='active_span_" . $chapter->chapter_id . "'>" . $active . "</span><span class='chapter_edit_icon featured_img' id='" . $chapter->chapter_id . "'></span><span class='chapter_delete trash_box_icon cursor-pointer' data-id='" . $chapter->chapter_id . "'></span><input type = 'hidden' id='featured_hidden_" . $chapter->chapter_id . "' class='featured_hidden' value = '" . $chapter->featured . "' ><input type = 'hidden' id='active_hidden_" . $chapter->chapter_id . "' class='active_hidden' value = '" . $chapter->inactive . "' ></div></td></tr>";
        }
        echo $data;
    }

    public function setChapterStatus() {
        $res = $this->chapter->update(array(
            'chapter_id' => Input::get('chapterId'),
            'chapter_name' => Input::get('chapter_name'),
            'featured' => Input::get('status'),
            'inactive' => Input::get('active')
        ));
        echo $res;
    }

    public function setChannelStatus() {
        $res = $this->channel->updateChannelStatus(array(
            'channel_id' => Input::get('channelId'),
            'inactive' => Input::get('status')
        ));
        echo $res;
    }

    public static function poiCoords() {

        $url = Input::get('poiUrl');
        $modSize = explode(',', Input::get('poi'));
		if($modSize[0] > 1){
			$size = getimagesize($url);
			$widthRatio = $size[0] / Input::get('cWidth');
			$heightRatio = $size[1] / Input::get('cHeight');
			$x = round(($modSize[0] / Input::get('cWidth')), 2);
			$y = round(($modSize[1] / Input::get('cHeight')), 2);
		}else{
			$x = $modSize[0];
			$y = $modSize[1];
		}
        echo $x . "," . $y;
    }

    public function deleteChannelDetails() {
        $res = $this->admin->deleteAdminUser(Input::get('channelId'));
		$this->manageexplore->updateManageExploreEntryOnChannelDelete(Input::get('channelId'));
        /*if ($res)
            $res = $this->chapter->deleteChapterByChannel(Input::get('channelId'));
        if ($res)
            $res = $this->post->deletePostByChannel(Input::get('channelId'));*/
        echo $res;
    }

    public function deleteChapterDetails() {
        $res = $this->chapter->deleteChapterById(Input::get('chapterId'));
        if ($res)
            $res = $this->post->deletePostByChapter(Input::get('chapterId'));
        echo $res;
    }

    public function deleteUploadedItemFromS3() {
        $data = Input::all();
        $postId = $data['postId'];
        $fileType = $data['fileType'];
        $fileName = $data['fileName'];
        $folderName = $data['folderName'];
        if ($postId != "") {
            
        } else {
            switch ($fileType) {
                case 'music':
                    S3::delete_file($folderName, $fileName);
                    break;
                case 'image':
                    S3::delete_file($folderName = "postimages/hdthumb", $fileName);
                    S3::delete_file($folderName = "postimages/thumb", $fileName);
                    S3::delete_file($folderName = "postimages/sd", $fileName);
                    S3::delete_file($folderName = "postimages/nonretina", $fileName);
                    S3::delete_file($folderName = "postimages/retina", $fileName);
                    break;
                case 'video':
                    $file = explode(".", $fileName);
                    S3::delete_file($folderName, $file[0] . '.mp4');
                    S3::delete_file($folderName, $file[0] . '.webm');
                    break;
                default:
                    break;
            }
        }
    }

    /* uploaded item format change starts */

    public function convertUploadedVideoFormat() {
	Log::info("converting video");
        $data = Input::all();
        $source = $data['source'];
        $type = $data['type'];
        if ($type == 1)
            S3::deleteUploadedVideosFromS3('videos', $source);
        elseif ($type == 0)
            S3::deleteUploadedVideosFromS3('secvideo', $source);
        $id = S3::convertUploadedVideoFormat($type, $source);
        echo $id;
    }

    /* uploaded item format change ends */


    /* get video conversion status starts */

    public function videoConversionStatus() {
        $data = Input::all();
        $id = $data['id'];
        $source = $data['source'];

        if (S3::getStatus($id) == 'Complete') {
            $duration = S3::getDuration($id);
            $interval = S3::getInterval();
            S3::delete_file('videos', $source);
            echo ceil($duration['Job']['Output']['Duration'] / $interval) + 1;
        } elseif (S3::getStatus($id) == 'Error') {
            echo '-1';
        } else {
            echo '0';
        }
    }

    /* get video conversion status ends */

    /* move user selected thumbnail starts here */

    public function moveUserSelectedFile() {
        $data = Input::all();
        $source = $data['fname'];
        //$thumbPattern = $data['thumb_pattern'];
        //$res = S3::moveUserSelectedFile($source,Auth::user()->channel);
        //S3::deleteCreatedThumbnails($thumbPattern);
        $res = S3::resizeUploadedThumbImage($source, Auth::user()->channel);
        echo $res;
    }

    /* move user selected thumbnail ends here */

    /* get last post image based on channel or chapter starts here */

    public function getLastPostImage() {
        $data = Input::all();
        $type = $data['type'];
        $id = $data['id'];
        if ($type == 1) {
            $postImage = $this->manageexplore->getCoverImage('mapping_id', $id) == "" ? S3::getS3Url(S3::getChannelAvatarPath('sd/' . $this->channel->getAvatarByChannelId($id))) : S3::getS3Url(S3::getChannelCoverPhotoPath('sd/' . $this->manageexplore->getCoverImage('mapping_id', $id)));
            echo $postImage;
        } elseif ($type == 0)
            echo S3::getS3Url(S3::getPostHdthumbPath($this->post->getLastPostImageByChapter($id)));
    }

    /* get last post image based on channel or chapter ends here */


    /* super admin manage explore add new chapter starts here */

    public function manageExploreAddNewChapter() {
        $data = Input::all();
        $no = $data['items'] + 1;
        $id = $data['id'];
        $chapters = $this->chapter->getActiveChapters();
        $str = "<div class='manage-explore-featured-list-item'>
					<div class='manage-explore-add-chapter-select-div col-md-9'>   
                          <select name='featured_chapter[]' class='form-control'> 
						  		<option value=''>Select Chapter</option>";
        foreach ($chapters as $chapter)
            if ($chapter->chapter_id == $id)
                $str.= "<option value='" . $chapter->chapter_id . "' selected='selected'>" . $chapter->chapter_name . "</option>";
            else
                $str.= "<option value='" . $chapter->chapter_id . "'>" . $chapter->chapter_name . "</option>";



        $str .= "        </select>
                     </div>
					<div class='manage-explore-add-chapter-text-div'>
						<img src='" . URL::to('images') . "/delete-chapter.png' class='manage-explore-delete-list-item cursor-pointer' />
					</div>
                </div>";
        echo $str;
    }

    /* super admin manage explore add  new chapter ends here */


    /* Social Media unlinking starts */

    public function twitterUnlink() {
        $res = $this->admin->saveTwitterToken(Input::get('channel_id'), array(
            'twitter_token' => '',
            'twitter_token_secret' => '',
            'twitter_uid' => '',
            'twitter_screen_name' => ''
        ));
        echo $res;
    }

    public function facebookUnlink() {
        $res = $this->admin->saveFbToken(Input::get('channel_id'), array(
            'fb_token' => '',
            'fb_link' => ''
        ));
        $page_id = '';
        $res1 = $this->admin->saveFbPageId(Input::get('channel_id'), $page_id);

        echo $res;
    }

    public function tumblrUnlink() {
        $res = $this->admin->saveTumblrToken(Input::get('channel_id'), array(
            'tumblr_token' => '',
            'tumblr_oauth_token_secret' => '',
            'tumblr_username' => ''
        ));
        echo $res;
    }

    /* Social Media unlinking ends */

    /* get uploaded video thumbs through encoding.com starts here */

    public function getUploadedVideoThumbs() {
        $data = Input::all();
        $source = S3::getS3Url(S3::getPostVideoPath($data['source']));
        $mediaId = S3::addMediaForEncoding($source);
        echo $mediaId;
    }

    public function getThumbCreationStatus() {
        $data = Input::all();
        $resp = S3::getMediaEncodingStatus($data['mediaID']);
        echo $resp;
    }

    /* get uploaded video thumbs through encoding.com ends here */

    public function addNewChannelGroup() {
        $resp = $this->channelgroup->addNewChannelGroup(Input::get('groupName'), Input::get('cover'), Input::get('id'));
        echo $resp;
    }

    public function getChannelGroupById() {
        $resp = $this->channelgroup->getChannelGroupById(Input::get('id'));
        echo $resp;
    }

    public function deleteChannelGroupById() {
	    $exploreDet = $this->manageexplore->getNetworkById(Input::get('id'));
		if($exploreDet->type == 1){
			$nonFeaturedLists = explode(',',$this->manageexplore->getFieldFromManageExplore('id',$exploreDet->network_id,'non_featured_list'));
			$key = array_search($exploreDet->mapping_id, $nonFeaturedLists); 
			$nonFeaturedLists[$key] = 0;
			$res = $this->manageexplore->update( array(
				'id' => $exploreDet->network_id,
				'type' => 0, 
				'non_featured_list' => implode(',',$nonFeaturedLists)
			));
			$manageExploreId = $this->manageexplore->getFieldFromManageExplore('mapping_id',$exploreDet->mapping_id,'id');
			$res = $this->manageexplore->update( array(
				'id' => $manageExploreId,
				'type' => 1, 
				'network_id' => 0,
				'isexplore' => 0
			));
			
		}else{
			$res = $this->manageexplore->deleteById(Input::get('id'));
		}
       
        if ($res)
            Session::flash('success', 'Deletion successful!');
        else
            Session::flash('warning', 'Deletion failed!');
    }

    public function updateLivestreamData() {
        $updateData = array(
            'livestream_name' => Input::get('livestream_name'),
            'livestream_title' => Input::get('livestream_title'),
			'livestream_event' => Input::get('livestream_event'),
            'live_thumb_url' => Input::get('photo'),
            'live_thumb_width' => Input::get('live_thumb_width'),
            'live_thumb_height' => Input::get('live_thumb_height'),
            'live_hdthumb_url' => Input::get('photo'),
            'live_hdthumb_width' => Input::get('live_hdthumb_width'),
            'live_hdthumb_height' => Input::get('live_hdthumb_height'),
            'live_sd_url' => Input::get('photo'),
            'live_sd_width' => Input::get('live_sd_width'),
            'live_sd_height' => Input::get('live_sd_height'),
            'live_featured' => Input::get('subscription')
        );
        $res = $this->channel->update(Auth::user()->channel->channel_id, $updateData);

        /*$channelId = Auth::user()->channel->channel_id;
        $channels = $this->channel->getChannelById(Auth::user()->channel->channel_id);
        $channelName = $channels['first_name'] . " " . $channels['last_name'];
        $messagetext = $channels['first_name'] . " " . $channels['last_name'] . " just went live. See what is happening now!.";
        $sound = "default";
        switch (Input::get('subscription')) {
            case '0':
                $users = $this->user->getUsers();
                foreach ($users as $user) {
                    $apnsToken = $this->user->getDeviceToken($user['ID']);
                    if ($apnsToken != "")
                        $this->channel->_sendPushNotification($apnsToken, $messagetext, $channelId, $channelName, $sound, $action = '');
                }
                break;
            case '1':
                $users = $this->subscribe->getSubscribersByChannel(Auth::user()->channel->channel_id);
                foreach ($users as $user) {
                    $apnsToken = $this->user->getDeviceToken($user['user_id']);
                    if ($apnsToken != "")
                        $this->channel->_sendPushNotification($apnsToken, $messagetext, $channelId, $channelName, $sound, $action = '');
                }
                break;
            default :
                break;
        }*/

        echo $res;
    }

    public function getChannelListAlphabetically() {
        $channels = $this->channel->getChannelListAlphabetically(Input::get('alpha'));
        $str = "";
        foreach ($channels as $channel)
            $str .= "<div id='" . $channel->channel_id . "' class='artists'>" . $channel->first_name . "  " . $channel->last_name . "</div>	";
        if (empty($str))
            $str .= "<h3>No Artists</h3>";
        echo $str;
    }

    public function getChapterStoryList() {
        $str = "";
        $str .= '<div class="me-chapter-listing">';
        $chapters = $this->chapter->getChaptersByChannelId(Input::get('id'));
        foreach ($chapters as $chapter) {
            $postDetails = $this->post->getPostsbyChapter($chapter->chapter_id);
            $getLastPostByChapter = $this->post->getLastPostByChapter($chapter->chapter_id);
            $chapterLastPostImage = S3::getS3Url(S3::getPostSdPath($getLastPostByChapter['sd_url']));
            $img = $mus = $vid = $evt = 0;
            $substr = "";
            foreach ($postDetails as $postDetail) {
                $classOverlay = "";
                switch ($postDetail->type) {
                    case 'image':
                        $classOverlay = "no-overlay";
                        $img++;
                        break;
                    case 'video':
                        $classOverlay = "video-overlay";
                        $vid++;
                        break;
                    case 'music':
                        $classOverlay = "music-overlay";
                        $mus++;
                        break;
                    case 'event':
                        $classOverlay = "event-overlay";
                        $evt++;
                        break;
                    default:
                        break;
                }
                $imgUrl = S3::getS3Url(S3::getPostHdthumbPath($postDetail->sd_url));
                $imgSdUrl = S3::getS3Url(S3::getPostSdPath($postDetail->sd_url));
                $substr .= '<div class="me-chapter-post-images"><div  class="me-chapter-post-images-inner ' . $classOverlay . '"><img onclick="storyImageSelector(' . $postDetail->post_id . ')"id="storyImageSelector_' . $postDetail->post_id . '" data-id="' . $postDetail->post_id . '" data-url="' . $imgSdUrl . '" id="selectedChapterStoryThumb-"' . $postDetail->post_id . ' class="img-rounded selectedChapterStoryThumb" src="' . $imgUrl . '" width="60" height="55"/></div></div>';
            }
            $str .= '<div class="me-chapter" id="me-chapter-' . $chapter->chapter_id . '">
			 		 	<div class="me-chapter-head col-md-12 col-xs-12 col-sm-12" id="head-drp-' . $chapter->chapter_id . '">
                        	<div class="me-chapter-text col-md-6 col-xs-6 col-sm-6">
								<div class="me-chapter-name">' . $chapter->chapter_name . '</div>
								<div class="me-post-type">
									<span>AUDIO:' . $mus . '</span><span>VIDEO:' . $vid . '</span> <span>IMG:' . $img . '</span><span>EVENT:' . $evt . '</span>
								</div>
							</div>
                         	<div class="me-chapter-head-image col-md-4 col-xs-4 col-sm-4"><img width="146" src="' . $chapterLastPostImage . '" /></div>
                         	<div class="me-chapter-head-drop col-md-2 col-xs-2 col-sm-2">
								<img id="drp-' . $chapter->chapter_id . '" onclick="sliderContent(' . $chapter->chapter_id . ')" src="' . URL::to('images') . '/me-button-down.png" />
							</div>
                     	</div>
						
						<div id="open-drp-' . $chapter->chapter_id . '" class="me-chapter-content">';
            $str .= $substr;
            $str .= '</div>';
            $str .= '</div>';
        }
        $str .= '</div>';
        echo $str;
    }

    public function getChannelChapter() {
        $str = "";
        $str .= '<option value="0">Select Chapter</option>';
        $chapters = $this->chapter->getChaptersByChannelId(Input::get('id'));
        foreach ($chapters as $chapter) {
            $str .= '<option value="' . $chapter->chapter_id . '">' . $chapter->chapter_name . '</option>';
        }
        echo $str;
    }

    public function getChapterStory() {
        $str = "";
        $str .= '<option value="">Select Story</option>';
        $stories = $this->post->getPostsbyChapter(Input::get('id'));
        foreach ($stories as $story) {
            $newtitle = ($story->title != '') ? $story->title : $story->story;
            $title = substr($newtitle, 0, 75);
            $str .= '<option value="' . $story->post_id . '">' . $title . '</option>';
        }
        echo $str;
    }

    public function manageStorySave() {
        $data = Input::all();
        $this->manageexplore->create($data);
    }

    public function getLastPostImageByChannel() {
        $channel_id = Input::get('id');
        $type = Input::get('type');
        $postImage = S3::getS3Url(S3::getPostSdPath($this->post->getLastPostImageByChannel($channel_id)));
        $str = "";
        if ($type == 0)
            $str .= '<div data-id="0" class="col-sm-3 col-md-3 col-xs-3 remove-padding manage-network-small-container">
						<div class="delete-non-featured-edit-section"><img src ="' . URL::to('images') . '/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>
						<img src= "' . $postImage . '" width="117" height="117" class="img-rounded" />
						<input type="hidden" name="artist[]" value="' . $channel_id . '" />
					</div>';
        else
            $str .= '<div class="delete-non-featured-edit-section"><img src ="' . URL::to('images') . '/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>
    				  <img src= "' . $postImage . '" width="117" height="117" class="img-rounded" />
    				  <input type="hidden" name="artist[]" value="' . $channel_id . '" />';
        echo $str;
    }

    public function getTocChannelImage() {
        $channel_id = Input::get('id');
        $type = Input::get('type');
		$channel_name = $this->channel->getChannelNameByChannelId($channel_id);
		$getSelectedChannel = $this->channel->getChannelById($channel_id);
		$postImage = trim($getSelectedChannel->channel_cover) != "" ? S3::getS3Url(S3::getAppCover("sd/" . $getSelectedChannel->channel_cover)) : S3::getS3Url(S3::getChannelAvatarPath('sd/' . $this->channel->getAvatarByChannelId($channel_id)));
        //$postImage = $this->manageexplore->getCoverImage('mapping_id', $channel_id) == "" ? S3::getS3Url(S3::getChannelAvatarPath('sd/' . $this->channel->getAvatarByChannelId($channel_id))) : S3::getS3Url(S3::getChannelCoverPhotoPath('sd/' . $this->manageexplore->getCoverImage('mapping_id', $channel_id)));
        $str = "";
        if ($type == 0){
            $str .= '<div data-id="0" class="col-sm-3 col-md-3 col-xs-3 remove-padding manage-network-small-container">
						<div class="delete-non-featured-edit-section"><img src ="' . URL::to('images') . '/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>
						<img src= "' . $postImage . '" width="117" height="117" class="img-rounded" />
						<input type="hidden" name="artist[]" value="' . $channel_id . '" />
					</div>';
		}elseif($type == 2){
			$str .= '<div class="network_channel_selector_container">
				<div class="delete-non-featured-edit-section remove-non-featured-edit-section"><img src ="' . URL::to('images') . '/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>
									<img src="'.$postImage.'" class="channel_image" alt=""><input name="artist[]" type="hidden" value="'.$channel_id.'">									<div class="network_channel_selector_channel_name">\\'.$channel_name.'</div>
								</div>';
        }else{
            $str .= '<div class="delete-non-featured-edit-section"><img src ="' . URL::to('images') . '/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>
    				  <img src= "' . $postImage . '" width="117" height="117" class="img-rounded" />
    				  <input type="hidden" name="artist[]" value="' . $channel_id . '" />';
        }
		echo $str;
    }

    public function manageExploreSave() {
        $data = Input::all();
        $res = $this->manageexplore->create($data);
        if ($res)
            Session::flash('success', 'Data Inserted Successfully!');
        else
            Session::flash('warning', 'Data Insertion Failed!');
    }

    public function getArtistChapter() {
        $chapters = $this->chapter->getChaptersByChannelId(Input::get('id'));
        $str = "";
        $str .= '<option value="0">Select Chapter</option>';
        $chapterStr = "";
        $chapterStr .= '<div class="me-chapter-listing">';
        foreach ($chapters as $chapter) {
            $str .= '<option value="' . $chapter->chapter_id . '">' . $chapter->chapter_name . '</option>';
            $postDetails = $this->post->getPostsbyChapter($chapter->chapter_id);
            $getLastPostByChapter = $this->post->getLastPostByChapter($chapter->chapter_id);
            $chapterLastPostImage = S3::getS3Url(S3::getPostSdPath($getLastPostByChapter['sd_url']));
            $img = $mus = $evt = $vid = 0;
            $substr = "";
            foreach ($postDetails as $postDetail) {
                $classOverlay = "";
                switch ($postDetail->type) {
                    case 'image':
                        $classOverlay = "no-overlay";
                        $img++;
                        break;
                    case 'video':
                        $classOverlay = "video-overlay";
                        $vid++;
                        break;
                    case 'music':
                        $classOverlay = "music-overlay";
                        $mus++;
                        break;
                    case 'event':
                        $classOverlay = "event-overlay";
                        $evt++;
                        break;
                    default:
                        break;
                }
                $imgUrl = S3::getS3Url(S3::getPostHdthumbPath($postDetail->sd_url));
                $imgSdUrl = S3::getS3Url(S3::getPostSdPath($postDetail->sd_url));
                $substr .= '<div class="me-chapter-post-images">
								<div  class="me-chapter-post-images-inner ' . $classOverlay . '">
									<img id="storyImageSelector_' . $postDetail->post_id . '" data-id="' . $postDetail->post_id . '" data-url="' . $imgSdUrl . '" id="selectedChapterStoryThumb-"' . $postDetail->post_id . ' class="img-rounded selectedChapterStoryThumb" src="' . $imgUrl . '" width="60" height="55"/>
								</div>
							</div>';
            }
            $chapterStr .= '<div class="me-chapter" id="me-chapter-' . $chapter->chapter_id . '">
			 		 			<div class="me-chapter-head col-md-12 col-xs-12 col-sm-12" id="head-drp-' . $chapter->chapter_id . '">
                        			<div class="me-chapter-text col-md-6 col-xs-6 col-sm-6">
										<div class="me-chapter-name">' . $chapter->chapter_name . '</div>
										<div class="me-post-type">
											<span>AUDIO:' . $mus . '</span><span>VIDEO:' . $vid . '</span> <span>IMG:' . $img . '</span> <span>EVENT:' . $evt . '</span>
										</div>
									</div>
                         			<div class="me-chapter-head-image col-md-4 col-xs-4 col-sm-4"><img width="146" src="' . $chapterLastPostImage . '" /></div>
                         			<div class="me-chapter-head-drop col-md-2 col-xs-2 col-sm-2">
									<img id="drp-' . $chapter->chapter_id . '" onclick="sliderContent(' . $chapter->chapter_id . ')" src="' . URL::to('images') . '/me-button-down.png" />
								</div>
                     		</div>
						
							<div id="open-drp-' . $chapter->chapter_id . '" class="me-chapter-content">';
            $chapterStr .= $substr;
            $chapterStr .= '</div>';
            $chapterStr .= '</div>';
        }
        $chapterStr .= '</div>';
        $arr = array('str' => $str, 'chapterStr' => $chapterStr);
        $dataResp = json_encode($arr);
        echo $dataResp;
    }

    public function checkScreenName() {
        $resp = $this->manageexplore->checkScreenName(Input::get('screenname'));
        echo $resp;
    }

    public function updateNetworkStoryCover() {
        $updateData = array(
            'id' => Input::get('id'),
            'type' => Input::get('type'),
            'cover' => Input::get('imgUrl'),
            'retinaH' => Input::get('retinaH'),
            'retinaW' => Input::get('retinaW'),
            'nonretinaH' => Input::get('nonretinaH'),
            'nonretinaW' => Input::get('nonretinaW'),
            'sdH' => Input::get('sdH'),
            'sdW' => Input::get('sdW')
        );
        $res = $this->manageexplore->update($updateData);
        echo $res;
    }

    public function updateChapterStoryCoverImage() {
        if (Input::get('selected_post_id') != "") {
            if (Input::get('post_type') == "image") {
                $updateData = array(
                    'post_id' => (int) Input::get('selected_post_id'),
                    'retina_url' => Input::get('story_cover_image'),
                    'nonretina_url' => Input::get('story_cover_image'),
                    'thumb_url' => Input::get('story_cover_image'),
                    'hdthumb_url' => Input::get('story_cover_image'),
                    'sd_url' => Input::get('story_cover_image'),
                    'thumb_width' => Input::get('thumbW'),
                    'thumb_height' => Input::get('thumbH'),
                    'hdthumb_width' => Input::get('hdW'),
                    'hdthumb_height' => Input::get('hdH'),
                    'sd_width' => Input::get('sdW'),
                    'sd_height' => Input::get('sdH'),
                    'retina_width' => Input::get('retinaW'),
                    'retina_height' => Input::get('retinaH'),
                    'nonretina_width' => Input::get('nonretinaW'),
                    'nonretina_height' => Input::get('nonretinaH')
                );
            } else {
                $updateData = array(
                    'post_id' => (int) Input::get('selected_post_id'),
                    'thumb_url' => Input::get('story_cover_image'),
                    'hdthumb_url' => Input::get('story_cover_image'),
                    'sd_url' => Input::get('story_cover_image'),
                    'thumb_width' => Input::get('thumbW'),
                    'thumb_height' => Input::get('thumbH'),
                    'hdthumb_width' => Input::get('hdW'),
                    'hdthumb_height' => Input::get('hdH'),
                    'sd_width' => Input::get('sdW'),
                    'sd_height' => Input::get('sdH')
                );
            }
            $res = $this->post->updatePost($updateData);
        }
        if (Input::get('chapterName') != "") {
            $updateData = array(
                'chapter_id' => (int) Input::get('selected_chapter_id'),
                'chapter_name' => Input::get('chapterName')
            );
            $res = $this->chapter->update($updateData);
        }
        echo $res;
    }

    public function publishChannelNow() {
        $updateData = array(
            'id' => Input::get('id'),
            'type' => 1,
            'flag' => 1
        );
        $res = $this->manageexplore->update($updateData);
        echo $res;
    }

    public function doWatermarking() {
        $type = Input::get('type');
        $forFb = Input::get('fb');
        $waterLogo = $type == 0 ? URL::asset('images/vast_app_logo.png') : URL::asset('images/image-lock.png');
        $imgUrl = Input::get('path');
        return $this->post->createWatermark($imgUrl, $waterLogo, $type, URL::asset('images/audio-player-gradient.png'), $forFb);
    }

    public function updateVideoConversionPercent() {
        echo $this->conversion->updateVideoConversionPercentage(Input::get('post_id'));
    }

    public function createQRCode() {
        $qr = new BarcodeQR();
        // create URL QR code 
        $arr = array('eventcode' => Input::get('event_code'), 'date' => Input::get('event_date'), 'location' => Input::get('event_location'), 'eventname' => Input::get('event_name'));
        $str = json_encode($arr);
        $qr->url("https://prod.thefutureisvast.us");
        $qr->text($str);
        $img = $qr->draw();
        $base64img = 'data:image/PNG;base64,' . base64_encode($img);
        echo $base64img;
    }

    public function createEventValidation() {
        $validator = Validator::make(Input::all(), EventBouncer::$rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();
            return Response::json(array(
                        'success' => false,
                        'errors' => $messages
            )); // 400 being the HTTP code for an invalid request.
        } else {
            return Response::json(array(
                        'success' => true
            ));
        }
    }

    public function updateEventsListBasedOnSelection() {
        $event_type = Input::get('event_type');
        $events = $this->post->getEventsBasedOnChannel(Auth::user()->channel->channel_id, $event_type);
        $html = "";
        foreach ($events as $event) {
            $html .= '<tr id="tr_' . $event->post_id . '">
                    	<td>
                        	<div class="bouncer_list_name">' . $event->title . '</div>
                            <div class="bouncer_list_date">' . date("m.d.Y", $event->event_date) . '</div>
                        </td>
                        <td>
                        	<div class="bouncer_list_image">
                            	<img src="' . S3::getS3Url(S3::getPostHdthumbPath($event->sd_url)) . '" width="148px" class="event-image-poper cursor-pointer" data-event-type = "' . $event->event_type . '" data-id ="' . $event->post_id . '" data-title = "' . $event->title . '" />
                            </div>
                        </td>
                        <td>
                        	<div class="bouncer_tickets_remaining">' . $event->avail_tickets . '</div>
                            <div class="bouncer_total_tickets">' . $event->num_tickets . '</div>
                        </td>
                        <td>
                        	<div class="bouncer_list_expire_date">' . date("m.d.Y", $event->expire_date) . '</div>
                        </td>
                        <td>
							<a href="' . URL::to('/') . '/cms/bouncer/event/' . $event->post_id . '/edit">
								<span class="edit_icon bouncer_list_edit" data-event-type="'.$event->event_type.'" ></span>
							</a>
							<span class="bouncer_list_delete" data-id="'.$event->post_id.'"></span>
 
                        </td>
                    </tr>';
        }
        echo $html;
    }

    public function getEventDetailsBasedOnId() {
        date_default_timezone_set('America/New_York');
        $event_type = Input::get('type');
        $resp = $this->post->getEventDetailsBasedOnId(Input::get('id'));
        $html = "";
        if ($event_type == 1) {
            $html .= '<div id="event-poper-content-vip">
					  	 <img src = "' . S3::getS3Url(S3::getPostSdPath($resp[0]->sd_url)) . '" id = "merch_image" class="vip-pass-event-image img-responsive" />
						 	 <div id="vip-merch-qr-container">
							 	 <img src = "' . S3::getS3Url(S3::getQrImagePath($resp[0]->qr_image)) . '" id = "merch-preview-qrcode" class="event-pass-qr-image img-responsive" />
								 	<div id="vip-merch-description-container"><img src="" id="merch-preview-desc-profile-image" alt=""><div id="merch-preview-desc-title">' . $resp[0]->title . '</div>' . $resp[0]->description . '</div>
                            </div>
                      </div>';
        } else if ($event_type == 0) {
            $html .= '<div id="event-poper-content-merch">
                        	<div id="vip-pass-container">
                                <div id="vip-pass-event-image-overlay"></div>
								 <img src = "/images/access_text_image.png" id = "vip-event-access-pass-image" class="img-responsive" />
								 <img src = "' . S3::getS3Url(S3::getPostSdPath($resp[0]->sd_url)) . '" id = "vip-pass-event-image" class="vip-pass-event-image img-responsive cursor-pointer" />
                                <div id="event_preview_eventname">' . $resp[0]->title . '</div>
                                <div id="event_preview_location_time">' . $resp[0]->location . ' <span>' . date("g:i a", $resp[0]->event_date) . '</span></div>
                                <div id="event_preview_card_date">' . date("m.d.Y", $resp[0]->event_date) . '</div>
								<img src = "' . S3::getS3Url(S3::getQrImagePath($resp[0]->qr_image)) . '" id = "event-pass-qr-image" class="event-pass-qr-image img-responsive cursor-pointer" width="150" height="150" />
                            </div>
                     </div>';
        }

        echo $html;
    }

    public function deleteEvent() {
        $res = self::deletePost(Input::get('id'));
        echo $res;
    }

    public function createNewBouncerAdmin() {
        $validator = Validator::make(Input::all(), Admin::$bouncerRules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();
            $resp = array();
            if ($validator->getMessageBag()->has('email'))
                $resp['email'] = $messages['email'][0];
            if ($validator->getMessageBag()->has('username'))
                $resp['username'] = $messages['username'][0];
            if ($validator->getMessageBag()->has('password'))
                $resp['password'] = $messages['password'][0];
            return Response::json(array(
                        'success' => false,
                        'errors' => $resp
            ));
        }
        else {
            $userData = array(
                'email_id' => Input::get('email'),
                'username' => Input::get('username'),
                'role_id' => '3',
                'status' => '1',
                'password' => md5(Input::get('password')),
                'channel' => Auth::user()->channel->channel_id
            );
            $res = $this->admin->create($userData);
            if ($res)
                return Response::json(array(
                            'success' => true
                ));
        }
    }

    public function updateBouncerUser() {
        $validator = Validator::make(Input::all(), Admin::editRules(Input::get('user_id')));
        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();
            $resp = array();
            if ($validator->getMessageBag()->has('email'))
                $resp['email'] = $messages['email'][0];
            if ($validator->getMessageBag()->has('username'))
                $resp['username'] = $messages['username'][0];
            if ($validator->getMessageBag()->has('password'))
                $resp['password'] = $messages['password'][0];
            Session::flash('error', 'User Updation Failed!');
            return Response::json(array(
                        'success' => false,
                        'errors' => $resp
            ));
        }
        else {
            $password = Input::get('password') == "" ? "" : md5(Input::get('password'));
			$data = array(
                'id' => Input::get('user_id'),
                'email_id' => Input::get('email'),
                'username' => Input::get('username')
            );
			if($password!="") {
				$data['password'] = $password;
			}
            $res = $this->admin->updateBouncerUserDetails($data);
            if ($res) {
                Session::flash('success', 'User Edited Successfully!');
                return Response::json(array('success' => true));
            }
        }
    }

    public function getChannelGridItems() {
        $feeds = $this->post->getPostsByChannelIdForChannellGrid(Input::get('channel_id'), Input::get('offset'), Input::get('order'));
        $html = '';
        foreach ($feeds as $feed) {
			/*$html .= '<div class="vast_channel_grid_left_channel_description">';
			$html .= '<img src="' . S3::getS3Url(S3::getChannelAvatarPath("sd/".$feed->avatar)) . '" class="img-circle" height="42" width="42" />';
			$html .= '<div>';
			$html .= '<span><strong>&bsol;&bsol;</strong>'.$feed->vast_name.' added a new post</span>';
			$html .= '<span>to <strong>&bsol;</strong>'.$feed->chapter_name.'</span>';
			$html .= '<span>'.\Carbon\Carbon::createFromTimeStamp($feed->date)->diffForHumans().'</span>';
			$html .= '</div>';
			$html .= '</div>';*/
            $html .= '<div class="vast_channel_grid_left_channel">';
            $html .= '<img src="' . S3::getS3Url(S3::getPostSdPath($feed->sd_url)) . '" class="vast_channel_grid_left_channel_img" data-type="' . $feed->type . '" data-id="'.$feed->post_id.'" />';
            $html .= '</div>';
        }
        echo $html;
    }
    
    public function getChannelGridPostDetails(){
        $resp = $this->post->getPostById(Input::get('id'));
        $html = '';
        $html .= '<img class="channel_grid_main_image" src="' .S3::getS3Url(S3::getPostSdPath($resp->sd_url)). '" />';
        if($resp->type == "music"){
            $html .= '<div id="channel_grid_audio_container">';
            $html .= '<audio class="hidden-audio-player channelgrid_player" id="channelgrid_audio_player" data-audio-type="0" data-title="'.$resp->title.'" data-artist="'.$resp->author.'" controls>';
            $html .= '<source src="' .S3::getS3Url(S3::getPostMusicPath($resp->music_url)). '" type="audio/mpeg">';
            $html .= 'Your browser does not support the audio element';
            $html .= '</audio></div>';
        }elseif($resp->type == "video"){
            $filename = explode('.',$resp->video_url);
            $html .= '<div id="channel_grid_video_play"></div>';
            $html .= '<div id="channel_grid_player_container">';
            $html .= '<video id="channel_grid_player" class="channelgrid_player" width="320" height="240"  controls >';
            $html .= '<source src="' .S3::getS3Url(S3::getPostVideoPath($filename[0].".webm")). '" type="video/webm">';
            $html .= '<source src="' .S3::getS3Url(S3::getPostVideoPath($filename[0].".mp4")). '" type="video/mp4">';
            $html .= 'Your browser does not support the video tag';
            $html .= '</video></div>';
        }
		/*$html .= '<div id="details_container">';
		$html .= '<img id="artist_avatar_image" src="' . S3::getS3Url(S3::getChannelAvatarPath('sd/'.$this->channel->getAvatarByChannelId($resp->channel_id))) .'" class="img-responsive, img-circle" alt="" height="42" width="42" />';
		$html .= '<div class="feed-descTop-right feed-popup-chp">'.$resp->title.'</div>';
		$html .= $resp->story;
    	$html .= '</div>';*/
        echo $html;
    }

    /* vast v1 */

    public function doLogin() {
        $rules = array(
            'username' => 'required|min:3',
            'password' => 'required|alphaNum|min:3'
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();
            $resp = array();
            /*if ($validator->getMessageBag()->has('username'))
                $resp['username'] = $messages['username'][0];
            if ($validator->getMessageBag()->has('password'))
                $resp['password'] = $messages['password'][0];*/
            if ($validator->getMessageBag()->has('username'))
                $resp['username'] = "This field can't be blank.";
            if ($validator->getMessageBag()->has('password'))
                $resp['password'] = "This field can't be blank.";
            
            return Response::json(array(
                        'success' => false,
                        'errors' => $resp
            ));
        } else {
            $field = filter_var(Input::get('username'), FILTER_VALIDATE_EMAIL) ? 'email_id' : 'username';
            $userData = array(
                $field => Input::get('username'),
                'password' => Input::get('password')
            );

            if (!Auth::attempt($userData)) {
                $loginSuccess = false;
                $field = filter_var(Input::get('username'), FILTER_VALIDATE_EMAIL) ? 'email_id' : 'username';
                $user = Admin::where($field, Input::get('username'))->first();               
                if ($user && $user->password == md5(Input::get('password'))) {
                    $user->password = $hash = Hash::make(Input::get('password'));
                    $user->save();
                    if (Auth::attempt($userData)) {
                        $loginSuccess = true;
                    }
                }
                if (!$loginSuccess) {
                    $resp = array();
                    $resp['username'] = 'Invalid Username';
                    $resp['password'] = 'Invalid Password';
                    return Response::json(array('success' => false, 'errors' => $resp));
                }
				
            }
			else if(Auth::attempt($userData) && Auth::user()->role_id == 4)
			{
				$resp = array();
				$resp['username'] = 'You are not activated yet for login!';
				
				return Response::json(array('success' => false, 'errors' => $resp,'role_id' => 4));
			}
        }
        
        if (Auth::user()->role_id == 2)
            return Response::json(array('success' => true, 'role_id' => 2));
        elseif (Auth::user()->role_id == 3)
            return Response::json(array('success' => true, 'role_id' => 3));
//		elseif (Auth::user()->role_id == 4)
//            return Response::json(array('success' => false, 'role_id' => 4));
        else
            return Response::json(array('success' => true, 'role_id' => 1));
		

    }
	
	 public function livestreamGoLive() {
     	$channelId = Auth::user()->channel->channel_id;
        $channels = $this->channel->getChannelById(Auth::user()->channel->channel_id);
        $channelName = $channels['first_name'] . " " . $channels['last_name'];
		$eventName = $channels['livestream_event'];
        $messagetext = $channels['first_name'] . " " . $channels['last_name'] . " just went live. See what is happening now!.";
        $sound = "default";
        switch (Input::get('subscription')) {
            case '0':
                $users = $this->user->getUsers();
                foreach ($users as $user) {
                    $apnsToken = $this->user->getDeviceToken($user['ID']);
                    if ($apnsToken != "")
                        $this->channel->_sendPushNotificationLive($apnsToken, $messagetext, $channelId, $channelName, $eventName, $sound, $action = '');
                }
                break;
            case '1':
                $users = $this->subscribe->getSubscribersByChannel(Auth::user()->channel->channel_id);
                foreach ($users as $user) {
                    $apnsToken = $this->user->getDeviceToken($user['user_id']);
                    if ($apnsToken != "")
                        $this->channel->_sendPushNotificationLive($apnsToken, $messagetext, $channelId, $channelName, $eventName, $sound, $action = '');
                }
                break;
            default :
                break;
        }

        echo 1;
    }
	
	// post delete
	public function deletePosts($id) {
		Log::info("Deleting post with id: $id");
		try {
		$postsDet = $this->post->getPostsByPostId($id);
		$postsDet = $postsDet[0];
		$chapterDetails = call_user_func_array('array_merge', $this->chapter->getChapterDetailsById($postsDet->chapter_id));
		if($chapterDetails['cover'] == $id){
			$this->chapter->update(array('chapter_id' => $chapterDetails['chapter_id'],'cover' => 0));
		}
		$postCount = count($this->post->getPostsbyChapter($postsDet->chapter_id));
		/*if($postCount == 1){
			$this->chapter->update(array('chapter_id' =>$postsDet->chapter_id,'inactive' => 1 ,'featured' => 0));
		}*/
		$hasMorePost = $postCount == 1 ? 0:1;
		$this->manageexplore->updateEntriesOnPostDeletion($id,$postsDet->chapter_id,Auth::user()->channel->channel_id,$hasMorePost);
		$this->chapter->updateCover($id);
		if($postsDet->audio_url!="")
			S3::delete_file($folderName="audios", $fileName=$postsDet->audio_url);
		if($postsDet->video_url!="")
			S3::delete_file($folderName="videos", $fileName=$postsDet->video_url);
		if($postsDet->second_video!="")
			S3::delete_file($folderName="secvideo", $fileName=$postsDet->second_video);
		if($postsDet->music_url!="")
			S3::delete_file($folderName="music", $fileName=$postsDet->music_url);
		if($postsDet->thumb_url!="")
			S3::delete_file($folderName="postimages/thumb", $fileName=$postsDet->thumb_url);
		if($postsDet->hdthumb_url!="")
			S3::delete_file($folderName="postimages/hdthumb", $fileName=$postsDet->hdthumb_url);
		if($postsDet->retina_url!="")
			S3::delete_file($folderName="postimages/retina", $fileName=$postsDet->retina_url);
		if($postsDet->nonretina_url!="")
			S3::delete_file($folderName="postimages/nonretina", $fileName=$postsDet->nonretina_url);
		if($postsDet->sd_url!="")
			S3::delete_file($folderName="postimages/sd", $fileName=$postsDet->sd_url);

		$this->chapter->updateCover(Input::get('id'));
		$this->post->delete($id, Auth::user()->channel->channel_id);
        $this->updateChannelPostOrderSettings('delete',Auth::user()->channel->channel_id,$postsDet->chapter_id,$id);
        self::createAdvastJson(Auth::user()->channel->channel_id);
		Session::flash('success', 'Post Deleted Successfully!');
		echo 1;
		} catch (\Exception $e) {
			Log::error($e->getMesage());
		}
	}
	
	public function doShareEdit(){    
		   
		if( Input::get('facebook_share') == '1' ) {
		
			$title = Input::get('title');
			if ( $title == '' )
				$title  = 'Vast';
			$title = ( $title =='' ) ? 'Vast' : $title;
			$title  = Str::limit($title, 35);
			
			$fb     = new Facebook();
			if(Input::get('post_type')=='image'){
				if(Input::get('subscription')!="")
					$image = URL::to('/').'/cms/do-water-marking?path='.Input::get('share_image_url').'&type=1&fb=0';
				else
					$image = Input::get('share_image_url');
			}else {
				$image = Input::get('share_image_url');
			}
				
			
			$share_array = array(
                                            'access_token' => Auth::user()->fb_token,
                                            'link' => Input::get('short_url'),
											'caption' => 'VAST - The ultimate fan experience',
											'picture' => $image,
                                            'name' => $title,
                                            'message' => Input::get('story'),
											'scrap' => true
					);       
					
			switch (Input::get('post_type')) {
                            case 'text':
                                        $fbImageToshare = ( Input::get('post_message_image') != '') ? S3::getPostSdPath(Input::get('post_message_image')) : '' ;
                                break;

                            case 'music':

                                        $fbImageToshare = ( Input::get('post_music_image') != '') ? S3::getPostSdPath( Input::get('post_music_image') ) : '' ;
                                break;

                            case 'video':
                            case 'touchcast_video':

                                        $fbImageToshare = ( Input::get('post_video_thumb') != '') ? S3::getPostSdPath( Input::get('post_video_thumb') ) : '' ;
                            break;

                            case 'image':
                                        $fbImageToshare = ( Input::get('post_photo') != '') ? S3::getPostSdPath(Input::get('post_photo')) : '' ;
                                break;

                            default:

                                break;
			}
			/*if($fbImageToshare != ''){
                            $share_array['picture'] = S3::getS3Url($fbImageToshare);
                        }*/
			$response = $fb->shareLink( $share_array, Input::get('fb_page_data'));
			if (!$response || !isset($response['id'])) {
                // echo 'Facebook share failed';
			}				
		}

        /* Sharing To Tumblr */
        if( Input::get('tumblr_share') == '1' ){
                    /* Message Sharing to Tumblr */
                    $shortUrl = Input::get('short_url');
                    switch (Input::get('post_type')) {
                	case 'text':
                            $caption        = Input::get('story');
                            $path           = S3::getPostSdPath(Input::get('post_message_image'));
                            $source_url     = S3::getS3Url($path);
                            $post_data      = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);   
                        break;
                	
                	case 'music':
                            $caption        = Input::get('story').', Song : '. Input::get('song_name') .', Artist : '.Input::get('artist_name');
                            $caption        = ( Input::get('story') == '' )? Input::get('title') : Input::get('story');
                            $path           = S3::getPostMusicPath(Input::get('post_music'));
                            $source_url     = S3::getS3Url($path);

                            $post_data      = array('type' => 'audio', 'caption' => '<a href="'.$shortUrl.'">'.$caption.'<a>', 'external_url' => $source_url);    
                	break;
                	
                	case 'video':
                            $caption        = Input::get('story');
                            $path           = S3::getPostVideoPath(Input::get('post_video'));
                            $source_url     = S3::getS3Url($path);
                            $embed          = ' <video controls> 
                                                    <source src="'.$source_url .'"></source> 
                                                </video>';
                            $post_data = array('type' => 'video', 'caption' => '<a href="'.$shortUrl.'">'.$caption.'<a>', 'embed' => $embed);
    
                        break;
                	
                	case 'image':
                            $caption        = Input::get('story');
                            $path           = S3::getPostRetinaPath(Input::get('post_photo'));
                            $source_url     = S3::getS3Url($path);
                            $post_data      = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $shortUrl);
                       break;
                	
                	case 'touchcast_video':
                            $caption        = Input::get('story');
                            $source_url     = Input::get('touchcast_url');
                            $embed          = ' <video controls> 
                                                    <source src="'.$source_url .'"></source> 
                                                </video>';
                            $post_data = array('type' => 'video', 'caption' => '<a href="'.$shortUrl.'">'.$caption.'<a>', 'embed' => $embed);
                        break;
                	
                	default:
                		
                        break;
                    
                    }
                    
                    if ($post_data) {
                        $token          = Auth::user()->tumblr_token;
                        $tokenSecret    = Auth::user()->tumblr_oauth_token_secret;
                        $blog_name      = Auth::user()->tumblr_username.'.tumblr.com';  
                        $tumblr         = new Tumblr($token, $tokenSecret);
                        $response       = $tumblr->createPost($blog_name, $post_data);       

                        if (!$response) {
                           	//fail
						}
                    }    
		}
				
		/* Sharing To Twitter */
		if( Input::get('twiiter_share') != '0' ) {
                        $title = Input::get('story');
                        if (Input::get('type') == 'text')
                                $title = Input::get('story');

                        $title = Str::limit($title, 135 - 18 - 3);

                        $twitter = new Twitter(Auth::user()->twitter_token, Auth::user()->twitter_token_secret);
                        $response = $twitter->tweet($title . ' ' . Input::get('short_url'));

                        if (!$response) {
	                        // fail
						}
		}
		if (!$response) {
			Session::flash('error', 'Post Sharing Failed!');
			echo 0;
		} else {
			Session::flash('success', 'Post Shared Successfully!');
			echo 1;
		}
         
	}
	
	public function generateChannelGrid(){
		
		/*$res = $this->channelgrid->create(array(
        	'channel_id' => Input::get('channel_id'),
            'framesize' => Input::get('framesize'),
			'default_video' => Input::get('videoUrl'),
			'poster' => Input::get('poster'),
			'channelgrid_url' =>  URL::to('/'),
			'chapter' => Input::get('chapter'), 
        ));
		echo $res;*/
		
		/*$res =  self::select($data['channel_id']);
		$channel_grid_id = 'channelgrid'.$data['channel_id'].mt_rand();	
		$framesize = explode('_',$data['framesize']);
		$iframecode =  $previewcode = $channelgrid_url = "";
		if(empty($res)){	
			$channelgrid_url = $data['channelgrid_url'].'/cms/channelgrid/channel/'.$channel_grid_id;	
			$iframecode = '<iframe width="'.$framesize[0].'" height="'.$framesize[1].'" src="'.$channelgrid_url.'" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" frameborder=0></iframe>';
			$previewcode = '<iframe id="myframe" width="'.$framesize[0].'" height="'.$framesize[1].'" src="'.$channelgrid_url.'" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" frameborder=0></iframe>';	
			$this->channelgrid->channel_id = $data['channel_id'];
			$this->channelgrid->frame_code = $iframecode;
			$this->channelgrid->channel_grid_id = $channel_grid_id;			
			$this->channelgrid->default_video = $data['default_video'];
			$this->channelgrid->poster = $data['poster'];
			$this->channelgrid->chapter_id = $data['chapter'];
			$this->channelgrid->save();
		}else{
			$channelgrid_url = $data['channelgrid_url'].'/cms/channelgrid/channel/'.$res['channel_grid_id'];
			$channelgrid = $this->channelgrid->find($res['grid_id']);
			$iframecode = '<iframe width="'.$framesize[0].'" height="'.$framesize[1].'" src="'.$channelgrid_url.'" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" frameborder=0></iframe>';
			$previewcode = '<iframe id="myframe" width="'.$framesize[0].'" height="'.$framesize[1].'" src="'.$channelgrid_url.'" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" frameborder=0></iframe>';		
			$channelgrid->fill(array(
				'frame_code' => $iframecode,
				'channel_grid_id' => $res['channel_grid_id'],
				'default_video' => $data['default_video'],
				'poster' => $data['poster'],
				'chapter_id' => $data['chapter']
			));
			$channelgrid->save();			
		}	*/
		$channel_id = Input::get('channel_id');
		$framesize =  explode('_',Input::get('framesize'));
		if(Input::get('videoUrl')!=""){
			$default_video = explode('.',Input::get('videoUrl'));
			$default_video = $default_video[0];
		}else
			$default_video = 0;
		$poster = Input::get('poster') == "" ? 0 : Input::get('poster');
		$channelgrid_url =  URL::to('/');
		$chapter = Input::get('chapter') == "" ? 0 : Input::get('chapter'); 		
		$channelgrid_url = $channelgrid_url.'/cms/gridview/'.$channel_id.'/'.$default_video.'/'.$chapter.'/'.$poster;	
		$iframecode = '<iframe width="'.$framesize[0].'" height="'.$framesize[1].'" src="'.$channelgrid_url.'" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" frameborder=0></iframe>';
		$previewcode = '<iframe id="myframe" width="'.$framesize[0].'" height="'.$framesize[1].'" src="'.$channelgrid_url.'" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" frameborder=0></iframe>';	
		$arr = array('iframecode' => $iframecode, 'previewcode' => $previewcode, 'iframewidth' => $framesize[0], 'iframeheight' => $framesize[1]);
		$data = json_encode($arr);
        return $data;
	}
	
	public function getVideoPostsByChannel(){
		$html = "";
		$videoPosts = $this->post->getVideoPostsByChannel(Input::get('channel_id'));
		foreach($videoPosts as $videoPost)
			$html .= "<div class='channelgrid_videopost_thumbs'><img src='".S3::getS3Url(S3::getPostHdthumbPath($videoPost['thumb_url']))."' data-chapter='".$videoPost['chapter_id']."' data-poster='".$videoPost['thumb_url']."' data-id='".$videoPost['post_id']."' data-video-url='".$videoPost['video_url']."'  /></div>";
		echo $html;
	}
	
    /*public function channelGridManipulations(){
		$chapter = Input::get('chapter');
		$postId = Input::get('post');
		$posts = $this->post->getPostsbyChapter($chapter);
		$resp = $thumbResp = $html = $secVideo = '';
		$respContainer = '<div class="slider gridSlider">';
		$thumbRespConatiner = '<img src="'.URL::to('images/showHideStripButton@2x.png') .'" id="thumbnailToggler" width="80" /><div class="slider" id="gridThumbSlider">';
		foreach($posts as $post){
			if($post->second_video !=""){
				$secVideo = explode('.',$post->second_video);
				$secVideo = $secVideo[0];
			}
			if($post->type == "image"){
				$html = '<div class="slide_div"> <img data-lazy= "'. S3::getS3Url(S3::getPostSdPath($post->sd_url)) .'" /><input type="hidden" class="currentSlideDetails" data-secvideo="'.$secVideo.'" data-secaudio="'.$post->audio_url.'" data-short-url="'.$post->short_url.'" data-post-name="'.$post->title.'" value="'.$post->story.'" /> </div>';
			}elseif($post->type == "music"){
				$html = '<div class="slide_div"> <img  data-lazy="'. S3::getS3Url(S3::getPostSdPath($post->sd_url)) .'" /><input type="hidden" class="currentSlideDetails" data-secvideo="'.$secVideo.'" data-secaudio="'.$post->audio_url.'" data-short-url="'.$post->short_url.'" data-post-name="'.$post->title.'" value="'.$post->story.'" /> </div>';
			}elseif($post->type == "video"){
				if($post->video_url!=""){
					$video =  explode('.',$post->video_url);
					$poster = $post->sd_url;
					$vtype = $post->post_id == $postId ? 1 : 0;
					$html = '<div class="slide_div"><iframe class="videoPlayerIframe" src="'.URL::to('/').'/cms/videoplayer/'.$video[0].'/'.$vtype.'/'.$poster.'"></iframe><input type="hidden" data-secvideo="'.$secVideo.'" data-secaudio="'.$post->audio_url.'" data-short-url="'.$post->short_url.'" class="currentSlideDetails" data-post-name="'.$post->title.'" value="'.$post->story.'" /></div>';
				}
			}
			if($post->post_id == $postId){
				$resp = $html.$resp;
				$thumbResp = '<div class="thumb_slide"><img  src="'. S3::getS3Url(S3::getPostPhotoPath($post->sd_url)) .'" /></div>'.$thumbResp;	
			}else{
				$resp .= $html;
				$thumbResp .= '<div class="thumb_slide"><img  src="'. S3::getS3Url(S3::getPostPhotoPath($post->sd_url)) .'" /></div>';	
			}			
		}
		$resp = $respContainer.$resp.'</div>';
		$thumbResp = $thumbRespConatiner.$thumbResp.'</div>';
		$respData = array();
		$respData['sliderData'] = $resp;
		$respData['thumbdata'] = $thumbResp;
		$response = json_encode($respData);
		echo $response;
	}*/
	
	public function doImageCropping() {
        $poix = Input::get('poix');
        $poiy = Input::get('poiy');
        $img = S3::getS3Url(S3::getPostSdPath(Input::get('image')));
        return $this->post->createCroppedImage($img, $poix, $poiy);
    }
	
	/*public function getAdvastBannerJson($channelid) {
   		$manageExplore =  $this->manageexplore->getRowsByChannel($channelid);
		$postList = explode(',',$manageExplore['non_featured_post_list']);
		$chapterList = explode(',',$manageExplore['non_featured_list']);
		$featuredPosts =  explode(',',$manageExplore['featured_list']);	
		$postCount = count(array_filter($postList));
		$chapterCount = count(array_filter($chapterList));
		$chapterid = 0;
		if($postCount < 8){
			for($i = $postCount ;$i<8;$i++){
				$postList[$i] = 0;
			}
			for($i = $chapterCount ;$i<8;$i++){
				$chapterList[$i] = 0;
			}
			
			$chapterPostsDetails = $this->chapter->getGridChapterPostDetails($channelid);	
			
			$manualPosts = $manualChapters = array();
			for($i = 0; $i < count($chapterPostsDetails); $i++){
				$manualPosts[] = $chapterPostsDetails[$i]->post_id;
				$manualChapters[] = $chapterPostsDetails[$i]->chapter_id;	
			}
			$cnt = count($manualPosts) > 8 ? 8 : count($manualPosts);
			for($i = 0; $i < $cnt; $i++){
				if($postList[$i] == 0){
					if($chapterCount[$i] == 0){
						for($j = 0; $j < 8; $j++){
							if(!in_array($manualPosts[$j], $postList)){
    							$postList[$i] = $manualPosts[$j];	
							}
						}
					}
					else{
						if(!in_array($manualPosts[$i], $postList)){
    						$postList[$i] = $manualPosts[$i];	
						}					
					}
				}
			}
		}	
			
		$gridPostChannelDetails = $this->post->getGridPostAndChapterDetails($postList);
		$thumbDetails = $adBannerDetails = array();
		foreach($gridPostChannelDetails as $gridPostChannelDetail){			
			$detArray = ["chapter" => $gridPostChannelDetail['chapter_name'],'chapter_id' => $gridPostChannelDetail['chapter_id'],'post' =>  $gridPostChannelDetail['post_id'],"img" =>  '<div class="grid_channel_element_thumbname"><div><span class="coloredVastFirstSlash">\</span>'.$gridPostChannelDetail['chapter_name'].'</div></div><div data-poix="'.$gridPostChannelDetail['poix'].'" data-poiy="'.$gridPostChannelDetail['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostHdthumbPath($gridPostChannelDetail['sd_url'])).');" class="grid_chapter_element" data-chapter-id = "'.$gridPostChannelDetail['chapter_id'].'" data-img-width = "'.$gridPostChannelDetail['hdthumb_width'].'" data-img-height="'.$gridPostChannelDetail['hdthumb_height'].'" data-post="'.$gridPostChannelDetail['post_id'].'"></div>'];
	
			$adBannerArray = ['src' =>S3::getS3Url(S3::getPostHdthumbPath($gridPostChannelDetail['sd_url'])),'poix'=>$gridPostChannelDetail['poix'],'poiy'=>$gridPostChannelDetail['poiy'],'imgW' =>$gridPostChannelDetail['hdthumb_width'],'imgH' => $gridPostChannelDetail['hdthumb_height'],'chapter' => $gridPostChannelDetail['chapter_id'],'post' => $gridPostChannelDetail['post_id']];
			if($chapterid == $gridPostChannelDetail['chapter_id']){
				array_unshift($thumbDetails,$detArray);
				array_unshift($adBannerDetails,$adBannerArray);				
			}else{
				array_push($thumbDetails,$detArray);
				array_push($adBannerDetails,$adBannerArray);				
			}
					
	
		}		
		$selectedChapter = $chapterid == 0 ? $chapterList[0] : $chapterid;		
		$featuredPostCount = count(array_filter($featuredPosts));
		if($featuredPostCount < 5){
			$featuredPosts = array_filter($featuredPosts); 
			$defaultPostsLists = $this->post->getLastFivePostBasedOnChannel($channelid);
			for($i = 0;$i < 5;$i++){
				$featuredPosts[] = $defaultPostsLists[$i]['post_id'];
			}
			$featuredPosts = array_slice(array_unique($featuredPosts), 0, 5);
		}
		$gridMainSliderDetails = $this->post->getGridPostAndChapterDetails($featuredPosts);
		$resp = '';

		$resp .= '<div class="slider" id="mainAppSlider">';
		foreach($gridMainSliderDetails as $post){
			$resp .= '<div class="slide_div"><img  data-lazy="'. S3::getS3Url(S3::getPostSdPath($post['sd_url'])) .'" /><div class="mainAppPostTitle hidden-xs">'.$post['title'].'</div></div>';
		}		
		$resp .= '</div>';
		$twts = "";
		$twitter = new Twitter();
		$oauthToken   = $twitter->login();  
		$connection = new TwitterOAuth(Config::get('services.advast.consumerKey'), Config::get('services.advast.consumerSecret'), Config::get('services.advast.accessToken'), Config::get('services.advast.accessTokenSecret'));
		$twitteruser =  substr($gridPostChannelDetails[0]['twitter_url'], strrpos($gridPostChannelDetails[0]['twitter_url'], '/') + 1);
		$tweets = $connection->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=".$twitteruser."&count=20&token=".$oauthToken['oauth_token']);
		$response = json_encode($tweets);
		$twts .= "<ul>";
		foreach($tweets as $tweet){
			$twts .= "<li>".$tweet->text."</li>";
		}
		$twts .="</ul>";		
		return array(
		 	'sliderData' => $resp,
			'thumbdata' => '',
			'adBannerDetails' => $adBannerDetails,
		 	'thumbDetails' => $thumbDetails,
			'defaultVideoName' => '',
			'channelname' => $gridPostChannelDetails[0]['first_name']." ".$gridPostChannelDetails[0]['last_name'],
			'avatar' => S3::getS3Url(S3::getChannelAvatarPath('thumb/'.$gridPostChannelDetails[0]['avatar'])),
			'fb_url' => $gridPostChannelDetails[0]['fb_url'],
			'twitter_url' => $gridPostChannelDetails[0]['twitter_url'],
			'youtube_url' => $gridPostChannelDetails[0]['youtube_url'] != "" ? $gridPostChannelDetails[0]['youtube_url'] : "#",
			'instagram_url' => $gridPostChannelDetails[0]['instagram_url'] != "" ? $gridPostChannelDetails[0]['instagram_url'] : "#",
			'story' => '',
			'post-name' => '',
			'secVideo' => '',
			'secAudio' => '',
			'st' => 0,
			'fbSocialShare' => 'https://www.facebook.com/sharer/sharer.php?u='.$post['sd_url'],
			'twSocialShare' => 'https://twitter.com/intent/tweet?text=Check My Vast&url='.$post['sd_url'].'&via=GetVASTnow',
			'tweets' => $twts		
		 );		

    }*/
    public function getAdvastBlastArray($channelid,$firstFeatured,$chapterArray){
        $res = $this->chapter->getChaptersByChannelId($channelid);  
        $flag = 0;
        $bestOf = $detArray = $bestOfChptr =  array(); 
        foreach ($res as $chapter) {
            $chapterName = strtolower(preg_replace('/[^A-Za-z0-9]/', '', str_replace(' ', '-', $chapter->chapter_name))); 
            if($chapterName == 'bestof' || $chapterName == 'featured'){
                $flag = 1;
                $resp = $this->post->getPostsbyChapter($chapter->chapter_id);
                $bestOfDetails = self::channelGridManipulations($chapter->chapter_id,$firstFeatured);
                $bestOf = array(
                    'chapter' => $chapter->chapter_name,
                    'chapter_id' => $chapter->chapter_id,
                    'posttype' => $bestOfDetails[0]['posttype'],
                    'video' => $bestOfDetails[0]['video'],
                    'post' =>  $bestOfDetails[0]['post_id'],
                    'imgsrc' => $bestOfDetails[0]['img'],
                    'poix' => $bestOfDetails[0]['poix'],
                    'poiy' => $bestOfDetails[0]['poiy'],
                    'imgw' => $bestOfDetails[0]['sd_w'],
                    'imgh' => $bestOfDetails[0]['sd_h'],
                    'postData' => $bestOfDetails
                );
                array_unshift($chapterArray, $bestOf);
                $chapterArray = array_slice($chapterArray,0,5);

            }
        }        
        return $chapterArray;
    }
	
	public function getAdvastBannerJson($channelid) {
        $allChapterDetailsArray = $this->getAdvastAllChapterLists($channelid);
		if(empty($allChapterDetailsArray)){
			return array(
				'sliderData' => '',            
		 	'thumbDetails' => '',
			'defaultVideoName' => '',
			'featured_youtube' => '',
			'channelname' => '',
			'avatar' => '',
			'fb_url' =>  '',
			'twitter_url' => '',
			'youtube_url' => '',
			'instagram_url' => '',
            'tumblr_url' => '',
			'story' => '',
			'post-name' => '',
			'secVideo' => '',
			'secAudio' => '',
			'st' => 0,
			'fbSocialShare' => '',
			'twSocialShare' => '',
			'tweets' => '',
            'chapters' => '',
            'advastBlast'  => ''	
		 );	
		}
   		$manageExplore =  $this->manageexplore->getRowsByChannel($channelid);
		$chapterList = array_filter(explode(',',$manageExplore['non_featured_list']));
		$featuredPosts =  explode(',',$manageExplore['featured_list']);	
		$chapterCount = count(array_filter($chapterList));
		$chapterid = 0;
		$firstFeaturedPost = $featuredPosts[0];
		
		$allChapterArray = $this->chapter->getAllChapterIdsByChannel($channelid);	
		$chapterList = array_merge($chapterList,array_diff($allChapterArray,$chapterList));
		if($chapterCount > 0 && $chapterCount <= 8){
			$chapterList = array_slice($chapterList, 0, 8);  
		}elseif($chapterCount > 8){
			$chapterList = array_slice($chapterList, 0, $chapterCount);  
		}elseif($chapterCount == 0){
			$chapterList = array_slice($chapterList, 0, count($allChapterArray));  
		}
		$postList = $this->getCoverOrLastestPostBasedOnChapterIds($chapterList);
		$gridPostChannelDetails = $this->post->getGridPostAndChapterDetails(explode(',',$postList));
		$channelDetails  = $this->channel->getChannelById($channelid);
		$thumbDetails = array();
		foreach($gridPostChannelDetails as $gridPostChannelDetail){		
			$thumbData = self::channelGridManipulations($gridPostChannelDetail['chapter_id'],$gridPostChannelDetail['post_id']);
			if($gridPostChannelDetail['chapter_name'] != "VIP"){
				$detArray = array(
					'chapter' => $gridPostChannelDetail['chapter_name'],
					'chapter_id' => $gridPostChannelDetail['chapter_id'],
					'post' =>  $gridPostChannelDetail['post_id'],
					'imgsrc' => S3::getS3UrlForJson(S3::getPostHdthumbPath($gridPostChannelDetail['sd_url'])),
					'poix' => $gridPostChannelDetail['poix'],
					'poiy' => $gridPostChannelDetail['poiy'],
					'imgw' => $gridPostChannelDetail['hdthumb_width'],
					'imgh' => $gridPostChannelDetail['hdthumb_height'],
					'postData' => $thumbData
				);
				/*if($chapterid == $gridPostChannelDetail['chapter_id']){
					array_unshift($thumbDetails,$detArray);				
				}else{
					array_push($thumbDetails,$detArray);		
				}*/
				array_push($thumbDetails,$detArray);
			}	
		}		
		$selectedChapter = $chapterid == 0 ? $chapterList[0] : $chapterid;		
		/* Commented for making featured images same as in manageexplore


        $featuredPostCount = count(array_filter($featuredPosts));
		if($featuredPostCount < 5){
			$featuredPosts = array_filter($featuredPosts); 
			$defaultPostsLists = $this->post->getLastFivePostBasedOnChannel($channelid);
			for($i = 0;$i < count($defaultPostsLists);$i++){
				$featuredPosts[] = $defaultPostsLists[$i]['post_id'];
			}
			$featuredPosts = array_slice(array_unique($featuredPosts), 0, 5);
		}  */   

        $featuredArrays = $featuredPosts;
        $defaultFeatured  = $this->post->getLastFivePostBasedOnChannel($channelid);
        if(!count(array_filter($featuredPosts))){
            $featuredArrays =  array(0,0,0,0,0);
        }
        foreach ($featuredArrays as $featuredArray) {
            if ($featuredArray == '0') {
                foreach ($defaultFeatured as $lastPostItems) {
                    if (isset($lastPostItems->post_id) != "") {
                        if (!in_array($lastPostItems->post_id, $featuredArrays)) {
                            $key = array_search(0, $featuredArrays);
                            $featuredArrays[$key] = $lastPostItems->post_id;
                        } else
                            continue;
                    } else
                        array_push($featured, 0);
                    break;
                }
            } 
        }
        $arrCount = count($featuredArrays);
        while ($arrCount < 5) {
            array_push($featuredArrays, 0);
            $arrCount++;
        } 
        $featuredPosts = $featuredArrays;
        $featuredPosts = array_filter($featuredPosts); 
		$gridMainSliderDetails = $this->post->getGridPostAndChapterDetails($featuredPosts);
		$sliderImgDets = array();
		foreach($gridMainSliderDetails as $post){
            $videoName = explode('.',$post['video_url']);
		    $imgDets = array(
                'type'          => $post['type'],
                'post_id'       => $post['post_id'],
                'chapter_id'    => $post['chapter_id'],
                'video'         => $videoName[0],
				'imgUrl'        => S3::getS3UrlForJson(S3::getPostHdthumbPath($post['sd_url'])),
                'imgW'        =>  $post['hdthumb_width'],
                'imgH'        => $post['hdthumb_height'],
                'poix'  =>          $post['poix'],
                'poiy'  =>          $post['poiy'],
				'title'         => $post['title'],
                'description'   => $post['story'],
                'short_url'     => $post['short_url'],
                'youtube_url'   => $post['youtube'],
				'hash_url'      => URL::to('cms/post',$post['post_hash'])
			);
			array_push($sliderImgDets,$imgDets);
    		}	

        $featuredPostDetails  = $this->post->getGridPostAndChapterDetails(array($sliderImgDets[0]['post_id']));
		$featuredThumbData    = self::channelGridManipulations($sliderImgDets[0]['chapter_id'],$sliderImgDets[0]['post_id']);        
        $featuredMainContent = array(
            'chapter'       => $featuredPostDetails[0]['chapter_name'],
            'chapter_id'    => $featuredPostDetails[0]['chapter_id'],
            'post'          => $featuredPostDetails[0]['post_id'],
            'imgsrc'        => S3::getS3UrlForJson(S3::getPostHdthumbPath($featuredPostDetails[0]['sd_url'])),
            'poix'          => $featuredPostDetails[0]['poix'],
            'poiy'          => $featuredPostDetails[0]['poiy'],
            'imgw'          => $featuredPostDetails[0]['hdthumb_width'],
            'imgh'          => $featuredPostDetails[0]['hdthumb_height'],
            'postData'      => $featuredThumbData
        );
		$resp = array(
			'id' => 'mainAppSlider',
			'imgDetails' => $sliderImgDets,
            'featuredChapter' => $featuredMainContent		
		);
       
		$twts = "";
		$twitter = new Twitter();
		$oauthToken   = $twitter->login();  
		$connection = new TwitterOAuth(Config::get('services.advast.consumerKey'), Config::get('services.advast.consumerSecret'), Config::get('services.advast.accessToken'), Config::get('services.advast.accessTokenSecret'));
		$twitteruser =  substr($gridPostChannelDetails[0]['twitter_url'], strrpos($gridPostChannelDetails[0]['twitter_url'], '/') + 1);
		$tweets = $connection->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=".$twitteruser."&count=20&token=".$oauthToken['oauth_token']);
		$response = json_encode($tweets);
		$twts .= "<ul>";
		if(count($tweets)){
			foreach($tweets as $tweet){
				$twts .= "<li>".$tweet->text."</li>";
			}
		}
		$twts .="</ul>";	
        $advastBlast = $this->getAdvastBlastArray($channelid,$firstFeaturedPost,array_slice($thumbDetails,0,5));
		$channelName = ($channelid == 96 || $channelid == 178) ?   $gridPostChannelDetails[0]['first_name']." ".$gridPostChannelDetails[0]['last_name'] :  $gridPostChannelDetails[0]['vast_name'];
		
		return array(
		 	'sliderData' => $resp,            
		 	'thumbDetails' => $thumbDetails,
			'defaultVideoName' => '',
			'featured_youtube' => $channelDetails->featured_youtube_video,
			'channelname' => $channelName,
			'avatar' => S3::getS3UrlForJson(S3::getChannelAvatarPath('thumb/'.$gridPostChannelDetails[0]['avatar'])),
			'fb_url' =>  $gridPostChannelDetails[0]['fb_url'] != "" ? $gridPostChannelDetails[0]['fb_url'] : "javascript:void(0)",
			'twitter_url' => $gridPostChannelDetails[0]['twitter_url'] != "" ? $gridPostChannelDetails[0]['twitter_url'] : "javascript:void(0)",
			'youtube_url' => $gridPostChannelDetails[0]['youtube_url'] != "" ? $gridPostChannelDetails[0]['youtube_url'] : "javascript:void(0)",
			'instagram_url' => $gridPostChannelDetails[0]['instagram_url'] != "" ? $gridPostChannelDetails[0]['instagram_url'] : "javascript:void(0)",
            'tumblr_url' => $gridPostChannelDetails[0]['tumblr_url'] != "" ? $gridPostChannelDetails[0]['tumblr_url'] : "javascript:void(0)",
			'story' => '',
			'post-name' => '',
			'secVideo' => '',
			'secAudio' => '',
			'st' => 0,
			'fbSocialShare' => 'https://www.facebook.com/sharer/sharer.php?u='.$post['short_url'],
			'twSocialShare' => 'https://twitter.com/intent/tweet?text=Check My Vast&url='.$post['short_url'].'&via=GetVASTnow',
			'tweets' => $twts,
            'chapters' => $allChapterDetailsArray,
            'advastBlast'  => $advastBlast	
		 );	

    }
	
	public function getCoverOrLastestPostBasedOnChapterIds($chapterLists){
		$chapterDetails = $this->chapter->getChapterDetailsFromList($chapterLists);
		$covers = array_column($chapterDetails, 'cover');
		$postLists = array();
		$i=0;
		foreach($covers as $cover){
			if($cover == 0){
				$lastPost = $this->post->getLastPostByChapter($chapterLists[$i]);
				if(isset($lastPost->post_id)){
					array_push($postLists,$lastPost->post_id);
				}else{
					array_push($postLists,0);
				}
			}else{
				array_push($postLists,$cover);
			}
			$i++;
		}
		return implode(',',$postLists);
	}


    public function getAdvastAllChapterLists($channelid) {        
        $manageExplore =  $this->manageexplore->getRowsByChannel($channelid);
        $nonFeaturedPostArray = explode(',',$manageExplore['non_featured_list']);
		$chapterLists = array_filter($nonFeaturedPostArray);
		$nonFeaturedCount = count($chapterLists);
		$allChapterArray = $this->chapter->getAllChapterIdsByChannel($channelid);	
		$chapterLists = array_merge($chapterLists,array_diff($allChapterArray,$chapterLists));
		if($nonFeaturedCount > 0 && $nonFeaturedCount <= 8){
			$chapterLists = array_slice($chapterLists, 0, 8);  
		}elseif($nonFeaturedCount > 8){
			$chapterLists = array_slice($chapterLists, 0, $nonFeaturedCount);  
		}elseif($nonFeaturedCount == 0){
			$chapterLists = array_slice($chapterLists, 0, count($allChapterArray));  
		}
        $postList = $this->getCoverOrLastestPostBasedOnChapterIds($chapterLists);
		$gridPostChannelDetails = $this->post->getGridPostAndChapterDetails(explode(',',$postList));
		$allChapterDetailsArray = array();
		if(!empty($gridPostChannelDetails)){
			
			foreach($gridPostChannelDetails as $gridPostChannelDetail){     
				$thumbData = self::channelGridManipulations($gridPostChannelDetail['chapter_id'],$gridPostChannelDetail['post_id']);
				//if($gridPostChannelDetail['chapter_name'] != "VIP"){
					$chapterid = $gridPostChannelDetail['chapter_id'];
					 $isBestOf  = strtolower(preg_replace('/[^A-Za-z0-9]/', '', str_replace(' ', '-', $gridPostChannelDetail['chapter_name']))) == 'bestof' ? 1 : 0; 
					$allChapterDetailsArray[$chapterid] = array(
						'chapter' => $gridPostChannelDetail['chapter_name'],
						'chapter_id' => $gridPostChannelDetail['chapter_id'],
						'isBestOf' => $isBestOf,
						'post' =>  $gridPostChannelDetail['post_id'],
						'imgsrc' => S3::getS3Url(S3::getPostHdthumbPath($gridPostChannelDetail['sd_url'])),
						'poix' => $gridPostChannelDetail['poix'],
						'poiy' => $gridPostChannelDetail['poiy'],
						'imgw' => $gridPostChannelDetail['hdthumb_width'],
						'imgh' => $gridPostChannelDetail['hdthumb_height'],
						'postData' => $thumbData
					);
				//}   
			} 
		}
		return $allChapterDetailsArray;     
		
    }
	
	
	public function channelGridManipulations($chapter,$postId){
		$chapter_details = call_user_func_array('array_merge', $this->chapter->getChapterDetailsById($chapter));
		$posts = $this->post->getPostsbyChapterOrderByPostOrder($chapter,explode(',',$chapter_details['post_order']));
		$resp = $postDet = array();
		$secVideo = '';
		foreach($posts as $post){
			if($post->second_video !=""){
				$secVideo = explode('.',$post->second_video);
				$secVideo = $secVideo[0];
			}
			switch($post->type){
				case 'image':
					$postDet = array(
						'posttype' => 'image',
                        'post_id' => $post->post_id,
						'video' => '',
						'secvideo' => $secVideo,
						'audio' => '',
						'secaudio' => $post->audio_url,
						'shorturl' => $post->short_url,
						'hash_url'      => URL::to('cms/post',$post->post_hash),
						'postname' => $post->title,
						'story' => $post->story,
						'img' => S3::getS3UrlForJson(S3::getPostSdPath($post->sd_url)),
                        'poix' => $post->poix,
                        'poiy' => $post->poiy,
                        'sd_w'  => $post->sd_width,
                        'sd_h'  => $post->sd_height
					);
				break;
				
				case 'music':
					$postDet = array(
						'posttype' => 'music',
                        'post_id' => $post->post_id,
						'video' => '',
						'secvideo' => $secVideo,
						'audio' => $post->music_url,
						'secaudio' => '',
						'shorturl' => $post->short_url,
						'hash_url'      => URL::to('cms/post',$post->post_hash),
						'postname' => $post->title,
						'story' => $post->story,
						'img' => S3::getS3UrlForJson(S3::getPostSdPath($post->sd_url)),
                        'poix' => $post->poix,
                        'poiy' => $post->poiy,
                        'sd_w'  => $post->sd_width,
                        'sd_h'  => $post->sd_height
					);
				break;
				
				case 'video':
					$video =  explode('.',$post->video_url);
					$postDet = array(
						'posttype' => 'video',
                        'post_id' => $post->post_id,
						'video' => $video[0],
						'secvideo' => $secVideo,
						'audio' => '',
						'secaudio' => $post->audio_url,
						'shorturl' => $post->short_url,
						'hash_url'      => URL::to('cms/post',$post->post_hash),
						'postname' => $post->title,
						'story' => $post->story,
						'type' => $post->post_id == $postId ? 1 : 0,
						'img' => S3::getS3UrlForJson(S3::getPostSdPath($post->sd_url)),
                        'youtube_url' => $post->youtube,
                        'poix' => $post->poix,
                        'poiy' => $post->poiy,
                        'sd_w'  => $post->sd_width,
                        'sd_h'  => $post->sd_height
					);
				break;
				
			}
			/*if($post->post_id == $postId){
				array_unshift($resp,$postDet);
			}else{
				array_push($resp,$postDet);
			}*/
			array_push($resp,$postDet);
			
		
		}
		return $resp;
	}

    
	public function createAdvastJson($channelId){
		$jsonData = self::getAdvastBannerJson($channelId);
		$jsonUrl = S3::createAdvastBannerJson($jsonData,$channelId);
	}
	
	public function replaceEmptyPostWithLatestFromChapters($postList,$chapterList){
		for($i=0;$i<count($postList);$i++){
			if($postList[$i] == 0){
				if($chapterList[$i] != 0){
					$lastPost = $this->post->getLastPostByChapter($chapterList[$i]);
					if(isset($lastPost->post_id))
						$postList[$i] = $lastPost->post_id;
				}
			}
		}
		return $postList;
	}
	
	public function createAdvastNetworkJson($networkId){
		$jsonData = self::getAdvastNetworkBannerJson($networkId);
		$jsonUrl = S3::createAdvastNetworkBannerJson($jsonData,$networkId);
	}
	
	public function getAdvastNetworkBannerJson($networkId) {
   		$manageExplore =  $this->manageexplore->getNetworkById($networkId);
		$channelLists = explode(',',$manageExplore['non_featured_list']);
		$featuredPosts =  explode(',',$manageExplore['featured_list']);	
		$channelDets = $this->channel->getChannelDetailsForBanner($channelLists);
		$thumbDetails = array();
		foreach($channelDets as $channelDet){		
			$postsDets = $this->post->getLastFivePostBasedOnChannel($channelDet['channel_id']);
			$thumbData = self::arrangePostsForAdVastNetwork($postsDets);
			$detArray = array(
				'chapter' 	 => $channelDet['first_name'].' '.$channelDet['last_name'],
				'chapter_id' => $channelDet['channel_id'],
				'post' 		 =>  0,
				'imgsrc' => S3::getS3Url(S3::getChannelAvatarPath('hdthumb/'.$channelDet['avatar'])),
				'poix' => 0.5,
				'poiy' => 0.2,
				'imgw' => $channelDet['hdthumbW'],
				'imgh' => $channelDet['hdthumbH'],
				'postData' => $thumbData
			);
			array_push($thumbDetails,$detArray);
		}
		
		$gridMainSliderDetails = $this->post->getGridPostAndChapterDetails($featuredPosts);
		$sliderImgDets = array();
		foreach($gridMainSliderDetails as $post){
			$imgDets = array(
				'imgUrl' =>S3::getS3Url(S3::getPostSdPath($post['sd_url'])),
				'title' => $post['title']
			);
			array_push($sliderImgDets,$imgDets);
		}	
		
		$resp = array(
			'id' => 'mainAppSlider',
			'imgDetails' => $sliderImgDets
		
		);
		
		return array(
		 	'sliderData' => $resp,
		 	'thumbDetails' => $thumbDetails,
			'defaultVideoName' => '',
			'channelname' => $manageExplore['screen_name'],
			'avatar' => '',
			'fb_url' =>  "javascript:void(0)",
			'twitter_url' => "javascript:void(0)",
			'youtube_url' => "javascript:void(0)",
			'instagram_url' => "javascript:void(0)",
			'story' => '',
			'post-name' => '',
			'secVideo' => '',
			'secAudio' => '',
			'st' => 0,
			'fbSocialShare' => '',
			'twSocialShare' => '',
			'tweets' => ''	
		 );		

    }	
	
	public function arrangePostsForAdVastNetwork($posts){
		$resp = $postDet = array();
		$secVideo = '';
		foreach($posts as $post){
			if($post->second_video !=""){
				$secVideo = explode('.',$post->second_video);
				$secVideo = $secVideo[0];
			}
			switch($post->type){
				case 'image':
					$postDet = array(
						'posttype' => 'image',
						'video' => '',
						'secvideo' => $secVideo,
						'audio' => '',
						'secaudio' => $post->audio_url,
						'shorturl' => $post->short_url,
						'postname' => $post->title,
						'story' => $post->story,
						'img' => S3::getS3Url(S3::getPostSdPath($post->sd_url))
					);
				break;
				
				case 'music':
					$postDet = array(
						'posttype' => 'music',
						'video' => '',
						'secvideo' => $secVideo,
						'audio' => $post->music_url,
						'secaudio' => '',
						'shorturl' => $post->short_url,
						'postname' => $post->title,
						'story' => $post->story,
						'img' => S3::getS3Url(S3::getPostSdPath($post->sd_url))
					);
				break;
				
				case 'video':
					$video =  explode('.',$post->video_url);
					$postDet = array(
						'posttype' => 'video',
						'video' => $video[0],
						'secvideo' => $secVideo,
						'audio' => '',
						'secaudio' => $post->audio_url,
						'shorturl' => $post->short_url,
						'postname' => $post->title,
						'story' => $post->story,
						'type' => 0,
						'img' => S3::getS3Url(S3::getPostSdPath($post->sd_url))
					);
				break;
				
			}
			array_push($resp,$postDet);		
		
		}
		return $resp;
	}

    public function updateAdvastJsonData($channel_id){
        $jsonData = self::getAdvastBannerJson($channel_id);
		$jsonUrl = S3::createAdvastBannerJson($jsonData,$channel_id);
    }

    public function userEditManageExploreNetwork()
    {
        $nonFeaturedLists      = Input::get('artist');
        $nonFeaturedPostArrays = Input::get('post');
        $nonFeaturedPostsList  = array();
        $nonFeaturedList       = array();
        $i                     = 0;
        foreach ($nonFeaturedPostArrays as $nonFeaturedPostArray) {
            if ($nonFeaturedPostArray == 0) {
                if ($nonFeaturedLists[$i] == 0) {
                    array_push($nonFeaturedList, 0);
                    array_push($nonFeaturedPostsList, 0);
                } else {
                    array_push($nonFeaturedList, $nonFeaturedLists[$i]);
                    $lastPost = $this->post->getLastPostByChapter($nonFeaturedLists[$i]);
                    array_push($nonFeaturedPostsList, $lastPost['post_id']);
                }
            } else {
                $post = $this->post->getPostById($nonFeaturedPostArray);
                array_push($nonFeaturedList, $post['chapter_id']);
                array_push($nonFeaturedPostsList, $nonFeaturedPostArray);
            }
            $i++;
        }
        $data = array(
            'id' => Input::get('network_id'),
            'type' => 1,
            //'cover' => Input::get('network_cover'),
            'non_featured_list' => implode(",", $nonFeaturedList),
            'non_featured_post_list' => implode(",", $nonFeaturedPostsList)
        );
        if (Input::get('featuredlist') != "")
            $data['featured_list'] = Input::get('featuredlist');
        $res = $this->manageexplore->update($data);
        if ($res){
            self::createAdvastJson(Auth::user()->channel->channel_id); 
            Session::flash('success', 'Update Successful!');
        }else{
            Session::flash('warning', 'Update Failed!');
        }
        return Redirect::to('cms/channel/explore')->with('data', $this->data);
    }
	
	public function updateSlideDetails(){
        $rules = Input::get('type') == 'edit' ? SlideShow::$editRules : SlideShow::$deleteRules;
        $validator = Validator::make(Input::all(),$rules,SlideShow::$messages);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag()->toArray();            
            return Response::json(array(
                'success' => false,
                'errors' => $messages
            ));
        }else{
            $updateData = array(
                'slide_id'          => Input::get('id'),
                'slide_title'       => Input::get('title'),
                'slide_description' => Input::get('description'),
                'slide_img'         => Input::get('filename'),
                'sdH'         => Input::get('sdH'),
                'sdW'         => Input::get('sdW'),
                'hdH'         => Input::get('hdH'),
                'hdW'         => Input::get('hdW'),
                'thumbH'         => Input::get('thumbH'),
                'thumbW'         => Input::get('thumbW')

            );
            $res = $this->slideshow->update($updateData);
            echo $res;
        }

    }

    public function sortSlideShowSlides(){
        $updateData = array();
        $i =1;
        foreach (Input::get('slide_details') as $key => $value) {
            $updateData = array('slide_id' => $value,
            'slide_order' => $i);
            $this->slideshow->update($updateData);
            $i++;
        }
    }

    public function resizeUploadedSlides(){
         $data = S3::resizeUploadedSlideImage(Input::get('img'));
         echo $data;
    }
	
	public function updateFeaturedYoutubeVideo($channel_id,$youtube_id)
	{
		$this->channel->update($channel_id,array(
			'featured_youtube_video' => $youtube_id == 'null' ? '' : $youtube_id
		));
		
	}
	
	public function updateManageExploreNonFeatured(){	
		$this->manageexplore->updateNonFeaturedListById(
			array('id' => $this->manageexplore->getFieldFromManageExplore('mapping_id',Input::get('channel_id'),'id'),
				'non_featured_list' => implode(',',Input::get('non_featured')),
				'type' => 1,
				'latestEntry' => 0
			));
		$this->createAdvastJson(Input::get('channel_id'));
	}
	
	public function manageExploreUpdateChapter(){
		$data = array('chapter_id' => Input::get('chapter_id'));
		$delete = 0;
		switch(Input::get('action')){
			case 'chapter_name':
				$data['chapter_name'] = Input::get('chapter_name');
			break;
			case 'cover':
				$data['cover'] = Input::get('post');
			break;
			case 'unpublish_chapter':
				$data['inactive'] = 1;
			break;
			case 'publish_chapter':
				$data['inactive'] = 0;
			break;
			case 'delete_chapter':
				$delete = 1;
			break;
			default:
			break;
		}
		if($delete == 1){
			$res = $this->chapter->deleteChapterById(Input::get('chapter_id'));
		}else{
			$res = $this->chapter->update($data);
		}		
		if($res == 1 && (Input::get('action')=="unpublish_chapter" || Input::get('action')=="delete_chapter")){
			$nonFeaturedList = explode(',',$this->manageexplore->getFieldFromManageExplore('mapping_id',Auth::user()->channel->channel_id,'non_featured_list'));
			if(($key = array_search(Input::get('chapter_id'), $nonFeaturedList)) !== false) {
				unset($nonFeaturedList[$key]);
			}
			$this->manageexplore->updateNonFeaturedListById(
				array('id' => $this->manageexplore->getFieldFromManageExplore('mapping_id',Auth::user()->channel->channel_id,'id'),
					'non_featured_list' => implode(',',$nonFeaturedList),
					'type' => 1,
					'latestEntry' => 0
				));
				
		}
		if(Input::get('action')=="cover"){
			$res = 2;
		}else{
			$res =1;
		}
		
		$this->createAdvastJson(Auth::user()->channel->channel_id);
		echo $res;
	}
	
	public function updatePostsBasedOnChapterSelected(){
		$chapter_id = Input::get('id');
		$post_id = Input::get('post');
		$posts = $this->post->getPostByChapterId($chapter_id);
		$str = '<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 remove-padding">';
		foreach($posts as $post){
			$poix = $post['sd_url'] == "" && $post['type'] == "event" ? 0.5 : $post['poix']- (1 - $post['poix']);
			$poiy = $post['sd_url'] == "" && $post['type'] == "event" ? 0.5 : $post['poiy']- (1 - $post['poiy']);
			$classname = $post["post_id"] == $post_id ?"active":"";
			$imgSrc = $post["sd_url"] == "" ? URL::asset('images/no_image_for_chapter_list.png') : S3::getS3Url(S3::getPostHdthumbPath($post["sd_url"]));
			$str .= '<div class="fifty_percent_tile focuspoint" data-focus-x="'.$poix.'" data-focus-y="'.$poiy.'" data-image-w="'.$post["hdthumb_width"].'" data-image-h="'.$post["hdthumb_height"].'"><img src="'.$imgSrc.'" /><div class="btn_set_as_featured cursor-pointer" data-id="'.$post["post_id"].'"><span class="btn_change_chapter_cover_icon '.$classname.'"></span></div><i class="icn_post_type '.$post["type"].'"></i></div>';
		}
		$str .= '</div><input class="vast_btn_fullwidth vast_btn_layout pull-left" id="update_featured_post" type="button" value="DONE" placeholder="">';
		echo $str;
		
	}
	
    public function incrementPostsLike(){
        $cnt =  $this->like->getUserLikedPostById(Input::get('user_id'),Input::get('post_id'));
        if($cnt == 0){
            $this->like->create(array(
                'post_id'       => Input::get('post_id'),
                'user_id'       => Input::get('user_id'),
                'channel_id'    => Input::get('user_id'),
                'refdate'       => time()
            ));
        }else{
            $this->like->delete(Input::get('post_id'),Input::get('user_id'));
        }
        return $this->like->getLikesByPostId(Input::get('post_id'));
    }
    
	public function updateSocialIconsByChannel(){
		$res = $this->channel->update(Auth::user()->channel->channel_id, array(
			'fb_url' 		=> Input::get('facebook_url'),
			'twitter_url' 	=> Input::get('twitter_url'),
			'instagram_url' => Input::get('instagram_url'),
			'tumblr_url' 	=> Input::get('tumblr_url'),
			'youtube_url' 	=> Input::get('youtube_url'),
		));
		echo $res;
	}
	
	public function chapterPostDelete(){
		$this->chapter->deletePostFromChapterPostListing(Input::get('id'),Input::get('chapter'));
		$this->chapter->updateCover(Input::get('id'));
		$res = $this->post->delete(Input::get('id'),Auth::user()->channel->channel_id);
		$jsonData = self::getAdvastBannerJson(Auth::user()->channel->channel_id);
		$jsonUrl = S3::createAdvastBannerJson($jsonData,Auth::user()->channel->channel_id);
        echo $res;
	}
	
	public function accountSettingsCropImages(){
		$filePath = Input::get('crop_type') == 1 ? S3::getAppCover('') :  S3::getChannelAvatarPath(''); 
		$data = array(
			'cropUrl'  => Input::get('crop_selection_img_src'),
			'modWidth' => Input::get('crop_selection_img_w'),
			'modHeight' => Input::get('crop_selection_img_h'),
			'cropX'     => Input::get('crop_selection_x'),
			'cropY'     => Input::get('crop_selection_y'),
			'cropW'     => Input::get('crop_selection_w'),
			'cropH'     => Input::get('crop_selection_h'),
			'filePath'  => $filePath
		);
		$fileUpload = S3::cropImageBasedOnCoordinatesAndUploadToFolder($data);
		$fileDetails = json_decode($fileUpload,true);
		if(Input::get('crop_type') == 0){
			$res = $this->channel->update(Auth::user()->channel->channel_id,array('avatar' => $fileDetails['photo']));
		}
        echo $fileUpload ? $fileUpload : '';
		
	}
	
	public function createCustomPagination($count,$paginateNumber){
		$pages = ceil($count/$paginateNumber);
		
		$paginateHtml = '<ul id="custom-paginate" class="pagination">';
		for($i=1;$i<=$pages;$i++){
			$class = $i==1?"active":"";
			$paginateHtml .= '<li class="'.$class.'" data-page="'.$i.'"><a href="#">'.$i.'</a></li>';
		}
		$paginateHtml .= '</ul>';
		if($pages > 1 ){
			return $paginateHtml;
		}else{
			return '';
		}
	}
	
	public function adminUserListFilter(){
		$users = $this->admin->filterAdminUsersLists(10,Input::get('search_key'),Input::get('alpha'),Input::get('date'));
		$this->data['user'] = $users;
		$html = '';
		$paginateHtml = $this->createCustomPagination(count($users),30);
		$i = $cnt = 1;
		foreach ($users as $user){
			$html .= '<div class="subscribers paginateItem paginateItem-'.$i.'">';
			$html .= '<span class="subject-bold colorWhite textCaps col-xs-7 col-md-4 remove-padding">'.$user->first_name.' '.$user->last_name;
			if( $user->status == 0 ){
                $html .= '<span class="time"> <span class="pending">Pending </span></span>';
			}else{
				$html .= '<span class="time"> <span class="approved"> Approved </span></span>';
			}
            $html .= '</span> ';
			
			$html .= '<span class="subject-bold col-xs-7 col-md-4 colorWhite subject-user">Username : '.$user->username;
			$html .= '<span class="time user_email">'.$user->email_id.'</span>';
			$html .= '</span>';
			$html .= '<span class="col-xs-5 col-md-4 remove-padding tools_cont">';
			$html .= '<a href="'.URL::route("superadmin.user.view", $user->vast_user_id).'"><button class="view-edit-user-button vast_btn_layout">View/Edit User </button> </a>';
			$html .= '<span class="imgDeleteUser admin_user_list_trash trash_box_icon" data-id="'.$user->vast_user_id.'"></span>';
			$html .= '</span>';
			$html .= '</div>';
			$cnt++;
			if($cnt%30 == 0){
				$i++;
			}
		}
		$html .= $paginateHtml;			
		echo $html;
		//return View::make('default.superadmin.filter_user_list', array('data' => $this->data))->render();
	}
	
	public function networkRearrangements(){
		$channels = Input::get('network_sort_order');
		$i = 1;
		foreach($channels as $channel){
			$res = $this->manageexplore->updateChannelCover(array('id' => $channel, 'network_order' => $i));			
			$i++;
		}
		echo $res;
	}
	
	public function manageExploreChapterPostsSort(){
		$posts = Input::get('post-rearranger');
		$chapter = Input::get('chapter_id');
		if(count($posts) == 1){
			array_push($posts,0);
		}
		foreach($posts as $key => $value) {
			$postQry = $this->post->getPostsListByPostId($value);
			if(empty($postQry)) {
				unset($posts[$key]);
			}
		}
		$res = $this->chapter->update(array('chapter_id' => $chapter, 'post_order' => implode(',',$posts)));
        self::createAdvastJson(Auth::user()->channel->channel_id);
		echo $res;
		
	}

    public function updateChannelPostOrderSettings($type,$channel,$chapter,$post){
        $chapterDetails = call_user_func_array('array_merge', $this->chapter->getChapterByIdAndChannel( $channel, $chapter)) ;
        $postOrder = array_filter(explode(',',$chapterDetails['post_order']));
        switch($type){
            case 'add':
                array_push($postOrder,$post);
            break;
            case 'delete':
                $key = array_search($post, $postOrder); 
                unset($postOrder[$key]);    
            break;
            default:
            break;
        }
        $this->chapter->update(array(
            'chapter_id' => $chapter,
            'post_order' => implode(',',$postOrder)
        ));

        

    }
	
	public function resizeImageUploadedViaEmbedly(){
		echo S3::resizeUploadedImageUsingEmbedly(S3::getS3Url(S3::getTempImageUrl(Input::get('filename'))),Input::get('path'),Input::get('type'));
	}
}
