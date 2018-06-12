@extends('layouts.default')

@section('head')
@if (isset($data['viewmode']) && $data['viewmode'] && isset($data['feeds'][0]))
		<meta name="apple-itunes-app" content="app-id=853570494, app-argument=com.vastplatform.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->vast_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}" />
		<!--	[START]	Open Graph data for FB	-->
		<!--meta property="fb:app_id" content="1549126295300252" /-->
        @if ( $data['feeds'][0]->type=='image' || $data['feeds'][0]->type=='event' )
     	<meta property="og:type" content="website">
        <meta property="og:title" @if ($data['feeds'][0]->title=='') content="Get closer to your favorite artists - VAST" @else content="{{ $data['feeds'][0]->title }}" @endif />
		<meta property="og:description" @if ($data['feeds'][0]->story=='') content="VAST is the ultimate fan experience. Follow your favorite artists to get exclusive content, access to fan experiences and much more! https://prod.thefutureisvast.us" @else content="{{ $data['feeds'][0]->story }}" @endif />
		<meta property="og:url" content="{{ URL::full() }}" />
       	<meta property="og:image" content={{ $data['og-image'] }} />
        <meta property="og:site_name" content="VAST" />
		<!--	[END]	Open Graph data for FB	-->
		
        <!--	[START]	Twitter Card data	-->
        <meta name="twitter:card" content="summary_large_image">
		<meta name="twitter:site" content="@GetVASTnow">
		<meta name="twitter:url" content="{{ URL::full() }}">
		<meta name="twitter:title" @if ($data['feeds'][0]->title=='') content="Get closer to your favorite artists - VAST"  @else content="{{ $data['feeds'][0]->title }}" @endif />
		<meta name="twitter:description" @if ($data['feeds'][0]->story=='') content="VAST is the ultimate fan experience. Follow your favorite artists to get exclusive content, access to fan experiences and much more! https://prod.thefutureisvast.us" @else content="{{ $data['feeds'][0]->story }}" @endif />
		@if ( $data['feeds'][0]->subscription_id=='')
			<meta name="twitter:image:src" content="{{ $data['feeds'][0]->sd_url ? URL::to('/').'/cms/do-water-marking?path='.S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->sd_url)).'&type=0&fb=0' : URL::to('images/no_image.png') }}" />
		@else
			<meta name="twitter:image:src" content="{{ $data['feeds'][0]->sd_url ? URL::to('/').'/cms/do-water-marking?path='.S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->sd_url)).'&type=1&fb=0' : URL::to('images/no_image.png') }}" />
		@endif
		<!--meta name="twitter:image:src" content="{{ $data['feeds'][0]->sd_url ? S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->sd_url)) : URL::to('images/no_image.png') }}"-->
		<meta name="twitter:app:name:iphone" content="GetVASTnow"/>
		<meta name="twitter:app:id:iphone" content="853570494"/>
		<meta name="twitter:app:url:iphone" content="com.vastplatform.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->first_name . $data['feeds'][0]->channel->last_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}"/>
		<meta name="twitter:app:name:ipad" content="GetVASTnow"/>
		<meta name="twitter:app:url:ipad" content="com.vastplatform.vastonline://channelId={{ $data['feeds'][0]->channel->channel_id }}&channelName={{ $data['feeds'][0]->channel->first_name . $data['feeds'][0]->channel->last_name }}&chapterId={{ $data['feeds'][0]->chapter->chapter_id }}&chapterName={{ $data['feeds'][0]->chapter->chapter_name }}&postId={{$data['post_id']}}"/>
		<!--	[END]	Twitter Card data	-->
		
        @elseif ( $data['feeds'][0]->type=='music')
 		<!--	[START]	Open Graph data for FB 	-->
        <meta property="og:title" @if ($data['feeds'][0]->title=='') content="Get closer to your favorite artists - VAST" @else content="{{ $data['feeds'][0]->title }}" @endif />
		<meta property="og:description" @if ($data['feeds'][0]->story=='') content="VAST is the ultimate fan experience. Follow your favorite artists to get exclusive content, access to fan experiences and much more! https://prod.thefutureisvast.us" @else content="{{ $data['feeds'][0]->story }}" @endif />
       	<meta property="og:type" content="video">
       	<meta property="og:image" content="{{ $data['feeds'][0]->sd_url ? S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->thumb_url)) : URL::to('images/no_image.png') }}">
       	<meta property="og:site_name" content="Vast">
       	<meta property="og:video" content="https://vastprod.s3.amazonaws.com/audios/VastMusicPlayer.swf?post_id={{$data['post_id']}}&type=1&autoplay=true">
       	<meta property="og:video:type"  content="application/x-shockwave-flash">
        <meta property="og:video:width" content="504" /> 
		<meta property="og:video:height" content="280" />
        <!--	[END]	Open Graph data for FB	-->
		
		<!--	[START]	Twitter Card data	-->
        <meta name="twitter:card" content="player" />
		<meta name="twitter:site" content="@GetVASTnow">
		<meta name="twitter:url" content="{{ URL::full() }}">
        <meta name="twitter:title" @if ($data['feeds'][0]->title=='') content="Get closer to your favorite artists - VAST"  @else content="{{ $data['feeds'][0]->title }}" @endif />
		<meta name="twitter:description" @if ($data['feeds'][0]->story=='') content="VAST is the ultimate fan experience. Follow your favorite artists to get exclusive content, access to fan experiences and much more! https://prod.thefutureisvast.us" @else content="{{ $data['feeds'][0]->story }}" @endif />
        <meta name="twitter:image" content="{{ $data['feeds'][0]->sd_url ? S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->sd_url)) : URL::to('images/no_image.png') }}" />
        <meta name="twitter:player" content="https://prod.thefutureisvast.us/cms/player/{{$data['post_id']}}" />
		<meta name="twitter:player:stream" content="{{ S3::getS3Url(S3::getPostMusicPath($data['feeds'][0]->music_url)) }}" />
		<meta name="twitter:player:stream:content_type" content="audio/mpeg; ">
        <meta name="twitter:player:width" content="480" />
        <meta name="twitter:player:height" content="270" />
		<!--	[END]	Twitter Card data	-->
		
        @elseif ( $data['feeds'][0]->type=='video')
		<!--	[START]	Open Graph data for FB 	-->
        <meta property="og:type" content="video">
        <meta property="og:title" @if ($data['feeds'][0]->title=='') content="Get closer to your favorite artists - VAST" @else content="{{ $data['feeds'][0]->title }}" @endif />
		<meta property="og:description" @if ($data['feeds'][0]->story=='') content="VAST is the ultimate fan experience. Follow your favorite artists to get exclusive content, access to fan experiences and much more! https://prod.thefutureisvast.us" @else content="{{ $data['feeds'][0]->story }}" @endif />
       	<meta property="og:type" content="video">
       	<meta property="og:image" content="{{ $data['feeds'][0]->sd_url ? S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->sd_url)) : URL::to('images/no_image.png') }}">
       	<meta property="og:site_name" content="Vast">
       	<meta property="og:video" content="https://prod.thefutureisvast.us/fbvideoplayer/VastSimpleEmbedPlayer.swf?post_id={{$data['post_id']}}&type=1&autoplay=true">
       	<meta property="og:video:type"  content="application/x-shockwave-flash">
        <meta property="og:video:width" content="472" /> 
		<meta property="og:video:height" content="320" />    
		<!--	[END]	Open Graph data for FB	-->        
        
		<!--	[START]	Twitter Card data	-->
        <meta name="twitter:card" content="player" />
		<meta name="twitter:site" content="@GetVASTnow">
		<meta name="twitter:url" content="{{ URL::full() }}">
        <meta name="twitter:title" @if ($data['feeds'][0]->title=='') content="Get closer to your favorite artists - VAST"  @else content="{{ $data['feeds'][0]->title }}" @endif />
		<meta name="twitter:description" @if ($data['feeds'][0]->story=='') content="VAST is the ultimate fan experience. Follow your favorite artists to get exclusive content, access to fan experiences and much more! https://prod.thefutureisvast.us" @else content="{{ $data['feeds'][0]->story }}" @endif />
        <meta name="twitter:image" content="{{ $data['feeds'][0]->sd_url ? S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->sd_url)) : URL::to('images/no_image.png') }}" />
        <meta name="twitter:player" content="https://prod.thefutureisvast.us/cms/player/{{$data['post_id']}}" />
		<meta name="twitter:player:stream" content="{{ S3::getS3Url(S3::getPostVideoPath($data['feeds'][0]->video_url)) }}" />
		<meta name="twitter:player:stream:content_type" content="video/mp4; ">
        <meta name="twitter:player:width" content="480" />
        <meta name="twitter:player:height" content="270" />
        @endif
		<!--	[END]	Twitter Card data	-->
		
		<!--	[START]	Schema.org markup for Google+	-->
		<meta itemprop="url" content="{{ URL::full() }}">
		<meta itemprop="name" @if ($data['feeds'][0]->title=='') content="Get closer to your favorite artists - VAST" @else content="{{ $data['feeds'][0]->title }}" @endif />
		<meta itemprop="description" @if ($data['feeds'][0]->story=='') content="VAST is the ultimate fan experience. Follow your favorite artists to get exclusive content, access to fan experiences and much more! https://prod.thefutureisvast.us" @else content="{{ $data['feeds'][0]->story }}" @endif />
		<meta itemprop="image" content="{{ $data['feeds'][0]->hdthumb_url ? S3::getS3Url(S3::getPostSdPath($data['feeds'][0]->hdthumb_url)) : URL::to('images/no_image.png') }}">
		<!--	[END]	Schema.org markup for Google+	-->
@endif
@parent
@stop

@section('body')
	@parent
		@if (!isset($data['viewmode']) || !$data['viewmode'])
			<div class="container">
				@include('default.sidebar')
				<div class="content-area col-md-8 feed-content">
			   
				<div class="feed-header-section">
					<div class="feedpage-title" style="height: 100px;">
						<span class="feedpage-title-icon">{{ HTML::image('images/feed-title-icon.png', '') }}</span>
						<span class="feed-title-text">MY POSTS</span>
					</div>
					<div class="feed-title-right">
						<div style="color: #fff;"><strong>Sort by:</strong></div>
						<div class="date_chapter"><strong><a href="javascript:void(0)" class="feed-date active">DATE</a><span class="feed-title-sep">/</span><a href="javascript:void(0)" class="feed-chapter">CHAPTER</a></strong></div>
					</div>
					<div class="clr"></div>
                   <a href="{{ URL::route('post.add'); }}" >
						<input class="vast_btn_fullwidth vast_btn_layout pull-left" type="button" value="POST NOW" placeholder="">
                   		<!--{{ HTML::image('images/btn_post_now.png', '',array('class' =>'img-responsive','style' => 'width:100%;margin-bottom:20px;')) }}-->
                    </a>
					<div class="clr"></div>
					 @if (Session::get('success'))
					<div class="alert alert-success">
						{{ Session::get('success') }}
					</div>
					@endif
					@if (Session::get('error'))
					<div class="alert alert-danger">
						{{ Session::get('error') }}
					</div>
					@endif
					<div id="progress-bar-container">
						<?php $index = 0; ?>

						


						@foreach($data['conversions'] as $conversion)
						<div class="video-progress-bar video-progress-bar{{$index}}" id="video-progress-bar-{{$conversion->post_id}}" >
							<div class="video-progress-bar-progress" style="width:{{$conversion->percent}}%"></div>
							@if($conversion->percent==0)
								<div class="video-progress-bar-pending"></div>
							@else
								<div class="video-progress-bar-pending" style="width:{{100-$conversion->percent}}%;border-top-left-radius:0px;border-bottom-left-radius:0px;"></div>
							@endif
							<div class="video-progress-bar-text">
								<span class="video-progress-bar-post-title">{{"Pending post ".$conversion->post_title." ..."}}</span>
								<span class="video-progress-bar-percent-text">({{$conversion->percent}}%)</span>
							</div>
							<input type="hidden" value="{{$conversion->percent}}" class="progressHidden" /> 
							<input type="hidden" value="{{$conversion->post_id}}" class="postIDHidden" /> 
						</div>
						<?php $index++; ?>
						@endforeach
					</div>
				</div> 
				<div id="feed-contents">
					@foreach ($data['feeds'] as $feed)
					@include('default.post._partial.post', array('feed' => $feed))
					@endforeach
				</div>
				<div class="loading-post">{{ HTML::image('images/loading.gif', '') }} Loading posts...</div>
				@include('default._partial.delete_confirm')
				@include('default._partial.feed_advast_popup')
			  </div><!-- /.container -->
		@else
			<div id="feed-content">
				@foreach ($data['feeds'] as $feed)
				@include('default.post._partial.viewpost', array('feed' => $feed))
				@endforeach
			</div>
        @endif
		@if (isset($data['feeds'][0]))
			@include('default._partial.feed_popup')
			@include('default._partial.feed_popup_secondary')
		@endif

@stop
