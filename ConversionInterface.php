<?php
namespace Vast\Repo\Conversion;

interface ConversionInterface {

	public function create(array $data);

	public function update(array $data);
	
	public function getPendingConversion();
	
	public function updateSelectedConversion($id);
	
	public function deleteEntry($slno);
	
	public function getPendingConversionsBasedOnChannel($channel_id);
	
	public function updateVideoConversionPercentage($post_id);

	public function insertPost(array $data);
	
}