<div id="main-page-shortlink" class="container-fluid remove-padding col-xs-12 col-sm-12 col-md-12">

        <div id="feed_post_logo_container" class="feed_post_logo_container add-padding-on-small-screens">
            <div id="feed_post_logo_container_inner" class="">
                <div id="feed_post_social_logo_container_top">               
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{$feed->short_url}}"><span class="feed_post_social_facebook" id="fbSocialShare"></span></a>
					@if($feed->channel->channel_id == '96')
                    <a href="https://twitter.com/intent/tweet?text=Check+out+UNICEF+%7C+%23IMAGINE+videos+from+every+continent+at+bit.ly/imagine+including+this+one&url={{$feed->short_url}}"><span class="feed_post_social_twitter" id="twSocialShare"></span></a>
					@else
					<a href="https://twitter.com/intent/tweet?text=Check My Vast&url={{$feed->short_url}}&via=GetVASTnow"><span class="feed_post_social_twitter" id="twSocialShare"></span></a>
					@endif
                </div>
            </div>
        </div>

        <!--start middle area-->
        <div id="middle-area" class="col-xs-12 col-sm-12 col-md-12 remove-padding">        
            {{ Form::hidden('featured_post', $feed->subscription_id, array('id' => 'featured_post')) }}
            <div class="post-container">
                @if($feed->channel->channel_id == '96' || $feed->channel->channel_id == '24' ||  $feed->channel->channel_id == '178' || $feed->channel->channel_id == '32')
                <div class="imagine_banner_container add-padding-on-small-screens"> 
                    @if($feed->channel->cover != '')
						@if($feed->channel->channel_id == '96')
						<a href="http://support.unicef.org" style="text-decoration:none;" target="_blank">
                        {{ HTML::image(S3::getS3Url(S3::getChannelCoverPath('sd/'.$feed->channel->cover)), '', array('class'=>'img-responsive','id' => 'main_banner_image')) }}
						</a>
						@elseif($feed->channel->channel_id == '178')
						<a href="http://itunes.com/BlackEyedPeas" style="text-decoration:none;" target="_blank">
                        {{ HTML::image(S3::getS3Url(S3::getChannelCoverPath('sd/'.$feed->channel->cover)), '', array('class'=>'img-responsive','id' => 'main_banner_image')) }}
						</a>
						@else
						{{ HTML::image(S3::getS3Url(S3::getChannelCoverPath('sd/'.$feed->channel->cover)), '', array('class'=>'img-responsive','id' => 'main_banner_image')) }}
						@endif
                    <?php /*    @if($feed->channel->channel_id == '96')
                            @if($feed->type == 'video')
                            {{ HTML::image('images/video-share-text-unicef.png', '', array('class' => 'img-responsive', 'id' => 'sub_banner_text')) }}
                            @elseif($feed->type == 'music')
                            {{ HTML::image('images/audio-share-text-unicef.png', '', array('class' => 'img-responsive', 'id' => 'sub_banner_text')) }}
                            @elseif($feed->type == 'image')
                            {{ HTML::image('images/video-share-text-unicef.png', '', array('class' => 'img-responsive', 'id' => 'sub_banner_text')) }}
                            @endif
                        @endif
                     @else
                    <div id="banner_head1">SHARE THIS {{$feed->type}}</div>
                    <div id="banner_head2"><span id='will_donate_text'>UNICEF #ACTofHUMANITY</span></div>
                    <!--<div id="banner_head3">{{ HTML::image('images/donate_to_unicef.png', '', array('class' => 'img-responsive')) }}</div>--> */ ?>
                    @endif
                </div>
                @endif

                @if($feed->subscription_id != "")          
                @if($feed->type == 'video')
                <div id="put-lock">
                    @if(strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iPad'))
                    <a href="com.vast.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->first_name . $data['feeds'][0]->channel->last_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}" style="text-decoration:none;">
                        @else
                        <a href="https://itunes.apple.com/us/" target="_blank" style="text-decoration:none;">
                            @endif
                            <div id="put-lock-image">{{ HTML::image('images/video-lock.png', '', array()) }}</div></a></div>
                @elseif($feed->type == 'music')
                <div id="put-lock">
                    @if(strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iPad'))
                    <a href="com.vast.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->first_name . $data['feeds'][0]->channel->last_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}" style="text-decoration:none;">
                        @else
                        <a href="https://itunes.apple.com/us/" target="_blank" style="text-decoration:none;">
                            @endif
                            <div id="put-lock-image">{{ HTML::image('images/audio-lock.png', '', array()) }}</div></a></div>
                @elseif($feed->type == 'image')
                <div id="put-lock" style="display:block">
                    @if(strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iPad'))
                    <a href="com.vast.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->first_name . $data['feeds'][0]->channel->last_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}" style="text-decoration:none;">
                        @else
                        <a href="https://itunes.apple.com/us/" target="_blank" style="text-decoration:none;">
                            @endif
                            <div id="put-lock-image" style="height:80px;">{{ HTML::image('images/image-lock.png', '', array()) }}</div></a></div>
                @endif
                @endif
                <?php
                $noImageUrl = URL::asset('images/no_image.png');
                if ($feed->type == 'image')
                    $imgUrl = (($feed->thumb_url != "") && ($feed->thumb_url != '0')) ? S3::getS3Url(S3::getPostRetinaPath($feed->thumb_url)) : $noImageUrl;
                elseif ($feed->type == 'music')
                    $imgUrl = (($feed->thumb_url != "") && ($feed->thumb_url != '0')) ? S3::getS3Url(S3::getPostSdPath($feed->thumb_url)) : $noImageUrl;
                elseif ($feed->type == 'video' || $feed->type == 'touchcast_video' || $feed->type == 'event')
                    $imgUrl = (($feed->sd_url != "") && ($feed->sd_url != '0')) ? S3::getS3Url(S3::getPostSdPath($feed->sd_url)) : $noImageUrl;
                else
                    $imgUrl = (($feed->thumb_url != "") && ($feed->thumb_url != '0')) ? S3::getS3Url(S3::getPostSdPath($feed)) : $noImageUrl;
                ?>
                <!--<div class="feed-popup-head">{{ $feed->chapter->chapter_name }}</div>--> 
               <div id="post-bg-image-container" style="position:relative;float:left;">
                    @if($data['post_id'] == 4268)
                    <div class="youtube_video_container_shtlnk">
						<div id="player"></div>
						<input type="hidden" id="featured_youtube_video" value="{{ $feed->channel->featured_youtube_video }}" />
                      <!--iframe width="100%" height="100%" src="https://www.youtube.com/embed/RWKtPRMX0MY" frameborder="0" allowfullscreen></iframe-->
					   <img src="http://img.youtube.com/vi/{{ $feed->channel->featured_youtube_video }}/maxresdefault.jpg" alt="" class="feed-bg-image">
					   <div class="popup-video-icon shortlink-video-container start-video" data-url="{{ $feed->video_url }}" style="display:block;">
						{{ HTML::image('images/video-icon.png', '') }}
						</div>
                    </div>
                    @else
                        <img src="{{$imgUrl}}" alt="" class="feed-bg-image">
                    @endif
                    
                    @if ($feed->type == 'video')
                        @if($data['post_id'] != 4268)
                    <div class="popup-video-icon primary_video_toggle shortlink-video-container" data-url="{{ $feed->video_url }}" style="display:block;">
                        {{ HTML::image('images/video-icon.png', '') }}
                    </div>
                    @endif
                    
                    @endif
                    <div class="secondary-content shortlink-video-container shortlink-audio-container"></div>
                    @if ($feed->video_url) 
                        @if($data['post_id'] != 4268)
                            <div id="primary-content" class="feed-popup-player shortlink-video-container" style="display:none;"></div>
                        @endif
                    <div id="primary-content" class="feed-popup-player shortlink-video-container" style="display:none;"></div>
                    @endif
                    @if ($feed->music_url)
                    <div id="primary-content" class="feed-popup-player shortlink-audio-container primary-music"> 
                        <audio id="popupAudioId" class="hidden-audio-player" data-audio-type ="0" data-title="{{$feed->song}}" data-artist="{{$feed->author}}" onplay="initiateAudioPoster()" controls>
                            <source src="{{ S3::getS3Url(S3::getPostMusicPath($feed->music_url)) }}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                    @endif
                </div>
                

               

                <div id="post_details_container">            
                  <div class="post-details-wrapper">
                    <div class="post-details-img-wrapper"><img src='@if($feed->channel->avatar) {{ S3::getS3Url(S3::getTwitterAvatar($feed->channel->avatar)) }} @endif' class="pull-left img-responsive img-circle" alt="" /></div>
                    <div class="feed-descTop-right">{{ $feed->title }}</div>
                    <p class="view-post-desc-p">{{ $feed->story }}</p>
                  </div>
                  
                 
                </div>
                       
            </div>
            

        </div>
        <!--end middle area-->

        <!--starts bottom area-->
        <div id="bottom-area" class="main-bottom-area add-padding-on-small-screens col-xs-12 col-sm-12 col-md-12 col-lg-12 remove-padding" >
            <div id="post_details_download_container">
                <div class="post_details_downloaders">
                    <!--<div class="app-store-new">
                        {{ HTML::image('images/appstorenew.png', '', array('class' => 'img-responsive')) }}
                    </div>

                    <div class="google-store-new">
                        {{ HTML::image('images/googlestorenew.png', '', array('class' => 'img-responsive')) }}
                    </div> -->

                    <div class="see-more-vast">
                        @if(strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iPad'))
                            <a href="com.vast.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->first_name . $data['feeds'][0]->channel->last_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}" style="text-decoration:none;">
                        @else
                            <a href="https://itunes.apple.com/us/" target="_blank" style="text-decoration:none;">
                        @endif
                        <!--<div class="vast_see_more_conent">{{ HTML::image('images/see-more-content-small.png', '', array('class' => 'img-responsive', 'id' => '')) }}</div>-->
                        <div class="vast_see_more_conent">{{ HTML::image('images/vast_new_logo.png', '', array('class' => 'img-responsive', 'id' => '')) }}</div>
                        </a>
                    </div>
                    
                    
                </div>

               <!-- <div class="see-more-vast-last">
                    @if(strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iPad'))
                        <a href="com.vast.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->first_name . $data['feeds'][0]->channel->last_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}" style="text-decoration:none;">
                    @else
                        <a href="https://itunes.apple.com/us/" target="_blank" style="text-decoration:none;">
                    @endif
                    <!--<div class="vast_see_more_conent">{{ HTML::image('images/see-more-content-large.png', '', array('class' => 'img-responsive', 'id' => 'seemorevast_img')) }}</div>
                    <div class="vast_see_more_conent">{{ HTML::image('images/vast_new_logo.png', '', array('class' => 'img-responsive', 'id' => '')) }}</div>
                    </a>
                </div>-->

            </div>
            
            <div id="post_details_copy_right">&copy; 2018 TheFutureIsVAST.us LLC</div>
        </div>
        <!--end bottom area-->
        
    </div>
    <style>
        body{
            background-color: #0b0b0f;
        }

    </style>

    <script type="text/javascript">
        (function($) {
    $.fn.hasScrollBar = function() {
        return this.get(0).scrollHeight > this.height();
    }
    })(jQuery);
        $(window).on("load orientationchange resize", function () {
            var res = $('#main-page-shortlink').hasScrollBar();
            if(res === true){
                $('#bottom-area').removeClass('addPosition');
            }else{
                $('#bottom-area').addClass('addPosition');
            }
            
        });
		
		
		var tag = document.createElement('script');
		tag.src = "//www.youtube.com/iframe_api";
		var firstScriptTag = document.getElementsByTagName('script')[0];
		firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
		var vidId = document.getElementById('featured_youtube_video').value;
		var player;

		onYouTubeIframeAPIReady = function () {
			player = new YT.Player('player', {
				videoId: vidId,  // youtube video id
				playerVars: {
					'autoplay': 0,
					'rel': 0,
					'showinfo': 0
				},
				events: {
					'onStateChange': onPlayerStateChange
				}
			});
		}

		var p = document.getElementById ("player");
		$(p).hide();

		onPlayerStateChange = function (event) {
			if (event.data == YT.PlayerState.ENDED) {
				$('.start-video').fadeIn('normal');
			}
		}

		$(document).on('click', '.start-video', function () {
			$(this).hide();
			$('.youtube_video_container_shtlnk').css('padding-bottom', '56.25%');
			$("#player").show();
			$(".feed-bg-image").hide();
			player.playVideo();
		});
    </script>
