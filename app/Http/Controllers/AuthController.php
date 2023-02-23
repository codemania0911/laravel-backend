<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\SystemComponent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMail;
use Illuminate\Support\Facades\Artisan;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('jwt.auth:api', ['except' => ['login', 'twoFactorLogin']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['username', 'password']);
        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['type' => 'error', 'message' => 'Incorrect username or password.'], 401);
        }

        if (User::where('username', request('username'))->first()->deleted_at) {
            return response()->json(['type' => 'error', 'message' => 'That user is deleted.'], 401);
        }

        if (!User::where('username', request('username'))->first()->active) {
            return response()->json(['type' => 'warning', 'message' => 'That user is inactive.'], 401);
        }

        $nowTime = date("Y-m-d H:i:s");
        
        $twoFactorExpiredAt = User::where([['username', request('username')], 
                                            ['password', Hash::make(request('password'))]])
                                            ->first()
                                            ->two_factor_expired_at;

        if(request('unlocked') && $twoFactorExpiredAt && $twoFactorExpiredAt > $nowTime) {
            return $this->respondWithToken($token);
        } else {
            $two_factor_code = rand(1000000, 9999999);
            
            $data = array(
                'subject' => 'DONJON-SMIT Portal Authentication Code',
                'message' => 'Your two factor authentication code is ' . $two_factor_code,
            );

            $user = User::where([['username', request('username')], 
                            ['password', Hash::make(request('password'))]])
                        ->first();
            
            if($user->email_sent_time) {
                $emailSentTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
                if($user->email_sent_time > $emailSentTime) {
                    return response()->json(['type' => 'warning', 'message' => 'You already have DONJON-SMIT Portal Authentication Code in your email.', 'verified' => false]);
                }
            }

            // Mail::to($user->email)->send(new SendMail($data));
            require app_path('/Helpers/google-api-php/vendor/autoload.php');
            // Get the API client and construct the service object.
            $client = $this->getClient();
            $service = new \Google_Service_Gmail($client);

            $userId = "me";
            $sender = "no-reply@donjon-smit.com";
            $to = $user->email;
            $subject = $data['subject'];
            $messageText = $data['message'];

            $message = $this->createMessage($sender, $to, $subject, $messageText);		
            $response = $this->sendMessage($service, $userId, $message);

            // update the email sent time
            User::where([['username', request('username')], 
                        ['password', Hash::make(request('password'))]])
                ->update(['two_factor_code' => $two_factor_code,
                          'email_sent_time' => date('Y-m-d H:i:s')]);

            return response()->json(['type' => 'warning', 'message' => 'That user needs to verify the 2 factor.', 'verified' => false]);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json([
            'user' => auth()->user(),
            'role' => auth()->user()->roles()->first(),
            // 'permissions' => $this->getPermissions()
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user(),
            'role' => auth()->user()->roles()->first(),
            // 'permissions' => $this->getPermissions(),
            'message' => 'Successfully logged in',
            'verified' => true,
        ]);
    }

    // Not Using Now!!!!!
    protected function getPermissions()
    {

        $roleID = auth()->user()->role_id;
        $components = SystemComponent::select('id', 'name', 'code')->get();
        $permissions_data = [];
        $num_permissions = 0;
        foreach ($components as $component) {
            $permissions = Permission::where([['role_id', $roleID], ['system_component_id', $component->id]])->get();
            if (count($permissions)) {
                $merged_permissions = [];
                foreach ($permissions as $permission) {
                    $merged_permissions = array_unique(array_merge($merged_permissions, array_filter(explode(',', $permission->permissions))), SORT_REGULAR);
                }
                $permissions_data[$component->code] = $merged_permissions;
                if (count($merged_permissions)) {
                    $num_permissions++;
                }
            } else {
                $permissions_data[$component->code] = [];
            }
        }
        $has_permissions = $num_permissions ? true : false;

        return [
            'has' => $has_permissions,
            'data' => $permissions_data
        ];
    }

    public function twoFactorLogin()
    {
        $userInfo = User::where([['username', request('username')],
                                ['password', Hash::make(request('password'))],
                                ['two_factor_code', request('two_factor_code')]])
                            ->first();
        if($userInfo) {
            $credentials = request(['username', 'password']);
            $token = auth()->attempt($credentials);
            $nowTime = date("Y-m-d H:i:s");
            if($userInfo->two_factor_expired_at < $nowTime) {
                User::where([['username', request('username')],
                             ['password', Hash::make(request('password'))]
                            ])
                        ->update([
                            'two_factor_expired_at' => date('Y-m-d', strtotime('+7 days'))
                        ]);
            }
            return $this->respondWithToken($token);
        } else {
            return response()->json(['message' => 'Incorrect 2 Factor Code.', 'verified' => false]);
        }
    }

    function getClient($authCode = "")
	{
		$client = new \Google_Client();
		$client->setApplicationName('Gmail API PHP Quickstart');
		$client->setScopes(\Google_Service_Gmail::GMAIL_READONLY);
		$client->setAuthConfig('gmail-api-credential.json');
		$client->setAccessType('offline');
		$client->setScopes(
			[
				'https://www.googleapis.com/auth/gmail.send',
				'https://www.googleapis.com/auth/gmail.readonly',
				'https://www.googleapis.com/auth/gmail.compose',
				'https://www.googleapis.com/auth/gmail.insert',
				'https://www.googleapis.com/auth/gmail.modify',
				'https://www.googleapis.com/auth/gmail.metadata',
			]
		);
		$client->setPrompt('select_account consent');

		// Load previously authorized token from a file, if it exists.
		// The file token.json stores the user's access and refresh tokens, and is
		// created automatically when the authorization flow completes for the first
		// time.
		$tokenPath = 'token.json';
		if (file_exists($tokenPath)) {
			$accessToken = json_decode(file_get_contents($tokenPath), true);
			$client->setAccessToken($accessToken);
		}
		// If there is no previous token or it's expired.
		if ($client->isAccessTokenExpired()) {
			// Refresh the token if possible, else fetch a new one.
			if ($client->getRefreshToken()) {
				$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
			} else {
				if ($authCode == "") {
					// Request authorization from the user.
					$authUrl = $client->createAuthUrl();
					header("location:".$authUrl);exit;
				}

				// Exchange authorization code for an access token.
				$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
				$client->setAccessToken($accessToken);

				// Check to see if there was an error.
				if (array_key_exists('error', $accessToken)) {
					throw new Exception(join(', ', $accessToken));
				}
			}
			// Save the token to a file.
			if (!file_exists(dirname($tokenPath))) {
				mkdir(dirname($tokenPath), 0700, true);
			}
			file_put_contents($tokenPath, json_encode($client->getAccessToken()));
		}
		return $client;
    }
    
    function createMessage($sender, $to, $subject, $messageText) {
		$message = new \Google_Service_Gmail_Message();

		$rawMessageString = "From: <{$sender}>\r\n";
		$rawMessageString .= "To: <{$to}>\r\n";
		$rawMessageString .= 'Subject: =?utf-8?B?' . base64_encode($subject) . "?=\r\n";
		$rawMessageString .= "MIME-Version: 1.0\r\n";
		$rawMessageString .= "Content-Type: text/html; charset=utf-8\r\n";
		$rawMessageString .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
		$rawMessageString .= "{$messageText}\r\n";
		$rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));
		$message->setRaw($rawMessage);
		return $message;
    }
    
    function sendMessage($service,$userId, $message) {
		$response = [];
		try {
			$message = $service->users_messages->send($userId, $message);
			$response['status'] = true;
			$response['msg'] = 'Message with ID: ' . $message->getId() . ' sent.';			
			// return $message;
		} catch (Exception $e) {
			$response['status'] = false;
			$response['msg'] = 'An error occurred: ' . $e->getMessage();			
		}
		return $response;
	}
}
