<?php
include 'PLOC-Config.php';

/**
 * @package    PLOC
 * @author     Matthieu Ducrocq <lapaire@PLOC.co>
 * @copyright  2013-2020 PLOC
 * @license    MIT License
 * @version    Release: 1.2
 * @link       https://app.PLOC.pro
 */

class PLOC
{
	const API_END_POINT = 'https://app.PLOC.pro/api/';
	const METHOD_POST = 'POST';
	const METHOD_GET = 'GET';
	const METHOD_PUT = 'PUT';
	const METHOD_DELETE = 'DELETE';
	const STATUS_CODE_SUCCESS = 200;
	const DEFAULT_PAGE_SIZE = 10;

	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

	const REACTION_NONE = 'none'; 
	const REACTION_LIKE = 'like'; 
	const REACTION_DISLIKE = 'dislike'; 
	const REACTION_LOVE = 'love'; 
	const REACTION_SURPRISED = 'surprised'; 
	const REACTION_OK = 'ok'; 
	const REACTION_EXCLAMATION = 'exclamation'; 
	const REACTION_INTERROGATION = 'interrogation'; 

	const LINK_STATUS_ACTIVE = 'active';
	const LINK_STATUS_CANCELLED = 'cancelled';
	const LINK_STATUS_SERVER_ERROR = 'error';
	const LINK_STATUS_INVALID_PARAMS = 'invalid';
 
	/**
	 * @var string
	 */
	var $PUBLIC_KEY;

	/**
	 * @var string
	 */
	var $PRIVATE_KEY;

	/**
	 * @var string
	 */
	var $HMAC_KEY;

	/**
	 * @var bool
	 */
	var $VERBOSE = FALSE;

	function __construct() {
		if (defined('PLOC_PUBLIC_KEY')) {
			$this->PUBLIC_KEY = PLOC_PUBLIC_KEY;
		}
		if (defined('PLOC_PRIVATE_KEY')) {
			$this->PRIVATE_KEY = PLOC_PRIVATE_KEY;
		}
		if (defined('PLOC_HMAC_KEY')) {
			$this->HMAC_KEY = PLOC_HMAC_KEY;
		}
		if($this->isNullOrEmpty($this->PUBLIC_KEY)) {
			throw new Exception('$PUBLIC_KEY must be set', 1);
		}
		if($this->isNullOrEmpty($this->PRIVATE_KEY)) {
			throw new Exception('$PRIVATE_KEY must be set', 2);
		}
		if($this->isNullOrEmpty($this->HMAC_KEY)) {
			throw new Exception('HMAC_KEY must be set', 3);
		}
	}
	
	/**
	 * Return a secure link to bind your reference to a PLOC account.
	 *
	 * @param string $appToken Your unique customer reference
	 * 
	 * @return string
	 */ 
	function getFollowLink($appToken) {
		$date = new DateTime();
		$date->setTimezone(new DateTimeZone("UTC"));
		$timestamp = $date->getTimestamp();
		$signature = $this->PUBLIC_KEY."".$appToken."".$timestamp;
		$signature = $this->getSignature($signature);
		$plocData = $this->getQueryValue("ploc_data");
		return "https://app.PLOC.pro/Follow?App=".$this->PUBLIC_KEY."&appToken=".rawurlencode($appToken)."&timestamp=".$timestamp."&signature=".rawurlencode($signature)."&data=".rawurlencode($plocData);
	}

	/**
	 * Return the validity of a link using params & signature
	 *
	 * @param string $app
	 * @param string $appToken
	 * @param string $plocToken
	 * @param string $timestamp
	 * @param string $remoteSignature
	 * 
	 * @return bool
	 */ 
	function isValidFollowLink($app, $appToken, $plocToken, $timestamp, $remoteSignature) {
		if($app != $this->PUBLIC_KEY) {
			return false;
		}
		if(!$this->isValidTimestamp($timestamp)) {
			return false;
		}
		$signature = $app."".$appToken."".$plocToken."".$timestamp;
		$signature = $this->getSignature($signature);
		return $signature == $remoteSignature;
	}

	/**
	 * Return the appToken (stored in url)
	 *
	 * @return bool
	 */ 
	function getCurrentAppToken() {
		return $this->getQueryValue("appToken");
	}

	/**
	 * Return plocToken (stored in url)
	 *
	 * @return bool
	 */ 
	function getCurrentPlocToken() {
		return $this->getQueryValue("plocToken");
	}

	/**
	 * Return if the incoming request contains user credentials
	 *
	 * @return bool
	 */ 
	function containsUserCredentials() {
		return $this->getQueryValue("ploc_data") != '';
	}

	/**
	 * Return the validity of a link using current URI
	 *
	 * @return bool
	 */ 
	function isValidFollowLinkUsingCurrentURI() {
		$app = $this->getQueryValue("app");
		$appToken = $this->getQueryValue("appToken");
		$plocToken = $this->getQueryValue("plocToken");
		$timestamp = $this->getQueryValue("timestamp");
		$signature = $this->getQueryValue("signature");
		return $this->isValidFollowLink($app, $appToken, $plocToken, $timestamp, $signature);
	}

	/**
	 * Redirect user to PLOC app
	 *
	 * @return 
	 */ 
	function redirectToPloc() {
		if($this->getQueryValue("auto") != '1') {
			return '';
		}
		echo("<script language=javascript>\n");
		echo("document.location = 'ploc://?action=inbox&status=success';\n");
		echo("</script>\n");
	}

	/**
	 * Return current link status
	 *
	 * @param string $plocToken
	 * 
	 * @return string (LINK_STATUS_ACTIVE, LINK_STATUS_CANCELLED, LINK_STATUS_SERVER_ERROR, LINK_STATUS_INVALID_PARAMS)
	 */ 
	function getLinkStatus($plocToken) {
		if($this->isNullOrEmpty($plocToken)) {
			return self::LINK_STATUS_INVALID_PARAMS;
		}
		$apiCall = $this->makeApiCall('messenger/relation/'.rawurlencode($plocToken).'/status', self::METHOD_GET);
		if(is_null($apiCall)) {
			return self::LINK_STATUS_SERVER_ERROR;
		}
		if($apiCall->status != self::STATUS_SUCCESS) {
			return self::LINK_STATUS_CANCELLED;
		}
		return self::LINK_STATUS_ACTIVE;
	}

	/**
	 * Return the associated token to your reference.
	 *
	 * @param string $appToken Your unique customer reference
	 * 
	 * @return string
	 */ 
	function getPlocToken($appToken) {
		if($this->isNullOrEmpty($appToken)) {
			return NULL;
		}
		$apiCall = $this->makeApiCall('messenger/relation/'.rawurlencode($appToken), self::METHOD_GET);
		if(is_null($apiCall)) {
			return NULL;
		}
		if($apiCall->status != self::STATUS_SUCCESS) {
			return NULL;
		}
		return $apiCall->user;
	}

	/**
	 * Unlink account & delete all messages
	 *
	 * @param string $plocToken
	 * 
	 * @return bool
	 */ 
	function unlinkAccount($plocToken) {
		if($this->isNullOrEmpty($plocToken)) {
			return false;
		}
		$apiCall = $this->makeApiCall('messenger/relation/'.rawurlencode($plocToken), self::METHOD_DELETE);
		if(is_null($apiCall)) {
			return false;
		}
		return $apiCall->status == self::STATUS_SUCCESS;
	}

	/**
	 * Send a text message
	 *
	 * @param string $plocToken
	 * @param string $text
	 * 
	 * @return bool
	 */ 
	function sendTextMessage($plocToken, $text) {
		if(($this->isNullOrEmpty($plocToken))
			| ($this->isNullOrEmpty($text))) {
			return false;
		}
		$params = array(
			"text" => $text,
			"user" => $plocToken
		);
		$payload = json_encode($params);
		$apiCall = $this->makeApiCall('messenger/text', self::METHOD_POST, $payload);
		if(is_null($apiCall)) {
			return false;
		}
		return $apiCall->status == self::STATUS_SUCCESS;
	}

	/**
	 * Send an image message
	 *
	 * @param string $plocToken
	 * @param string $imageUrl
	 * @param string $callToActionUrl (optional) Page to open when the user taps on the image
	 * @param string $imageWidth (optional)
	 * @param string $imageHeight (optional)
	 * 
	 * @return bool
	 */ 
	function sendImageMessage($plocToken, $imageUrl, $callToActionUrl = '', $imageWidth = 0, $imageHeight = 0) {
		if(($this->isNullOrEmpty($plocToken))
			| ($this->isNullOrEmpty($imageUrl))) {
			return false;
		}
		if(($imageWidth < 0)
			| (imageHeight < 0)) {
			return false;
		}
		$params = array(
			"imageUrl" => $imageUrl,
			"imageWidth" => $imageWidth,
			"imageHeight" => $imageHeight,
			"user" => $plocToken
		);
		$payload = json_encode($params);
		$apiCall = $this->makeApiCall('messenger/image', self::METHOD_POST, $payload);
		if(is_null($apiCall)) {
			return false;
		}
		return $apiCall->status == self::STATUS_SUCCESS;
	}

	/**
	 * Send an order message
	 *
	 * @param string $plocToken
	 * @param string $text
	 * @param string $purchaseDate
	 * @param array $vendor
	 * @param array $lines
	 * 
	 * @return bool
	 */ 
	function sendOrderMessage($plocToken, $text, $purchaseDate, $vendor, $products) {
		if(($this->isNullOrEmpty($plocToken))
			| ($this->isNullOrEmpty($text))
			| ($vendor == null)
			| ($products == null)) {
			return false;
		}
		$params = array(
			"user" => $plocToken,
			"text" => $text,
			"purchaseDate" => $purchaseDate,
			"vendor" => $vendor,
			"products" => $products
		);
		$payload = json_encode($params);
		$apiCall = $this->makeApiCall('messenger/order', self::METHOD_POST, $payload);
		if(is_null($apiCall)) {
			return false;
		}
		return $apiCall->status == self::STATUS_SUCCESS;
	}

	/**
	 * Mark the message as read
	 *
	 * @param int $messageId
	 * 
	 * @return bool
	 */ 
	function markAsRead($messageId) {
		if($messageId == 0) {
			return false;
		}
		$params = array();
		$payload = json_encode($params);
		$apiCall = $this->makeApiCall('messenger/inbox/'.$messageId.'/read', self::METHOD_POST, $payload);
		if(is_null($apiCall)) {
			return false;
		}
		return $apiCall->status == self::STATUS_SUCCESS;
	}

	/**
	 * Add reaction to a message
	 *
	 * @param int $messageId
	 * @param string $reaction
	 * 
	 * @return bool
	 */ 
	function addReaction($messageId, $reaction) {
		if(($messageId == 0)
			| ($this->isNullOrEmpty($reaction))) {
			return false;
		}
		$params = array();
		$payload = json_encode($params);
		$apiCall = $this->makeApiCall('messenger/inbox/'.$messageId.'/'.$reaction, self::METHOD_POST, $payload);
		if(is_null($apiCall)) {
			return false;
		}
		return $apiCall->status == self::STATUS_SUCCESS;
	}

	/**
	 * Return all users inboxes
	 *
	 * @param int $page
	 * @param int $pageSize (optional) default @DEFAULT_PAGE_SIZE
	 * 
	 * @return inbox
	 */ 
	function getInbox($page = 0, $pageSize = self::DEFAULT_PAGE_SIZE)  {
		if($page < 0)  {
			return NULL;
		}
		if($pageSize < 1)  {
			return NULL;
		}
		$apiCall = $this->makeApiCall('messenger/inbox?page='.$page.'&pageSize='.$pageSize, self::METHOD_GET);
		if(is_null($apiCall)) {
			return false;
		}
		return $apiCall;
	}

	/**
	 * Return the user's inbox
	 *
	 * @param string $plocToken
	 * @param int $page
	 * @param int $pageSize (optional) default @DEFAULT_PAGE_SIZE
	 * 
	 * @return inbox
	 */ 
	function getUserInbox($plocToken, $page = 0, $pageSize = self::DEFAULT_PAGE_SIZE) {
		if($this->isNullOrEmpty($plocToken)) {
			return NULL;
		}
		if($page < 0)  {
			return NULL;
		}
		if($pageSize < 1)  {
			return NULL;
		}
		$apiCall = $this->makeApiCall('messenger/'.$plocToken.'/inbox?page='.$page.'&pageSize='.$pageSize, self::METHOD_GET);
		if(is_null($apiCall)) {
			return NULL;
		}
		return $apiCall;
	}

	/**
	 * Return a keyed hash value using the sha256 method and your HMAC key
	 *
	 * @param string $value
	 * 
	 * @return string
	 */ 
	function getSignature($value) {
		return base64_encode(hash_hmac('sha256', $value, $this->HMAC_KEY, true));
	}

	/**
	 * Return whether the specified string is null or an empty string
	 *
	 * @param string $value
	 * 
	 * @return string
	 */ 
	function isNullOrEmpty($value) {
		if((is_null($value))
			|| (strlen($value) == 0)) {
			return true;
		}
		return false;
	}

	/**
	 * Return the query string value or empty if is undefined
	 *
	 * @param string $value
	 * 
	 * @return string
	 */
	function getQueryValue($name) {
		if(isset($_GET[$name])) {
			return $_GET[$name];
		}
		return '';
	}

	/**
	 * Return if the specified timestamp is valid.
	 *
	 * @param long $remoteTimestamp
	 * 
	 * @return bool
	 */ 
	function isValidTimestamp($remoteTimestamp) {
		$date = new DateTime();
		$date->setTimezone(new DateTimeZone("UTC"));
		$timestamp = $date->getTimestamp();
		$difference = abs($timestamp - $remoteTimestamp) / 60;
		return $difference <= 2;
	}

	/**
	 * Return
	 *
	 * @param string $path
	 * @param string $method HTTP method @METHOD_GET, @METHOD_POST, @METHOD_PUT, @METHOD_DELETE
	 * 
	 * @return json
	 */ 
	function makeApiCall($path, $method, $payload = '') {
		$url = self::API_END_POINT."".$path;
		$curl = curl_init($url);
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);		
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$authorization = "Authorization: Bearer ".$this->PRIVATE_KEY;
		$headers = array(
			'content-type: application/json',
			$authorization,
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_VERBOSE, true);

		if($method != self::METHOD_GET) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		}
		
		$response = curl_exec($curl);
		$data = json_decode($response);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if($this->VERBOSE) {
			echo "URL : " .$url."<br><br>";  
			echo "payload : <pre>" .$payload."</pre><br><br>";  
			echo "responseCode : " .$httpCode."<br>";
			echo "response : <pre>" .$response."</pre><br><br>"; 
		}

		if($httpCode == self::STATUS_CODE_SUCCESS) {
			return ($data);
		}
		return NULL;
	}
}
?>