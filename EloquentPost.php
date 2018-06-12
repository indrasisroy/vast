<?php
namespace Vast\Repo\Post;
use DB;
use Vast\Repo\Post\PostInterface;
use Vast\Repo\Admin\AdminInterface;
use Vast\Repo\Conversion\ConversionInterface;
use Vast\Repo\EventBouncer\EventBouncerInterface;
use Illuminate\Database\Eloquent\Model;

class EloquentPost implements PostInterface {

	protected $post, $admin,$conversion;

	public function __construct(Model $post, AdminInterface $admin, ConversionInterface $conversion, EventBouncerInterface $event) 
	{
		$this->post = $post;
		$this->admin = $admin;
		$this->conversion = $conversion;
		$this->event = $event;
	}

	public function create(array $data) 
	{
		$this->post->channel_id = $data['channel_id'];
		$this->post->type = $data['type'];
		$this->post->thumb_width = $data['thumb_width'];
		$this->post->thumb_height = $data['thumb_height'];
		$this->post->thumb_url = $data['post_image'];
		$this->post->hdthumb_width = $data['hdthumb_width'];
		$this->post->hdthumb_height = $data['hdthumb_height'];
		$this->post->hdthumb_url = $data['post_image'];
		$this->post->retina_width = $data['retina_width'];
		$this->post->retina_height = $data['retina_height'];
		$this->post->retina_url = $data['retina_url'];
		$this->post->nonretina_width = $data['nonretina_width'];
		$this->post->nonretina_height = $data['nonretina_height'];
		$this->post->nonretina_url = $data['nonretina_url'];
		$this->post->sd_width = $data['sd_width'];
		$this->post->sd_height = $data['sd_height'];
		$this->post->sd_url = $data['post_image'];
		$this->post->second_video = $data['second_video'];
		$this->post->video_url = $data['post_video'];
		$this->post->music_url = $data['post_music'];
		$this->post->poix = $data['poix'];
		$this->post->poiy = $data['poiy'];
                if($data['type']=="image" || $data['type']=="video"  )
                    $this->post->audio_url = $data['second_audio'];
		if($data['type']=="music" || $data['type']=="video") {	
			$this->post->thumb_width = $data['secthumb_width'];
			$this->post->thumb_height = $data['secthumb_height'];
			$this->post->thumb_url = $data['second_image'];
			$this->post->hdthumb_width = $data['sechdthumb_width'];
			$this->post->hdthumb_height = $data['sechdthumb_height'];
			$this->post->hdthumb_url = $data['second_image'];
			$this->post->sd_width = $data['secsd_width'];
			$this->post->sd_height = $data['secsd_height'];
			$this->post->sd_url = $data['second_image'];
			
		}	
			
		$this->post->audio_url = $data['second_audio'];
		//$this->post->link_url = $data['link'];
		$this->post->title = $data['title'];
		$this->post->song = $data['song'];
		$this->post->author = $data['author'];
		$this->post->story = $data['story'];
		$this->post->chapter_id = $data['chapter'];
		if($data['post_video']!="" || $data['second_video']!="")
			$this->post->post_status = 0;
		else
			$this->post->post_status = 1;
		if($data['schedule'] == '1'){
			date_default_timezone_set('America/New_York');
			$this->post->date = strtotime($data['date'] . $data['time']);
		}else
			$this->post->date = $data['cdate'];
		$this->post->featured = $data['subscriber_only'];
		$this->post->subscription_id = $data['subscriber_only'] == 0 ? '' : 'com.vast.'.$this->getUser($data['channel_id']) ;
		
		$saved = $this->post->save();
		$post_id = $this->post->post_id;
		$hash = \Hash::make($post_id);
		
		$this->post->post_hash = $hash;
		$this->post->short_url = \Util::shortenUrl('https://prod.thefutureisvast.us/cms/post/'.$hash);
		$this->post->save();
		$shortUrl = $this->post->short_url;
		$tw_token = ($data['tw_token'] == '' ) ? '0' : $data['tw_token'];
		$tw_secret = ($data['tw_secret'] == '' ) ? '0' : $data['tw_secret'];
		$fb_token = ($data['fb_token'] == '' ) ? '0' : $data['fb_token'];
		$tb_token = ($data['tb_token'] == '' ) ? '0' : $data['tb_token'];
		$tb_username = ($data['tb_username'] == '' ) ? '0' : $data['tb_username'];
		$tb_secret = ($data['tb_secret'] == '' ) ? '0' : $data['tb_secret'];		
				
		
		$conversionData = array('post_id' => $post_id, 'videourl' => $data['post_video'],'secvideourl' =>  $data['second_video'],'channel_id' => $data['channel_id'],'percent' => 0,'post_title' => $data['title'], 'share_text' => $data['share_text'], 'tw' => $data['tw'], 'fb' => $data['fb'], 'tb' => $data['tb'], 'short_url' => $shortUrl, 'tw_token' => $tw_token, 'tw_secret' => $tw_secret, 'fb_token' => $fb_token, 'tb_token' => $tb_token, 'tb_username' => $tb_username, 'tb_secret' => $tb_secret);
		
		if($data['type']=="video")
			  $conversionData['create_thumb'] =  $data['second_image'] == "" ? 1 : 0 ;
		
		if($data['post_video']!="" || $data['second_video']!="")
			$this->conversion->create($conversionData);
	    if($data['type']=="event") {			
			$evntSaved = $this->event->create(array(
				'post_id' => $post_id,
				'event_code' => $data['event_code'],
				'event_type' => $data['event_type'],
				'event_date' => $data['event_date'],
				'num_tickets' => $data['num_tickets'],
				'avail_tickets' => $data['num_tickets'],
				'expire_date' => $data['expire_date'],
				'location' => $data['location'],
				'qr_image' => $data['qr_image']));
		}	
		if ($saved)
			return $this->post;
		
		return false;
	}

	public function update(array $data) 
	{
		$row = $this->post->find($data['post_id']);
		$row->fill($data);
	 	return $row->save();
	}

	public function delete($id, $channel_id) 
	{
		$post = $this->post->find($id);
		if ($post->channel_id != $channel_id)
			return false;

		$post->delete();

		return true;
	}

	public function getPosts() 
	{
		return $this->post->get();
	}

	public function getPostsByChannelId($channel_id, $offset = 0, $order = 'date') 
	{
		$posts = $this->post
			->with('Channel')
			->where('tbl_posts.channel_id', $channel_id)
			->where('tbl_posts.type','<>','event' )
			->where('tbl_posts.post_status', 1)
			->skip($offset)
			->take(10);

		if ($order == 'chapter_name'){
			$posts->join('tbl_chapters', 'tbl_chapters.chapter_id', '=', 'tbl_posts.chapter_id')
			->select('tbl_posts.*','tbl_chapters.chapter_name');
			}

		if ($order == 'date')
			$posts->orderBy($order, 'DESC');
		else {
			$posts->orderBy($order)
				->orderBy('tbl_posts.date', 'DESC');
		}

		return $posts->get();
	}
	
	public function getPostsByPostId($post_id) 
	{
		$posts = $this->post
		 	->select('*')
			->where('tbl_posts.post_id', $post_id);
		return $posts->get();
	}
	
	public function updateVastName($id) 
	{
		$channel = $this->channel->find($id);
		
		if (!$channel)
			return false;
		
		$channel->vast_name = $name;
		$channel->save();
		
		return true;
	}
	
	public function updatePost(array $data)
	{
	    $data = array_filter($data, 'strlen');
		$checkArray = array('audio_url', 'video_url', 'second_video','link_url','thumb_url','hdthumb_url','retina_url','nonretina_url','sd_url','music_url','story','touchcast_url','xml_url');
		foreach($checkArray as $key)
			if (!key_exists($key,$data))
				$data[$key] = "";
		//var_dump($data);
		foreach ($data as $key => $value) {
            if( $key == 'featured' || $key == 'post_status' || $key == 'featured_post'){
            	$data[$key] = $value == '0' ? '0' : $value;
            }else{
                $data[$key] = $value == '0' ? NULL : $value;
            }
		}
		$data['subscription_id'] = $data['featured'] == 0 ? '' : 'com.vast.'.$this->getUser($data['channel_id']) ;
		$data['post_edit'] = 1;
	    $posts = $this->post->find($data['post_id']);
		if($posts->chapter_id != $data['chapter_id']){
			DB::raw(DB::table('tbl_chapters')->where('chapter_id', $posts->chapter_id)->update(array('cover' => 0)));
		}
	    $posts->fill($data);
		if(isset($data['post_type']) == "event")
			$this->event->update($data);
	 	return $posts->save(); 
	}
	
	public function getUser($channel_id)
	{
		return $this->admin->getUser($channel_id);
	}
	
	public function deletePostByChannel($channel)
	{
		return $this->post
			->where('channel_id', $channel)
			->delete();
	}
	
	public function deletePostByChapter($chapter)
	{
		return $this->post
			->where('chapter_id', $chapter)
			->delete();
	}
	
	public function getLastPostImageByChannel($channel)
	{
		return  $this->post
			->where('channel_id', $channel)	
	   		->orderBy('post_id', 'DESC')
			->take(1)
			->pluck('hdthumb_url');
	}
	
	public function getLastPostImageByChapter($chapter)
	{
		return  $this->post
			->where('chapter_id', $chapter)	
	   		->orderBy('post_id', 'DESC')
			->take(1)
			->pluck('hdthumb_url');
	}
	
	public function updatePostsOnVideoConversion(array $data)
	{
		$posts = $this->post->find($data['post_id']);
		$posts->fill($data);
		return $posts->save();
	}
	
	public function getPostsbyChapter($chapter_id)
	{
		return $this->post
			->select('post_id','title','sd_url','story','type','video_url','short_url','audio_url','music_url','second_video')
			->where('date','<=',time())
			->where('post_status',1)
			->where('chapter_id',$chapter_id)
			->orderBy('post_id', 'DESC')
			->get();
	}
	
	public function getPostImageById($post_id)
	{
		return  $this->post
			->where('post_id', $post_id)	
			->pluck('sd_url');
	}
	
	public function getLastPostByChapter($chapter_id)
	{
		return $this->post
			->where('chapter_id', $chapter_id)
			->where('date','<=',time())
			->where('post_status', 1)
	   		->orderBy('post_id', 'DESC')
			->first();
	}
	
	public function getPostById($post_id)
	{
		return  $this->post
			->where('post_id', $post_id)	
			->first();
	}
	
	public function createWatermark($imageUrl,$waterLogo,$type,$gradient,$forFb)
	{
		//echo "am here".$imageUrl."***".$waterLogo."***".$type."***".$gradient."***".$forFb;exit;
		$waterLogo = imagecreatefrompng($waterLogo);
		$ext =substr(strrchr($imageUrl,'.'),1);
		switch($ext){
		case "PNG":
		case "png":
			$im = imagecreatefrompng($imageUrl);
		break;
		case "jpg":
		case "JPG":
		case "JPEG":
		case "jpeg":
			$im = imagecreatefromjpeg($imageUrl);
		break;
		}
		// Set the margins for the logo and get the height/width of the logo image
		// Copy the logo image onto our photo using the margin offsets and the photo 
		// width to calculate positioning of the logo. 
		switch($type){
		case 0 :
			$marge_right = 10;
			$sx = imagesx($waterLogo);
			$sy = imagesy($waterLogo);
			$logoX = imagesx($im)/2;
			$logoY = imagesy($im)/2;
			if($forFb == 1)
				imagecopy($im, $waterLogo, $logoX - ($sx/2), $logoY - ($sy/2), 0, 0, imagesx($waterLogo), imagesy($waterLogo));
			elseif($forFb == 0)	
				imagecopy($im, $waterLogo, imagesx($im) - $sx - $marge_right, 10, 0, 0, imagesx($waterLogo), imagesy($waterLogo));
				
		break;
		case 1:
			$sx = imagesx($waterLogo);
			$sy = imagesy($waterLogo);		
			$logoX = imagesx($im)/2;
			$logoY = imagesy($im)/2;
			$gradient = imagecreatefrompng($gradient);
			imagecopymerge($im, $gradient,0 ,0, 1, 120, imagesx($im), imagesy($im),50);		
			imagecopy($im, $waterLogo, $logoX - ($sx/2), $logoY - ($sy/2), 0, 0, imagesx($waterLogo), imagesy($waterLogo));
		break;
		default:
		break;
	}


	// Output and free memory
	switch($ext){
		case "png":
			header('Content-type: image/png');
			imagealphablending($im, false);
			imagesavealpha($im, true);
			imagepng($im);  	
		break;
		case "jpg":
		case "jpeg":
			header('Content-type: image/jpeg');
			imagejpeg($im); 
		break;
	}
	imagedestroy($im);
	}
	
	
	public function getLastFivePostBasedOnChannel($channel_id)
	{
		return $this->post
		 	->select('*')
		 	->where('date','<=', time())
			->orderBy('date', 'desc')
			->where('channel_id', $channel_id)
			->take(5)
			->get();
	
	}
	
	public function getLastestPostByChannels($channels)
	{
		$query = $this->post->where('post_status',1)
		->where('date','<=',time());
		foreach($channels as $channel)
			$query->orWhere('channel_id', $channel);
		$query->orderBy('post_id', 'DESC');
		$query->limit(5);
		return $query->get();
	}
	
	public function updateChannelFeaturedPosts($channel_id,$featured)
	{
		$res = $this->post->where('channel_id',$channel_id)->WhereNotIn('post_id', $featured)->update(array('featured_post' => 0));
	}
	
	public function getEventsBasedOnChannel($channel_id,$event_type)
	{
		$events = $this->post
			->join('tbl_events', 'tbl_posts.post_id', '=', 'tbl_events.post_id')
			->where('type', 'event')
			->where('channel_id',$channel_id)
			->orderBy('tbl_posts.post_id');
		if($event_type != 2)
			$events->where('event_type',$event_type);
		return $events->get();
	}
	
	public function getEventDetailsBasedOnId($id)
	{
		return $this->post
			->join('tbl_events', 'tbl_posts.post_id', '=', 'tbl_events.post_id')
			->where('type', 'event')
			->where('tbl_posts.post_id',$id)
			->get();
	}
	
	public function getEventByPostId($id)
	{
		return $this->post
			->join('tbl_events', 'tbl_posts.post_id', '=', 'tbl_events.post_id')
			->where('tbl_posts.post_id', $id)
			->first();
	}
	
	public function getPostsByChannelIdForChannellGrid($channel_id, $offset = 0, $order = 'date') 
	{
		$posts = $this->post
			->leftJoin('tbl_chapters', 'tbl_chapters.chapter_id', '=', 'tbl_posts.chapter_id')
			->leftJoin('tbl_channels', 'tbl_channels.channel_id', '=', 'tbl_posts.channel_id')
			->where('tbl_posts.channel_id', $channel_id)
			->where('tbl_posts.type','<>','event' )
			->where('tbl_posts.post_status', 1)
			->select('tbl_posts.post_id','tbl_posts.sd_url','tbl_posts.type','tbl_posts.music_url','tbl_posts.video_url')
			->orderBy('tbl_posts.date', 'DESC');
		return $posts->get();
	}
	
	public function getVideoPostsByChannel($channel_id)
	{
		return $this->post
			->select('post_id','video_url','sd_url','thumb_url','chapter_id')
			->where('channel_id',$channel_id)
			->where('type','video')
			->where('post_status',1)
			->get();	
	}
	
	public function createCroppedImage($img, $poix, $poiy)
	{	
		if (($img_info = getimagesize($img)) === FALSE)
  			die("Image not found or not an image");

		$width = $img_info[0];
		$height = $img_info[1];
		switch ($img_info[2]) {
		  case IMAGETYPE_GIF  : $src = imagecreatefromgif($img);  break;
		  case IMAGETYPE_JPEG : $src = imagecreatefromjpeg($img); break;
		  case IMAGETYPE_PNG  : $src = imagecreatefrompng($img);  break;
		  default : die("Unknown filetype");
		}
		list($width_orig, $height_orig) = getimagesize($img);
		
		$posX = ($width_orig*$poix)-128;
		$posY = ($height_orig*$poiy)-128;
		$posX = $posX > 0 ? $posX : 0;
		$posY = $posY > 0 ? $posY : 0;
		$newImage = imagecreatetruecolor(256, 256);
		imagecopyresampled ( $newImage , $src , 0, 0 , $posX , $posY, 256 , 256 ,  256  , 256 );
		header('Content-type: image/jpeg');
		imagejpeg($newImage); 
		imagedestroy($newImage);
	}
	
	public function getGridPostAndChapterDetails(array $postList)
	{
		$postListStr = implode(',',$postList);
		return $this->post
			->select('tbl_chapters.chapter_name','tbl_posts.sd_url','tbl_posts.short_url','tbl_posts.title','tbl_posts.type','tbl_posts.video_url','tbl_chapters.chapter_id','tbl_posts.poix','tbl_posts.poiy','tbl_posts.sd_width','tbl_posts.sd_height','tbl_posts.hdthumb_width','tbl_posts.hdthumb_height','tbl_channels.first_name','tbl_channels.last_name','tbl_channels.fb_url','tbl_channels.avatar','tbl_channels.tumblr_url','tbl_channels.twitter_url','tbl_posts.post_id','tbl_channels.youtube_url','tbl_channels.instagram_url','tbl_posts.youtube_url as youtube','tbl_posts.story','tbl_posts.poix','tbl_posts.poiy','tbl_channels.vast_name','tbl_posts.post_hash')
			//->select('tbl_chapters.chapter_name','tbl_posts.sd_url','tbl_posts.short_url','tbl_posts.title','tbl_posts.type','tbl_posts.video_url','tbl_chapters.chapter_id','tbl_posts.poix','tbl_posts.poiy','tbl_posts.sd_width','tbl_posts.sd_height','tbl_posts.hdthumb_width','tbl_posts.hdthumb_height','tbl_channels.first_name','tbl_channels.last_name','tbl_channels.fb_url','tbl_channels.avatar','tbl_channels.twitter_url','tbl_posts.post_id','tbl_channels.youtube_url','tbl_channels.instagram_url')
			->join('tbl_chapters', 'tbl_posts.chapter_id', '=' , 'tbl_chapters.chapter_id')
			->join('tbl_channels', 'tbl_posts.channel_id', '=' , 'tbl_channels.channel_id')
			->whereIn('tbl_posts.post_id', $postList)
			->orderByRaw(DB::raw("FIELD(tbl_posts.post_id, ".$postListStr.")"))
			->get()
			->toArray();	
	}
	
	public function getPostsCountByChannelId($channel_id, $status)
	{
		return	$this->post
			->where('channel_id', $channel_id)
			->where('post_status', $status)	
			->count();	
	}

	public function getPostsCountByChnlId($channel_id) 
	{
		return	$this->post
			->where('channel_id', $channel_id)
			->count();
	}

	public function getPostsLimitByChannelId($channel_id, $offset, $order, $count) 
	{
		$posts = $this->post
			->where('tbl_posts.channel_id', $channel_id)
			->where('tbl_posts.post_status', 1)
			->skip($offset)
			->take($count);

		if ($order == 'chapter_name'){
			$posts->join('tbl_chapters', 'tbl_chapters.chapter_id', '=', 'tbl_posts.chapter_id')
			->select('tbl_posts.*','tbl_chapters.chapter_name');
			}

		if ($order == 'date')
			$posts->orderBy($order, 'DESC');
		else {
			$posts->orderBy($order)
				->orderBy('tbl_posts.date', 'DESC');
		}

		return $posts->get()->toArray();
	}

	public function getPostIdLimitByChannelId($channel_id, $offset, $pId, $order, $count) 
	{
		$posts = $this->post
			->where('tbl_posts.channel_id', '=' , $channel_id)
			->where('tbl_posts.post_id',  '>' , $pId)
			->where('tbl_posts.post_status', 1)
			->skip($offset)
			->take($count);

		if ($order == 'chapter_name'){
			$posts->join('tbl_chapters', 'tbl_chapters.chapter_id', '=', 'tbl_posts.chapter_id')
			->select('tbl_posts.*','tbl_chapters.chapter_name');
			}

		if ($order == 'date')
			$posts->orderBy($order, 'DESC');
		else {
			$posts->orderBy($order)
				->orderBy('tbl_posts.date', 'DESC');
		}

		return $posts->get()->toArray();
	}

	public function getLastPostByChannel($channel) 
	{
		return  $this->post
			->where('channel_id', $channel)	
	   		->orderBy('post_id', 'DESC')
			->take(1)
			->get()
			->toArray();
	}
	
	public function getPostsByChannelIdAndDate($channel_id, $time, $order) 
	{
		$posts = $this->post
		->where('tbl_posts.date', '>',  $time)
		->where('tbl_posts.channel_id', $channel_id)
		->where('tbl_posts.post_status',1)
		->orderBy('tbl_posts.date', 'DESC');
		return $posts->get()->toArray();
	}

	public function getEventPostByChannelId($channel_id, $time)
	{
		return $this->post
			->select('tbl_events.*','tbl_posts.*')
			->join('tbl_events', 'tbl_posts.post_id', '=' , 'tbl_events.post_id')
			->where('tbl_posts.channel_id', $channel_id)
			->where('tbl_events.expire_date', '>', $time)
			->where('tbl_posts.type', 'event')
			->get()
			->toArray();	
	}

	public function getEventCodeByChannelId($eventCode, $channel_id, $time) 
	{
		return $this->post
			->select('tbl_events.*','tbl_posts.*')
			->join('tbl_events', 'tbl_posts.post_id', '=' , 'tbl_events.post_id')
			->where('tbl_events.event_code', $eventCode)
			->where('tbl_posts.channel_id', $channel_id)
			->where('tbl_events.expire_date', '>', $time)
			->get()
			->toArray();
	}

	public function insertPostDetails(array $data) 
	{
		if(isset($data['post_id']) && $data['post_id'] > 0)	{
			$updateData = array(
				'chapter_id' => $data['chapter_id'], 
				'channel_id' => $data['channel_id'], 
				'type' => $data['type'], 
				'date' => $data['date'], 
				'story' => $data['story'], 
				'title' => $data['title'], 
				'subscription_id' => $data['subscription_id'], 
				'audio_url' => $data['audio_url'], 
				'video_url' => $data['video_url'], 
				'second_video' => $data['second_video'], 
				'thumb_width' => $data['thumb_width'],
				'thumb_height' => $data['thumb_height'],
				'thumb_url' => $data['thumb_url'],
				'hdthumb_width' => $data['hdthumb_width'],
				'hdthumb_height' => $data['hdthumb_height'],
				'hdthumb_url' => $data['hdthumb_url'],
				'retina_width' => $data['retina_width'],
				'retina_height' => $data['retina_height'],
				'retina_url' => $data['retina_url'],
				'nonretina_width' => $data['nonretina_width'],
				'nonretina_height' => $data['nonretina_height'],
				'nonretina_url' => $data['nonretina_url'],
				'sd_width' => $data['sd_width'],
				'sd_height' => $data['sd_height'],
				'sd_url' => $data['sd_url'],
				'music_url' => $data['music_url'],
				'song' => $data['song'],			
				'author' => $data['author'],
				'poix' => $data['poix'],
				'poiy' => $data['poiy'],
				'post_status' => $data['post_status'],
				'post_edit' => $data['post_edit']
			);
			$this->post->where('post_id',$data['post_id'])->update($updateData);
		} else {	
			$insertData = array(
				'chapter_id' => $data['chapter_id'], 
				'channel_id' => $data['channel_id'], 
				'type' => $data['type'], 
				'date' => $data['date'], 
				'story' => $data['story'], 
				'title' => $data['title'], 
				'subscription_id' => $data['subscription_id'], 
				'audio_url' => $data['audio_url'], 
				'video_url' => $data['video_url'], 
				'second_video' => $data['second_video'], 
				'thumb_width' => $data['thumb_width'],
				'thumb_height' => $data['thumb_height'],
				'thumb_url' => $data['thumb_url'],
				'hdthumb_width' => $data['hdthumb_width'],
				'hdthumb_height' => $data['hdthumb_height'],
				'hdthumb_url' => $data['hdthumb_url'],
				'retina_width' => $data['retina_width'],
				'retina_height' => $data['retina_height'],
				'retina_url' => $data['retina_url'],
				'nonretina_width' => $data['nonretina_width'],
				'nonretina_height' => $data['nonretina_height'],
				'nonretina_url' => $data['nonretina_url'],
				'sd_width' => $data['sd_width'],
				'sd_height' => $data['sd_height'],
				'sd_url' => $data['sd_url'],
				'music_url' => $data['music_url'],
				'song' => $data['song'],			
				'author' => $data['author'],
				'poix' => $data['poix'],
				'poiy' => $data['poiy'],
				'post_status' => $data['post_status']
			);
			return $this->post->insertGetId($insertData);
		}
		
	}
	
	public function updateShortUrlByPostId(array $data) 
	{
		$res = $this->post
			->where('post_id',$data['post_id'])
			->update($data);
	}

	public function getPostsListByPostId($post_id) 
	{
		$posts = $this->post
		 	->select('*')
			->where('tbl_posts.post_id', $post_id);
		return $posts->get()->toArray();
	}
	
	public function getPostByDate($channel_id, $refer_date, $time) 
	{
		return $this->post
			->where('channel_id', $channel_id)
			->where('post_status', 1)
			->whereBetween('date', array($refer_date, $time))
			->orderBy('post_id', 'DESC')
			->get();
	}
	
	public function getOnePostByChannel($channel, $time) 
	{
		return  $this->post
			->where('channel_id', $channel)
			->where('date', '<', $time)
	   		->orderBy('post_id', 'DESC')
			->take(1)
			->get()
			->toArray();
	}

	public function getPostsByChannelIdForFeed($channel_id, $offset = 0, $count) 
	{
		$posts = $this->post
			->select('tbl_posts.*', 'tbl_posts.subscription_id as subscribeId', 'C.*', 'C.subscription_id as subId', 'CP.chapter_id as chapterId', 'CP.chapter_name as chapterName', 'LP.thumb_width as thmbWidth', 'LP.thumb_height as thmbHeight', 'LP.thumb_url as thmbUrl', 'LP.hdthumb_width as hdWidth', 'LP.hdthumb_height as hdHeight', 'LP.hdthumb_url as hdUrl', 'LP.sd_width as sdWidth', 'LP.sd_height as sdHeight', 'LP.sd_url as sdUrl', 'LP.poix as lPoix', 'LP.poiy as lPoiy', DB::raw('(select count(L1.likes_id) from tbl_likes L1 where L1.post_id = tbl_posts.post_id) as num'))
			->join('tbl_channels AS C', 'C.channel_id', '=', 'tbl_posts.channel_id')
			->join('tbl_chapters AS CP', 'CP.chapter_id', '=', 'tbl_posts.chapter_id')
			->join(DB::raw('(select LP1.* from tbl_posts LP1 join (select LP2.post_id, LP2.channel_id, max(LP2.post_id) as lastpost from tbl_posts LP2 group by LP2.channel_id) LP3 on LP1.channel_id = LP3.channel_id and LP1.post_id = lastpost) LP'), function($query) 
			{
				$query->on('tbl_posts.channel_id', '=', 'LP.channel_id');
			})
			->where('tbl_posts.channel_id', $channel_id)
			->where('tbl_posts.post_status', 1)
			->orderBy('tbl_posts.post_id', 'DESC')
			->skip($offset)
			->take($count);
		return $posts->get();
	}

	public function getPostsByPostIdForFeed($channel_id, $postId, $count) 
	{
		$posts = $this->post
			->select('tbl_posts.*', 'tbl_posts.subscription_id as subscribeId', 'C.*', 'C.subscription_id as subId', 'CP.chapter_id as chapterId', 'CP.chapter_name as chapterName', 'LP.thumb_width as thmbWidth', 'LP.thumb_height as thmbHeight', 'LP.thumb_url as thmbUrl', 'LP.hdthumb_width as hdWidth', 'LP.hdthumb_height as hdHeight', 'LP.hdthumb_url as hdUrl', 'LP.sd_width as sdWidth', 'LP.sd_height as sdHeight', 'LP.sd_url as sdUrl', 'LP.poix as lPoix', 'LP.poiy as lPoiy', DB::raw('(select count(L1.likes_id) from tbl_likes L1 where L1.post_id = tbl_posts.post_id) as num'))
			->join('tbl_channels AS C', 'C.channel_id', '=', 'tbl_posts.channel_id')
			->join('tbl_chapters AS CP', 'CP.chapter_id', '=', 'tbl_posts.chapter_id')
			->join(DB::raw('(select LP1.* from tbl_posts LP1 join (select LP2.post_id, LP2.channel_id, max(LP2.post_id) as lastpost from tbl_posts LP2 group by LP2.channel_id) LP3 on LP1.channel_id = LP3.channel_id and LP1.post_id = lastpost) LP'), function($query) 
			{
				$query->on('tbl_posts.channel_id', '=', 'LP.channel_id');
			})
			->where('tbl_posts.channel_id', $channel_id)
			->where('tbl_posts.post_id', '>', $postId)
			->where('tbl_posts.post_status', 1)
			->orderBy('tbl_posts.post_id', 'DESC')
			->take($count);
		return $posts->get();
	}

	public function getNumOfFavorites($postId, $userId, $count) 
	{
		if(!$postId) {
		    $order	=	'L.likes_id';
		    $offset	=	0;
		} else {
		    $order	=	'L.post_id';
		    $offset =	$postId-1;
		}
		$fav	=	$this->post
	    ->select('L.*', 'tbl_posts.*', 'C.*', 'CP.*', 'LP.*', 'tbl_posts.subscription_id as subscribeId', 'C.subscription_id as subId', 'CP.chapter_id as chapterId', 'CP.chapter_name as chapterName', 'LP.thumb_width as thmbWidth', 'LP.thumb_height as thmbHeight', 'LP.thumb_url as thmbUrl', 'LP.hdthumb_width as hdWidth', 'LP.hdthumb_height as hdHeight', 'LP.hdthumb_url as hdUrl', 'LP.sd_width as sdWidth', 'LP.sd_height as sdHeight', 'LP.sd_url as sdUrl', 'LP.poix as lPoix', 'LP.poiy as lPoiy', DB::raw( '(select count(L1.likes_id) FROM tbl_likes L1 where L1.post_id = L.post_id) as num' ) )
	    ->join('tbl_likes AS L', 'L.post_id', '=', 'tbl_posts.post_id')
	    ->join('tbl_channels AS C', 'C.channel_id', '=', 'tbl_posts.channel_id')
	    ->join('tbl_chapters AS CP', 'CP.chapter_id', '=', 'tbl_posts.chapter_id')
	    ->join(DB::raw('(SELECT LP1.* FROM tbl_posts LP1 join (SELECT LP2.post_id, LP2.channel_id, max(LP2.post_id) as lastpost FROM tbl_posts LP2 group by LP2.channel_id) LP3 on LP1.channel_id = LP3.channel_id and LP1.post_id = lastpost) LP'), function($join) {
	    		$join->on('C.channel_id', '=', 'LP.channel_id');
	   		})
	    ->where('L.user_id', '=', $userId)
	    ->orderBy($order, 'desc')
	    ->take($count)
	    ->offset($offset);
	    $count	=	$fav->count();
	    $arr	=	$fav->get()->toArray();

	     return array('num' => $count, 'fav' => $arr);
	}
	
	
	public function getPostByChannelIdAndDate($featuresArr, $time, $order, $limit) 
	{
		$featuresArr[0]	=	(!isset($featuresArr[0])) ? '' : $featuresArr[0];
		$featuresArr[1]	=	(!isset($featuresArr[1])) ? '' : $featuresArr[1];
		$featuresArr[2]	=	(!isset($featuresArr[2])) ? '' : $featuresArr[2];
		$featuresArr[3]	=	(!isset($featuresArr[3])) ? '' : $featuresArr[3];
		$featuresArr[4]	=	(!isset($featuresArr[4])) ? '' : $featuresArr[4];
		return $this->post
		->where('post_status', '=', 1)
		->where('channel_id', '=', $featuresArr[0])
		->orWhere('channel_id', '=', $featuresArr[1])
		->orWhere('channel_id', '=', $featuresArr[2])
		->orWhere('channel_id', '=', $featuresArr[3])
		->orWhere('channel_id', '=', $featuresArr[4])
		->where('date', '<', $time)
		->orderBy($order, 'desc')
		->take($limit)
		->get()
		->toArray();
	}

	public function getPostByStatus($post_id, $post_status) 
	{
		return  $this->post
			->where('post_id', $post_id)
			->where('post_status', '=', $post_status)
			->get()
			->toArray();
	}
	
	public function getEventPostChapter($chapter_id) 
	{
		return $this->post
			->join('tbl_events', 'tbl_posts.post_id', '=' , 'tbl_events.post_id')
			->where('tbl_posts.chapter_id', $chapter_id)
			->where('tbl_posts.type', 'event')
			->orderBy('tbl_posts.post_id', 'DESC')
			->get();		
	}
	
	public function getPostByChapter($chapter_id, $time) 
	{
		return  $this->post
			->where('chapter_id', $chapter_id)
			->where('date', '<', $time)
	   		->orderBy('post_id', 'DESC')
			->get();
	}

	public function getPostByChapterStatus($chapter_id, $time) 
	{
		return  $this->post
			->where('chapter_id', $chapter_id)
			->where('date', '<', $time)
			->where('post_status', 1)
	   		->orderBy('post_id', 'DESC')
			->get();
	}
	
	public function getPostByChannelAndDate($channel_id, $time) 
	{
		return $this->post
			->join('tbl_chapters', 'tbl_chapters.chapter_id', '=', 'tbl_posts.chapter_id')
			->where('tbl_posts.channel_id', '=', $channel_id)
			->where('tbl_posts.date', '<', $time)
			->where('tbl_posts.post_status', 1)
			->where('tbl_chapters.inactive', '=', 0)
			->orderBy('tbl_posts.post_id', 'DESC')
			->take(5)
			->get()
			->toArray();
	}
	
	public function getPostOrderByMax($channel_id, $time) 
	{
		return $this->post
			->where('channel_id', $channel_id)
			->where('date', '<', $time)
	   		->where('post_status', '=', 1)
			->groupBy('chapter_id')
	   		->orderBy(DB::raw('max(post_id)'), 'desc')
			->get()
			->toArray();
	}
	
	public function getPostByChapterAndChannel($channel_id, $chapter_id, $time) 
	{
		return  $this->post
			->where('channel_id', $channel_id)
			->where('chapter_id', $chapter_id)
			->where('date', '<', $time)
	   		->orderBy('post_id', 'DESC')
			->take(1)
			->get()
			->toArray();
	}
	
	public function getPostByHash($hash)
	{
		$posts = $this->post
		 	->select('*')
			->where('tbl_posts.post_hash', $hash);
		return $posts->get();
	}
	
	public function getPostByChannel($id)
	{
		$posts = $this->post
		 	->select('*')
			->where('tbl_posts.channel_id', $id);
		return $posts->get();
	}

	public function getChannelNameByPostId($id)
	{
		return $this->post
			->select('tbl_channels.first_name','tbl_channels.last_name')
			->join('tbl_channels', 'tbl_posts.channel_id', '=' , 'tbl_channels.channel_id')
			->where('tbl_posts.post_id', $id)
			->get();
	}
	
	public function getPostsCountByChannels(array $channelLists, $status)
	{
		return	$this->post
			->whereIn('tbl_posts.channel_id', $channelLists)
			->where('post_status', $status)	
			->count();	
	}

	public function getPostsByChannelsForFeed(array $channelLists, $offset = 0, $count) 
	{
		$posts = $this->post
			->select('tbl_posts.*', 'tbl_posts.subscription_id as subscribeId', 'C.*', 'C.subscription_id as subId', 'CP.chapter_id as chapterId', 'CP.chapter_name as chapterName', 'LP.thumb_width as thmbWidth', 'LP.thumb_height as thmbHeight', 'LP.thumb_url as thmbUrl', 'LP.hdthumb_width as hdWidth', 'LP.hdthumb_height as hdHeight', 'LP.hdthumb_url as hdUrl', 'LP.sd_width as sdWidth', 'LP.sd_height as sdHeight', 'LP.sd_url as sdUrl', 'LP.poix as lPoix', 'LP.poiy as lPoiy', DB::raw('(select count(L1.likes_id) from tbl_likes L1 where L1.post_id = tbl_posts.post_id) as num'))
			->join('tbl_channels AS C', 'C.channel_id', '=', 'tbl_posts.channel_id')
			->join('tbl_chapters AS CP', 'CP.chapter_id', '=', 'tbl_posts.chapter_id')
			->join(DB::raw('(select LP1.* from tbl_posts LP1 join (select LP2.post_id, LP2.channel_id, max(LP2.post_id) as lastpost from tbl_posts LP2 group by LP2.channel_id) LP3 on LP1.channel_id = LP3.channel_id and LP1.post_id = lastpost) LP'), function($query) 
			{
				$query->on('tbl_posts.channel_id', '=', 'LP.channel_id');
			})
			->whereIn('tbl_posts.channel_id', $channelLists)
			->where('tbl_posts.post_status', 1)
			->where('CP.inactive', '=', 0)
			->orderBy('tbl_posts.post_id', 'DESC')
			->skip($offset)
			->take($count);
		return $posts->get();
	}
	
	public function getPostByChapterId($chapter_id) 
	{
		return $this->post
			->where('chapter_id', $chapter_id)
			->where('post_status', 1)
			->orderBy('post_id', 'DESC')
			->get();
	}

	public function getVIPPostsByChannelId($channelLists)
	{
		$posts = $this->post
			->select('tbl_posts.*', 'tbl_posts.subscription_id as subscribeId', 'C.*', 'C.subscription_id as subId', 'CP.chapter_id as chapterId', 'CP.chapter_name as chapterName', 'LP.thumb_width as thmbWidth', 'LP.thumb_height as thmbHeight', 'LP.thumb_url as thmbUrl', 'LP.hdthumb_width as hdWidth', 'LP.hdthumb_height as hdHeight', 'LP.hdthumb_url as hdUrl', 'LP.sd_width as sdWidth', 'LP.sd_height as sdHeight', 'LP.sd_url as sdUrl', 'LP.poix as lPoix', 'LP.poiy as lPoiy', DB::raw('(select count(L1.likes_id) from tbl_likes L1 where L1.post_id = tbl_posts.post_id) as num'))
			->join('tbl_channels AS C', 'C.channel_id', '=', 'tbl_posts.channel_id')
			->join('tbl_chapters AS CP', 'CP.chapter_id', '=', 'tbl_posts.chapter_id')
			->join(DB::raw('(select LP1.* from tbl_posts LP1 join (select LP2.post_id, LP2.channel_id, max(LP2.post_id) as lastpost from tbl_posts LP2 group by LP2.channel_id) LP3 on LP1.channel_id = LP3.channel_id and LP1.post_id = lastpost) LP'), function($query) 
			{
				$query->on('tbl_posts.channel_id', '=', 'LP.channel_id');
			})
			->whereIn('tbl_posts.channel_id', $channelLists)	
			->whereNotNull('tbl_posts.subscription_id')
			->where('tbl_posts.subscription_id', '!=', '')			
			->where('tbl_posts.post_status', 1)
			->orderBy('tbl_posts.post_id', 'DESC');
			/*
			->get();
			$queries = DB::getQueryLog();
			$last_query = end($queries);
			print_r($last_query);
			exit;*/
		return $posts->get();
	}
	
	public function getLastestPostByChapter($chapter)
	{
		return $this->post
			->where('post_status',1)
			->where('chapter_id',$chapter)
			->orderBy('post_id', 'DESC')
			->first();
	}
	
	public function getPostsbyChapterOrderByPostOrder($chapter_id,$post_order)
	{
		$posts = $this->post
			->select('post_id','title','sd_url','story','type','video_url','short_url','audio_url','music_url','second_video','post_hash')
			->where('date','<=',time())
			->where('post_status',1)
			->where('chapter_id',$chapter_id);
			
			if(count(array_filter($post_order)) > 0){				
				$posts->orderByRaw(DB::raw("FIELD(post_id, ".implode(',',$post_order).")"));
			}else{
				$posts->orderBy('post_id', 'DESC');
			}
		return $posts->get();
		
	}


	public function getPostByChapterIdBasedOnPostOrder($chapter,$postOrder){
		$postLists = explode(',',$postOrder);
		$posts =  $this->post
		          ->where('chapter_id', $chapter)
		          ->where('post_status', 1);
		if(count(array_filter($postLists)) > 0){
			$posts->orderByRaw(DB::raw("FIELD(post_id, ".$postOrder.")"));
		}else{
			$posts->orderBy('post_id', 'DESC');
		}
		return $posts->get();

	}

}
