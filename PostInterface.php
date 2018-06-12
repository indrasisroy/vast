<?php
namespace Vast\Repo\Post;

interface PostInterface {

	public function create(array $data);

	public function update(array $data);

	public function delete($id, $channel_id);

	public function getPosts();

	public function getPostsByChannelId($channel_id);
	
	public function updatePost(array $data);
	
	public function getUser($channel_id);
	
	public function deletePostByChannel($channel);
	
	public function deletePostByChapter($chapter);
	
	public function getLastPostImageByChannel($channel);
	
	public function getLastPostImageByChapter($chapter);
	
	public function updatePostsOnVideoConversion(array $data);
	
	public function getPostsbyChapter($chapter_id);
	
	public function getPostImageById($post_id);
	
	public function getLastPostByChapter($chapter_id);
	
	public function getPostById($post_id);
	
	public function createWatermark($imageUrl,$waterLogo,$type,$gradient,$forFb);
	
	public function getLastFivePostBasedOnChannel($channel_id);
	
	public function getLastestPostByChannels($channels);
	
	public function updateChannelFeaturedPosts($channel_id,$featured);
	
	public function getEventsBasedOnChannel($channel_id,$event_type);
	
	public function getEventDetailsBasedOnId($id);
	
	public function getEventByPostId($id);
	
	public function getPostsByChannelIdForChannellGrid($channel_id);
	
	public function getVideoPostsByChannel($channel_id);
	
	public function createCroppedImage($img, $poix, $poiy);
	
	public function getGridPostAndChapterDetails(array $postList);

	public function getPostsCountByChannelId($channel_id, $status);

	public function getPostsLimitByChannelId($channel_id, $offset, $order, $count);

	public function getPostIdLimitByChannelId($channel_id, $offset, $pId, $order, $count);

	public function getLastPostByChannel($channel);

	public function getPostsByChannelIdAndDate($channel_id, $time, $order);

	public function getEventPostByChannelId($channel_id, $time);

	public function getEventCodeByChannelId($eventCode, $channel_id, $time);

	public function updateShortUrlByPostId(array $data);	
	
	public function getPostsListByPostId($post_id);

	public function getPostByDate($channel_id, $refer_date, $time);

	public function getOnePostByChannel($channel, $time);
	
	public function getPostsByChannelIdForFeed($channel_id, $offset = 0, $count);
	
	public function getPostsByPostIdForFeed($channel_id, $postId, $count);

	public function getNumOfFavorites($postId, $userId, $count);

	public function getPostByChannelIdAndDate($featuresArr, $time, $order, $limit);

	public function getPostByStatus($post_id, $post_status);
	
	public function getEventPostChapter($chapter_id);
	
	public function getPostByChapter($chapter_id, $time);
	
	public function getPostByChannelAndDate($channel_id, $time);
	
	public function getPostOrderByMax($channel_id, $time);
	
	public function getPostByChapterAndChannel($channel_id, $chapter_id, $time);

	public function getPostByChapterStatus($chapter_id, $time);

	public function getPostsCountByChnlId($channel_id);
	
	public function getPostByHash($hash);
	
	public function getPostByChannel($id);

	public function getChannelNameByPostId($id);

	public function getPostsCountByChannels(array $channelLists, $status);

	public function getPostsByChannelsForFeed(array $channelLists, $offset = 0, $count);
	
	public function getPostByChapterId($chapter_id);

	public function getVIPPostsByChannelId($channelLists);
	
	public function getLastestPostByChapter($chapter_id);	
	
	public function getPostsbyChapterOrderByPostOrder($chapter_id,$post_order);

	public function getPostByChapterIdBasedOnPostOrder($chapter,$postOrder);
}