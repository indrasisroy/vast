<div class="modal fade" id="feedPopup" role="dialog" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
	<div class="modal-dialog" id="feed-popup-modal-dialog">
    	<div class="modal-content" style="float: left;">
			<div class="feed-popup">
				<div class="feed-popup-head"></div>
                {{ HTML::image(S3::getS3Url(S3::getPostPhotoPath('thumb_390100ac3c3d58561e54be096b96bdc1.jpg')), '', array('class' => 'feed-bg-image feed-bg-image-popup img-responsive')) }}
				<div class="secondary-content"></div>
				<div class="feed-popup-player"></div>
				<div class="feed-popup-desc content-toggle-class">
					<div id="feed-popup-descBottom" class="feed-popup-descBottom">
                    	@if(isset($data['userChannel']))
                    	{{ HTML::image(S3::getS3Url(S3::getChannelAvatarPath('sd/'.$data['userChannel']->avatar)), '', array('id' => 'artist_avatar_image','class' => 'img-responsive img-circle','height' => '42', 'width' => '42')) }}
                        @endif
            			<div class="feed-descTop-right feed-popup-chp"></div>
            		</div>
				</div>
				
                <div class="feed-popup-footer">
					<div class="feed-footer-left" style="position: absolute; bottom: 7px;padding-left:0px;left:5px;">
            			<div id="cart_icon_btn_poper">
                			@if($feed->channel->ticket_url)
                				<div class="cart_icon_btn_poper_block">
                               	{{ html_entity_decode( HTML::link($feed->channel->ticket_url, HTML::image('images/tickets.png', '').'#tickets',array())) }}
                               	</div>
							@endif
                            @if($feed->channel->beats_url)
                            	<div class="cart_icon_btn_poper_block">
                                	{{ html_entity_decode( HTML::link($feed->channel->beats_url, HTML::image('images/beats1.png', '').'#beats',array())) }}
                                </div>
                            @endif
                            @if($feed->channel->spotify_url)
                            	<div class="cart_icon_btn_poper_block">
                               {{ html_entity_decode( HTML::link($feed->channel->spotify_url, HTML::image('images/spotify1.png', '').'#spotify',array())) }}
                                </div>
                            @endif
                            @if($feed->channel->deezer_url)
                            	<div class="cart_icon_btn_poper_block">
                                {{ html_entity_decode( HTML::link($feed->channel->deezer_url, HTML::image('images/deezer1.png', '').'#deezer',array())) }}
                                </div>
                            @endif
							@if($feed->channel->itunes_url)
                				<div class="cart_icon_btn_poper_block">
                                {{ html_entity_decode( HTML::link($feed->channel->itunes_url, HTML::image('images/itunes1.png', '').'#itunes',array())) }}
                                </div>
							@endif
      					</div>
						<div class="feed-footer-span">
                        	{{ HTML::link('#', '',array('class' => 'feedpopup-cart', 'id' => 'cart_btn_icon') )}}
                        </div>
						<div class="clr"></div> 
					</div>
					<div class="feed-footer-right">
                        <div class="feed-footer-span">
                        	{{ html_entity_decode( HTML::link('#', HTML::image('images/feedpopup-music.png', '',array()),array('class' => 'feedpopup-music post-clickable-secondary'))) }}                         
                        </div>
                        <div class="feed-footer-span">
                        	{{ html_entity_decode( HTML::link('#', HTML::image('images/audio-icon.png', '',array()),array('class' => 'feedpopup-video post-clickable-secondary'))) }}    
                        </div>
                        <div class="feed-footer-span">
                        	{{ HTML::link('#', '',array('id'=> 'content_pop_toggler','class' => 'feedpopup-feed toggle-elements active','style' => 'display:block'))}}  
                        </div>
					</div>
					<div class="clr"></div>
				</div>
				<div class="popup-video-icon primary_video_player_toggle" style="display:none;">{{ HTML::image('images/video-icon.png', '') }}</div>
			</div>
  		</div>
		<div class="feed-close">
    		<button id="feed_close_btn" type="button" class="close btn-outl" data-dismiss="modal" aria-hidden="true" style="color: #fff; font-weight: normal;">×</button>
    	</div>
	</div>
</div>
