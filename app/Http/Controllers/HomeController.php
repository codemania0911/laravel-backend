<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Frontend\SendMailRequest;

/**
 * Class HomeController.
 */
class HomeController extends Controller
{
	/**
	 * @return \Illuminate\View\View
	 */
	public function index()
	{
		require app_path('Helpers/google-api-php/vendor/autoload.php');
		// Get the API client and construct the service object.
		$client = $this->getClient();
		$service = new \Google_Service_Gmail($client);

		return view("frontend.index");
		

	}

	public function send() {
		require app_path('Helpers/google-api-php/vendor/autoload.php');
		// Get the API client and construct the service object.
		$client = $this->getClient();
		$service = new \Google_Service_Gmail($client);

		$userId = "me";
		$sender = "frommail@gmail.com";
		$to = 'slarkjm@outlook.com';
		$subject = 'test';
		$messageText = 'ok';

		$message = $this->createMessage($sender, $to, $subject, $messageText);		
		$response = $this->sendMessage($service,$userId, $message);

		
dd($response);

	}


	/**
	 * Returns an authorized API client.
	 * @return Google_Client the authorized client object
	 */
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


	public function callback()
	{ 
		require app_path('Helpers/google-api-php/vendor/autoload.php');

		$authCode = $_GET['code'];

		$client = new \Google_Client();
		$client->setApplicationName('Gmail API PHP Quickstart');
		$client->setScopes(\Google_Service_Gmail::GMAIL_READONLY);
		$client->setAuthConfig(env('GMAIL_API_KEY'));
		$client->setAccessType('offline');
		$client->setScopes(
			[
				'https://www.googleapis.com/auth/gmail.send',
				'https://www.googleapis.com/auth/gmail.readonly',
				'https://www.googleapis.com/auth/gmail.compose',
				'https://www.googleapis.com/auth/gmail.insert',
				'https://www.googleapis.com/auth/gmail.modify',
				'https://www.googleapis.com/auth/gmail.metadata'
			]
		);
		$client->setPrompt('select_account consent');

		// If there is no previous token or it's expired.
		if ($client->isAccessTokenExpired() && $authCode != "") {
			// Exchange authorization code for an access token.
			$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
			// Check to see if there was an error.
			if (array_key_exists('error', $accessToken)) {						
				echo "error";exit;
				// throw new Exception(join(', ', $accessToken));
			}
			$tokenPath = 'token.json';
			$client->setAccessToken($accessToken);

			// Save the token to a file.
			if (!file_exists(dirname($tokenPath))) {
				mkdir(dirname($tokenPath), 0700, true);
			}
			$token_arr = $client->getAccessToken();
			file_put_contents($tokenPath, json_encode($token_arr));
		}
		return $client;		 
	}


	/**
	* @param $sender string sender email address
	* @param $to string recipient email address
	* @param $subject string email subject
	* @param $messageText string email text
	* @return Google_Service_Gmail_Message
	*/
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

	/**
	* @param $service Google_Service_Gmail an authorized Gmail API service instance.
	* @param $userId string User's email address or "me"
	* @param $message Google_Service_Gmail_Message
	* @return null|Google_Service_Gmail_Message
	*/
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


	/**
	* @param $service Google_Service_Gmail an authorized Gmail API service instance.
	* @param $user string User's email address or "me"
	* @param $message Google_Service_Gmail_Message
	* @return Google_Service_Gmail_Draft
	*/
	function createDraft($service, $user, $message) {
		$draft = new Google_Service_Gmail_Draft();
		$draft->setMessage($message);

		try {
			$draft = $service->users_drafts->create($user, $draft);
			print 'Draft ID: ' . $draft->getId();
		} catch (Exception $e) {
			print 'An error occurred: ' . $e->getMessage();
		}

		return $draft;
	}

}
