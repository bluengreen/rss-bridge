<?php
/**
 * This file is part of RSS-Bridge, a PHP project capable of generating RSS and
 * Atom feeds for websites that don't have one.
 *
 * For the full license information, please view the UNLICENSE file distributed
 * with this source code.
 *
 * @package	Core
 * @license	http://unlicense.org/ UNLICENSE
 * @link	https://github.com/rss-bridge/rss-bridge
 */

/**
 * Gets contents from the Internet.
 *
 * @param string $url The URL.
 * @param array $header (optional) A list of cURL header.
 * For more information follow the links below.
 * * https://php.net/manual/en/function.curl-setopt.php
 * * https://curl.haxx.se/libcurl/c/CURLOPT_HTTPHEADER.html
 * @param array $opts (optional) A list of cURL options as associative array in
 * the format `$opts[$option] = $value;`, where `$option` is any `CURLOPT_XXX`
 * option and `$value` the corresponding value.
 *
 * For more information see http://php.net/manual/en/function.curl-setopt.php
 * @return string The contents.
 */
function getContents($url, $header = array(), $opts = array()){
	Debug::log('Reading contents from "' . $url . '"');

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

	if(is_array($header) && count($header) !== 0) {

		Debug::log('Setting headers: ' . json_encode($header));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

	}

	curl_setopt($ch, CURLOPT_USERAGENT, ini_get('user_agent'));
	curl_setopt($ch, CURLOPT_ENCODING, '');
	curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

	if(is_array($opts) && count($opts) !== 0) {

		Debug::log('Setting options: ' . json_encode($opts));

		foreach($opts as $key => $value) {
			curl_setopt($ch, $key, $value);
		}

	}

	if(defined('PROXY_URL') && !defined('NOPROXY')) {

		Debug::log('Setting proxy url: ' . PROXY_URL);
		curl_setopt($ch, CURLOPT_PROXY, PROXY_URL);

	}

	// We always want the response header as part of the data!
	curl_setopt($ch, CURLOPT_HEADER, true);

	$data = curl_exec($ch);
	$curlError = curl_error($ch);
	$curlErrno = curl_errno($ch);

	if($data === false)
		Debug::log('Cant\'t download ' . $url . ' cUrl error: ' . $curlError . ' (' . $curlErrno . ')');

	$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$errorCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$header = substr($data, 0, $headerSize);

	Debug::log('Response header: ' . $header);

	$headers = parseResponseHeader($header);
	$finalHeader = end($headers);

	if($errorCode !== 200) {

		if(array_key_exists('Server', $finalHeader) && strpos($finalHeader['Server'], 'cloudflare') !== false) {
			returnServerError(<<< EOD
The server responded with a Cloudflare challenge, which is not supported by RSS-Bridge!
If this error persists longer than a week, please consider opening an issue on GitHub!
EOD
			);
		}

		returnError(<<<EOD
The requested resource cannot be found!
Please make sure your input parameters are correct!
EOD
		, $errorCode);
	}

	curl_close($ch);
	return substr($data, $headerSize);
}

/**
 * Gets contents from the Internet as simplhtmldom object.
 *
 * @param string $url The URL.
 * @param array $header (optional) A list of cURL header.
 * For more information follow the links below.
 * * https://php.net/manual/en/function.curl-setopt.php
 * * https://curl.haxx.se/libcurl/c/CURLOPT_HTTPHEADER.html
 * @param array $opts (optional) A list of cURL options as associative array in
 * the format `$opts[$option] = $value;`, where `$option` is any `CURLOPT_XXX`
 * option and `$value` the corresponding value.
 *
 * For more information see http://php.net/manual/en/function.curl-setopt.php
 * @param bool $lowercase Force all selectors to lowercase.
 * @param bool $forceTagsClosed Forcefully close tags in malformed HTML.
 *
 * _Remarks_: Forcefully closing tags is great for malformed HTML, but it can
 * lead to parsing errors.
 * @param string $target_charset Defines the target charset.
 * @param bool $stripRN Replace all occurrences of `"\r"` and `"\n"` by `" "`.
 * @param string $defaultBRText Specifies the replacement text for `<br>` tags
 * when returning plaintext.
 * @param string $defaultSpanText Specifies the replacement text for `<span />`
 * tags when returning plaintext.
 * @return string Contents as simplehtmldom object.
 */
function getSimpleHTMLDOM($url,
$header = array(),
$opts = array(),
$lowercase = true,
$forceTagsClosed = true,
$target_charset = DEFAULT_TARGET_CHARSET,
$stripRN = true,
$defaultBRText = DEFAULT_BR_TEXT,
$defaultSpanText = DEFAULT_SPAN_TEXT){
	$content = getContents($url, $header, $opts);
	return str_get_html($content,
	$lowercase,
	$forceTagsClosed,
	$target_charset,
	$stripRN,
	$defaultBRText,
	$defaultSpanText);
}

/**
 * Gets contents from the Internet as simplhtmldom object. Contents are cached
 * and re-used for subsequent calls until the cache duration elapsed.
 *
 * _Notice_: Cached contents are forcefully removed after 24 hours (86400 seconds).
 *
 * @param string $url The URL.
 * @param int $duration Cache duration in seconds.
 * @param array $header (optional) A list of cURL header.
 * For more information follow the links below.
 * * https://php.net/manual/en/function.curl-setopt.php
 * * https://curl.haxx.se/libcurl/c/CURLOPT_HTTPHEADER.html
 * @param array $opts (optional) A list of cURL options as associative array in
 * the format `$opts[$option] = $value;`, where `$option` is any `CURLOPT_XXX`
 * option and `$value` the corresponding value.
 *
 * For more information see http://php.net/manual/en/function.curl-setopt.php
 * @param bool $lowercase Force all selectors to lowercase.
 * @param bool $forceTagsClosed Forcefully close tags in malformed HTML.
 *
 * _Remarks_: Forcefully closing tags is great for malformed HTML, but it can
 * lead to parsing errors.
 * @param string $target_charset Defines the target charset.
 * @param bool $stripRN Replace all occurrences of `"\r"` and `"\n"` by `" "`.
 * @param string $defaultBRText Specifies the replacement text for `<br>` tags
 * when returning plaintext.
 * @param string $defaultSpanText Specifies the replacement text for `<span />`
 * tags when returning plaintext.
 * @return string Contents as simplehtmldom object.
 */
function getSimpleHTMLDOMCached($url,
$duration = 86400,
$header = array(),
$opts = array(),
$lowercase = true,
$forceTagsClosed = true,
$target_charset = DEFAULT_TARGET_CHARSET,
$stripRN = true,
$defaultBRText = DEFAULT_BR_TEXT,
$defaultSpanText = DEFAULT_SPAN_TEXT){
	Debug::log('Caching url ' . $url . ', duration ' . $duration);

	// Initialize cache
	$cache = Cache::create('FileCache');
	$cache->setPath(PATH_CACHE . 'pages/');
	$cache->purgeCache(86400); // 24 hours (forced)

	$params = [$url];
	$cache->setParameters($params);

	// Determine if cached file is within duration
	$time = $cache->getTime();
	if($time !== false
	&& (time() - $duration < $time)
	&& Debug::isEnabled()) { // Contents within duration
		$content = $cache->loadData();
	} else { // Content not within duration
		$content = getContents($url, $header, $opts);
		if($content !== false) {
			$cache->saveData($content);
		}
	}

	return str_get_html($content,
	$lowercase,
	$forceTagsClosed,
	$target_charset,
	$stripRN,
	$defaultBRText,
	$defaultSpanText);
}

/**
 * Parses the cURL response header into an associative array
 *
 * Based on https://stackoverflow.com/a/18682872
 *
 * @param string $header The cURL response header.
 * @return array An associative array of response headers.
 */
function parseResponseHeader($header) {

	$headers = array();
	$requests = explode("\r\n\r\n", trim($header));

	foreach ($requests as $request) {

		$header = array();

		foreach (explode("\r\n", $request) as $i => $line) {

			if($i === 0) {
				$header['http_code'] = $line;
			} else {

				list ($key, $value) = explode(': ', $line);
				$header[$key] = $value;

			}

		}

		$headers[] = $header;

	}

	return $headers;

}

/**
 * Determines the MIME type from a URL/Path file extension.
 *
 * _Remarks_:
 *
 * * The built-in functions `mime_content_type` and `fileinfo` require fetching
 * remote contents.
 * * A caller can hint for a MIME type by appending `#.ext` to the URL (i.e. `#.image`).
 *
 * Based on https://stackoverflow.com/a/1147952
 *
 * @param string $url The URL or path to the file.
 * @return string The MIME type of the file.
 */
function getMimeType($url) {
	static $mime = null;

	if (is_null($mime)) {
		// Default values, overriden by /etc/mime.types when present
		$mime = array(
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'png' => 'image/png',
			'image' => 'image/*'
		);
		// '@' is used to mute open_basedir warning, see issue #818
		if (@is_readable('/etc/mime.types')) {
			$file = fopen('/etc/mime.types', 'r');
			while(($line = fgets($file)) !== false) {
				$line = trim(preg_replace('/#.*/', '', $line));
				if(!$line)
					continue;
				$parts = preg_split('/\s+/', $line);
				if(count($parts) == 1)
					continue;
				$type = array_shift($parts);
				foreach($parts as $part)
					$mime[$part] = $type;
			}
			fclose($file);
		}
	}

	if (strpos($url, '?') !== false) {
		$url_temp = substr($url, 0, strpos($url, '?'));
		if (strpos($url, '#') !== false) {
			$anchor = substr($url, strpos($url, '#'));
			$url_temp .= $anchor;
		}
		$url = $url_temp;
	}

	$ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
	if (!empty($mime[$ext])) {
		return $mime[$ext];
	}

	return 'application/octet-stream';
}
