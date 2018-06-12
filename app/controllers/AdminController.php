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
use Vast\Repo\Inbox\InboxInterface;
use Vast\Repo\Message\MessageInterface;
use Vast\Repo\Role\RoleInterface;
use Vast\Repo\Conversion\ConversionInterface;
use Vast\Repo\Channelgroup\ChannelgroupInterface;
use Vast\Repo\Channelgrid\ChannelgridInterface;
use Vast\Repo\Manageexplore\ManageexploreInterface;
use Vast\OAuth\OAuthUtil;
use Aws\Common\Credentials\Credentials;
use Aws\Sts\StsClient;
use Aws\S3\S3Client;
use Doctrine\Common\Cache\FilesystemCache;
use Guzzle\Cache\DoctrineCacheAdapter;
use Vast\Repo\EventBouncer\EventBouncerInterface;
use Vast\Repo\Version\VersionInterface;
use Vast\Repo\Share\ShareInterface;
use Vast\Repo\SlideShow\SlideShowInterface;

class AdminController extends BaseController
{
    
    protected $layout = 'layouts.default';
    
    protected $admin, $channel, $chapter, $favorite, $follow, $like, $post, $session, $subscribe, $user, $inbox, $message, $role, $conversion, $channelgroup, $managexplore, $channelgrid, $event, $version, $share,$slideshow;
    
    protected $data = array();
    
    public function __construct(AdminInterface $admin, ChannelInterface $channel, ChapterInterface $chapter, FavoriteInterface $favorite, FollowInterface $follow, LikeInterface $like, PostInterface $post, SessionInterface $session, SubscribeInterface $subscribe, UserInterface $user, InboxInterface $inbox, MessageInterface $message, RoleInterface $role, ConversionInterface $conversion, ChannelgroupInterface $channelgroup, ManageexploreInterface $manageexplore,ChannelgridInterface $channelgrid, EventBouncerInterface $event, VersionInterface $version, ShareInterface $share, SlideShowInterface $slideshow)
    {
        
        $this->beforeFilter('auth', array(
            'except' => array(
                'login',
                'doLogin',
                'signup',
                'doSignUp',
                'forgetPassword',
                'doForgetPassword',
                'resetPassword',
                'doResetPassword',
                'viewPost',
                'resetUsrpwd',
                'doResetUsrpwd',
                'twitterSharePlayer',
                'channelGridView',
				'channelGridCreate',
				'privacy',
				'gridView',
				'gridVideoPlayer',
				'gridViewApp',
				'gridViewResponsiveApp',
				'adVastBannerApp',
				'about',
				'terms',
				'resetmsg',
				'home',
				'download',
				'imagineView',
				'genNewShortUrl',
                'imagineBloggersEmbed',
				'doVideoConversion',
                'whereisthelove',
                'whereistheloveBloggersEmbed',
				'resizeImage',
				'websiteView',
				'parsonsBloggersEmbed',
				'adamfranzinoBloggersEmbed',
				'genesisBloggersEmbed',
				'websiteBloggerView'
            )
        ));
        
        $this->admin         = $admin;
        $this->channel       = $channel;
        $this->chapter       = $chapter;
        $this->favorite      = $favorite;
        $this->follow        = $follow;
        $this->like          = $like;
        $this->post          = $post;
        $this->session       = $session;
        $this->subscribe     = $subscribe;
        $this->user          = $user;
        $this->inbox         = $inbox;
        $this->message       = $message;
        $this->role          = $role;
        $this->conversion    = $conversion;
        $this->channelgroup  = $channelgroup;
        $this->manageexplore = $manageexplore;
		$this->channelgrid   = $channelgrid;
        $this->event         = $event;
		$this->version       = $version;
		$this->share         = $share;
        $this->slideshow     = $slideshow;
        
        if (Auth::user()) {
            if (Auth::user()->role_id == 1) {
                $this->data['userChannel']      = null;
                $this->data['followers']        = null;
                $this->data['subscribers']      = null;
                $this->data['role']             = 1;
                $this->data['channelFlag']      = '0';
                $this->data['manageExpoloreId'] = '0';
				$this->data['version'] = $this->version->getVersion();
				$this->data['share'] = $this->share->getTotalShares(96);
            } elseif (Auth::user()->role_id == 2) {
                $this->data['userChannel']      = $this->channel->getChannelById(Auth::user()->channel->channel_id);
                $this->data['followers']        = $this->follow->getTotalFollowers(Auth::user()->channel->channel_id);
                $this->data['subscribers']      = $this->subscribe->getNumSubscribers(Auth::user()->channel->subscription_id);
                $this->data['role']             = Auth::user()->role_id;
                $this->data['channelFlag']      = $this->manageexplore->getFieldFromManageExplore('mapping_id', Auth::user()->channel->channel_id, 'flag');
                $this->data['manageExpoloreId'] = $this->manageexplore->getFieldFromManageExplore('mapping_id', Auth::user()->channel->channel_id, 'id');
            }
            if (Auth::user()->role_id == 3) {
                $this->data['username']         = Auth::user()->username;
                $this->data['userChannel']      = Auth::user()->channel;
                $this->data['followers']        = null;
                $this->data['subscribers']      = null;
                $this->data['role']             = 3;
                $this->data['channelFlag']      = '0';
                $this->data['manageExpoloreId'] = '0';
            }
        } else {
            $this->data['role'] = null;
        }
    }
    
    public function index()
    {
        $feeds               = $this->post->getPostsByChannelId(Auth::user()->channel->channel_id);
        $this->data['feeds'] = $feeds;
        return Redirect::to('cms/feeds')->with('data', $this->data);
    }
    
    public function login()
    {
        return View::make('default.login')->with('data', $this->data);
    }
    
    public function googleloginpage()
    {
        return View::make('default.googlelogin')->with('data', $this->data);
    }
    
    public function signup()
    {
        return View::make('default.signup')->with('data', $this->data);
    }
    
    public function fbCode()
    {
        
        $fbCode = Input::get('code');
        
        $fb = new Facebook();
        
        $accessToken = $fb->callback($fbCode);
        
        if (!$fb->validateToken(Auth::user()->fb_token)) {
            
            if (is_array($accessToken) && isset($accessToken['fb_link'])) {
                $fbData = array(
                    'fb_token' => $accessToken['fb_token'],
                    'fb_link' => $accessToken['fb_link']
                );
                $this->admin->saveFbToken(Auth::user()->vast_user_id, $fbData);
            }
        }
        $this->data['page_data'] = $accessToken['page_data'];
        return View::make('default.fbpage')->with('data', $this->data);
    }
    
    public function fbPagePost()
    {
        $pageId                = Input::get('fb_page');
        $this->data['page_id'] = $pageId;
        $this->admin->saveFbPageId(Auth::user()->vast_user_id, $pageId);
        return View::make('ajax.facebook.callback')->with('data', $this->data);
    }
    
    public function resetPassword($token)
    {
        $this->data['token'] = $token;
        $token               = Admin::where('remember_token', $token)->first();
        
        if (sizeof($token) > 0) {
            return View::make('default.reset_password')->with('data', $this->data);
        } else {
            return Redirect::to('cms/login')->with('error', 'The account recovery information has expired and is no longer valid.!');
        }
    }
    
    public function forgetPassword()
    {
        return View::make('default.forget_password')->with('data', $this->data);
    }
    
    
    public function doLogin()
    {
       /* $rules = array(
            'username' => 'required|min:3',
            'password' => 'required|alphaNum|min:3'
        );
        
        $validator = Validator::make(Input::all(), $rules);
        
        if ($validator->fails()) {
            return Redirect::to('cms/login')->withErrors($validator)->withInput(Input::except('password'));
        } else {
            $field    = filter_var(Input::get('username'), FILTER_VALIDATE_EMAIL) ? 'email_id' : 'username';
            $userData = array(
                $field => Input::get('username'),
                'password' => Input::get('password'),
                'status' => 1
            );
            
            if (!Auth::attempt($userData)) {
                $loginSuccess = false;
                $field        = filter_var(Input::get('username'), FILTER_VALIDATE_EMAIL) ? 'email_id' : 'username';
                $user         = Admin::where($field, Input::get('username'))->where('status', 1)->first();
                
                if ($user && $user->password == md5(Input::get('password'))) {
                    $user->password = $hash = Hash::make(Input::get('password'));
                    $user->save();
                    if (Auth::attempt($userData)) {
                        $loginSuccess = true;
                    }
                }
                
                if (!$loginSuccess) {
                    return Redirect::to('cms/login')->with('error', '<strong>Invalid credentials!</strong> <br> Invalid Username or Password');
                }
            }
        }
        //print_r(Auth::user());exit;
        if (Auth::user()->role_id == 2) {
            return Redirect::to('cms/post/add');
        } elseif (Auth::user()->role_id == 3) {
            return Redirect::to('cms/bouncer/list');
        } else {
            return Redirect::to('cms/superadmin/list-users');
        }*/
    }
    
    public function doSignUp()
    {
        $rules = array(
            'firstname' => 'required|alphaNum',
            'email_id' => 'required|email|unique:tbl_vast_users',
            'username' => 'required|alphaNum|min:2|unique:tbl_vast_users',
            'password' => 'required|alphaNum|min:3',
            'confirm_pass' => 'required|alphaNum|min:3|same:password'
        );
        
        $validator = Validator::make(Input::all(), $rules);
        
        if ($validator->fails()) {
            $this->data['message'] =  $validator->messages()->toArray();
            return Redirect::to('cms/signup')->withInput()->with('message',$validator->messages()->toArray() );
        } else {
            
            $password = md5(Input::get('password'));
            $userData = array(
                'firstname' => Input::get('firstname'),
                'lastname' => Input::get('lastname'),
                'email_id' => Input::get('email_id'),
                'username' => Input::get('username'),
                'role_id' => '2',
                'status' => '0',
                'avatar' => Input::get('avatar_image'),
                'channel_group_id' => 0,
                'password' => $password,
				'join_date' =>  time(),
				'inactive'   => '1'
               /* 'sdW' => Input::get('sdW'),
                'sdH' => Input::get('sdH'),
                'thumbW' => Input::get('thumbW'),
                'thumbH' => Input::get('thumbH'),
                'hdthumbW' => Input::get('hdthumbW'),
                'hdthumbH' => Input::get('hdthumbH')*/
            );
            $this->admin->create($userData);
            
            $name      = Input::get('firstname') . ' ' . Input::get('lastname');
            $username  = Input::get('username');
            $loginUrl  = URL::to('cms/login');
            $html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>VAST</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="' . URL::to("images") . '/logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;text-transform:capitalize;">Dear ' . $name . ',</span></p>
												<p>Username : ' . $username . '</p>
                                                <p>Thank you for your interest in VAST. </p>
                                                <p>Your account has been successfully created. you will receive an email from us when your account get activated </p>
												<p>Click on the below link to login:<br></p>
												<a href="' . $loginUrl . '">Click to login</a>
												<br>
												<p>Thanks,<br />
												  <br />
											  The Vast Team</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; ' . date('Y') . ' by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';
            
            Util::sendSESMail('noreply@vast.am', Input::get('email_id'), 'Registration successful', null, $html_body);
            
            
        }
        
        return Redirect::to('cms/login')->with('success', 'Sign Up Successful!');
    }
    
    public function doForgetPassword()
    {
        
        $rules = array(
            'user_login' => 'required|min:3'
        );
        
        $validator = Validator::make(Input::all(), $rules);
        
        if ($validator->fails()) {
            return Redirect::to('cms/forget-password')->with('error', '<span class="error-text">Please enter your User Name Or Email</span>');
        } else {
            
            $userData = array(
                'user_login' => Input::get('user_login')
            );
            
            $user_exist = false;
            
            $user = Admin::where('username', Input::get('user_login'))->first();
            if ($user && $user->email_id != '') {
                $user_exist = true;
            } else {
                $user = Admin::where('email_id', Input::get('user_login'))->first();
                if ($user && $user->email_id != '') {
                    $user_exist = true;
                }
            }
            
            if ($user_exist == true) {
                $this->admin->sendPasswordRecoveryOption(array(
                    'email_id' => $user['email_id'],
                    'vast_user_id' => $user['vast_user_id']
                ));
                
                $url       = $this->admin->data['url']; //echo $url; exit;
                $name      = $user['firstname'] . ' ' . $user['lastname'];
                $html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>VAST</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="' . URL::to("images") . '/logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;">Hi ' . $name . ',</span></p>
												<p>You recently requested a password reset for your Vast! account. To create a new password, click on the link below: 

  <a href="' . $url . '">Reset My Password </a> </p>
												<p>Thanks,<br />
												  <br />
											  The Vast Team</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; ' . date('Y') . ' by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';
                
                Util::sendSESMail('noreply@vast.am', $user['email_id'], 'Reset your Vast Password', null, $html_body);
                
                return Redirect::to('cms/forget-password')->with('success', 'We have sent you the recovery details to your Email address	');
            } else {
                return Redirect::to('cms/forget-password')->with('error', '<span class="error-text">Sorry! no user found with that login information</span>');
            }
            
            
        }
        
        return Redirect::to('cms/forget-password')->with('success', 'Sign Up Successful!');
    }
    
    public function doResetPassword()
    {
        
        $rules = array(
            'password' => 'required|alphaNum|min:3',
            'confirm_pass' => 'required|alphaNum|min:3|same:password'
            
        );
        
        $validator = Validator::make(Input::all(), $rules);
        
        if ($validator->fails()) {
            return Redirect::to('cms/' . Input::get('token') . '/reset-password')->with('error', '<strong>The following errors occurred!</strong>')->withErrors($validator);
        } else {
            
            $user = Admin::where('remember_token', Input::get('token'))->first();
            
            if ($user && $user->email_id != '') {
                
                $this->admin->resetPassword(array(
                    'password' => Input::get('password'),
                    'vast_user_id' => $user['vast_user_id']
                ));
                
                
                $name      = $user['firstname'] . ' ' . $user['lastname'];
                $username  = $user['username'];
                $html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>Giada</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="' . URL::to("images") . '/logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;">Dear ' . $name . ',</span></p>
												<p>Username : ' . $username . '</p>
                                                <p>The password for your VAST  has been successfully reset. </p>
												<p>Thanks,<br />
												  <br />
											  The Vast Team</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; ' . date('Y') . ' by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';
                
                Util::sendSESMail('noreply@vast.am', $user['email_id'], 'Your VAST password has been reset.', null, $html_body);
                
                return Redirect::to('cms/login')->with('success', 'Password Updated successfully. Please login to access your account!');
            } else {
                return Redirect::to('cms/login')->with('error', 'The account recovery information has expired and is no longer valid.!');
            }
        }
        
    }
    
    public function logout()
    {
        Auth::logout();
        return Redirect::to('cms/login');
    }
    
    public function accountCreate()
    {
		$manageExploreDetails = $this->manageexplore->getRowsByChannel(Auth::user()->vast_user_id);
		$this->data['manage_id'] =  $manageExploreDetails == '' ? 0 : $manageExploreDetails->id;
		$this->data['cover'] = $manageExploreDetails == '' ? '' : $manageExploreDetails->cover;
		$this->data['sdW'] = $manageExploreDetails == '' ? 0 : $manageExploreDetails->sdW;
		$this->data['sdH'] = $manageExploreDetails == '' ? 0 : $manageExploreDetails->sdH;
		$this->data['retinaW'] = $manageExploreDetails == '' ? 0 : $manageExploreDetails->retinaW;
		$this->data['retinaH'] = $manageExploreDetails == '' ? 0 : $manageExploreDetails->retinaH;
		$this->data['nonretinaW'] = $manageExploreDetails == '' ? 0 : $manageExploreDetails->nonretinaW;
		$this->data['nonretinaH'] = $manageExploreDetails == '' ? 0 : $manageExploreDetails->nonretinaH;			
		$this->data['channel_details'] = $this->channel->getChannelById(Auth::user()->vast_user_id);		
        $this->data['userChannel']['session_id'] = Crypt::encrypt(Auth::user()->vast_user_id);
        $this->layout->content                   = View::make('default.account.create')->with('data', $this->data);
    }
    
    
    public function accessProtected($obj, $prop)
    {
        $reflection = new ReflectionClass($obj);
        $property   = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
    public function addPost()
    {
        $credentials =  $key = $secret = $token = "";
        $cacheAdapter = new DoctrineCacheAdapter(new FilesystemCache('/tmp/cache'));
        $sts          = StsClient::factory(array(
            'credentials.cache' => $cacheAdapter
        ));
        $response     = $sts->assumeRole(array(
            'RoleArn' => 'arn:aws:iam::603415607477:role/production-servers',
            'RoleSessionName' => 'vastprod-sts-upload',
            'Name' => 'test-sts',
            'DurationSeconds' => 900
            
        ));
        
        $credentials = $sts->createCredentials($response);
        $key    = $this->accessProtected($credentials, 'key');
        $secret = $this->accessProtected($credentials, 'secret');
        $token  = $this->accessProtected($credentials, 'token'); 
        
        $key = $key == "" ? Config::get('aws::key'): $key;
        
        $filename                                = 'vastvideo' . time() . rand(0, 9) . '.tmp';
        $uploadCredentials                       = S3::getUploadPermission('videos/' . $filename, $key, $secret, $token);
        $this->data['filename']                  = $filename;
        $this->data['policy']                    = $uploadCredentials['policy'];
        $this->data['signature']                 = $uploadCredentials['signature'];
        $secfilename                             = 'vastsecvideo' . time() . rand(0, 9) . '.tmp';
        $uploadCredentials                       = S3::getUploadPermission('secvideo/' . $secfilename, $key, $secret, $token);
        $this->data['secfilename']               = $secfilename;
        $this->data['accesskey']                 = $key;
        $this->data['token']                     = $token;
        $this->data['secpolicy']                 = $uploadCredentials['policy'];
        $this->data['secsignature']              = $uploadCredentials['signature'];
        $musicfilename                           = 'music' . time() . rand(0, 9) . '.mp3';
        $uploadCredentials                       = S3::getUploadPermission('music/' . $musicfilename, $key, $secret, $token);
        $this->data['musicfilename']             = $musicfilename;
        $this->data['musicpolicy']               = $uploadCredentials['policy'];
        $this->data['musicsign']                 = $uploadCredentials['signature'];
        $secmusicfilename                           = 'sec_music' . time() . rand(0, 9) . '.mp3';
        $uploadCredentials                       = S3::getUploadPermission('audios/' . $secmusicfilename, $key, $secret, $token);
        $this->data['secmusicfilename']             = $secmusicfilename;
        $this->data['secmusicpolicy']               = $uploadCredentials['policy'];
        $this->data['secmusicsign']                 = $uploadCredentials['signature'];
		$this->data['chapters']                  = $this->chapter->getChaptersByChannel(Auth::user()->channel);
        $this->data['userChannel']['session_id'] = Crypt::encrypt(Auth::user()->vast_user_id);
        $this->data['eventCode']                 = 'vast' . time();
		$imagefile =      'vast_image'.time().rand(0,9).'.png';
        $uploadCredentials                       = S3::getUploadPermission('tempimage/'.$imagefile, $key, $secret, $token);
		 $this->data['image_file']                    = $imagefile;
        $this->data['uploadimage_policy']                    = $uploadCredentials['policy'];
        $this->data['uploadimage_sign']                 = $uploadCredentials['signature'];
        
        return View::make('default.post.add')->with('data', $this->data);
    }
    
    public function editPost($id,$type=0)
    {
        $credentials  = $key = $secret = $token = "";
        $cacheAdapter = new DoctrineCacheAdapter(new FilesystemCache('/tmp/cache'));
        $sts          = StsClient::factory(array(
            'credentials.cache' => $cacheAdapter
        ));
        $response     = $sts->assumeRole(array(
            'RoleArn' => 'arn:aws:iam::603415607477:role/production-servers',
            'RoleSessionName' => 'vastprod-sts-upload',
            'Name' => 'test-sts',
            'DurationSeconds' => 900
            
        ));
        
        $credentials = $sts->createCredentials($response);
        $key         = $this->accessProtected($credentials, 'key');
        $secret      = $this->accessProtected($credentials, 'secret');
        $token       = $this->accessProtected($credentials, 'token');
		$key = $key == "" ? Config::get('aws::key'): $key;
        
        date_default_timezone_set('America/New_York');
        $filename                                = 'vastvideo' . time() . rand(0, 9) . '.tmp';
        $uploadCredentials                       = S3::getUploadPermission('videos/' . $filename, $key, $secret, $token);
        $this->data['filename']                  = $filename;
        $this->data['policy']                    = $uploadCredentials['policy'];
        $this->data['signature']                 = $uploadCredentials['signature'];
        $secfilename                             = 'vastsecvideo' . time() . rand(0, 9) . '.tmp';
        $uploadCredentials                       = S3::getUploadPermission('secvideo/' . $secfilename, $key, $secret, $token);
        $this->data['secfilename']               = $secfilename;
        $this->data['secpolicy']                 = $uploadCredentials['policy'];
        $this->data['accesskey']                 = $key;
        $this->data['token']                     = $token;
        $this->data['secsignature']              = $uploadCredentials['signature'];
        $musicfilename                           = 'music' . time() . rand(0, 9) . '.mp3';
        $uploadCredentials                       = S3::getUploadPermission('music/' . $musicfilename, $key, $secret, $token);
        $this->data['musicfilename']             = $musicfilename;
        $this->data['musicpolicy']               = $uploadCredentials['policy'];
        $this->data['musicsign']                 = $uploadCredentials['signature'];
        $secmusicfilename                        = 'sec_music' . time() . rand(0, 9) . '.mp3';
        $uploadCredentials                       = S3::getUploadPermission('audios/' . $secmusicfilename, $key, $secret, $token);
        $this->data['secmusicfilename']          = $secmusicfilename;
        $this->data['secmusicpolicy']            = $uploadCredentials['policy'];
        $this->data['secmusicsign']              = $uploadCredentials['signature'];   
        $this->data['post_details']              = $this->post->getPostsByPostId($id);
        $this->data['chapters']                  = $this->chapter->getChaptersByChannel(Auth::user()->channel);
        $this->data['chapterName']               = $this->chapter->getChapterById($this->data['post_details'][0]->chapter_id);
        $this->data['userChannel']['session_id'] = Crypt::encrypt(Auth::user()->vast_user_id);
		$this->data['post_type']                 = $type;
		$imagefile =      'vast_image'.time().rand(0,9).'.png';
        $uploadCredentials                       = S3::getUploadPermission('tempimage/'.$imagefile, $key, $secret, $token);
		 $this->data['image_file']                    = $imagefile;
		$this->data['uploadimage_policy']                    = $uploadCredentials['policy'];
        $this->data['uploadimage_sign']                 = $uploadCredentials['signature'];
        return View::make('default.post.edit')->with('data', $this->data);
    }
    
    public function feeds()
    {
        $feeds                     = $this->post->getPostsByChannelId(Auth::user()->channel->channel_id);
        $conversions               = $this->conversion->getPendingConversionsBasedOnChannel(Auth::user()->channel->channel_id);
        $this->data['feeds']       = $feeds;
        $this->data['conversions'] = $conversions;
        
        return View::make('default.post.feed')->with('data', $this->data);
    }
    
    /*  public function viewPost($id)
    {
        $post = $this->post->getPostsByPostId($id);
        if (count($post) > 0) {
            $this->data['feeds']    = $post;
            $this->data['viewmode'] = true;
            $this->data['post_id']  = $id;
            if ($post[0]->type == 'image') {
                if ($post[0]->subscription_id != '')
                    $this->data['og-image'] = URL::to('/') . '/cms/do-water-marking?path=' . S3::getS3Url(S3::getPostSdPath($post[0]->sd_url)) . '&type=1&fb=0';
                else
                    $this->data['og-image'] = URL::to('/') . '/cms/do-water-marking?path=' . S3::getS3Url(S3::getPostSdPath($post[0]->sd_url)) . '&type=0&fb=0';
            } else
                $this->data['og-image'] = URL::to('/') . '/cms/do-water-marking?path=' . S3::getS3Url(S3::getPostSdPath($post[0]->sd_url)) . '&type=0&fb=0';
            
            return View::make('default.post.feed')->with('data', $this->data);
        } else
            return View::make('default.404');
    } */
	
	public function viewPost($hash)
    {
        $post = $this->post->getPostByHash($hash);
        if (count($post) > 0) {
            $this->data['feeds']    = $post;
            $this->data['viewmode'] = true;
            $this->data['post_id']  = $post[0]->post_id;
            if ($post[0]->type == 'image') {
                if ($post[0]->subscription_id != '')
                    $this->data['og-image'] = S3::getS3Url(S3::getPostSdPath($post[0]->sd_url)) . '&type=1&fb=0';
                else
                    $this->data['og-image'] = S3::getS3Url(S3::getPostSdPath($post[0]->sd_url)) . '&type=0&fb=0';
            } else
                $this->data['og-image'] = S3::getS3Url(S3::getPostSdPath($post[0]->sd_url)) . '&type=0&fb=0';
            
            return View::make('default.post.feed')->with('data', $this->data);
        } else
            return View::make('default.404');
    }
	
	public function genNewShortUrl($id)
	{
		$postDetails = $this->post->getPostByChannel($id);
		if(count($postDetails) > 0) {
			foreach ($postDetails as $postDet) {
				$hash = Hash::make($postDet->post_id);
				$surl = Util::shortenUrl(URL::to('cms/post', $hash));
				$updateArr = array(
					'post_id' => $postDet->post_id,
					'short_url' => $surl,
					'post_hash' => $hash
				);
				$this->post->update($updateArr);
			}
			echo 1;
		}
	}
    
    public function updateChannel()
    {
        
        $vast_name = '';
        if (Input::get('inputprofile-name') != '') {
            $vname = Channel::where('vast_name', Input::get('inputprofile-name'))->where('channel_id', '<>', Auth::user()->channel->channel_id)->first();
            
            if ($vname['vast_name'] != '') {
                return Redirect::to('cms/account/setting')->with('error', 'VAST name already exist!');
            } else {
                $vast_name = Input::get('inputprofile-name');
            }
        } else {
            
            $vname     = Channel::where('channel_id', Auth::user()->channel->channel_id)->first();
            $vast_name = $vname['vast_name'];
        }
        $updateData = 
        $res = $this->channel->update(Auth::user()->channel->channel_id, array(
            'vast_name' => $vast_name,
            'biography' => Input::get('inputBiography'),
            'first_name' => Input::get('first-name'),
            'last_name' => Input::get('second-name'),
            'fb_url' => Input::get('facebook'),
            'twitter_url' => Input::get('twitter'),
            'instagram_url' => Input::get('instagram'),
            'tumblr_url' => Input::get('tumblr'),
            'gplus_url' => Input::get('google-plus'),
            'touchcast_url' => Input::get('touchcast'),
            'youtube_url' => Input::get('youtube'),
            'tour_url' => Input::get('tour-url'),
            'store_url' => Input::get('store-url'),
            'website_url' => Input::get('website-url'),
            'spotify_url' => Input::get('spotify'),
            'tidal_url' => Input::get('deezer'),
            'beats_url' => Input::get('beats'),
            'itunes_url' => Input::get('itunes'),
            'ticket_url' => Input::get('ticket-url'),
            'subscription_id' => Input::get('subscription-id'),
			'channel_cover'	 => Input::get('app_cover'),
			'channel_cover_sdW' 	=>  Input::get('app_cover_sdW'),
			'channel_cover_sdH'	=>  Input::get('app_cover_sdH'),
			'channel_cover_thumbW'	=> Input::get('app_cover_thumbW'),
			'channel_cover_thumbH'	=> Input::get('app_cover_thumbH'),
			'channel_cover_hd_thumbW'	=> Input::get('app_cover_hdW'),
			'channel_cover_hd_thumbH'	=> Input::get('app_cover_hdH')
        ));
		/*if(Input::get('manage_id') != '0'){
			$res = $this->manageexplore->updateChannelCover(array(
			'id' => Input::get('manage_id'),
            'cover' => Input::get('channel_cover_image'),
            'retinaW' => Input::get('retinaW'),
            'retinaH' => Input::get('retinaH'),
            'nonretinaW' => Input::get('nonretinaW'),
            'nonretinaH' => Input::get('nonretinaH'),
            'sdW' => Input::get('sdW'),
            'sdH' => Input::get('sdH')
        ));
		}*/
        if ($res == 1) {
            Session::flash('success', 'Profile updated successfully!');
            echo 1;
        } else {
            Session::flash('error', 'Profile updating failed!');
            echo 0;
            
        }
        
        return Redirect::to('cms/account/setting');
    }
    
    public function messageWrite()
    {
        $this->data['action']  = '';
        $this->layout->content = View::make('default.message.write')->with('data', $this->data);
    }
    public function messageInbox()
    {
        $inbox = $this->inbox->getInboxByChannelId(Auth::user()->channel->channel_id);
        
        $this->data['inbox'] = $inbox;
        
        $this->layout->content = View::make('default.message.inbox')->with('data', $this->data);
    }
    public function messageView($id)
    {
        $this->data['inbox'] = $this->inbox->getInboxById($id);
        
        $this->layout->content = View::make('default.message.view')->with('data', $this->data);
    }
    public function messageReply($id)
    {
        $this->data['inbox']   = $this->inbox->getInboxById($id);
        $this->data['message'] = $this->message->getMessageById($id);
        $this->data['action']  = 'reply_message';
        $this->layout->content = View::make('default.message.write')->with('data', $this->data);
    }
    public function doMessageWrite()
    {
        if (Input::get('message-action') == "reply_message")
            $rules = array(
                'subject' => 'required|min:3',
                'inputMessage' => 'required|min:3'
            );
        else
            $rules = array(
                'subject' => 'required|min:3',
                'inputMessage' => 'required|min:3',
                'msg-users' => 'required'
            );
        
        $validator = Validator::make(Input::all(), $rules, array(
            'msg-users.required' => 'Please select the recipient.',
            'inputMessage.required' => 'The Message field is required.'
        ));
        
        if ($validator->fails()) {
            return Redirect::back()->with('error', '<b>The following errors occurred</b>')->withErrors($validator)->withInput();
            
        } else {
            $uploadDate = time();
            if (Input::get('message-action') == "reply_message") {
                $message_id = $this->message->create(array(
                    'channel_id' => Auth::user()->channel->channel_id,
                    'subject' => Input::get('subject'),
                    'message' => Input::get('inputMessage'),
                    'date' => $uploadDate,
                    'type' => 3,
                    'ref_id' => Input::get('message-id'),
                    'msg_state' => 1, //1 for reply messages
                    'status' => 0,
                    'image' => Input::get('message_image'),
                    'image_width' => Input::get('image_width'),
                    'image_height' => Input::get('image_height'),
                    'action' => 'reply'
                ));
                
            } else {
                $message_id  = $this->message->create(array(
                    'channel_id' => Auth::user()->channel->channel_id,
                    'type' => Input::get('msg-users'),
                    'subject' => Input::get('subject'),
                    'message' => Input::get('inputMessage'),
                    'image' => Input::get('message_image'),
                    'image_width' => Input::get('image_width'),
                    'image_height' => Input::get('image_height'),
                    'date' => $uploadDate,
                    'msg_state' => 0, //0 for new messages
                    'action' => 'write'
                ));
				// below is commented intentionally to avoid push notification error.
                /* $channelName = $this->channel->getChannelById(Auth::user()->channel->channel_id);
                $messagetext = "Holla! " . $channelName['first_name'] . " " . $channelName['last_name'] . " sent you a message.";
                $sound       = "default";
                $messageId   = $message_id;
                switch (Input::get('msg-users')) {
                    case '1':
                        $users = $this->follow->getFollowersByChannel(Auth::user()->channel->channel_id);
                        foreach ($users as $user) {
                            $apnsToken = $this->user->getDeviceToken($user['user_id']);
                            if ($apnsToken != "")
                                $this->message->_sendPushNotification($apnsToken, $messagetext, $messageId, $sound, $action = '');
                        }
                        break;
                    case '2':
                        $users = $this->subscribe->getSubscribersByChannel(Auth::user()->channel->channel_id);
                        foreach ($users as $user) {
                            $apnsToken = $this->user->getDeviceToken($user['user_id']);
                            if ($apnsToken != "")
                                $this->message->_sendPushNotification($apnsToken, $messagetext, $messageId, $sound, $action = '');
                        }
                        break;
                    default:
                        break;
                } */
            }
            
            if ($message_id != 0)
                return Redirect::to('cms/message/inbox')->with('success', 'Message Added Successfully.');
            else
                return Redirect::to('cms/message/write')->with(array(
                    'error' => 'Message Adding Failed.'
                ));
            
            
            
        }
        
        return Redirect::to('cms/message/write');
    }
    public function doMessageDelete()
    {
        $res = $this->message->deleteMessage(Input::get('message-id'));
        if ($res > 0)
            return Redirect::to('cms/message/inbox')->with(array(
                'success' => 'Succesfully Deleted Message.'
            ));
        else
            return Redirect::to('cms/message/inbox')->with(array(
                'error' => 'Message Deletion Failed.'
            ));
        
        return Redirect::to('cms/message/inbox');
    }
    
    
    // super admin dash board
    public function listAdminUsers()
    {
        $list_user          = $this->admin->getAdminUsers(Auth::user()->vast_user_id);
        $this->data['user'] = $list_user;   
        $this->layout->content = View::make('default.superadmin.list_users')->with('data', $this->data);
    }
    
    
    //super admin featured starts here
    public function featuredChapters()
    {
        $this->data['channels'] = $this->channel->getChannelsDetailsBasedOnVastUser();
        $this->layout->content  = View::make('default.superadmin.featured')->with('data', $this->data);
    }
    //super admin featured ends here
    
    public function adminUserView($id)
    {
        $this->data['channelgroups'] = $this->channelgroup->getChannelGroups();
        $this->data['user']          = $this->admin->getAdminUserById($id);
        $this->data['channel']       = $this->channel->getChannelById($id);
        $this->data['roles']         = $this->role->getRoles();
        $this->data['avatar']        = $this->channel->getAvatarByChannelId($id);
        $this->layout->content       = View::make('default.superadmin.view_user')->with('data', $this->data);
    }
    
    
    public function adminAddUserView()
    {
        //$this->data['channelgroups'] = $this->channelgroup->getChannelGroups();
        $this->data['channelgroups'] = $this->manageexplore->getOnlyNetworks();
        $this->data['roles']         = $this->role->getRoles();
        $this->layout->content       = View::make('default.superadmin.add_user')->with('data', $this->data);
    }
    
    public function adminAddUser()
    {
        $validator = Validator::make(Input::all(), Admin::$rules);
        if ($validator->fails())
            return Redirect::to('cms/superadmin/users/add')->with('error', 'The following errors occurred')->withErrors($validator)->withInput();
        else {
            $userData = array(
                'firstname' => Input::get('firstname'),
                'lastname' => Input::get('lastname'),
                'email_id' => Input::get('email'),
                'username' => Input::get('username'),
                'role_id' => Input::get('role'),
                'status' => Input::get('status'),
                'password' => md5(Input::get('password')),
                'channel_group_id' => Input::get('channel_group'),
                'avatar' => Input::get('uploaded_avatar'),
				'join_date' => time(),
				'inactive'  => Input::get('status') == 0 ? '1' : '0'
                /*'sdW' => Input::get('sdW'),
                'sdH' => Input::get('sdH'),
                'thumbW' => Input::get('thumbW'),
                'thumbH' => Input::get('thumbH'),
                'hdthumbW' => Input::get('hdthumbW'),
                'hdthumbH' => Input::get('hdthumbH')*/
            );
            //uploaded_avatar
            $res      = $this->admin->create($userData);
            if ($res)
                return Redirect::to('cms/superadmin/list-users')->with('success', 'User Details has been Saved Successfully!');
            return Redirect::to('cms/superadmin/users/add')->with('error', 'User Details has been Saving Failed!');
            
        }
        
    }
    
    public function instagramLogin()
    {
        $instagram = new Instagram();
        return Redirect::to($instagram->getAuthUrl());
    }
    
    public function instagramCallback()
    {
        $instagram = new Instagram();
        $response  = $instagram->callback(Input::get('code'));
        
        if (is_array($response) && isset($response['token']) && isset($response['external_id'])) {
            // @todo: Save token and instagram user id
            
        }
    }
    
    public function twitterLogin()
    {
        $twitter = new Twitter();
        $token   = $twitter->login();
        Session::put('twitterToken', $token);
        return Redirect::to($twitter->getAuthURL($token, URL::to('cms/twitter/callback')));
    }
    
    public function twitterCallback()
    {
        $oauthToken = Session::get('twitterToken');
        $twitter    = new Twitter($oauthToken['oauth_token'], $oauthToken['oauth_token_secret']);
        
        $response = $twitter->callback(Input::get('oauth_verifier'));
        if (is_array($response) && isset($response['oauth_token']) && isset($response['twitter_id'])) {
            $twitterData = array(
                'twitter_token' => $response['oauth_token'],
                'twitter_token_secret' => $response['oauth_token_secret'],
                'twitter_uid' => $response['twitter_id'],
                'twitter_screen_name' => $response['screen_name']
            );
            $this->admin->saveTwitterToken(Auth::user()->vast_user_id, $twitterData);
        }
        
        return View::make('ajax.twitter.callback')->with('data', json_encode($response));
    }
    
    public function checkTwitterTokenExist()
    {
        return (Auth::user()->twitter_token != '' && Auth::user()->twitter_uid != '') ? '1' : '0';
    }
    
    public function tumblrLogin()
    {
        $tumblr = new Tumblr();
        $token  = $tumblr->login();
        Session::put('tumblrToken', $token);
        return Redirect::to($tumblr->getAuthURL($token, URL::to('cms/tumblr/callback')));
    }
    
    public function tumblrCallback()
    {
        $oauthToken = Session::get('tumblrToken');
        $tumblr     = new Tumblr($oauthToken['oauth_token'], $oauthToken['oauth_token_secret']);
        
        $response = $tumblr->callback(Input::get('oauth_verifier'));
        
        if (is_array($response) && isset($response['token']) && isset($response['external_id'])) {
            // Saving token and oauth_token_secret
            $tumblrData = array(
                'tumblr_token' => $response['token']['oauth_token'],
                'tumblr_oauth_token_secret' => $response['token']['oauth_token_secret'],
                'tumblr_username' => $response['external_id']
            );
            $this->admin->saveTumblrToken(Auth::user()->vast_user_id, $tumblrData);
            
            return View::make('ajax.tumblr.callback');
        } else {
            return Redirect::to(URL::to('cms/tumblr/login'));
        } //print_r($response); exit;
    }
    
    public function checkTumblrTokenExist()
    {
        return (Auth::user()->tumblr_token != '' && Auth::user()->tumblr_username != '') ? '1' : '0';
    }
    
    public function checkFacebookTokenExist()
    {
        return (Auth::user()->fb_token != '') ? '1' : '0';
    }
    
    public function doUpdateAdminUserDetails()
    {
        //print_r( $_POST ); exit;
        $edit_id = Input::get('admin_user_edit_id');
        
        $rules = array(
            'firstname' => 'required|alphaNum|min:3',
            'email_id' => 'required|email|unique:tbl_vast_users,email_id,' . $edit_id . ',vast_user_id',
            'username' => 'required|alphaNum|min:3|unique:tbl_vast_users,username,' . $edit_id . ',vast_user_id',
            'role_id' => 'required|numeric'
        );
        
        if (Input::get('reset_password') == '1') {
            array_push($rules, array(
                'password' => 'required|alphaNum|min:3',
                'confirm_pass' => 'required|alphaNum|min:3|same:password'
            ));
        }
        
        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails()) {
            return Redirect::to('cms/superadmin/users/' . $edit_id . '/view')->with('error', 'The following errors occurred')->withErrors($validator);
        } else {
            
            $edit_id = Input::get('admin_user_edit_id');
            
            $userData = array(
                'firstname' => Input::get('firstname'),
                'lastname' => Input::get('lastname'),
                'email_id' => Input::get('email_id'),
                'username' => Input::get('username'),
                'role_id' => Input::get('role_id'),
                'status' => Input::get('status'),
                'channel_group_id' => Input::get('channel_group'),
                'sdW' => Input::get('sdW'),
                'sdH' => Input::get('sdH'),
                'thumbW' => Input::get('thumbW'),
                'thumbH' => Input::get('thumbH'),
                'hdthumbW' => Input::get('hdthumbW'),
                'hdthumbH' => Input::get('hdthumbH')
            );
            
            if (Input::get('password') != '') {
                $password             = md5(Input::get('password'));
                $userData['password'] = $password;
            }
            
            
            if (Input::get('uploaded_avatar') != '') {
                $userData['avatar'] = Input::get('uploaded_avatar');
            }
            
            // sending mail for approved users
            $user = $this->admin->getAdminUserById($edit_id);
            if (Input::get('status') == '1' && $user['status'] == '0') { #  User been activated from pending approval
                
                $name      = $user['firstname'] . ' ' . $user['lastname'];
                $loginUrl  = URL::to('login');
                $html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>Vast</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="' . URL::to("images") . '/logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;text-transform:capitalize;">Hi ' . $name . ',</span></p>
												<p>Your Account has been successfully activated. You can now log in to <a href="' . $loginUrl . '">vast</a> using the username and password you chose during the registration </p>
												
												<p>Thanks,<br />
												  <br />
											  The Vast Team</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; ' . date('Y') . ' by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';
                
                Util::sendSESMail('noreply@vast.am', $user['email_id'], 'Your account has been activated', null, $html_body);
            }
			if(Input::get('status') == 1){
				$id = $this->manageexplore->getRowsByMappingId($edit_id,0,1);
				if($id == 0 || $id == "") {
					$res = $this->manageexplore->insertManageExploreChannel(array(
						'type' => 1,
						'featured' => '0,0,0,0,0',
						'non_featured' => '0,0,0,0,0,0,0,0',
						'mapping_id' =>$edit_id,
						'featured_chapter_details' => '0,0',
					));
				}
			}else{
				$id = $this->manageexplore->getRowsByMappingId($edit_id,0,1);
				$this->manageexplore->deleteById($id);
			}
            $res = $this->admin->updateAdminUserDetails($edit_id, $userData);
            if ($res)
                return Redirect::to('cms/superadmin/list-users')->with('success', 'User Details has been updated Successfully!');
            else
                return Redirect::to('cms/superadmin/users/' . $edit_id . '/view')->with('error', 'User Details update Failed!');
        }
        
    }
    
    //super admin channels list page starts here
    public function listChannels()
    {
        $this->data['channels'] = $this->channel->getChannelsWithPaginate();
        $this->layout->content  = View::make('default.superadmin.channels')->with('data', $this->data);
    }
    //super admin channels list page ends here
    
    //Forgot password code from app starts here
    public function resetUsrpwd($token)
    {
        $this->data['token'] = $token;
        $token               = User::where('reset_token', $token)->first();
        
        if (sizeof($token) > 0) {
            return View::make('default.reset_usrpwd')->with('data', $this->data);
        } else {
            return Redirect::to('cms/login')->with('error', 'The account recovery information has expired and is no longer valid.!');
        }
    }
    
    public function doResetUsrpwd()
    {
        
        $rules = array(
            'password' => 'required|alphaNum|min:3',
            'confirm_pass' => 'required|alphaNum|min:3|same:password'
            
        );
        
        $validator = Validator::make(Input::all(), $rules);
        
        if ($validator->fails()) {
            return Redirect::to('cms/' . Input::get('token') . '/reset-usrpwd')->with('error', '<strong>The following errors occurred!</strong>')->withErrors($validator);
        } else {
            
            $user = User::where('reset_token', Input::get('token'))->first();
            
            if ($user && $user->email != '') {
                
                $this->user->resetUsrpwd(array(
                    'password' => Input::get('password'),
                    'id' => $user['ID']
                ));
                
                
                //$name 		= $user['firstname'].' ' . $user['lastname'];
                //$username 	=  $user['username'];
                $html_body = '<html xmlns="http://www.w3.org/1999/xhtml">
								<head>
								<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
								<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
								<title>VAST</title>
								<style type="text/css">
								table td {
									border-collapse:collapse;
								}
								body {
									margin:0 !important;
									padding:0 !important;
									width:100% !important;
									background-color: #ffffff !important;
								}
								</style>
								</head>
								<body>
								<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#f2f2f2" style="padding-right: 10px;">
								  <tbody>
									<tr>
									  <td valign="top" style="padding-left: 10px;"><table align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="font-family:Arial, Tahoma, Verdana, sans-serif; margin-top: 40px; margin-bottom: 100px; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; border: 1px solid #cccccc; max-width: 620px;padding-bottom: 17px;">
										  <tbody>
											<tr>
											  <td style="background-color:#000000; padding:17px;-webkit-border-top-left-radius: 5px;-webkit-border-top-right-radius: 5px;-moz-border-radius-topleft: 5px;-moz-border-radius-topright: 5px;border-top-left-radius: 5px;border-top-right-radius: 5px;" valign="middle" align="center"><img width="130" src="' . URL::to("images") . '/logo.png" alt=""  /></td>
											</tr>
											<tr>
											  <td valign="middle" align="left" bgcolor="#ffffff" class="mail-content" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #181818; padding:15px;"><p style="margin-top: 10px;"><span style="margin-top:20px;">Dear User,</span></p>
                                                <p>The password for your VAST  has been successfully reset. </p>
												<p>Thanks,<br />
												  <br />
											  The Vast Team</p></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><hr color="#cccccc" style="margin-top: 10px; margin-bottom: 10px;" /></td>
											</tr>
											<tr>
											  <td valign="middle" align="center" bgcolor="#ffffff"><p style="text-align: center;color: #606060;font-size: 11px;line-height: 15px;margin: 10px;">Copyright &copy; ' . date('Y') . ' by Vast</td>
											</tr>
										  </tbody>
										</table></td>
									</tr>
								  </tbody>
								</table>
								</body>
								</html>';
                
                Util::sendSESMail('noreply@vast.am', $user['email'], 'Your VAST password has been reset.', null, $html_body);
                
                return Redirect::to('cms/resetmsg')->with('success', 'Your password has been updated. Please be sure to use this new password when logging in with the Vast app');
            } else {
                return Redirect::to('cms/resetmsg')->with('error', 'The account recovery information has expired and is no longer valid.!');
            }
        }
        
    }
    //Forgot password code from app ends here
    
    
    
    /* do video conversion through cron starts here */
    public function doVideoConversion()
    {
        $pendingConversion = $this->conversion->getPendingConversion();
        if ($pendingConversion['post_id'] != "") {
            $this->conversion->updateSelectedConversion((int) $pendingConversion['post_id']);
            $videoname      = "";
            $secVideoname   = "";
            $videoStatus    = 1;
            $secVideoStatus = 1;
            $id             = 0;
            $updateData     = array(
                'post_id' => (int) $pendingConversion['post_id']
            );
            if ($pendingConversion['videourl'] != "") {
                $videoStatus = 0;
                //S3::deleteUploadedVideosFromS3('videos', $pendingConversion['videourl']);
                $id  = S3::convertUploadedVideoFormat($type = 1, $pendingConversion['videourl']);
                $res = self::statusCheck($id);
                if ($res == 1) {
                    $videoStatus = 1;
                    //S3::delete_file('videos', $pendingConversion['videourl']);
                }
                $filename  = explode(".", $pendingConversion['videourl']);
                $videoname = $filename[0] . ".mp4";
                $coverFile = $filename[0] . "-thumbnail-00004.jpg";
                if ($pendingConversion['create_thumb'] == 1) {
                    $resData                      = json_decode(S3::moveUserSelectedFile($coverFile, 1), true);
                    $updateData['thumb_url']      = $resData['photo'];
                    $updateData['hdthumb_url']    = $resData['photo'];
                    $updateData['sd_url']         = $resData['photo'];
                    $updateData['thumb_height']   = $resData['thumbh'];
                    $updateData['thumb_width']    = $resData['thumbw'];
                    $updateData['hdthumb_height'] = $resData['hdthumbh'];
                    $updateData['hdthumb_width']  = $resData['hdthumbw'];
                    $updateData['sd_height']      = $resData['sdh'];
                    $updateData['sd_width']       = $resData['sdw'];
                }
               // S3::deleteCreatedThumbnails($filename[0]);
                $updateData['video_url'] = $videoname;
            }
            if ($pendingConversion['secvideourl'] != "") {
                $secVideoStatus = 0;
               // S3::deleteUploadedVideosFromS3('secvideo', $pendingConversion['secvideourl']);
                $id  = S3::convertUploadedVideoFormat($type = 0, $pendingConversion['secvideourl']);
                $res = self::statusCheck($id);
                if ($res == 1) {
                    $secVideoStatus = 1;
                   // S3::delete_file('secvideo', $pendingConversion['secvideourl']);
                }
                $filename                   = explode(".", $pendingConversion['secvideourl']);
                $secVideoname               = $filename[0] . ".mp4";
                $updateData['second_video'] = $secVideoname;
            }
            if ($videoStatus == 1 && $secVideoStatus == 1) {
                $updateData['post_status'] = 1;
                $res                       = $this->post->updatePostsOnVideoConversion($updateData);
                $postDetails               = $this->post->getPostsByPostId((int) $pendingConversion['post_id']);
                $postDetails               = $postDetails[0];
                App::make('AjaxController')->updateChannelPostOrderSettings('add',$postDetails->channel_id,$postDetails->chapter_id,$postDetails->post_id);
				App::make('AjaxController')->updateAdvastJsonData($pendingConversion['channel_id']);
				//$result = $this->post->getPostsByPostId($pendingConversion['post_id']);
				/* Sharing To Twitter */
				if ($pendingConversion['tw'] != '0') {
					$title = $pendingConversion['share_text'];
			
					$title = Str::limit($title, 135 - 18 - 3);

					$twitter = new Twitter($pendingConversion['tw_token'], $pendingConversion['tw_secret']);
					$twitter->tweet($title . ' - ' . $pendingConversion['short_url']);
				}

				/* Sharing To Facebook */
				if ($pendingConversion['fb'] == '1') {
					$title = $pendingConversion['share_text'];
					if ($title == '')
						$title = $pendingConversion['post_title'];

					$title = ( $title == '' ) ? 'Vast' : $title;
					//$title = Str::limit($title, 55);

					$fb = new Facebook();
					$share_array = array(
						'access_token' => $pendingConversion['fb_token'],
						'link' => $pendingConversion['short_url'],
						'caption' => 'VAST - The ultimate fan experience',
						'message' => $title,
						'scrap' => true
					);
					$fb->shareLink($share_array);
				}

				/* Sharing To Tumblr */
				if ($pendingConversion['tb'] != '0' && ( $pendingConversion['tb_token'] != '' && $pendingConversion['tb_username'] != '' )) {
					if($pendingConversion['videourl'] != '') {
						$caption = $pendingConversion['share_text'];
						$path = S3::getPostVideoPath($videoname);
						$source_url = S3::getS3Url($path);
						$embed = ' <video controls><source src="' . $source_url . '"></source></video>';
						$post_data = array('type' => 'video', 'caption' => '<a href="' . $pendingConversion['short_url'] . '">' . $caption . '<a>', 'embed' => $embed);
					} else {
						$caption = $pendingConversion['share_text'];
						$path = S3::getSecVideoPath($secVideoname);
						$source_url = S3::getS3Url($path);
						$embed = ' <video controls><source src="' . $source_url . '"></source></video>';
						$post_data = array('type' => 'video', 'caption' => '<a href="' . $pendingConversion['short_url'] . '">' . $caption . '<a>', 'embed' => $embed);
					}
					if ($post_data) {
						$token = $pendingConversion['tb_token'];
						$tokenSecret = $pendingConversion['tb_secret'];
						$blog_name = $pendingConversion['tb_username'] . '.tumblr.com';
						$tumblr = new Tumblr($token, $tokenSecret);
						$tumblr->createPost($blog_name, $post_data);
					}
					/*switch (Input::get('type')) {
						case 'text':
							$caption = Input::get('share_text');
							$path = S3::getPostSdPath(Input::get('post_message_image'));
							$source_url = S3::getS3Url($path);
							$post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $result[0]['short_url']);
							break;

						case 'music':
							$caption = Input::get('share_text') . ', Song : ' . Input::get('song_name') . ', Artist : ' . Input::get('artist_name');
							$caption = ( Input::get('share_text') == '' ) ? Input::get('title') : Input::get('share_text');
							$path = S3::getPostMusicPath(Input::get('post_music'));
							$source_url = S3::getS3Url($path);

							$post_data = array('type' => 'audio', 'caption' => '<a href="' . $result[0]['short_url'] . '">' . $caption . '<a>', 'external_url' => $source_url);
							break;

						case 'video':
							$caption = Input::get('share_text');
							$path = S3::getPostVideoPath(Input::get('post_video'));
							$source_url = S3::getS3Url($path);
							$embed = ' <video controls><source src="' . $source_url . '"></source></video>';
							$post_data = array('type' => 'video', 'caption' => '<a href="' . $result[0]['short_url'] . '">' . $caption . '<a>', 'embed' => $embed);
							break;

						case 'image':
							$caption = Input::get('share_text');
							$path = S3::getPostRetinaPath(Input::get('post_image'));
							$source_url = S3::getS3Url($path);

							$post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $result[0]['short_url']);
							break;
						
						 case 'event':
							$caption = Input::get('share_text');
							$path = S3::getPostSdPath(Input::get('post_image'));
							$source_url = S3::getS3Url($path);

							$post_data = array('type' => 'photo', 'caption' => $caption, 'source' => $source_url, 'link' => $result[0]['short_url']);
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
					}*/
				}
                if($res == 1){
                    $res = $this->conversion->deleteEntry($pendingConversion['post_id']);
                }
            }
        }
        
        
    }
    
    
    public function statusCheck($id)
    {
        while (true) {
            $status = S3::getStatus($id);
            if ($status == 'Progressing' || $status == 'Submitted')
                sleep(2); //wait some seconds
            elseif ($status == "Complete")
                return 1;
            elseif ($status == 'Error')
                return 0;
        }
    }
    /* do video conversion through cron ends here */
    
    
    public function listChannelGroups()
    {
        $this->data['networks'] = $this->manageexplore->getExploreEntries();
        $this->layout->content  = View::make('default.superadmin.list_channelgroups')->with('data', $this->data);
    }
    
    public function viewExploreImage($id, $type)
    {
        $this->data['type']    = $type;
        $this->data['explore'] = $this->manageexplore->getNetworkById($id);
        $this->layout->content = View::make('default.superadmin.edit-explore')->with('data', $this->data);
    }
    
    
    public function addCoverPage()
    {
        $this->data['networks'] = $this->manageexplore->getOnlyNetworks();
        $this->data['chapter']  = $this->chapter->getActiveChapters();
        $this->data['artist']   = $this->channel->getActiveChannels();
        $this->data['channels'] = $this->channel->getActiveChannels();
        $this->layout->content  = View::make('default.superadmin.add_cover')->with('data', $this->data);
    }
    
    public function manageNetworkView($id)
    {
        $lastFeatured      = "";
        $networkDetails    = $this->manageexplore->getNetworkById($id);
        $featuredArrays    = explode(',', $networkDetails['featured_list']);
        $nonFeaturedArrays = explode(',', $networkDetails['non_featured_list']);
        $channels          = array_filter($nonFeaturedArrays);
        $count             = count($channels);
        $f                 = 0;
        $defaultFeatured   = array();
        $postDetails       = $this->post->getLastestPostByChannels($channels);
        if ($count == 0)
            $defaultFeatured = array(
                0,
                0,
                0,
                0,
                0
            );
        else {
            foreach ($postDetails as $postDetail) {
                $defaultFeatured[$f] = isset($postDetail->post_id) ? $postDetail : 0;
                $f++;
                if ($f == 5)
                    break;
            }
            $cnt = count($defaultFeatured);
            if ($cnt < 5) {
                for ($cnt = $f; $cnt < 5; $cnt++)
                    $defaultFeatured[$cnt] = 0;
            }
        }
        $nonFeatured = $nonFeaturedChannelArray = array();
        $j           = 0;
        $i           = 0;
        $featured    = array();
        foreach ($featuredArrays as $featuredArray) {
            if ($featuredArray == '0') {
                foreach ($defaultFeatured as $lastPostItems) {
                    if (isset($lastPostItems->post_id) != "") {
                        if (!in_array($lastPostItems->post_id, $featuredArrays)) {
                            $key = array_search('0', $featuredArrays);
                            array_push($featured, S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url)));
                            $lastFeatured         = S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url));
                            $featuredArrays[$key] = $lastPostItems->post_id;
                        } else
                            continue;
                    } else
                        array_push($featured, "");
                    break;
                }
            } else {
                array_push($featured, S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($featuredArray))));
                $lastFeatured = S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($featuredArray)));
            }
        }
        $arrCount = count($featured);
        while ($arrCount < 5) {
            array_push($featured, "");
            $arrCount++;
        }
        
        if ($featured[0] != "")
            $lastFeatured = $featured[0];
        foreach ($nonFeaturedArrays as $nonFeaturedArray) {
            if ($nonFeaturedArray == '0')
                $nonFeatured[$j] = "";
            else {
                $getSelectedChannel = $this->channel->getChannelById($nonFeaturedArray);
                 if (isset($getSelectedChannel->channel_cover) && (trim($getSelectedChannel->channel_cover) != ""))
                    $nonFeatured[$j] = S3::getS3Url(S3::getAppCover("sd/" . $getSelectedChannel->channel_cover));
                else
                    $nonFeatured[$j] = S3::getS3Url(S3::getChannelAvatarPath('sd/' . $this->channel->getAvatarByChannelId($nonFeaturedArray)));
				$nonFeaturedChannelArray[$j] = $this->channel->getChannelNameByChannelId($nonFeaturedArray);
            }
            $j++;
        }
        $this->data['networkDetails']   = $networkDetails;
        $this->data['featured']         = $featured;
        $this->data['lastFeatured']     = $lastFeatured;
        // $this->data['nonfeatured']      = $nonFeatured;
		$this->data['nonfeatured']      = array_values(array_filter($nonFeatured));
        //$this->data['nonFeaturedArray'] = $nonFeaturedArrays;
	    $this->data['nonFeaturedArray'] = array_values(array_filter($nonFeaturedArrays));
		//print_r($this->data['nonfeatured']);print_r($this->data['nonFeaturedArray']);exit;
        $this->data['featuredArray']    = $featuredArrays;
        $this->data['channels']         = $this->channel->getActiveChannels();
        $this->data['nonFeaturedCount'] = count($nonFeaturedArrays) > 8 ? count($nonFeaturedArrays) : 8;
		$this->data['nonFeaturedChannelArray'] = array_values(array_filter($nonFeaturedChannelArray));
        $this->data['latestEntry']      = $networkDetails['latestEntry'];
        $this->layout->content          = View::make('default.superadmin.edit-manage-network')->with('data', $this->data);
    }
    
    public function editManageExploreNetwork()
    {
        $nonFeaturedLists    = Input::get('artist');
        $i                   = 0;
        $nonFeaturedPostList = array();
        if (Input::get('network_type') == 1)
            foreach ($nonFeaturedLists as $nonFeaturedList) {
                if ($nonFeaturedList == 0)
                    $nonFeaturedPostList[$i] = 0;
                else {
                    $lastPost                = $this->post->getLastPostByChapter($nonFeaturedList);
                    $nonFeaturedPostList[$i] = $lastPost['post_id'];
                }
                $i++;
            }
        $data = array(
            'id' => Input::get('network_id'),
            'screen_name' => Input::get('networkname'),
            'non_featured_list' => implode(",", Input::get('artist')),
            'retinaH' => Input::get('retinaH'),
            'retinaW' => Input::get('retinaW'),
            'nonretinaH' => Input::get('nonretinaH'),
            'nonretinaW' => Input::get('nonretinaW'),
            'sdH' => Input::get('sdH'),
            'sdW' => Input::get('sdW'),
            'type' => Input::get('network_type'),
            'non_featured_post_list' => implode(",", $nonFeaturedPostList),
            'disabled' => Input::get('app_network_disabled'),
            'alert_text' => Input::get('alert_text')
        );
        if (Input::get('featuredlist') != "")
            $data['featured_list'] = Input::get('featuredlist');
        if (Input::get('network_type') == 1) {
            $data['flag']        = 1;
            $data['inner_cover'] = Input::get('network_cover');
        } else {
            $data['cover'] = Input::get('network_cover');
        }
        $res = $this->manageexplore->update($data);
        if ($res)
            Session::flash('success', 'Update Successful!');
        else
            Session::flash('warning', 'Update Failed!');
        return Redirect::to('cms/superadmin/list-channelgroups')->with('data', $this->data);
    }
    
    public function manageNetworkChapterList($id)
    {
        
        $createdChannels = array();
        $getChannels     = $this->manageexplore->getChannelsByNetwork($id);
        foreach ($getChannels as $getChannel) {
            $channelDet  = $this->channel->getChannelById($getChannel->mapping_id);
            $screen_name = $getChannel->screen_name == "" ? $channelDet['vast_name'] : $getChannel->screen_name;
            $cover       = $getChannel->channel_cover == "" ? S3::getS3Url(S3::getChannelAvatarPath('sd/' . $this->channel->getAvatarByChannelId($getChannel->mapping_id))) :  S3::getS3Url(S3::getAppCover("sd/" . $getChannel->channel_cover));
            $channel     = array(
                'mapping_id' => $getChannel->mapping_id,
                'id' => $getChannel->id,
                'cover' => $cover,
                'screen_name' => $screen_name
            );
            array_push($createdChannels, $channel);
        }
        $this->data['channels'] = $createdChannels;
        $this->layout->content  = View::make('default.superadmin.list_chaptergroups')->with('data', $this->data);
    }
    
    public function manageChapterView($id)
    {
        
        $lastFeatured           = "";
        $channelDetails         = $this->manageexplore->getNetworkById($id);
        $featuredArrays         = explode(',', $channelDetails['featured_list']);
        $nonFeaturedArrays      = explode(',', $channelDetails['non_featured_list']);
        $nonFeaturedPostArrays  = explode(',', $channelDetails['non_featured_post_list']);
        $featuredChapterDetails = explode(',', $channelDetails['featured_chapter_details']);
        $defaultNonFeatured     = $this->chapter->getActiveFeaturedChaptersByChannel($channelDetails['mapping_id']);
		
        $nonFeatured            = array();
        $j                      = 0;
        $i                      = 0;
        $featured               = array();
        $tmp                    = array_filter($nonFeaturedArrays);
        $nonFeaturedArrays      = sizeof($tmp) == 0 ? $this->chapter->getActiveFeaturedChaptersByChannel($channelDetails['mapping_id']) : $nonFeaturedArrays;
        if (sizeof($tmp) == 0) {
            $arrPos = count($nonFeaturedPostArrays);
            for ($arrPos = count($nonFeaturedPostArrays); $arrPos < count($nonFeaturedArrays); $arrPos++) {
                $nonFeaturedPostArrays[$arrPos] = "0";
            }
        }
        
        $channelChapters = $this->chapter->getChaptersByChannelId($channelDetails['mapping_id']);
        $str             = "";
        foreach ($channelChapters as $channelChapter) {           
			$nos  = count($this->post->getPostsbyChapter($channelChapter->chapter_id));
            if ($nos > 0) {
				 $getLastPostByChapter = $this->post->getLastPostByChapter($channelChapter->chapter_id);
				$imgUrl               = S3::getS3Url(S3::getPostSdPath($getLastPostByChapter['sd_url']));
				$str .= '<div class="channel_chapter_row col-md-12 col-xs-12 col-sm-12 remove-padding">
								<div class="channel_chapter col-md-6 col-xs-6 col-sm-6">' . $channelChapter->chapter_name . '</div>
								<div class="channel_chapter_head_image col-md-4 col-xs-4 col-sm-4"><img class="channel-chapter-image" src="' . $imgUrl . '" /></div>
								<div class="channel_chapter_btn_ok col-md-2 col-xs-2 col-sm-2">
									<img data-id="' . $channelChapter->chapter_id . '" data-url="' . $imgUrl . '" class="" id=""  src="' . URL::to('images') . '/btn-ok.png" />
								</div>
						</div>';
				}
        }
        
        $defaultFeatured = $this->post->getLastFivePostBasedOnChannel($channelDetails['mapping_id']);
        foreach ($featuredArrays as $featuredArray) {
            if ($featuredArray == '0') {
                foreach ($defaultFeatured as $lastPostItems) {
                    if (isset($lastPostItems->post_id) != "") {
                        if (!in_array($lastPostItems->post_id, $featuredArrays)) {
                            $key = array_search('0', $featuredArrays);
                            array_push($featured, S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url)));
                            $lastFeatured         = S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url));
                            $featuredArrays[$key] = $lastPostItems->post_id;
                        } else
                            continue;
                    } else
                        array_push($featured, "");
                    break;
                }
            } else {
                array_push($featured, S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($featuredArray))));
                $lastFeatured = S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($featuredArray)));
            }
        }
        $arrCount = count($featured);
        while ($arrCount < 5) {
            array_push($featured, "");
            $arrCount++;
        }
        
        if ($featured[0] != "")
            $lastFeatured = $featured[0];	
        foreach ($nonFeaturedPostArrays as $nonFeaturedPostArray) {
            if ($nonFeaturedPostArray == '0') {
                if (isset($nonFeaturedArrays[$j]) && $nonFeaturedArrays[$j] != "0") {
                    $nos = count($this->post->getPostsbyChapter($nonFeaturedArrays[$j]));
                    if ($nos > 0)
						$nonFeatured[$j] = S3::getS3Url(S3::getPostSdPath($this->post->getLastPostImageByChapter($nonFeaturedArrays[$j])));
                    else
                        $nonFeatured[$j] = "";
                } else
                    $nonFeatured[$j] = "";
            } else {
                if ($nonFeaturedPostArray != "0")
                    $nonFeatured[$j] = S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($nonFeaturedPostArray)));
                else
                    $nonFeatured[$j] = "";
            }
            $j++;
        }
        $cnt = sizeof($nonFeaturedArrays);
        if ($cnt < 8) {
            for ($k = $cnt; $k < 8; $k++) {
                $nonFeaturedArrays[$k] = 0;
                $nonFeatured[$k]       = "";
            }
        }
        $channelDet                          = $this->channel->getChannelById($channelDetails['mapping_id']);
        $cover                               = $channelDetails['inner_cover'] == "" ? S3::getS3Url(S3::getChannelAvatarPath('sd/' . $this->channel->getAvatarByChannelId($channelDetails['mapping_id']))) : S3::getS3Url(S3::getChannelCoverPhotoPath("sd/" . $channelDetails['inner_cover']));
        //$cover = $channelDetails['cover'] == "" ? S3::getS3Url(S3::getPostSdPath($this->post->getLastPostImageByChannel($channelDetails['mapping_id']))) : S3::getS3Url(S3::getChannelCoverPhotoPath("sd/".$channelDetails['cover']));
        $channelDetails['screen_name']       = $channelDetails['screen_name'] == "" ? ($channelDet['first_name'] . " " . $channelDet['last_name']) : $channelDetails['screen_name'];
        $this->data['mapping_id']            = $channelDetails['mapping_id'];
        $this->data['channelDetails']        = $channelDetails;
        $this->data['featured']              = $featured;
        $this->data['lastFeatured']          = $lastFeatured;
        $this->data['nonfeatured']           = $nonFeatured;
        $this->data['nonFeaturedArray']      = $nonFeaturedArrays;
        $this->data['featuredArray']         = $featuredArrays;
        $this->data['channels']              = $this->channel->getActiveChannels();
        $this->data['channelChapters']       = $str;
        $this->data['channelCover']          = $cover;
        $this->data['nonFeaturedCount']      = count($nonFeaturedArrays) > 8 ? count($nonFeaturedArrays) : 8;
        $this->data['hasChannelCover']       = $channelDetails['cover'] == "" ? 0 : 1;
        $this->data['latestEntry']           = $channelDetails['latestEntry'];
        $this->data['network_cover_profile'] = S3::getS3Url(S3::getChannelAvatarPath('sd/' . $this->channel->getAvatarByChannelId($channelDetails['mapping_id'])));        
        $this->layout->content = View::make('default.superadmin.edit-manage-network-channel')->with('data', $this->data);
    }
    
    public function channelExploreView()
    {
		$this->data['channel_id'] = Auth::user()->channel->channel_id;
        $networkDetails    = $this->manageexplore->getRowsByChannel(Auth::user()->channel->channel_id);		
        $this->data['res'] = $networkDetails;
        if(!is_null($networkDetails)) {
			if(count($this->post->getPostsByChannelId(Auth::user()->channel->channel_id)) > 0){
				$featuredArrays         = explode(',', $networkDetails['featured_list']);
				$nonFeaturedArrays      = explode(',', $networkDetails['non_featured_list']);
				$featured               = array();
				$featuredChapterDetails = explode(',', $networkDetails['featured_chapter_details']);
				$defaultFeatured        = $this->post->getLastFivePostBasedOnChannel(Auth::user()->channel->channel_id);
				/*if(count(array_filter($featuredArrays)>0)){
					$featuredArrays = array_filter($featuredArrays);
				}*/
				foreach ($featuredArrays as $featuredArray) {
					if ($featuredArray == '0') {
						foreach ($defaultFeatured as $lastPostItems) {
							if (isset($lastPostItems->post_id) != "") {
								if (!in_array($lastPostItems->post_id, $featuredArrays)) {
									$key = array_search('0', $featuredArrays);
									array_push($featured, array(
										
										'image' =>  $lastPostItems->sd_url == "" ? URL::asset('images/no_image_for_chapter_list.png') : S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url)),
										'poi-x' => $lastPostItems->poix,
										'poi-y' => $lastPostItems->poiy,
										'sd-w'  => $lastPostItems->sd_width,
										'sd-h'  => $lastPostItems->sd_height
									));
									$featuredArrays[$key] = $lastPostItems->post_id;
								} else
									continue;
							} else
								array_push($featured, array('image' => '',
									'poi-x' => 0,
									'poi-y' => 0,
									'sd-w'  => 0,
									'sd-h'  => 0
								));
							break;
						}
					} else {
						$post_details = $this->post->getPostById($featuredArray);
						array_push($featured, array(							
							'image' => $post_details->sd_url == "" ? URL::asset('images/no_image_for_chapter_list.png') : S3::getS3Url(S3::getPostSdPath($post_details->sd_url)),
							'poi-x' => $post_details->poix,
							'poi-y' => $post_details->poiy,
							'sd-w'  => $post_details->sd_width,
							'sd-h'  => $post_details->sd_height
						));
					}
				}
				$arrCount = count($featured);
				while ($arrCount < 5) {
					array_push($featured, array('image' => '',
						'poi-x' => 0,
						'poi-y' => 0,
						'sd-w'  => 0,
						'sd-h'  => 0
					));
					$arrCount++;
				}	
				
				$this->data['featured']           = $featured; 
				$this->data['display_type']           = $networkDetails['display_type'];  
				$allChapterArray = $this->chapter->getAllChapterIdsIncludingInactiveByChannel(Auth::user()->channel->channel_id);	
				$nonFeaturedArrays = array_filter($nonFeaturedArrays);
				$nonFeaturedCount = count(array_filter($nonFeaturedArrays));
				$chapterLists = array_merge($nonFeaturedArrays,array_diff($allChapterArray,$nonFeaturedArrays));
				$chapterDetails = $this->chapter->getChapterDetailsFromList($chapterLists);
				/*if($nonFeaturedCount > 0 && $nonFeaturedCount <= 8){
					$chapterDetails = array_slice($chapterDetails, 0, 8);  
				}elseif($nonFeaturedCount > 8){
					$chapterDetails = array_slice($chapterDetails, 0, $nonFeaturedCount);  
				}elseif($nonFeaturedCount == 0){
					$chapterDetails = array_slice($chapterDetails, 0, count($allChapterArray));  
				}*/
				$chapterDetails = array_slice($chapterDetails, 0, count($allChapterArray));  
				$chapters = array();
				foreach($chapterDetails as $chapterDetail){
					$postDetail = $chapterDetail['cover'] == 0 ? $this->post->getLastestPostByChapter($chapterDetail['chapter_id']) : $this->post->getPostById($chapterDetail['cover']);
					$likes = $this->like->getLikesByPostId($postDetail['post_id']);
					$isliked = $this->like->getUserLikedPostById(Auth::user()->channel->channel_id,$postDetail['post_id']);
					if(count($this->post->getPostByChapterId($chapterDetail['chapter_id']))> 0){
						$poix = $postDetail['type'] == 'event' && $postDetail['sd_url'] != '' ? 0.5 :  $postDetail['poix']-(1-$postDetail['poix']);
						$poiy = $postDetail['type'] == 'event' && $postDetail['sd_url'] != '' ? 0.5 :  $postDetail['poiy']-(1-$postDetail['poiy']);
						array_push($chapters,array('chapter_id' => $chapterDetail['chapter_id'],
							'chapter_name' => $chapterDetail['chapter_name'],
							'post_id' => $postDetail['post_id'],
							'image' => $postDetail['sd_url'],
							'poi_x'  => $poix,
							'poi_y'  => $poiy,
							'sd_w'  =>  $postDetail['sd_width'],
							'sd_h'  =>  $postDetail['sd_height'],
							'hd_w'  =>  $postDetail['hdthumb_width'],
							'hd_h'  =>  $postDetail['hdthumb_height'],
							'title'  =>  $postDetail['title'],
							'date'  =>  $postDetail['date'],
							'likes'  => $likes,
							'isliked' => $isliked
							
						));
					}
				}
				$this->data['chapters']      = $chapters;
				$this->data['message'] = "";
			}else{
				$this->data['message'] = "no post"; 
			}				
		}else{
			$this->data['message'] = "not listed"; 
		}
        $this->layout->content = View::make('default.post.chapterexplore')->with('data', $this->data);
    }
	
	/*
	 public function channelExploreView()
    {
		
        $lastFeatured      = "";
        $networkDetails    = $this->manageexplore->getRowsByChannel(Auth::user()->channel->channel_id);
        $this->data['res'] = $networkDetails;
        if (!is_null($networkDetails)) {
            $featuredArrays         = explode(',', $networkDetails['featured_list']);
            $nonFeaturedArrays      = explode(',', $networkDetails['non_featured_list']);
            $nonFeatured            = array();
            $j                      = 0;
            $i                      = 0;
            $featured               = array();
            $nonFeaturedChapterList = array();
            $nonFeaturedPosts       = array();
            $nonFeaturedPostArray   = explode(',', $networkDetails['non_featured_post_list']);
            $latestEntry            = $networkDetails['latestEntry'];
            $featuredChapterDetails = explode(',', $networkDetails['featured_chapter_details']);
            $defaultFeatured        = $this->post->getLastFivePostBasedOnChannel(Auth::user()->channel->channel_id);
            foreach ($featuredArrays as $featuredArray) {
                if ($featuredArray == '0') {
                    foreach ($defaultFeatured as $lastPostItems) {
                        if (isset($lastPostItems->post_id) != "") {
                            if (!in_array($lastPostItems->post_id, $featuredArrays)) {
                                $key = array_search('0', $featuredArrays);
                                array_push($featured, S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url)));
                                $lastFeatured         = S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url));
                                $featuredArrays[$key] = $lastPostItems->post_id;
                            } else
                                continue;
                        } else
                            array_push($featured, "");
                        break;
                    }
                } else {
                    array_push($featured, S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($featuredArray))));
                    $lastFeatured = S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($featuredArray)));
                }
            }
            $arrCount = count($featured);
            while ($arrCount < 5) {
                array_push($featured, "");
                $arrCount++;
            }
            if ($featured[0] != "")
                $lastFeatured = $featured[0];			
			$nonFeaturedCount = sizeof(array_filter($nonFeaturedArrays));
			if($nonFeaturedCount < 8){
				$nonFeaturedArrays = array_filter($nonFeaturedArrays);
				$cpList = $this->chapter->getActiveFeaturedChaptersByChannel(Auth::user()->channel->channel_id);
				$extraChapters = array_diff($cpList,$nonFeaturedArrays);
				$nonFeaturedArrays = array_merge($nonFeaturedArrays, $extraChapters); 
				if(sizeof($nonFeaturedArrays) < 8 ){
					for($k = sizeof($nonFeaturedArrays);$k<8;$k++){
						$nonFeaturedArrays[$k] = 0;
					}
				}
				$nonFeaturedArrays = array_slice($nonFeaturedArrays,0,8);
			}
			foreach ($nonFeaturedArrays as $nonFeaturedArray) {
				if ($nonFeaturedArray == '0')
					$nonFeatured[$j] = "";
				else {
					$nonFeaturedChapterList[$j] = $this->chapter->getChapterById($nonFeaturedArray);
					if ($nonFeaturedPostArray[$j] == '0') {
						$nos = count($this->post->getPostsbyChapter($nonFeaturedArray));
						if ($nos > 0) {
							$getLastPostByChapter = $this->post->getLastPostByChapter($nonFeaturedArray);
							$nonFeatured[$j]      = S3::getS3Url(S3::getPostSdPath($getLastPostByChapter['sd_url']));
						} else
							$nonFeatured[$j] = "";
						
					} else {
						
						$nonFeatured[$j] = S3::getS3Url(S3::getPostSdPath($this->post->getPostImageById($nonFeaturedPostArray[$j])));
						
					}
				}
				$j++;
			}
            $channelChapters = $this->chapter->getChaptersByChannelId(Auth::user()->channel->channel_id);
            $str             = "";
            foreach ($channelChapters as $channelChapter) {
				$nos  = count($this->post->getPostsbyChapter($channelChapter->chapter_id));
            	if ($nos > 0) {
					$getLastPostByChapter = $this->post->getLastPostByChapter($channelChapter->chapter_id);
					$imgUrl = S3::getS3Url(S3::getPostSdPath($getLastPostByChapter['sd_url']));
					$str .= '<div class="channel_chapter_row col-md-12 col-xs-12 col-sm-12 remove-padding">
								<div class="channel_chapter col-md-6 col-xs-6 col-sm-6">' . $channelChapter->chapter_name . '</div>
								<div class="channel_chapter_head_image col-md-4 col-xs-4 col-sm-4"><img class="channel-chapter-image" src="' . $imgUrl . '" /></div>
								<div class="channel_chapter_btn_ok col-md-2 col-xs-2 col-sm-2">
									<img data-name = "' . $channelChapter->chapter_name . '" data-id="' . $channelChapter->chapter_id . '" data-url="' . $imgUrl . '" class="" id=""  src="' . URL::to('images') . '/btn-ok.png" />
								</div>
						</div>';
				}
            }
            
            $htmlStr = "";
            $htmlStr .= '<div class="me-chapter-listing">';
            $chapters = $this->chapter->getChaptersByChannelId(Auth::user()->channel->channel_id);
            foreach ($chapters as $chapter) {
                $postDetails          = $this->post->getPostsbyChapter($chapter->chapter_id);
                $getLastPostByChapter = $this->post->getLastPostByChapter($chapter->chapter_id);
                $chapterLastPostImage = S3::getS3Url(S3::getPostSdPath($getLastPostByChapter['sd_url']));
                $img                  = $mus = $vid = $evt = 0;
                $substr               = "";
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
                    $imgUrl   = S3::getS3Url(S3::getPostHdthumbPath($postDetail->sd_url));
                    $imgSdUrl = S3::getS3Url(S3::getPostSdPath($postDetail->sd_url));
                    $substr .= '<div class="me-chapter-post-images"><div  class="me-chapter-post-images-inner ' . $classOverlay . '"><img onclick="storyImageSelectorCptr(' . $postDetail->post_id . ')"id="storyImageSelectorCptr_' . $postDetail->post_id . '" data-id="' . $postDetail->post_id . '" data-url="' . $imgSdUrl . '" data-chapter="'. $chapter->chapter_id .'"id="selectedChapterStoryThumb-"' . $postDetail->post_id . ' class="img-rounded selectedChapterStoryThumb" src="' . $imgUrl . '" width="60" height="55"/></div></div>';
                    
                }
                $htmlStr .= '<div class="me-chapter" id="me-chapter-' . $chapter->chapter_id . '">
			 		 	<div class="me-chapter-head col-md-12 col-xs-12 col-sm-12" id="head-drp-cptr-' . $chapter->chapter_id . '">
                        	<div class="me-chapter-text col-md-6 col-xs-6 col-sm-6">
								<div class="me-chapter-name">' . $chapter->chapter_name . '</div>
								<div class="me-post-type">
									<span>AUDIO:' . $mus . '</span><span>VIDEO:' . $vid . '</span> <span>IMG:' . $img . '</span><span>EVENT:' . $evt . '</span>
								</div>
							</div>
                         	<div class="me-chapter-head-image col-md-4 col-xs-4 col-sm-4"><img width="146" src="' . $chapterLastPostImage . '" /></div>
                         	<div class="me-chapter-head-drop col-md-2 col-xs-2 col-sm-2">
								<img id="drp-cptr-' . $chapter->chapter_id . '" onClick="sliderContentChapter(' . $chapter->chapter_id . ')" src="' . URL::to('images') . '/me-button-down.png" />
							</div>
                     	</div>
						
						<div id="open-drp-cptr-' . $chapter->chapter_id . '" class="me-chapter-content">';
                $htmlStr .= $substr;
                $htmlStr .= '</div>';
                $htmlStr .= '</div>';
            }
            $htmlStr .= '</div>';
            $this->data['storySelectorHtml']      = $htmlStr;		
            $this->data['nonFeaturedPostArray']   = $nonFeaturedPostArray;
            $this->data['networkDetails']         = $networkDetails;
            $this->data['featured']               = $featured;
            $this->data['lastFeatured']           = $lastFeatured;
            $this->data['nonfeatured']            = $nonFeatured;
            $this->data['nonFeaturedArray']       = $nonFeaturedArrays;
            $this->data['featuredArray']          = $featuredArrays;
            $this->data['channelChapters']        = $str;
            $this->data['nonFeaturedCount']       = count($nonFeaturedArrays) > 8 ? count($nonFeaturedArrays) : 8;
            $this->data['nonFeaturedChapterList'] = $nonFeaturedChapterList;
            $this->data['latestEntry']            = $latestEntry;
        }
        $this->layout->content = View::make('default.post.chapterexplore')->with('data', $this->data);
    }
	*/
    
    public function manageNetworkStoriesList($id)
    {
        $this->data['stories'] = $this->manageexplore->getStoriesByChannel($id);
        $this->layout->content = View::make('default.superadmin.list_stories')->with('data', $this->data);
    }
    
    /*public function userEditManageExploreNetwork()
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
        $ajaxController = new AjaxController($this->admin, $this->channel, $this->chapter, $this->favorite, $this->follow, $this->like, $this->post, $this->session, $this->subscribe, $this->user, $this->conversion,     $this->channelgroup,  $this->manageexplore, $this->event,  $this->channelgrid);
        if ($res){
            $ajaxController->createAdvastJson(Auth::user()->channel->channel_id); 
            Session::flash('success', 'Update Successful!');
        }else{
            Session::flash('warning', 'Update Failed!');
        }
        return Redirect::to('cms/channel/explore')->with('data', $this->data);
    }*/
    
    public function twitterSharePlayer($id)
    {
        $postDet = $this->post->getPostsByPostId($id);
        if ($postDet[0]['type'] == 'video') {
            $videoname                  = explode('.', $postDet[0]['video_url']);
            $this->data['url']          = S3::getS3Url(S3::getPostVideoPath($videoname[0]));
            $this->data['subscription'] = $postDet[0]['subscription_id'] == "" ? "" : "isFeaturedVideo";
        } elseif ($postDet[0]['type']   == 'music') {
            $this->data['url']          = S3::getS3Url(S3::getPostMusicPath($postDet[0]['music_url']));
            $this->data['subscription'] = $postDet[0]['subscription_id'] == "" ? "" : "isFeaturedAudio";
            $this->data['artist']       = $postDet[0]['author'];
            $this->data['song']         = $postDet[0]['song'];
        }
        $this->data['image_url']        = S3::getS3Url(S3::getPostSdPath($postDet[0]['sd_url']));
        $this->data['post_id']          = $postDet[0]['post_id'];
        $this->data['type']             = $postDet[0]['type'];
        $this->data['short_url']        = $postDet[0]['short_url'];
        $this->data['title']            = $postDet[0]['title'];
        $this->data['paying_subcriber_only']  = $postDet[0]['featured'];
        $channelname = $this->post->getChannelNameByPostId($id);
        $this->data['channelname'] = $channelname[0]['first_name'].' '.$channelname[0]['last_name'];
        return View::make('default.post.twittershareplayer')->with('data', $this->data);
    }
    
    public function bouncerLists()
    {
        $this->data['events'] = $this->post->getEventsBasedOnChannel(Auth::user()->channel->channel_id, 2);
        return View::make('default.bouncer.list')->with('data', $this->data);
    }
    
    public function bouncerEventAdd()
    {
        $eventCode                               = 'vast' . time();
        $filename                                = 'vastvideo' . time() . rand(0, 9) . '.tmp';
        $uploadCredentials                       = S3::getUploadPermission('videos/' . $filename);
        $this->data['filename']                  = $filename;
        $this->data['policy']                    = $uploadCredentials['policy'];
        $this->data['signature']                 = $uploadCredentials['signature'];
        $secfilename                             = 'vastsecvideo' . time() . rand(0, 9) . '.tmp';
        $uploadCredentials                       = S3::getUploadPermission('secvideo/' . $secfilename);
        $this->data['secfilename']               = $secfilename;
        $this->data['secpolicy']                 = $uploadCredentials['policy'];
        $this->data['secsignature']              = $uploadCredentials['signature'];
        $this->data['chapters']                  = $this->chapter->getChaptersByChannel(Auth::user()->channel);
        $this->data['vip_chapter_id']            = $this->chapter->getChapterVipBasedOnChannel(Auth::user()->channel->channel_id);
        $this->data['userChannel']['session_id'] = Crypt::encrypt(Auth::user()->vast_user_id);
        $this->data['eventCode']                 = $eventCode;
        return View::make('default.bouncer.write')->with('data', $this->data);
    }
    
    public function bouncerAdminLists()
    {
        $this->data['admins'] = $this->admin->getBouncerAdminsBasedOnChannel(Auth::user()->channel->channel_id);
        return View::make('default.bouncer.admins')->with('data', $this->data);
    }
    
    public function bouncerAddAdmin()
    {
        $this->data['action'] = "add";
        return View::make('default.bouncer.addadmin')->with('data', $this->data);
    }
    
    public function bouncerEditAdmin($id)
    {
        $this->data['action']  = "edit";
        $this->data['bouncer'] = $this->admin->getBouncerAdminBasedOnId($id);
        return View::make('default.bouncer.addadmin')->with('data', $this->data);
    }
    
    public function bouncerEventEdit($id,$type=0)
    {
		$this->data['event_type'] = $type;
        $this->data['event'] = $this->post->getEventByPostId($id);
        return View::make('default.bouncer.edit')->with('data', $this->data);
    }
    
    public function channelGridView($id)
    {
		$this->data['channelgrid'] = $this->channelgrid->getChannelGridByGridId($id);
		$channelGrid = $this->channelgrid->getChannelGridByGridId($id);
        $this->data['feeds'] = $this->post->getPostsByChannelIdForChannellGrid($channelGrid['channel_id']);
        return View::make('default.channelgrid.view')->with('data', $this->data);
    }
	
	public function sharePost($id) {       
        $this->data['post_details']  = $this->post->getPostsByPostId($id);
        return View::make('default.post.share')->with('data', $this->data);
    }
	
	public function channelGridCreate() {      
        $this->data['channels']  = $this->channel->getActiveChannels();
        return View::make('default.grid.create')->with('data', $this->data);
    }
	
	public function privacy() {       
        return View::make('default.pages.privacy')->with('data', $this->data);
    }
	
	/*public function grid($id){ 
		//$chapters = $this->chapter->getChaptersAndFirstPostByChannelId(24);
		//print_r($chapters);
		//die();
		$this->data['channelgrid'] = $this->channelgrid->getChannelGridByGridId($id);
		$channelGrid = $this->channelgrid->getChannelGridByGridId($id);
		$defaultVideoName = explode('.',$channelGrid->default_video);
		$this->data['defaultVideoName'] = $defaultVideoName[0];

		$this->data['channelname'] = $this->channel->getChannelById($channelGrid['channel_id']);
		$firstChapter = $this->chapter->getFirstChapterByChannel($channelGrid['channel_id']);
		$selectedChapter = $channelGrid['chapter_id'] == 0 ? $firstChapter['chapter_id'] : $channelGrid['chapter_id'];		
		$posts = $this->post->getPostsbyChapter($selectedChapter);
		$resp = $thumbResp = '';
		$resp .= '<div class="slider gridSlider">';
		$thumbResp .= '<img src="'.URL::to('images/showHideStripButton@2x.png') .'" id="thumbnailToggler" width="80" /><div class="slider" id="gridThumbSlider">';
		if($channelGrid['default_video'] != ""){
			$video =  explode('.',$channelGrid['default_video']);
			$poster = $channelGrid['poster'];
			$resp .= '<div class="slide_div"><iframe src="'.URL::to('/').'/cms/videoplayer/'.$video[0].'/1/'.$poster.'"></iframe></div>';
			$thumbResp .= '<div class="thumb_slide"><img  src="'. S3::getS3Url(S3::getPostSdPath($channelGrid['poster'])) .'" /></div>';	
		}
		$i = 1;
		foreach($posts as $post){
			if($post->type == "image"){
				$resp .= '<div class="slide_div"><img  data-lazy="'. S3::getS3Url(S3::getPostSdPath($post->sd_url)) .'" /></div>';
			}elseif($post->type == "music"){
				$resp .= '<div class="slide_div"> <img  data-lazy="'. S3::getS3Url(S3::getPostSdPath($post->sd_url)) .'" /></div>';
			}elseif($post->type == "video"){
				if($channelGrid['default_video'] != $post->video_url){
					$video =  explode('.',$post->video_url);
					$poster = $post->sd_url;
					$resp .= '<div class="slide_div"><iframe src="'.URL::to('/').'/cms/videoplayer/'.$video[0].'/0/'.$poster.'"></iframe></div>';
				}
			}
			if($channelGrid['poster'] != $post->sd_url)
				$thumbResp .= '<div class="thumb_slide"><img  src="'. S3::getS3Url(S3::getPostPhotoPath($post->sd_url)) .'" /></div>';		
			$i++;
		}
		$resp .= '</div>';
		$thumbResp .= '</div>';
		$this->data['sliderData'] = $resp;
		$this->data['thumbdata'] = $thumbResp;
		$chapters = $this->chapter->getChaptersByChannelId($channelGrid['channel_id']);
		$chapterLists = array();
		foreach($chapters as $chapter){
			$lastPost = $this->post->getLastPostByChapter($chapter->chapter_id);
			if($lastPost['sd_url']){					
				$detArray = ["chapter" => $chapter->chapter_name,'chapter_id' => $chapter->chapter_id,"img" =>  '<div class="grid_channel_element_thumbname"><div>'.$chapter->chapter_name.'</div></div><div data-poix="'.$lastPost['poix'].'" data-poiy="'.$lastPost['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostSdPath($lastPost->sd_url)).');" class="grid_chapter_element" data-chapter-id = "'.$chapter->chapter_id.'" data-img-width = "'.$lastPost['sd_width'].'" data-img-height="'.$lastPost['sd_height'].'"></div>'];
			$chapterLists[] = $detArray;
			}
			
		}
		$this->data['chapterLists'] = $chapterLists;
        return View::make('default.grid.view')->with('data', $this->data);
    }*/
	
	 public function gridVideoPlayer($video,$type,$poster){
	 	$this->data['video'] = $video;
		$this->data['poster'] = $poster;
		$this->data['type'] = $type;
        return View::make('default.grid.video')->with('data', $this->data);
	 }
	 
	 public function getPostWithImageByChapterId($chapter,$offset){
		$resp = $this->post->getPostWithFromOffsetByChapter($chapter,$offset);
		//print_r($resp);exit;
		$img = S3::getS3Url(S3::getPostPhotoPath($resp->sd_url));
		if (($img_info = getimagesize($img)) === FALSE){
		echo $offset; 
			$offset++;
			self::getPostWithImageByChapterId($chapter,$offset);
		}
		return $resp;
		
	 }
	 
	 public function gridView($channelid,$video,$chapterid,$poster){
	 	$manageExplore =  $this->manageexplore->getRowsByChannel($channelid);
		$postList = explode(',',$manageExplore['non_featured_post_list']);
		$chaptertList = explode(',',$manageExplore['non_featured_list']);	
		$postCount = count(array_filter($postList));
		$chapterCount = count(array_filter($chaptertList));
		if($postCount < 8){
			for($i = $postCount ;$i<8;$i++){
				$postList[$i] = 0;
			}
			for($i = $chapterCount ;$i<8;$i++){
				$chaptertList[$i] = 0;
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
		$thumbDetails = array();
		foreach($gridPostChannelDetails as $gridPostChannelDetail){
			$detArray = ["chapter" => $gridPostChannelDetail['chapter_name'],'chapter_id' => $gridPostChannelDetail['chapter_id'],'post' =>  $gridPostChannelDetail['post_id'],"img" =>  '<div class="grid_channel_element_thumbname"><div><span class="coloredVastFirstSlash">\</span>'.$gridPostChannelDetail['chapter_name'].'</div></div><div data-poix="'.$gridPostChannelDetail['poix'].'" data-poiy="'.$gridPostChannelDetail['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostSdPath($gridPostChannelDetail['sd_url'])).');" class="grid_chapter_element" data-chapter-id = "'.$gridPostChannelDetail['chapter_id'].'" data-img-width = "'.$gridPostChannelDetail['sd_width'].'" data-img-height="'.$gridPostChannelDetail['sd_height'].'" data-post="'.$gridPostChannelDetail['post_id'].'"></div>'];
			//$thumbDetails[] = $detArray;	
			if($chapterid == $gridPostChannelDetail['chapter_id'])
				array_unshift($thumbDetails,$detArray);
			else
				array_push($thumbDetails,$detArray);	
	
		}		
		$selectedChapter = $chapterid == 0 ? $chaptertList[0] : $chapterid;		
		$posts = $this->post->getPostsbyChapter($selectedChapter);
		$resp = $thumbResp = '';
		$resp .= '<div class="slider gridSlider">';
		$thumbResp .= '<img src="'.URL::to('images/showHideStripButton@2x.png') .'" id="thumbnailToggler" width="80" /><div class="slider" id="gridThumbSlider">';
		$story = $postTitle = $shortUrl = $secVideo = $secAudio = "";
		if($video != '0'){	
			$resp .= '<div class="slide_div"><iframe src="'.URL::to('/').'/cms/videoplayer/'.$video.'/1/'.$poster.'"></iframe></div>';
			$thumbResp .= '<div class="thumb_slide"><img  src="'. S3::getS3Url(S3::getPostSdPath($poster)) .'" /></div>';	
		}
		$i = 1;
		$st = 0;
		foreach($posts as $post){
			$postVideo = explode('.',$post->video_url);
			$postVideo =  $postVideo[0];
			if($post->second_video !=""){
				$secVideo = explode('.',$post->second_video);
				$secVideo = $secVideo[0];
			}
			if($i == 1){
				$story = $post->story;
				$postTitle = $post->title;
				$shortUrl = $post->short_url;
				$secVideo = $secVideo;
				$secAudio = $post->audio_url;
			}
			if($post->type == "image"){
				$resp .= '<div class="slide_div"><img  data-lazy="'. S3::getS3Url(S3::getPostSdPath($post->sd_url)) .'" /><input type="hidden" class="currentSlideDetails" data-secvideo="'.$secVideo.'" data-secaudio="'.$post->audio_url.'" data-short-url="'.$post->short_url.'" data-post-name="'.$post->title.'" value="'.$post->story.'" /></div>';
			}elseif($post->type == "music"){
				$resp .= '<div class="slide_div"> <img  data-lazy="'. S3::getS3Url(S3::getPostSdPath($post->sd_url)) .'" /><input type="hidden" class="currentSlideDetails" data-secvideo="'.$secVideo.'" data-secaudio="'.$post->audio_url.'" data-short-url="'.$post->short_url.'" data-post-name="'.$post->title.'" value="'.$post->story.'" /></div>';
			}elseif($post->type == "video"){
				if($video != $postVideo){
					$poster = $post->sd_url;
					$resp .= '<div class="slide_div"><iframe src="'.URL::to('/').'/cms/videoplayer/'.$postVideo.'/0/'.$poster.'"></iframe><input type="hidden" class="currentSlideDetails" data-secvideo="'.$secVideo.'" data-secaudio="'.$post->audio_url.'" 	 data-short-url="'.$post->short_url.'" data-post-name="'.$post->title.'" value="'.$post->story.'" /></div>';
				}else{
					$story = $post->story;
					$postTitle = $post->title;
					$shortUrl = $post->short_url;
					$secVideo = explode('.',$post->second_video);
					$secVideo = $secVideo[0];
					$secAudio = $post->audio_url;
					$st = 1;
				}
			}
			if($video != $postVideo)
				$thumbResp .= '<div class="thumb_slide"><img  src="'. S3::getS3Url(S3::getPostPhotoPath($post->sd_url)) .'" /></div>';					
			$i++;
		}
		
		$resp .= '</div>';
		$thumbResp .= '</div>';
		$this->data['sliderData'] = $resp;
		$this->data['thumbdata'] = $thumbResp;
		$this->data['thumbDetails'] = $thumbDetails;
		$this->data['defaultVideoName'] = $video;
		$this->data['channelname'] = $gridPostChannelDetails[0]['first_name']." ".$gridPostChannelDetails[0]['last_name'];
		$this->data['avatar'] = S3::getS3Url(S3::getChannelAvatarPath('thumb/'.$gridPostChannelDetails[0]['avatar']));
		$this->data['fb_url'] = $gridPostChannelDetails[0]['fb_url'];
		$this->data['twitter_url'] = $gridPostChannelDetails[0]['twitter_url'];
		$this->data['story'] = $story;
		$this->data['post-name'] = $postTitle;
		$this->data['secVideo'] = $secVideo;
		$this->data['secAudio'] = $secAudio;
		$this->data['st'] = $st;
		$this->data['fbSocialShare'] = 'https://www.facebook.com/sharer/sharer.php?u='.$shortUrl;
		$this->data['twSocialShare'] = 'https://twitter.com/intent/tweet?text=Check My Vast&url='.$shortUrl.'&via=GetVASTnow';
        return View::make('default.grid.view')->with('data', $this->data);
    }
	
	public function gridViewApp($channelid){
	 	$manageExplore =  $this->manageexplore->getRowsByChannel($channelid);
		$postList = explode(',',$manageExplore['non_featured_post_list']);
		$chaptertList = explode(',',$manageExplore['non_featured_list']);
		$featuredPosts =  explode(',',$manageExplore['featured_list']);	
		$postCount = count(array_filter($postList));
		$chapterCount = count(array_filter($chaptertList));
		$chapterid = 0;
		if($postCount < 8){
			for($i = $postCount ;$i<8;$i++){
				$postList[$i] = 0;
			}
			for($i = $chapterCount ;$i<8;$i++){
				$chaptertList[$i] = 0;
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
		$thumbDetails = array();
		foreach($gridPostChannelDetails as $gridPostChannelDetail){
			$detArray = ["chapter" => $gridPostChannelDetail['chapter_name'],'chapter_id' => $gridPostChannelDetail['chapter_id'],'post' =>  $gridPostChannelDetail['post_id'],"img" =>  '<div class="grid_channel_element_thumbname"><div><span class="coloredVastFirstSlash">\</span>'.$gridPostChannelDetail['chapter_name'].'</div></div><div data-poix="'.$gridPostChannelDetail['poix'].'" data-poiy="'.$gridPostChannelDetail['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostSdPath($gridPostChannelDetail['sd_url'])).');" class="grid_chapter_element" data-chapter-id = "'.$gridPostChannelDetail['chapter_id'].'" data-img-width = "'.$gridPostChannelDetail['sd_width'].'" data-img-height="'.$gridPostChannelDetail['sd_height'].'" data-post="'.$gridPostChannelDetail['post_id'].'"></div>'];
			//$thumbDetails[] = $detArray;	
			if($chapterid == $gridPostChannelDetail['chapter_id'])
				array_unshift($thumbDetails,$detArray);
			else
				array_push($thumbDetails,$detArray);	
	
		}		
		$selectedChapter = $chapterid == 0 ? $chaptertList[0] : $chapterid;		
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
			$resp .= '<div class="slide_div"><img  data-lazy="'. S3::getS3Url(S3::getPostSdPath($post['sd_url'])) .'" /><div class="mainAppPostTitle">'.$post['title'].'</div></div>';
		}
		
		$resp .= '</div>';
		$this->data['sliderData'] = $resp;
		$this->data['thumbdata'] = '';
		$this->data['thumbDetails'] = $thumbDetails;
		$this->data['defaultVideoName'] = '';
		$this->data['channelname'] = $gridPostChannelDetails[0]['first_name']." ".$gridPostChannelDetails[0]['last_name'];
		$this->data['avatar'] = S3::getS3Url(S3::getChannelAvatarPath('thumb/'.$gridPostChannelDetails[0]['avatar']));
		$this->data['fb_url'] = $gridPostChannelDetails[0]['fb_url'];
		$this->data['twitter_url'] = $gridPostChannelDetails[0]['twitter_url'];
		$this->data['story'] = '';
		$this->data['post-name'] = '';
		$this->data['secVideo'] = '';
		$this->data['secAudio'] = '';
		$this->data['st'] = 0;
		$this->data['fbSocialShare'] = 'https://www.facebook.com/sharer/sharer.php?u='.$post['sd_url'];
		$this->data['twSocialShare'] = 'https://twitter.com/intent/tweet?text=Check My Vast&url='.$post['sd_url'].'&via=GetVASTnow';
        return View::make('default.grid.view')->with('data', $this->data);
		
    }
	
	public function gridViewResponsiveApp($channelid){
	 	$manageExplore =  $this->manageexplore->getRowsByChannel($channelid);
		$postList = explode(',',$manageExplore['non_featured_post_list']);
		$chaptertList = explode(',',$manageExplore['non_featured_list']);
		$featuredPosts =  explode(',',$manageExplore['featured_list']);	
		$postCount = count(array_filter($postList));
		$chapterCount = count(array_filter($chaptertList));
		$chapterid = 0;
		if($postCount < 8){
			for($i = $postCount ;$i<8;$i++){
				$postList[$i] = 0;
			}
			for($i = $chapterCount ;$i<8;$i++){
				$chaptertList[$i] = 0;
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
		$thumbDetails = array();
		foreach($gridPostChannelDetails as $gridPostChannelDetail){
			//$detArray = ["chapter" => $gridPostChannelDetail['chapter_name'],'chapter_id' => $gridPostChannelDetail['chapter_id'],'post' =>  $gridPostChannelDetail['post_id'],"img" =>  '<div class="grid_channel_element_thumbname"><div><span class="coloredVastFirstSlash">\</span>'.$gridPostChannelDetail['chapter_name'].'</div></div><div data-poix="'.$gridPostChannelDetail['poix'].'" data-poiy="'.$gridPostChannelDetail['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostSdPath($gridPostChannelDetail['sd_url'])).');" class="grid_chapter_element" data-chapter-id = "'.$gridPostChannelDetail['chapter_id'].'" data-img-width = "'.$gridPostChannelDetail['sd_width'].'" data-img-height="'.$gridPostChannelDetail['sd_height'].'" data-post="'.$gridPostChannelDetail['post_id'].'"></div>'];
			//$thumbDetails[] = $detArray;	
			
			$detArray = ["chapter" => $gridPostChannelDetail['chapter_name'],'chapter_id' => $gridPostChannelDetail['chapter_id'],'post' =>  $gridPostChannelDetail['post_id'],"img" =>  '<div class="grid_channel_element_thumbname"><div><span class="coloredVastFirstSlash">\</span>'.$gridPostChannelDetail['chapter_name'].'</div></div><div data-poix="'.$gridPostChannelDetail['poix'].'" data-poiy="'.$gridPostChannelDetail['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostHdthumbPath($gridPostChannelDetail['sd_url'])).');" class="grid_chapter_element" data-chapter-id = "'.$gridPostChannelDetail['chapter_id'].'" data-img-width = "'.$gridPostChannelDetail['hdthumb_width'].'" data-img-height="'.$gridPostChannelDetail['hdthumb_height'].'" data-post="'.$gridPostChannelDetail['post_id'].'"></div>'];
			if($chapterid == $gridPostChannelDetail['chapter_id'])
				array_unshift($thumbDetails,$detArray);
			else
				array_push($thumbDetails,$detArray);	
	
		}		
		$selectedChapter = $chapterid == 0 ? $chaptertList[0] : $chapterid;		
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
		$this->data['sliderData'] = $resp;
		$this->data['thumbdata'] = '';
		$this->data['thumbDetails'] = $thumbDetails;
		$this->data['defaultVideoName'] = '';
		$this->data['channelname'] = $gridPostChannelDetails[0]['first_name']." ".$gridPostChannelDetails[0]['last_name'];
		$this->data['avatar'] = S3::getS3Url(S3::getChannelAvatarPath('thumb/'.$gridPostChannelDetails[0]['avatar']));
		$this->data['fb_url'] = $gridPostChannelDetails[0]['fb_url'];
		$this->data['twitter_url'] = $gridPostChannelDetails[0]['twitter_url'];
		$this->data['story'] = '';
		$this->data['post-name'] = '';
		$this->data['secVideo'] = '';
		$this->data['secAudio'] = '';
		$this->data['st'] = 0;
		$this->data['fbSocialShare'] = 'https://www.facebook.com/sharer/sharer.php?u='.$post['sd_url'];
		$this->data['twSocialShare'] = 'https://twitter.com/intent/tweet?text=Check My Vast&url='.$post['sd_url'].'&via=GetVASTnow';
        return View::make('default.grid.viewgrid')->with('data', $this->data);
		
    }
	
	public function adVastBannerApp($channelid,$bannertype){
	 	$manageExplore =  $this->manageexplore->getRowsByChannel($channelid);
		$postList = explode(',',$manageExplore['non_featured_post_list']);
		$chaptertList = explode(',',$manageExplore['non_featured_list']);
		$featuredPosts =  explode(',',$manageExplore['featured_list']);	
		$postCount = count(array_filter($postList));
		$chapterCount = count(array_filter($chaptertList));
		$chapterid = 0;
		if($postCount < 8){
			for($i = $postCount ;$i<8;$i++){
				$postList[$i] = 0;
			}
			for($i = $chapterCount ;$i<8;$i++){
				$chaptertList[$i] = 0;
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
			/*$detArray = ["chapter" => $gridPostChannelDetail['chapter_name'],'chapter_id' => $gridPostChannelDetail['chapter_id'],'post' =>  $gridPostChannelDetail['post_id'],"img" =>  '<div class="grid_channel_element_thumbname"><div><span class="coloredVastFirstSlash">\</span>'.$gridPostChannelDetail['chapter_name'].'</div></div><div data-poix="'.$gridPostChannelDetail['poix'].'" data-poiy="'.$gridPostChannelDetail['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostSdPath($gridPostChannelDetail['sd_url'])).');" class="grid_chapter_element" data-chapter-id = "'.$gridPostChannelDetail['chapter_id'].'" data-img-width = "'.$gridPostChannelDetail['sd_width'].'" data-img-height="'.$gridPostChannelDetail['sd_height'].'" data-post="'.$gridPostChannelDetail['post_id'].'"></div>'];*/
			
			$detArray = ["chapter" => $gridPostChannelDetail['chapter_name'],'chapter_id' => $gridPostChannelDetail['chapter_id'],'post' =>  $gridPostChannelDetail['post_id'],"img" =>  '<div class="grid_channel_element_thumbname"><div><span class="coloredVastFirstSlash">\</span>'.$gridPostChannelDetail['chapter_name'].'</div></div><div data-poix="'.$gridPostChannelDetail['poix'].'" data-poiy="'.$gridPostChannelDetail['poiy'].'" style="background-image:url('.S3::getS3Url(S3::getPostHdthumbPath($gridPostChannelDetail['sd_url'])).');" class="grid_chapter_element" data-chapter-id = "'.$gridPostChannelDetail['chapter_id'].'" data-img-width = "'.$gridPostChannelDetail['hdthumb_width'].'" data-img-height="'.$gridPostChannelDetail['hdthumb_height'].'" data-post="'.$gridPostChannelDetail['post_id'].'"></div>'];
			//$thumbDetails[] = $detArray;	
			$adBannerArray = ['src' =>S3::getS3Url(S3::getPostHdthumbPath($gridPostChannelDetail['sd_url'])),'poix'=>$gridPostChannelDetail['poix'],'poiy'=>$gridPostChannelDetail['poiy'],'imgW' =>$gridPostChannelDetail['hdthumb_width'],'imgH' => $gridPostChannelDetail['hdthumb_height'],'chapter' => $gridPostChannelDetail['chapter_id'],'post' => $gridPostChannelDetail['post_id']];
			if($chapterid == $gridPostChannelDetail['chapter_id']){
				array_unshift($thumbDetails,$detArray);
				array_unshift($adBannerDetails,$adBannerArray);				
			}else{
				array_push($thumbDetails,$detArray);
				array_push($adBannerDetails,$adBannerArray);				
			}
					
	
		}		
		$selectedChapter = $chapterid == 0 ? $chaptertList[0] : $chapterid;		
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
		$this->data['sliderData'] = $resp;
		$this->data['thumbdata'] = '';
		$this->data['adBannerDetails'] = $adBannerDetails;
		$this->data['thumbDetails'] = $thumbDetails;
		$this->data['defaultVideoName'] = '';
		$this->data['channelname'] = $gridPostChannelDetails[0]['first_name']." ".$gridPostChannelDetails[0]['last_name'];
		$this->data['avatar'] = S3::getS3Url(S3::getChannelAvatarPath('thumb/'.$gridPostChannelDetails[0]['avatar']));
		$this->data['fb_url'] = $gridPostChannelDetails[0]['fb_url'];
		$this->data['twitter_url'] = $gridPostChannelDetails[0]['twitter_url'];
		$this->data['youtube_url'] = $gridPostChannelDetails[0]['youtube_url'] != "" ? $gridPostChannelDetails[0]['youtube_url'] : "#";
		$this->data['instagram_url'] = $gridPostChannelDetails[0]['instagram_url'] != "" ? $gridPostChannelDetails[0]['instagram_url'] : "#";
		$this->data['story'] = '';
		$this->data['post-name'] = '';
		$this->data['secVideo'] = '';
		$this->data['secAudio'] = '';
		$this->data['st'] = 0;
		$this->data['bannerType'] = $bannertype;
		$this->data['fbSocialShare'] = 'https://www.facebook.com/sharer/sharer.php?u='.$post['sd_url'];
		$this->data['twSocialShare'] = 'https://twitter.com/intent/tweet?text=Check My Vast&url='.$post['sd_url'].'&via=GetVASTnow';
		$twts = "";
		if($bannertype == "bannerType6" || $bannertype == "bannerType1"){
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
		}
		$this->data['tweets'] = $twts;
        return View::make('default.grid.advastbanner')->with('data', $this->data);
		
    }
	
	public function about() {       
        return View::make('default.pages.about')->with('data', $this->data);
    }
	
	public function terms() {       
        return View::make('default.pages.terms')->with('data', $this->data);
    }
	
	public function resetmsg()
    {
        return View::make('default.reset_msg')->with('data', $this->data);
    }

	public function home(){
        $this->data['channel_id'] = 24;
        return View::make('default.home.view')->with('data', $this->data);
    }
	
	public function adminAddVersionView()
    {
        $this->layout->content       = View::make('default.superadmin.version')->with('data', $this->data);
    }
    
    public function adminAddVersion()
    {
        $res = $this->version->update(1, array(
            'version_number' => Input::get('versionnum'),
            'build_number' => Input::get('buildnum'),
            'update_link' => Input::get('updatelink'),
        ));
        if ($res == 1) {
            Session::flash('success', 'Version updated successfully!');
            echo 1;
        } else {
            Session::flash('error', 'Version updating failed!');
            echo 0;
            
        }
        
        return Redirect::to('cms/superadmin/version/add');
        
    }
	
	public function download() 
	{       
        return View::make('default.pages.download')->with('data', $this->data);
    }

    public function imagineView()
    {
        return View::make('default.post.imagine')->with('data', $this->data);
    }

    public function whereisthelove()
    {
        return View::make('default.post.whereisthelove')->with('data', $this->data);
    }

    
    public function imagineBloggersEmbed()
    {
        return View::make('default.post.imagineblogger')->with('data', $this->data);
    }

    public function whereistheloveBloggersEmbed()
    {
        return View::make('default.post.whereisloveblogger')->with('data', $this->data);
    }
	
	public function parsonsBloggersEmbed()
    {
        return View::make('default.post.parsonsblogger')->with('data', $this->data);
    }
	
	public function adamfranzinoBloggersEmbed()
    {
        return View::make('default.post.adamfranzinoblogger')->with('data', $this->data);
    }
	
	public function genesisBloggersEmbed()
    {
        return View::make('default.post.genesisblogger')->with('data', $this->data);
    }

	public function viewSlideShow()
    {
        $this->data['slides'] = $this->slideshow->getSlides();       
        return View::make('default.superadmin.slideshow_view')->with('data', $this->data);
    }

    public function viewSlideShowEdit($id){     
        $credentials =  $key = $secret = $token = "";
        $cacheAdapter = new DoctrineCacheAdapter(new FilesystemCache('/tmp/cache'));
        $sts          = StsClient::factory(array(
            'credentials.cache' => $cacheAdapter
        ));
        $response     = $sts->assumeRole(array(
            'RoleArn' => 'arn:aws:iam::603415607477:role/production-servers',
            'RoleSessionName' => 'vastprod-sts-upload',
            'Name' => 'test-sts',
            'DurationSeconds' => 900
            
        ));        
        $credentials = $sts->createCredentials($response);
        $key    = $this->accessProtected($credentials, 'key');
        $secret = $this->accessProtected($credentials, 'secret');
        $token  = $this->accessProtected($credentials, 'token');         
        $key = $key == "" ? Config::get('aws::key'): $key;
        $uploadCredentials                       = S3::getUploadPermission('slideshow/images/', $key, $secret, $token);
        $this->data['policy']                    = $uploadCredentials['policy'];
        $this->data['signature']                 = $uploadCredentials['signature'];
        $this->data['key']                       = $key;
        $this->data['token']                     = $token; 
        /*
        $this->data['policy']                    = '';
        $this->data['signature']                  = '';
        $this->data['key']                       = '';
        $this->data['token']                      = '';*/
        $this->data['slide']                     = $this->slideshow->getSlideById($id); 
        return View::make('default.superadmin.slideshow_add')->with('data', $this->data);
    }
	
	public function uploadFile(){     
        $credentials =  $key = $secret = $token = "";
        $cacheAdapter = new DoctrineCacheAdapter(new FilesystemCache('/tmp/cache'));
        $sts          = StsClient::factory(array(
            'credentials.cache' => $cacheAdapter
        ));
        $response     = $sts->assumeRole(array(
            'RoleArn' => 'arn:aws:iam::603415607477:role/production-servers',
            'RoleSessionName' => 'vastprod-sts-upload',
            'Name' => 'test-sts',
            'DurationSeconds' => 900
            
        ));        
        $credentials = $sts->createCredentials($response);
        $key    = $this->accessProtected($credentials, 'key');
        $secret = $this->accessProtected($credentials, 'secret');
        $token  = $this->accessProtected($credentials, 'token');         
        $key = $key == "" ? Config::get('aws::key'): $key;
        $uploadCredentials                       = S3::getUploadPermission('postimages/sd/', $key, $secret, $token);
        $this->data['policy']                    = $uploadCredentials['policy'];
        $this->data['signature']                 = $uploadCredentials['signature'];
        $this->data['key']                       = $key;
        $this->data['token']                     = $token; 
       /*
        $this->data['policy']                    = '';
        $this->data['signature']                  = '';
        $this->data['key']                       = '';
        $this->data['token']                      = '';*/
        
        return View::make('default.pages.uploadfile')->with('data', $this->data);
    }
	
	public function updatePostDetailsToAnother($id,$image){
		echo $image;
		$data = S3::photoPostMigration('http://vastdemo.s3.amazonaws.com/postimages/sd/'.$image);
		echo $data;
	}
	
	public function resizeImage(){
        $imgUrl  = "http://vastwww.s3.amazonaws.com/postimages/retina/thebridgeco1474551071.JPG";
		$res = S3::resizeUploadedImage($imgUrl);
		echo $res;
		exit;
	}

    public function channelBloggersEmbed()
    {
        //echo Auth::user()->channel->cover ;
        $this->data['channel'] = Auth::user()->channel->channel_id;
        $this->data['banner']  = Auth::user()->channel->cover == "" ? "" : S3::getS3Url(S3::getProfileSdThumbPath(Auth::user()->channel->cover));
        return View::make('default.post.channelblogger')->with('data', $this->data);
    }
	
	public function websiteView($name)
	{
		$this->data['channel'] = $this->channel->getChannelByName($name);
		if(count($this->data['channel']) > 0) {
			return View::make('default.post.website')->with('data', $this->data);
		} else {
			return View::make('default.404');
		}
	}

    public function channelExploreEdit()
    {
        $this->data['channel_id'] = Auth::user()->channel->channel_id;
        $networkDetails    = $this->manageexplore->getRowsByChannel(Auth::user()->channel->channel_id);		
        $this->data['res'] = $networkDetails;        
		$featuredArrays         = explode(',', $networkDetails['featured_list']);
		$nonFeaturedArrays      = explode(',', $networkDetails['non_featured_list']);
		$featured               = array();
		$featuredChapterDetails = explode(',', $networkDetails['featured_chapter_details']);
		$defaultFeatured        = $this->post->getLastFivePostBasedOnChannel(Auth::user()->channel->channel_id);
		foreach ($featuredArrays as $featuredArray) {
			if ($featuredArray == '0') {
				foreach ($defaultFeatured as $lastPostItems) {
					if (isset($lastPostItems->post_id) != "") {
						if (!in_array($lastPostItems->post_id, $featuredArrays)) {
							$key = array_search('0', $featuredArrays);
							array_push($featured, array(
								'image' => $lastPostItems->sd_url == "" ? URL::asset('images/no_image_for_chapter_list.png') : S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url)),
								'image_name' => $lastPostItems->sd_url,
								'poi-x' => $lastPostItems->poix - (1 -  $lastPostItems->poix),
								'poi-y' => $lastPostItems->poiy - (1 -  $lastPostItems->poiy),
								'sd-w'  => $lastPostItems->sd_url == "" ? 761 : $lastPostItems->sd_width,
								'sd-h'  => $lastPostItems->sd_url == "" ? 425 : $lastPostItems->sd_height
							));
							$featuredArrays[$key] = $lastPostItems->post_id;
						} else
							continue;
					} else
						array_push($featured, array('image' => '',
							'image_name' => '',
							'poi-x' => 0,
							'poi-y' => 0,
							'sd-w'  => 0,
							'sd-h'  => 0
						));
					break;
				}
			} else {
				$post_details = $this->post->getPostById($featuredArray);
				array_push($featured, array(
					'image' => $post_details->sd_url == "" ? URL::asset('images/no_image_for_chapter_list.png') : S3::getS3Url(S3::getPostSdPath($post_details->sd_url)),
					'image_name' => $post_details->sd_url,
					'poi-x' =>  $post_details->poix - (1 -  $post_details->poix),
					'poi-y' =>  $post_details->poix - (1 -  $post_details->poix),
					'sd-w'  => $post_details->sd_url == "" ? 761 : $post_details->sd_width,
					'sd-h'  => $post_details->sd_url == "" ? 425 : $post_details->sd_height
				));
			}
		}
		$arrCount = count($featured);
		while ($arrCount < 5) {
			array_push($featured, array('image' => '',
				'image_name' => '',
				'poi-x' => 0,
				'poi-y' => 0,
				'sd-w'  => 0,
				'sd-h'  => 0
			));
			$arrCount++;
		}	
		foreach($featured as $latest){
			if($latest['image'] !=""){
				$this->data['latest']  = array(
					'image' => $latest['image_name'] == "" ? URL::asset('images/no_image_for_chapter_list.png') : $latest['image'],
					'poi-x' => $latest['poi-x'],
					'poi-y' => $latest['poi-y'],
					'sd-w'  => $latest['sd-w'],
					'sd-h'  => $latest['sd-h']
				);
				break;
			}
		}		
		$this->data['featured']               = $featured;
		$this->data['display_type']           = $networkDetails['display_type'];  
		$allChapterArray = $this->chapter->getAllChapterIdsIncludingInactiveByChannel(Auth::user()->channel->channel_id);	
		$nonFeaturedArrays = array_filter($nonFeaturedArrays);
		$chapterLists = array_merge($nonFeaturedArrays,array_diff($allChapterArray,$nonFeaturedArrays));
		$chapterDetails = $this->chapter->getChapterDetailsFromList($chapterLists);
		$chapters = array();		
		foreach($chapterDetails as $chapterDetail){
			$postDetail = $chapterDetail['cover'] == 0 ? $this->post->getLastestPostByChapter($chapterDetail['chapter_id']) : $this->post->getPostById($chapterDetail['cover']);
			$likes = $this->like->getLikesByPostId($postDetail['post_id']);
			$isliked = $this->like->getUserLikedPostById(Auth::user()->channel->channel_id,$postDetail['post_id']);
			if(count($this->post->getPostByChapterId($chapterDetail['chapter_id']))> 0){
				array_push($chapters,array('chapter_id' => $chapterDetail['chapter_id'],
					'chapter_name' 	=> $chapterDetail['chapter_name'],
					'image'  		=>  $postDetail['sd_url'],
					'poi_x'  		=>  $postDetail['type'] == "event" ? 0.5 : $postDetail['poix']-(1-$postDetail['poix']),
					'poi_y'  		=>  $postDetail['type'] == "event" ? 0.5 : $postDetail['poiy']-(1-$postDetail['poiy']),
					'sd_w'   		=>  $postDetail['sd_width'],
					'sd_h'   		=>  $postDetail['sd_height'],
					'hd_w'  		=>  $postDetail['hdthumb_width'],
					'hd_h'   		=>  $postDetail['hdthumb_height'],
					'title'  		=>  $postDetail['title'],
					'date'   		=>  $postDetail['date'],
					'likes'  		=>  $likes,
					'isliked' 		=>  $isliked,
					'status'        =>  $chapterDetail['inactive']
					
				));
			}
		}
		//var_dump($chapters);
		//exit;
		$this->data['chapters']      = $chapters;
        $this->layout->content = View::make('default.post.chapterexploreedit')->with('data', $this->data);
    }

    public function channelManageSocialIcons()
    {
		//$this->layout->content = View::make('default.post.chapterpostedit')->with('data', $this->data); 
        return View::make('default.post.managesocialicons')->with('data', $this->data);
    }
	
	public function channelEditCarousel(){
		$networkDetails    = $this->manageexplore->getRowsByChannel(Auth::user()->channel->channel_id);
		$featuredArrays         = explode(',', $networkDetails['featured_list']);
		$defaultFeatured        = $this->post->getLastFivePostBasedOnChannel(Auth::user()->channel->channel_id);
		$featured = array();
		foreach ($featuredArrays as $featuredArray) {
			if ($featuredArray == '0') {
				foreach ($defaultFeatured as $lastPostItems) {
					if (isset($lastPostItems->post_id) != "") {
						if (!in_array($lastPostItems->post_id, $featuredArrays)) {
							$key = array_search('0', $featuredArrays);
							array_push($featured, array(
								'id'   	=> $lastPostItems->post_id,	
								'image' => $lastPostItems->sd_url == "" ? URL::asset('images/no_image_for_chapter_list.png') : S3::getS3Url(S3::getPostSdPath($lastPostItems->sd_url)),
								'poi-x' => $lastPostItems->type == "event"  ? 0.5 : $lastPostItems->poix - (1 - $lastPostItems->poix),
								'poi-y' => $lastPostItems->type == "event" ? 0.5 : $lastPostItems->poiy - (1 - $lastPostItems->poiy ),
								'sd-w'  => $lastPostItems->sd_url == "" ? 761 : $lastPostItems->sd_width,
								'sd-h'  => $lastPostItems->sd_url == "" ? 425 : $lastPostItems->sd_height
							));
							$featuredArrays[$key] = $lastPostItems->post_id;
						} else
							continue;
					} else
						array_push($featured, array(
							'id'   	=> 0,	
							'image' => '',
							'poi-x' => 0,
							'poi-y' => 0,
							'sd-w'  => 0,
							'sd-h'  => 0
						));
					break;
				}
			} else {
				$post_details = $this->post->getPostById($featuredArray);
				array_push($featured, array(
					'id'   	=> $post_details->post_id,
					'image' => $post_details->sd_url == "" ? URL::asset('images/no_image_for_chapter_list.png') : S3::getS3Url(S3::getPostSdPath($post_details->sd_url)),
					'poi-x' => $post_details->type == "event" && $post_details->sd_url != "" ? 0.5 : $post_details->poix - (1 -  $post_details->poix),
					'poi-y' => $post_details->type == "event" && $post_details->sd_url != "" ? 0.5 : $post_details->poiy - (1 -  $post_details->poiy),
					'sd-w'  => $post_details->sd_url == "" ? 761 : $post_details->sd_width,
					'sd-h'  => $post_details->sd_url == "" ? 425 : $post_details->sd_height
				));
			}	
		}
		
		$arrCount = count($featured);
		while ($arrCount < 5) {
			array_push($featured, array(
				'id'   	=> 0,	
				'image' => '',
				'image_name' => '',
				'poi-x' => 0,
				'poi-y' => 0,
				'sd-w'  => 0,
				'sd-h'  => 0
			));
			$arrCount++;
		}	

		$allChapterArray = $this->chapter->getAllChapterIdsByChannel(Auth::user()->channel->channel_id);	
		$chapterDetails = $this->chapter->getChapterDetailsFromList($allChapterArray);
		$chapters = array();
		foreach($chapterDetails as $chapterDetail){
			$postDetail = $chapterDetail['cover'] == 0 ? $this->post->getLastestPostByChapter($chapterDetail['chapter_id']) : $this->post->getPostById($chapterDetail['cover']);
			array_push($chapters,array(
				'chapter_id' => $chapterDetail['chapter_id'],
				'chapter_name' => $chapterDetail['chapter_name'],
				'image'  => $postDetail['sd_url'],
				'poi_x'  => $postDetail['sd_url'] == "" && $postDetail['type'] == "event" ? 0.5 : $postDetail['poix']- (1 - $postDetail['poix']),
				'poi_y'  => $postDetail['sd_url'] == "" && $postDetail['type'] == "event" ? 0.5 : $postDetail['poiy']- (1 - $postDetail['poiy']),
				'hd_w'   => $postDetail['hdthumb_width'],
				'hd_h'   => $postDetail['hdthumb_height']				
			));
		}

		$this->data['chapters']      = $chapters;		
		$this->data['featured'] = $featured;
		$this->data['network_id'] = $networkDetails['id'];
		return View::make('default.post.editcarousel')->with('data', $this->data); 
	}
	
	public function channelEditChapter($id){
		$this->data['chapter_id'] = $id;
		$post = $this->post->getLastPostByChapter($id);
		$this->data['last_post'] = $post['post_id'];
		$this->data['chapter'] = call_user_func_array('array_merge', $this->chapter->getChapterDetailsById($id));
		$this->data['posts'] = $this->post->getPostByChapterIdBasedOnPostOrder($id,$this->data['chapter']['post_order']);
		return View::make('default.post.chapteredit')->with('data', $this->data); 
	}
	
	public function channelEditChapterPost($id){
		$this->data['post_id'] = $id;
		$this->data['post'] = $this->post->getPostById($id);
		return View::make('default.post.chapterpostedit')->with('data', $this->data); 
	}
	
	public function channelEditCarouselSave(){
		$this->manageexplore->update(array('id'=>Input::get('network'),
			'featured_list' => implode(',',Input::get('featured_items')),
			'type' => 1
		));
		 return Redirect::to('cms/channel/explore/edit');
	}
	
	public function websiteBloggerView($name){
		$this->data['channel'] = $this->channel->getChannelByName($name);
		if(count($this->data['channel']) > 0) {
			return View::make('default.post.websiteblogger')->with('data', $this->data);
		} else {
			return View::make('default.404');
		}
	}
}