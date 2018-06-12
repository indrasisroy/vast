<?php

use Illuminate\Support\Facades\URL;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

/*Route::get('/', function()
{
	return View::make('hello');
});*/



//Route::get('admin', 'AdminController@index');

Route::get('/', array('as' => 'home', 'uses' => 'AdminController@login'));
Route::get('cms/logout', array('as' => 'logout', 'uses' => 'AdminController@logout'));
Route::get('cms/login', array('as' => 'login', 'uses' => 'AdminController@login'));
Route::get('cms/googleloginpage', array('as' => 'googleloginpage', 'uses' => 'AdminController@googleloginpage'));
Route::any('cms/post/twitterlogin', array('as' => 'twitterlogin', 'uses' => 'AdminController@twitterlogin'));
//Route::post('cms/login', 'AdminController@doLogin');
Route::post('ajax/upload-avatar-signup', array('as' => 'profile.upload_avatar', 'uses' => 'AjaxController@uploadUserAvatar'));
Route::get('cms/signup', array('as' => 'signup', 'uses' => 'AdminController@signup'));
Route::post('cms/doSignUp', array('as' => 'doSignUp', 'uses' => 'AdminController@doSignUp'));
Route::get('cms/forget-password', array('as' => 'forgetPassword', 'uses' => 'AdminController@forgetPassword'));
Route::post('cms/forget-password', array('as' => 'doForgetPassword', 'uses' => 'AdminController@doForgetPassword'));
Route::get('cms/{remember_token}/reset-password', array('as' => 'password.reset', 'uses' => 'AdminController@resetPassword'));
Route::post('cms/doResetPassword', 'AdminController@doResetPassword');
Route::post('ajax/upload-channel-avatar', array('as' => 'profile.upload_avatar', 'uses' => 'AjaxController@uploadChannelAvatar'));
Route::post('ajax/upload-user-avatar', array('as' => 'profile.user.upload_avatar', 'uses' => 'AjaxController@uploadUserAvatar'));
Route::get('cms/tumblr/token-exist', array('as' => 'tumblr.token.exist', 'uses' => 'AdminController@checkTumblrTokenExist'));
Route::get('cms/facebook/token-exist', array('as' => 'fb.token.exist', 'uses' => 'AdminController@checkFacebookTokenExist'));
Route::get('cms/twitter/token-exist', array('as' => 'twitter.token.exist', 'uses' => 'AdminController@checkTwitterTokenExist'));
Route::get('cms/{reset_token}/reset-usrpwd', array('as' => 'password.reset', 'uses' => 'AdminController@resetUsrpwd'));
Route::post('cms/doResetUsrpwd', 'AdminController@doResetUsrpwd');
Route::any('cms/fbpage', array('as' => 'fbpage', 'uses' => 'AdminController@fbPagePost'));
Route::any('cms/do-video-conversion', array('as' => 'videoConversion', 'uses' => 'AdminController@doVideoConversion'));
Route::post('ajax/get-chapter-story-list', array('as' => 'chapterstory.list', 'uses' => 'AjaxController@getChapterStoryList'));
//Route::get('test/tumblr', array('as' => 'tumblr.test', 'uses' => 'AjaxController@tumblrTest'));
Route::post('ajax/login', 'AjaxController@doLogin');
Route::get('cms/resetmsg', array('as' => 'resetmsg', 'uses' => 'AdminController@resetmsg'));
//Route::get('ajax/share-incremeter', array('as' => 'share.incrementer', 'uses' => 'AppController@advastShareIncrement'));
Route::get('ajax/share-incremeter', array('as' => 'post.share.incrementer', 'uses' => 'AppController@advastPostShareIncrement'));
Route::get('ajax/view-incremeter', array('as' => 'view.incrementer', 'uses' => 'AppController@advastViewIncrement'));


Route::group(array('before' =>'bouncerAccess;adminAccess'), function() {
	Route::get('cms/bouncer/list', array('as' => 'bouncer.list', 'uses' => 'AdminController@bouncerLists'));
	Route::get('cms/bouncer/event/add', array('as' => 'event.add', 'uses' => 'AdminController@bouncerEventAdd'));
	Route::post('ajax/upload-vip-image', array('as' => 'vip.create.image', 'uses' => 'AjaxController@uploadVIPImage'));
	Route::post('ajax/generate-qrcode', array('as' => 'vip.create.qrcode', 'uses' => 'AjaxController@createQRCode'));
	Route::post('ajax/validate-event-creation-details', array('as' => 'vip.create.event.validation', 'uses' => 'AjaxController@createEventValidation'));
	Route::post('ajax/add-post', array('as' => 'post.do_add', 'uses' => 'AjaxController@addPost'));
	Route::get('cms/bouncer/event/{id}/edit', array('as' => 'bouncer.event.edit', 'uses' => 'AdminController@bouncerEventEdit'));
	Route::get('cms/bouncer/event/{id}/edit/{type}', array('as' => 'bouncer.event.edit', 'uses' => 'AdminController@bouncerEventEdit'));
	Route::post('ajax/edit-event', array('as' => 'event.edit', 'uses' => 'AjaxController@doPostEdit'));
	Route::post('ajax/update-events-based-on-selection', array('as' => 'update.eventslist.onselection', 'uses' => 'AjaxController@updateEventsListBasedOnSelection'));
	Route::post('ajax/get-event-details-based-on-id', array('as' => 'event.getdetails', 'uses' => 'AjaxController@getEventDetailsBasedOnId'));
	Route::post('ajax/delete-event', array('as' => 'event.delete.item', 'uses' => 'AjaxController@deleteEvent'));
});

# Admin modules. All the Admin routes must be writing in side this call
Route::group(array('before' => 'adminAccess'), function() {
	Route::get('cms/', 'AdminController@index');
	Route::get('cms/fb', array('as' => 'fb', 'uses' => 'AdminController@fbCode'));	
	Route::get('cms/account/setting', array('as' => 'profile', 'uses' => 'AdminController@accountCreate'));
	Route::post('cms/account/setting', array('as' => 'profile.edit', 'uses' => 'AdminController@updateChannel'));
	Route::post('ajax/update-vast-name', array('as' => 'profile.update_vast', 'uses' => 'AjaxController@updateVastName'));
	Route::get('cms/post/add', array('as' => 'post.add', 'uses' => 'AdminController@addPost'));
	//Route::post('ajax/add-post', array('as' => 'post.do_add', 'uses' => 'AjaxController@addPost'));
	Route::post('ajax/edit-post', array('as' => 'post.do_edit', 'uses' => 'AjaxController@doPostEdit'));
	Route::post('ajax/share-post', array('as' => 'post.do_share', 'uses' => 'AjaxController@doShareEdit'));
	//Route::get('post/edit', array('as' => 'post.edit', 'uses' => 'HomeController@editPost'));
	Route::get('cms/post/{id}/edit', array('as' => 'post.edit', 'uses' => 'AdminController@editPost'));
	Route::get('cms/post/{id}/share', array('as' => 'post.share', 'uses' => 'AdminController@sharePost'));
	Route::get('cms/feeds', array('as' => 'feeds', 'uses' => 'AdminController@feeds'));
	Route::get('ajax/get-feeds', array('as' => 'feeds.get', 'uses' => 'AjaxController@getFeeds'));
	Route::delete('post/{id}/delete', array('as' => 'post.delete', 'uses' => 'AjaxController@deletePost'));
	Route::get('post/{id}/delete_post', array('as' => 'posts.delete', 'uses' => 'AjaxController@deletePosts'));
	Route::post('post/{id}/delete_post', array('as' => 'posts.delete', 'uses' => 'AjaxController@deletePosts'));
	Route::post('ajax/upload-video-post', array('as' => 'post.video', 'uses' => 'AjaxController@uploadVideoPost'));
	Route::post('ajax/upload-music-post', array('as' => 'post.music', 'uses' => 'AjaxController@uploadMusicPost'));
	Route::get('cms/message/write', array('as' => 'message.write', 'uses' => 'AdminController@messageWrite'));
	Route::get('cms/message/inbox', array('as' => 'message', 'uses' => 'AdminController@messageInbox'));
	Route::post('ajax/upload-photo-post', array('as' => 'post.photo', 'uses' => 'AjaxController@uploadPhotoPost'));
	
	//========================================added on 09.06.2018=================================================//
	Route::post('ajax/upload-video-post-new', array('as' => 'post.photo', 'uses' => 'AjaxController@uploadVideoPostNew'));
	//=========================================end on 09.06.2018===================================================//
	
	Route::post('ajax/upload-livestream-photo', array('as' => 'livestream.photo', 'uses' => 'AjaxController@uploadLivestreamPhoto'));
	Route::post('ajax/upload-photo-message', array('as' => 'message.photo', 'uses' => 'AjaxController@uploadPhotoMessage'));
	Route::get('cms/message/{id}/view', array('as' => 'message.view', 'uses' => 'AdminController@messageView'));
	Route::get('cms/message/{id}/reply', array('as' => 'message.reply', 'uses' => 'AdminController@messageReply'));
	Route::post('`', 'AdminController@doMessageWrite');
	Route::post('ajax/update-gplus', array('as' => 'admin.gplus', 'uses' => 'AjaxController@updateGPlusToken'));
	Route::post('ajax/upload-sec-video', array('as' => 'post.secvideo', 'uses' => 'AjaxController@uploadSecVideo'));
	Route::post('ajax/upload-sec-xml', array('as' => 'post.secxml', 'uses' => 'AjaxController@uploadSecXml'));
	Route::post('ajax/upload-sec-audio', array('as' => 'post.secaudio', 'uses' => 'AjaxController@uploadSecAudio'));
	Route::post('ajax/upload-sec-image', array('as' => 'post.secimage', 'uses' => 'AjaxController@uploadSecImage'));
	Route::post('ajax/delete-music', array('as' => 'profile.delete_music', 'uses' => 'AjaxController@updateMusic'));
	Route::post('cms/message/delete', array('as' => 'message.delete', 'uses' => 'AdminController@doMessageDelete'));
	Route::post('cms/post/edit', array('as' => 'post.edit.data', 'uses' => 'AdminController@doPostEdit'));
	Route::post('ajax/add-chapter', array('as' => 'chapter.do_add', 'uses' => 'AjaxController@addChapter'));
	Route::get('cms/instagram/login', array('as' => 'instagram.login', 'uses' => 'AdminController@instagramLogin'));
	Route::get('cms/instagram/callback', array('as' => 'instagram.callback', 'uses' => 'AdminController@instagramCallback'));
	Route::get('cms/tumblr/login', array('as' => 'tumblr.login', 'uses' => 'AdminController@tumblrLogin'));
	Route::get('cms/tumblr/callback', array('as' => 'tumblr.callback', 'uses' => 'AdminController@tumblrCallback'));
	Route::get('cms/twitter/login', array('as' => 'twitter.login', 'uses' => 'AdminController@twitterLogin'));
	Route::get('cms/twitter/callback', array('as' => 'twitter.callback', 'uses' => 'AdminController@twitterCallback'));
	Route::post('ajax/poi-coords', array('as' => 'post.poicoords', 'uses' => 'AjaxController@poiCoords'));
	Route::post('ajax/crop-image', array('as' => 'crop.image', 'uses' => 'AjaxController@cropImage'));
	Route::post('ajax/delete-uploaded-item', array('as' => 'uploadeditem.delete', 'uses' => 'AjaxController@deleteUploadedItemFromS3'));
	Route::post('ajax/convert-uploaded-video-format', array('as' => 'uploadeditem.format', 'uses' => 'AjaxController@convertUploadedVideoFormat'));
	Route::post('ajax/video-conversion-status', array('as' => 'uploadeditem.status', 'uses' => 'AjaxController@videoConversionStatus'));
	Route::get('ajax/video-conversion-status', array('as' => 'uploadeditem.status', 'uses' => 'AjaxController@videoConversionStatus'));
	Route::get('ajax/convert-uploaded-video-format', array('as' => 'uploadeditem.format', 'uses' => 'AjaxController@convertUploadedVideoFormat'));
	Route::post('ajax/move-user-selected-file', array('as' => 'thumbnail.move', 'uses' => 'AjaxController@moveUserSelectedFile'));
	Route::post('ajax/twitter/unlink', array('as' => 'twitter.unlink', 'uses' => 'AjaxController@twitterUnlink'));
	Route::post('ajax/facebook/unlink', array('as' => 'facebook.unlink', 'uses' => 'AjaxController@facebookUnlink'));
	Route::post('ajax/tumblr/unlink', array('as' => 'tumblr.unlink', 'uses' => 'AjaxController@tumblrUnlink'));	
	Route::post('ajax/get-uploaded-video-thumbs', array('as' => 'uploadedvideo.thumbs', 'uses' => 'AjaxController@getUploadedVideoThumbs'));
	Route::post('ajax/thumb-creation-status', array('as' => 'uploadedvideo.thumbstatus', 'uses' => 'AjaxController@getThumbCreationStatus'));
	Route::post('ajax/update-livestream-data', array('as' => 'livestream.update', 'uses' => 'AjaxController@updateLivestreamData'));
	Route::get('cms/channel/explore', array('as' => 'channelexplore.view', 'uses' => 'AdminController@channelExploreView'));
	Route::post('cms/channel/user-edit-chapters', array('as' => 'chapters.useredit.manageexplore', 'uses' => 'AjaxController@userEditManageExploreNetwork'));
	Route::post('ajax/upload-chapter-story-cover-image', array('as' => 'chapterstory.coverimage', 'uses' => 'AjaxController@uploadChapterStoryCoverImage'));
	Route::post('ajax/update-artist-chapter-story-cover', array('as' => 'chapter.story.coverimage', 'uses' => 'AjaxController@updateChapterStoryCoverImage'));
	Route::post('ajax/update-manage-explore-flag', array('as' => 'user.manageexplore.publishchannelnow', 'uses' => 'AjaxController@publishChannelNow'));
	Route::post('ajax/update-video-conversion-percent', array('as' => 'update.videoconversion.percent', 'uses' => 'AjaxController@updateVideoConversionPercent'));
	Route::get('cms/channel/explore/edit', array('as' => 'channelexplore.edit', 'uses' => 'AdminController@channelExploreEdit'));
	Route::get('cms/channel/managesocialicons', array('as' => 'channel.managesocialicons', 'uses' => 'AdminController@channelManageSocialIcons'));
	Route::get('cms/channel/editcarousel', array('as' => 'channel.editcarousel', 'uses' => 'AdminController@channelEditCarousel'));
	Route::get('cms/channel/chapter/{id}/edit', array('as' => 'channel.chapter.edit', 'uses' => 'AdminController@channelEditChapter'));
	Route::get('cms/channel/chapter/post/{id}/edit', array('as' => 'channel.chapter.post.edit', 'uses' => 'AdminController@channelEditChapterPost'));
	Route::post('ajax/update-manage-explore-edit', array('as' => 'manageexplore.update.nonfeatured', 'uses' => 'AjaxController@updateManageExploreNonFeatured'));
	Route::post('ajax/update-managesocialicons', array('as' => 'manageexplore.update.socialicons', 'uses' => 'AjaxController@updateSocialIconsByChannel'));
	Route::post('ajax/manageexploreupdatechapter', array('as' => 'manageexplore.update.chapter', 'uses' => 'AjaxController@manageExploreUpdateChapter'));
	Route::post('ajax/update-posts-based-on-chapter-selected', array('as' => 'featured.update.chapter.posts', 'uses' => 'AjaxController@updatePostsBasedOnChapterSelected'));
	Route::post('cms/channel/editcarousel', array('as' => 'channel.editcarousel.save', 'uses' => 'AdminController@channelEditCarouselSave'));
	Route::post('ajax/increment-post-like', array('as' => 'post.like.increment', 'uses' => 'AjaxController@incrementPostsLike'));
	//Route::post('ajax/upload-vip-image', array('as' => 'vip.create.image', 'uses' => 'AjaxController@uploadVIPImage'));
	//Route::post('ajax/generate-qrcode', array('as' => 'vip.create.qrcode', 'uses' => 'AjaxController@createQRCode'));
	//Route::post('ajax/validate-event-creation-details', array('as' => 'vip.create.event.validation', 'uses' => 'AjaxController@createEventValidation'));
	//Route::get('cms/bouncer/list', array('as' => 'bouncer.list', 'uses' => 'AdminController@bouncerLists'));
	
	//Route::get('cms/bouncer/event/add', array('as' => 'event.add', 'uses' => 'AdminController@bouncerEventAdd'));
	//Route::post('ajax/update-events-based-on-selection', array('as' => 'update.eventslist.onselection', 'uses' => 'AjaxController@updateEventsListBasedOnSelection'));
	//Route::post('ajax/get-event-details-based-on-id', array('as' => 'event.getdetails', 'uses' => 'AjaxController@getEventDetailsBasedOnId'));
	//Route::post('ajax/delete-event', array('as' => 'event.delete.item', 'uses' => 'AjaxController@deleteEvent'));
	Route::get('cms/bouncer/admins', array('as' => 'bouncer.admin.list', 'uses' => 'AdminController@bouncerAdminLists'));
	Route::get('cms/bouncer/add/admin', array('as' => 'bouncer.admin.add', 'uses' => 'AdminController@bouncerAddAdmin'));
	Route::post('ajax/bouncer-add-new', array('as' => 'bouncer.admin.create', 'uses' => 'AjaxController@createNewBouncerAdmin'));
	Route::post('ajax/delete-bouncer-admin', array('as' => 'delete.user', 'uses' => 'AjaxController@deleteUser'));
	Route::get('cms/bouncer/admin/{id}/edit', array('as' => 'bouncer.admin.edit', 'uses' => 'AdminController@bouncerEditAdmin'));
	Route::post('ajax/bouncer-update-user', array('as' => 'bouncer.admin.update', 'uses' => 'AjaxController@updateBouncerUser'));
	Route::post('ajax/livestream-go-live', array('as' => 'livestream.golive', 'uses' => 'AjaxController@livestreamGoLive'));
	Route::get('cms/post/{id}/edit/{type}', array('as' => 'post.edit.explore', 'uses' => 'AdminController@editPost'));
	
	Route::get('cms/createjson/{id}', array('as' => 'create.advastjson', 'uses' => 'AjaxController@createAdvastJson'));
	Route::get('cms/createnetworkjson/{id}', array('as' => 'create.advastjson', 'uses' => 'AjaxController@createAdvastNetworkJson'));
	Route::post('ajax/upload-settings-channel-cover', array('as' => 'settings.channel.cover', 'uses' => 'AjaxController@uploadSettingsChannelCover'));
	Route::get('cms/update-featured-youtube/{id}/{youtube}', array('as' => 'update.featured.youtube', 'uses' => 'AjaxController@updateFeaturedYoutubeVideo'));
	Route::get('cms/bloggers-embed', array('as' => 'channel.bloggers-embed', 'uses' => 'AdminController@channelBloggersEmbed'));
	Route::post('ajax/app-cover', array('as' => 'settings.app.cover', 'uses' => 'AjaxController@uploadSettingsAppCover'));
	Route::post('ajax/delete-chapter-post', array('as' => 'chapter.post.delete', 'uses' => 'AjaxController@chapterPostDelete'));
	Route::post('ajax/save-crop-image-setting', array('as' => 'settings.crop.image', 'uses' => 'AjaxController@accountSettingsCropImages'));
	Route::post('ajax/update-manage-explore-chapter-posts-sort-order', array('as' => 'manageexplore.chapter.posts.sort', 'uses' => 'AjaxController@manageExploreChapterPostsSort'));
	Route::post('ajax/resize-image-uploaded-via-embedly', array('as' => 'user.resize.image.via.embedly', 'uses' => 'AjaxController@resizeImageUploadedViaEmbedly'));
});

# Super admin modules. All the Super admin routes must be writing in side this call
Route::group(array('before' => 'superadminAccess'), function() {
 	
	Route::get('cms/superadmin/list-users', array('as' => 'superadmin.list.users', 'uses' => 'AdminController@listAdminUsers'));
	Route::get('cms/superadmin/users/{id}/view', array('as' => 'superadmin.user.view', 'uses' => 'AdminController@adminUserView'));
	Route::get('cms/superadmin/users/add', array('as' => 'superadmin.user.add', 'uses' => 'AdminController@adminAddUserView'));
	Route::post('cms/superadmin/users/add', array('as' => 'superadmin.user.add', 'uses' => 'AdminController@adminAddUser'));
	Route::post('cms/superadmin/users/update', 'AdminController@doUpdateAdminUserDetails');
	Route::get('cms/superadmin/featured-chapters', array('as' => 'superadmin.featured.view', 'uses' => 'AdminController@featuredChapters'));
	Route::post('ajax/get-chapters', array('as' => 'get.chapters', 'uses' => 'AjaxController@getChapters'));
	Route::post('ajax/delete-user', array('as' => 'delete.user', 'uses' => 'AjaxController@deleteUser'));
	Route::post('ajax/set-chapter-status', array('as' => 'setstatus.chapters', 'uses' => 'AjaxController@setChapterStatus'));
	Route::get('cms/superadmin/channels', array('as' => 'superadmin.list.channels', 'uses' => 'AdminController@listChannels'));
	Route::post('ajax/set-channel-status', array('as' => 'setstatus.channels', 'uses' => 'AjaxController@setChannelStatus'));
	Route::post('ajax/delete-channel-details', array('as' => 'deleteDetails.channels', 'uses' => 'AjaxController@deleteChannelDetails'));
	Route::post('ajax/delete-chapter-details', array('as' => 'deleteDetails.chapters', 'uses' => 'AjaxController@deleteChapterDetails'));
	Route::post('ajax/get-last-post-image', array('as' => 'manageartist.getlastpostimage', 'uses' => 'AjaxController@getLastPostImage'));
	Route::post('ajax/get-toc-channel-image', array('as' => 'manageartist.getlastpostimage', 'uses' => 'AjaxController@getLastPostImage'));
	Route::post('ajax/manage-explore-add-new-chapter', array('as' => 'manageartist.addnewchapter', 'uses' => 'AjaxController@manageExploreAddNewChapter'));
	Route::post('ajax/superadmin-manage-explore-save', array('as' => 'superadmin.manageexplore.save', 'uses' => 'AjaxController@manageExploreSave'));
	Route::get('cms/superadmin/list-channelgroups', array('as' => 'superadmin.list.channelgroups', 'uses' => 'AdminController@listChannelGroups'));
	Route::post('ajax/upload-channelgroup-cover-image', array('as' => 'channelgroup.coverimage', 'uses' => 'AjaxController@uploadChannelGroupCoverImage'));
	Route::post('ajax/upload-channel-cover-image', array('as' => 'channel.coverimage', 'uses' => 'AjaxController@uploadChannelCoverImage'));
	Route::post('ajax/upload-story-cover-image', array('as' => 'story.coverimage', 'uses' => 'AjaxController@uploadStoryCoverImage'));
	Route::post('ajax/get-channel-group-details', array('as' => 'channelgroup.getgroupdetails', 'uses' => 'AjaxController@getChannelGroupById'));
	Route::post('ajax/delete-channel-group', array('as' => 'channelgroup.delete', 'uses' => 'AjaxController@deleteChannelGroupById'));
	Route::get('cms/superadmin/add-cover-page', array('as' => 'superadmin.list.addcoverpage', 'uses' => 'AdminController@addCoverPage'));
	Route::get('cms/superadmin/managenetwork/{id}/view', array('as' => 'superadmin.managenetwork.view', 'uses' => 'AdminController@manageNetworkView'));
	Route::post('ajax/get-channel-list-alphabatically', array('as' => 'channellist.aphabatically', 'uses' => 'AjaxController@getChannelListAlphabetically'));
	Route::post('ajax/get-channel-chapter-list', array('as' => 'chapterlist.channelwise', 'uses' => 'AjaxController@getChannelChapter'));
	Route::post('ajax/get-chapter-storylist', array('as' => 'storylist.chapterwise', 'uses' => 'AjaxController@getChapterStory'));	
	Route::post('ajax/superadmin-manage-story', array('as' => 'superadmin.managestory.save', 'uses' => 'AjaxController@manageStorySave'));
	//Route::post('ajax/superadmin-manageexplore-getlastpostimage-channel', array('as' => 'superadmin.manageexplore.getlastpostimagechannel', 'uses' => 'AjaxController@getLastPostImageByChannel'));
	Route::post('ajax/superadmin-manageexplore-get-toc-image', array('as' => 'superadmin.manageexplore.gettocimage', 'uses' => 'AjaxController@getTocChannelImage'));
	Route::post('cms/superadmin/editManageExploreNetwork', array('as' => 'superadmin.editManageExploreNetwork', 'uses' => 'AdminController@editManageExploreNetwork'));
	Route::post('ajax/superadmin-getartistchapter', array('as' => 'superadmin.getartistchapter', 'uses' => 'AjaxController@getArtistChapter'));
	Route::get('cms/superadmin/managechapter/{id}/list', array('as' => 'superadmin.managechapter.list', 'uses' => 'AdminController@manageNetworkChapterList'));
	Route::get('cms/superadmin/managechapter/{id}/view', array('as' => 'superadmin.managechapter.view', 'uses' => 'AdminController@manageChapterView'));
	Route::post('ajax/superadmin-check-screenname', array('as' => 'superadmin.checkscreenname', 'uses' => 'AjaxController@checkScreenName'));
	Route::get('cms/superadmin/managestories/{id}/list', array('as' => 'superadmin.managestories.list', 'uses' => 'AdminController@manageNetworkStoriesList'));
	Route::post('ajax/update-network-story-cover', array('as' => 'superadmin.updatenetworkstorycover', 'uses' => 'AjaxController@updateNetworkStoryCover'));
	Route::get('cms/superadmin/view/{id}/{type}/explore/image', array('as' => 'superadmin.view.explore.image', 'uses' => 'AdminController@viewExploreImage'));
	Route::get('cms/superadmin/version/add', array('as' => 'superadmin.version.add', 'uses' => 'AdminController@adminAddVersionView'));
	Route::post('cms/superadmin/version/add', array('as' => 'superadmin.version.add', 'uses' => 'AdminController@adminAddVersion'));
	Route::get('cms/superadmin/slide-show', array('as' => 'superadmin.slideshow.view', 'uses' => 'AdminController@viewSlideShow'));
	Route::get('cms/superadmin/slide-show/{id}/edit', array('as' => 'superadmin.slideshow.add', 'uses' => 'AdminController@viewSlideShowEdit'));
	Route::post('ajax/update-slide-details', array('as' => 'superadmin.slideshow.edit', 'uses' => 'AjaxController@updateSlideDetails'));
	Route::post('ajax/sort-slides', array('as' => 'superadmin.slideshow.sort', 'uses' => 'AjaxController@sortSlideShowSlides'));
	Route::post('ajax/resize-uploded-slides', array('as' => 'superadmin.slideshow.resize', 'uses' => 'AjaxController@resizeUploadedSlides'));
	Route::any('cms/upload-file', array('as' => 'superadmin.file.upload', 'uses' => 'AdminController@uploadFile'));
	Route::get('cms/update-post-details-to-another/{id}/{image}', array('as' => 'update.postdetails.toanother', 'uses' => 'AdminController@updatePostDetailsToAnother'));
	Route::post('ajax/filter-admin-user-lists', array('as' => 'superadmin.userlist.filter', 'uses' => 'AjaxController@adminUserListFilter'));
	Route::post('ajax/reorder-network-rearrangement', array('as' => 'superadmin.network.rearrangement', 'uses' => 'AjaxController@networkRearrangements'));
});



Route::get('cms/post/{id}', array('as' => 'post.view', 'uses' => 'AdminController@viewPost'));
Route::get('cms/do-water-marking', array('as' => 'watermarking', 'uses' => 'AjaxController@doWatermarking'));
Route::get('cms/player/{id}', array('as' => 'twitter.share.player', 'uses' => 'AdminController@twitterSharePlayer'));
Route::get('cms/channelgrid/create', array('as' => 'channel.grid.create', 'uses' => 'AdminController@channelGridCreate'));
Route::get('cms/channelgrid/channel/{id}', array('as' => 'channel.grid.view', 'uses' => 'AdminController@channelGridView'));
Route::post('ajax/get-channel-grid-deatils', array('as' => 'channelgrid.populate', 'uses' => 'AjaxController@getChannelGridItems'));
Route::post('ajax/channel-grid-post-details', array('as' => 'channelgrid.getpost.details', 'uses' => 'AjaxController@getChannelGridPostDetails'));
Route::post('ajax/generate-channel-grid-details', array('as' => 'channelgrid.generate', 'uses' => 'AjaxController@generateChannelGrid'));
Route::post('ajax/update-video-posts-by-channel', array('as' => 'channelgrid.getVideoThumbs', 'uses' => 'AjaxController@getVideoPostsByChannel'));
Route::get('privacy', array('as' => 'vast.privacy', 'uses' => 'AdminController@privacy'));
Route::get('cms/grid/{id}', array('as' => 'vast.grid', 'uses' => 'AdminController@grid'));
//Route::post('ajax/channel-grid-manipulations', array('as' => 'channelgrid.manipulations', 'uses' => 'AjaxController@channelGridManipulations'));
Route::get('cms/videoplayer/{video}/{type}/{poster}', array('as' => 'grid.videoplayer', 'uses' => 'AdminController@gridVideoPlayer'));
Route::get('cms/do-image-cropping', array('as' => 'imageCropping', 'uses' => 'AjaxController@doImageCropping'));
Route::get('cms/gridview/{channelid}/{video}/{chapterid}/{poster}', array('as' => 'grid.videoplayer', 'uses' => 'AdminController@gridView'));
Route::get('cms/gridview/app/{channelid}', array('as' => 'grid.videoplayer', 'uses' => 'AdminController@gridViewApp'));
Route::get('cms/viewgrid/app/{channelid}', array('as' => 'grid.videoplayer.responsive', 'uses' => 'AdminController@gridViewResponsiveApp'));
Route::get('cms/advastgrid/app/{channelid}/{bannertype}', array('as' => 'advast.grid', 'uses' => 'AdminController@adVastBannerApp'));
Route::get('about', array('as' => 'vast.about', 'uses' => 'AdminController@about'));
Route::get('terms', array('as' => 'vast.terms', 'uses' => 'AdminController@terms'));
Route::get('download', array('as' => 'vast.download', 'uses' => 'AdminController@download'));
Route::get('imagine', array('as' => 'vast.imagine', 'uses' => 'AdminController@imagineView'));
Route::get('whereisthelove', array('as' => 'vast.whereisthelove', 'uses' => 'AdminController@whereisthelove'));
Route::get('{vastname}', array('as' => 'vast.website', 'uses' => 'AdminController@websiteView'));
Route::get('blogger/{vastname}', array('as' => 'vast.website.blogger', 'uses' => 'AdminController@websiteBloggerView'));
Route::any('genshorturl/{id}', array('as' => 'vast.shorturl', 'uses' => 'AdminController@genNewShortUrl'));
Route::get('imagine/bloggers-embed', array('as' => 'vast.imagine.bloggers-embed', 'uses' => 'AdminController@imagineBloggersEmbed'));
Route::get('whereisthelove/bloggers-embed', array('as' => 'vast.whereisthelove.bloggers-embed', 'uses' => 'AdminController@whereistheloveBloggersEmbed'));
Route::get('parsons/bloggers-embed', array('as' => 'vast.parsons.bloggers-embed', 'uses' => 'AdminController@parsonsBloggersEmbed'));
Route::get('adamfranzino/bloggers-embed', array('as' => 'vast.adamfranzino.bloggers-embed', 'uses' => 'AdminController@adamfranzinoBloggersEmbed'));
Route::get('genesis/bloggers-embed', array('as' => 'vast.genesis.bloggers-embed', 'uses' => 'AdminController@genesisBloggersEmbed'));
Route::post('ajax/upload-channel-cover-image', array('as' => 'channel.coverimage', 'uses' => 'AjaxController@uploadChannelCoverImage'));
Route::any('resize-image', array('as' => 'vast.resize.image', 'uses' => 'AdminController@resizeImage'));


/* v1 routes */

/* Client-api routes start here */
Route::get('cms/client-api/login', 'AppController@doLogin');
Route::get('cms/client-api/logout', 'AppController@doLogout');

/* New app client-api starts here */
Route::any('client-api/login', 'VastController@doLogin');
Route::any('client-api/logout', 'VastController@doLogout');
Route::any('client-api/forgot-password', 'VastController@forgotPassword');
Route::any('client-api/get-own-info', 'VastController@getOwnInfo');
Route::any('client-api/update-user-info', 'VastController@updateUserInfo');
Route::any('client-api/user-feed', 'VastController@userFeed');
Route::any('client-api/publish_post', 'VastController@publishPost');
Route::any('client-api/delete_post', 'VastController@deletePost');
Route::any('client-api/scheduled', 'VastController@scheduledPost');
Route::any('client-api/publish_message', 'VastController@publishMessage');
Route::any('client-api/my-messages', 'VastController@getMessages');
Route::any('client-api/edit_livestream', 'VastController@editLivestream');
Route::any('client-api/remove_livestream', 'VastController@removeLivestream');
Route::any('client-api/register', 'VastController@doRegister');
Route::any('client-api/validate-parameter', 'VastController@doValidateParameter');
Route::any('client-api/change-password', 'VastController@changePassword');
Route::any('client-api/follow', 'VastController@followChannel');
Route::any('client-api/like', 'VastController@addFavorite');
Route::any('client-api/subscribe', 'VastController@doSubscribe');
Route::any('client-api/notifications', 'VastController@getNotifications');
Route::any('client-api/push_notifications', 'VastController@pushNotifications');
Route::any('client-api/unsubscribe_apns', 'VastController@unsubscribeApns');
Route::any('client-api/read-message', 'VastController@readMessage');
Route::any('client-api/read-notification', 'VastController@readNotification');
Route::any('client-api/inc-shares-count', 'VastController@incrementShareCount');
Route::any('client-api/appversion', 'VastController@appVersion');
Route::any('client-api/favorites', 'VastController@userFavorite');
Route::any('client-api/get-channel-group', 'VastController@getChannelGroup');
Route::any('client-api/get-popular-channels', 'VastController@getPopularChannels');
Route::any('client-api/explore', 'VastController@explore');
Route::any('client-api/get-channel', 'VastController@getChannel');
Route::any('client-api/my-posts', 'VastController@getMyPosts');
Route::any('client-api/messages', 'VastController@getFollowedMessages');
Route::any('client-api/edit-chapter', 'VastController@editChapter');
Route::any('client-api/edit-featured', 'VastController@editFeatured');
Route::any('client-api/edit-chapters-order', 'VastController@editChaptersOrder');
Route::any('client-api/edit-sociallink', 'VastController@editSocialLink');
Route::any('client-api/track-share', 'VastController@trackShare');
Route::any('client-api/activities', 'VastController@getActivities');
Route::get('client-api/{reset_token}/reset-apppwd', array('as' => 'app.pwd.reset', 'uses' => 'VastController@resetUsrpwd'));
Route::post('client-api/doResetUsrpwd', 'VastController@doResetUsrpwd');
Route::get('client-api/resetmsg', array('as' => 'app.resetmsg', 'uses' => 'VastController@resetmsg'));
Route::get('client-api/del-message', array('as' => 'app.mymessage', 'uses' => 'VastController@deleteMessage'));
Route::get('client-api/channel-search', array('as' => 'app.channelsearch', 'uses' => 'VastController@channelSearch'));
Route::get('client-api/vip-content', array('as' => 'app.channelsearch', 'uses' => 'VastController@vipContent'));
Route::get('client-api/welcome', array('as' => 'app.welcome', 'uses' => 'VastController@welcomeSlide'));
Route::any('client-api/edit-posts-order', 'VastController@editPostsOrder');

