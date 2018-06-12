<?php

class Conversion extends Eloquent {
	
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'tbl_video_conversion_data';

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'post_id';
	
	protected $fillable = array('post_id','videourl','secvideourl','conv_status','channel_id','percent','post_title');
	
	public $timestamps = false;
	
	
}