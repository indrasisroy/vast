<?php
namespace Vast\Repo\Conversion;

use Vast\Repo\Conversion\ConversionInterface;
use Illuminate\Database\Eloquent\Model;

class EloquentConversion implements ConversionInterface {

	protected $conversion;

	public function __construct(Model $conversion) {
		$this->conversion = $conversion;
	}

	public function create(array $data) {
		$videourl = isset($data['videourl']) ? $data['videourl'] : "";
		$secvideourl = isset($data['secvideourl']) ? $data['secvideourl'] : "";
		$create_thumb = isset($data['create_thumb']) ? $data['create_thumb'] : 0;
		$updateData =  array('post_id' => $data['post_id'],
		 	'videourl' => $videourl,
		 	'secvideourl' =>  $secvideourl,
			'create_thumb' => $create_thumb,
			'conv_status' => 0,
			'channel_id' => $data['channel_id'],
			'percent' => $data['percent'],
			'post_title' => $data['post_title'],
			'share_text' => $data['share_text'],
			'tw' => $data['tw'],
			'fb' => $data['fb'],
			'tb' => $data['tb'],
			'short_url' => $data['short_url'],
			'tw_token' => $data['tw_token'],
			'tw_secret' => $data['tw_secret'],
			'fb_token' => $data['fb_token'],
			'tb_token' => $data['tb_token'],
			'tb_username' => $data['tb_username'],
			'tb_secret' => $data['tb_secret']); 
		 $post_id = $this->conversion
			->where('post_id', $data['post_id'])	
			->take(1)
			->pluck('post_id');
		if($post_id > 0){
			$conversions = $this->conversion->find($post_id);
			$conversions->fill($updateData);
			$conversions->save();
		}
		else
			$conversionId =  $this->conversion->insertGetId($updateData);	
	}

	public function update(array $data) {
		
	}
	
	public function getPendingConversion(){
		return $this->conversion
		->where('conv_status', 0)	
		->first();
	}
	
	public function deleteEntry($slno){
		$conversion = $this->conversion->find($slno);
		$conversion->delete();
	}
	
	public function updateSelectedConversion($id){
		$conversions = $this->conversion->find($id);
		$conversions->fill(array('conv_status' => 1));
		$conversions->save();
	}
	
	public function getPendingConversionsBasedOnChannel($channel_id){
		return $this->conversion
		->where('channel_id', $channel_id)	
		->get();
	}
	
	public function updateVideoConversionPercentage($post_id){
		$res =  $this->conversion
		->where('post_id', $post_id)	
		->first();
		if(isset($res['post_id']) > 0){
			if($res['conv_status']== 1){
				if($res['percent'] == 75)
					$percent = 75;
				else
					$percent = $res['percent']+1;
				$updateData = array(
				'post_id' => $post_id,
				'percent' => $percent);
				$conv = $this->conversion->find($updateData['post_id']);
				$conv->fill($updateData);
				$conv->save();
				return $percent;
			}else
				return 0;
		}else
			return -1;
		
	}

	public function insertPost(array $data) {
		$videourl = isset($data['videourl']) ? $data['videourl'] : "";
		$secvideourl = isset($data['secvideourl']) ? $data['secvideourl'] : "";
		$updateData =  array('post_id' => $data['post_id'],
		 	'videourl' => $videourl,
		 	'secvideourl' =>  $secvideourl,
			'channel_id' => $data['channel_id'],
			'percent' => $data['percent'],
			'post_title' => $data['post_title'],
			'share_text' => $data['share_text'],
			'tw' => $data['tw'],
			'fb' => $data['fb'],
			'tb' => $data['tb'],
			'short_url' => $data['short_url'],
			'tw_token' => $data['tw_token'],
			'tw_secret' => $data['tw_secret'],
			'fb_token' => $data['fb_token'],
			'tb_token' => $data['tb_token'],
			'tb_username' => $data['tb_username'],
			'tb_secret' => $data['tb_secret']); 
		$post_id = $this->conversion
			->where('post_id', $data['post_id'])	
			->take(1)
			->pluck('post_id');
		if($post_id > 0){
			$conversions = $this->conversion->find($post_id);
			$conversions->fill($updateData);
			$conversions->save();
		}
		else {
			$conversionId =  $this->conversion->insertGetId($updateData);
		}
	}
	
}
