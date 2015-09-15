<?php

namespace MindTouchApi;

class MindTouchApi {
	private $api_url;
	private $api_username;
	private $api_password;
	private $edit_time;

	public function __construct($config = array()) {
		if (count($config) > 0) {
			$this->setApiCredentials($config);
		}
		$this->edit_time = date('YmdHis');
	}

	/**
	 * Executes DELETE call to API.
	 * @param string $url URL of the API to call.
	 * @return string $output XML response to the API call.
	 */
	private function delete($url) {
		$url = $this->api_url . $url;

		// Open curl.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_USERPWD, $this->api_username . ":" . $this->api_password);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}

	/**
	 * Executes GET call to API.
	 * @param string $url URL of the API to call.
	 * @return string $output XML response to the API call.
	 */
	private function get($url) {
		$url = $this->api_url . $url;

		// Open curl.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->api_username . ":" . $this->api_password);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}

	/**
	 * Executes POST call to API.
	 * @param string $url URL of the API to call.
	 * @param string $content Content to send to the API method.
	 * @param string $type Content type of the content.
	 * @param string $header Optional header that can be sent.
	 * @return string $output XML response to the API call.
	 */
	private function post($url, $content, $type = '', $header = '') {
		$url = $this->api_url . $url;

		// Set headers.
		$headers = array();
		if (!empty($type)) {
			$headers[] = 'Content-Type: ' . $type;
		}
		if (!empty($header)) {
			$headers[] = $header;
		}

		// Open curl.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->api_username . ":" . $this->api_password);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		if (count($headers) > 0) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}

	/**
	 * Executes PUT call to API.
	 * @param string $url URL of the API to call.
	 * @param string $content Content to send to the API method.
	 * @return string $output XML response to the API call.
	 */
	private function put($url, $content = '') {
		$url = $this->api_url . $url;

		// PUT in PHP requires content to be in a file. Store in temp.
		$fp = fopen("php://temp", "r+");
		fputs($fp, $content);
		rewind($fp);

		// Open curl.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/xml',
				'Content-Length: ' . strlen($content)
			)
		);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->api_username . ":" . $this->api_password);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$output = curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		return $output;
	}

	/**
	 * Returns a page ID encoded to MindTouch's specifications.
	 * @param string $title Title of the page.
	 * @param  string $path Path of the page.
	 * @return string $page_id Page ID
	 */
	public function buildPageId($title, $path = '') {
		// The MindTouch API requires page IDs to be URL encoded twice.
		// Replace any slashes in the title first.
		$title = $this->escapeSlashesPageId($title);
		// Constrain the title to 150 characters.
		$title = substr($title, 0, 150);
		if (!empty($path)) {
			$path = rtrim($path, '/');
			$title = $path . '/' . $title;
		}
		return urlencode(urlencode($title));
	}

	/**
	 * Builds the MindTouch page ID string from the page's full path.
	 * @param string $path Path to the page.
	 * @return string $page_id
	 */
	public function buildPageIdFromPathAndTitle($path) {
		// MiondTouch uses double slashes to escape slashes in page titles.
		$double_slash_pos = strrpos($path, '//');
		if ($double_slash_pos !== false) {
			// A double-slash was found. Find the slash before it.
			$path_title_start = substr($path, 0, $double_slash_pos);
			$title_end = substr($path, $double_slash_pos + 1);

			// Break apart the path according to the slash.
			$path = explode('/', $path_title_start);

			// The title will be the last element of the array.
			$title_start = array_pop($path);
			$title = $title_start . $title_end;
		} else {
			// Break apart the path according to the slash.
			$path = explode('/', $path);

			// The title will be the last element of the array.
			$title = array_pop($path);
		}

		// Combine the path.
		$path = implode('/', $path);

		// Build the ID and return.
		return $this->buildPageId($title, $path);
	}

	/**
	 * Returns all context ID mappings for the site. When ID is provided, only that
	 * ID is returned.
	 * @param string $context_id MindTouch context ID.
	 * @param string $language Language to use. Defaults to English.
	 * @return string $output XML output of API response.
	 */
	public function contextMapsGet($context_id = '', $language = 'en-us') {
		// Build the MindTouch API URL to get the context mapping.
		$url = "contextmaps";
		if (!empty($context_id)) {
			$url .= '/' . $language . '/' . $context_id;
		}

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Maps a context ID to a page ID.
	 * @param string $context_id MindTouch context ID.
	 * @param integer $page_id MindTouch page ID to associate context ID with. Must be integer.
	 * @param string $language Language to use. Defaults to English.
	 * @return string $output XML output of API response.
	 */
	public function contextMapsPut($context_id, $page_id, $language = 'en-us') {
		// Build the MindTouch API URL to create the context mapping.
		$url = "contextmaps/$language/$context_id";

		// Deal with the context map XML.
		$content = "<contextmap>";
		$content .= "<pageid>$page_id</pageid>";
		$content .= "</contextmap>";

		// Get output from API.
		$output = $this->put($url, $content);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Deletes the given context ID.
	 * 
	 * @param string $context_id MindTouch context ID.
	 * @return string $output XML output of API response.
	 */
	public function contextsDelete($context_id) {
		// Build the MindTouch API URL to get the contexts.
		$url = "contexts";
		if (!empty($context_id)) {
			$url .= '/' . $context_id;
		}

		// Get output from API.
		$output = $this->delete($url);

		return $output;
	}

	/**
	 * Returns all context IDs for the site. When ID is provided, only that
	 * ID is returned.
	 * @param string $context_id MindTouch context ID.
	 * @return string $output XML output of API response.
	 */
	public function contextsGet($context_id = '') {
		// Build the MindTouch API URL to get the contexts.
		$url = "contexts";
		if (!empty($context_id)) {
			$url .= '/' . $context_id;
		}

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Updates or creates the given context ID.
	 * @param string $context_id MindTouch context ID.
	 * @param string $description Description of the context ID.
	 * @return string $output XML output of API response.
	 */
	public function contextsPut($context_id, $description = '') {
		// Build the MindTouch API URL to create the context mapping.
		$url = "contexts/$context_id";

		// Deal with the context map XML.
		$content = "<context>";
		$content .= "<description>$description</description>";
		$content .= "</context>";

		// Get output from API.
		$output = $this->put($url, $content);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Escapes slashes in the MindTouch page ID.
	 * @param string $page_id MindTouch page ID.
	 * @return string $page_id MindTouch page ID.
	 */
	public function escapeSlashesPageId($page_id) {
		$page_id = str_replace('/', '//', $page_id);
		return $page_id;
	}

	/**
	 * Returns list of groups. When group ID is supplied, only that group's
	 * information is returned.
	 * 
	 * @param mixed $group_id Can be group ID or group name.
	 * @return object XML object containing user information.
	 */
	public function groupsGet($group_id = '') {
		$url = 'groups';
		if (!empty($group_id)) {
			$url .= '/';
			if (is_string($group_id)) {
				$url .= '=';
				$group_id = urlencode(urlencode($group_id));
			}
			$url .= $group_id;
		}
		$output = $this->get($url);
		return $this->parseOutput($output);
	}

	/**
	 * Creates or updates a new group.
	 * 
	 * @param string $name Group name.
	 * @param integer $group_id MindTouch group ID.
	 * @return object XML object containing group information.
	 */
	public function groupsPost($name, $group_id = '') {
		// Build content for the group.
		$content = "<group";
		if (!empty($group_id)) {
			$content .= '  id="' . $group_id . '"';
		}
		$content .= ">";
		$content .= "<name>$name</name>";
		$content .= "</group>";

		$url = 'groups';
		$output = $this->post($url, $content, 'application/xml');
		return $this->parseOutput($output);
	}

	/**
	 * Returns list of users in the group.
	 * 
	 * @param mixed $group_id Can be group ID or group name.
	 * @return object XML object containing user information.
	 */
	public function groupsUsersGet($group_id) {
		$url = 'groups';
		if (!empty($group_id)) {
			$url .= '/';
			if (is_string($group_id)) {
				$url .= '=';
				$group_id = urlencode(urlencode($group_id));
			}
			$url .= $group_id . '/users';
		}
		$output = $this->get($url);
		return $this->parseOutput($output);
	}

	/**
	 * Adds users to a group.
	 * 
	 * @param mixed $group_id Can be group ID or group name.
	 * @param array $users Array of user MindTouch IDs (integers) to add.
	 * @return object XML object containing group information.
	 */
	public function groupsUsersPost($group_id, $users = array()) {
		// Build content for the group.
		$content = "<users>";
		foreach ($users as $user) {
			$content .= '<user id="' . $user . '"/>';
		}
		$content .= "</users>";

		$url = 'groups';
		if (!empty($group_id)) {
			$url .= '/';
			if (is_string($group_id)) {
				$url .= '=';
				$group_id = urlencode(urlencode($group_id));
			}
			$url .= $group_id . '/users';
		}
		$output = $this->post($url, $content, 'application/xml');
		return $this->parseOutput($output);
	}

	/**
	 * Builds the pages API URL. Supports both string and integer page IDs.
	 * 
	 * @param mixed $page_id String or integer.
	 * @return string $url URL for the pages API methods.
	 */
	private function pageUrl($page_id) {
		$url = "pages/";
		if (is_string($page_id)) {
			$url .= '=';
		}
		$url .= $page_id;
		return $url;
	}

	/**
	 * Creates the given page.
	 * @param mixed $page_id The MindTouch page ID.
	 * @param string $content Content of the page.
	 * @param  string $title Title of page to use when different from page ID.
	 * @return string $output XML output of page create API call.
	 */
	public function pageCreate($page_id, $content, $title = '') {
		// Build the MindTouch API URL to create or update a page's content.
		$url = $this->pageUrl($page_id) . "/contents?edittime=" . $this->edit_time . "&overwrite=true";

		// Deal with title when provided.
		if (!empty($title)) {
			$url .= "&title=" . urlencode($title);
		}

		// Get output from API.
		$output = $this->post($url, $content);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Parses the API response from a page creation to determine
	 * its success or failure.
	 * @param object $output XML object created by parseOutput.
	 * @return boolean True when page created successfully.
	 */
	public function pageCreateCheck($output) {
		$status = false;
		if (isset($output->status)) {
			$status = (string) $output->status;
		} elseif (isset($output['status'])) {
			$status = (string) $output['status'];
		}
		if ($status == 'success') {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Retrieves a page's contents.
	 * @param mixed $page_id The MindTouch page ID.
	 * @return object $output XML object.
	 */
	public function pageContentsGet($page_id, $options = array()) {
		// Build the MindTouch API URL to get a page's contents.
		$url = $this->pageUrl($page_id) . "/contents?" . http_build_query($options);

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Deletes the given page ID.
	 * @param mixed $page_id The MindTouch page ID.
	 * @return string $output XML output of page delete API call.
	 */
	public function pageDelete($page_id) {
		// Build the MindTouch API URL to delete the page.
		$url = $this->pageUrl($page_id);

		// Get output from API.
		$output = $this->delete($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Checks to see if the page exists.
	 * @param mixed $page_id The MindTouch page ID.
	 * @return boolean
	 */
	public function pageExists($page_id) {
		// Build the MindTouch API URL to check a page's existence.
		$url = $this->pageUrl($page_id);

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		if ((string) $output['id'] > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function pageGet($page_id) {
		// Build the MindTouch API URL to fetch the subpages.
		$url = $this->pageUrl($page_id);

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Returns the file.
	 * @param mixed $page_id The MindTouch page ID.
	 * @param string $file_name The file name.
	 * @return binary $output The contents of the file.
	 */
	public function pageFileGet($page_id, $file_name) {
		// Build the MindTouch API URL to get a page's specific file.
		if (strpos($file_name, '.') === false) {
			$file_name = '=' . $file_name;
		}
		$url = $this->pageUrl($page_id) . "/files/" . $file_name;

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Attaches a file to the given MindTouch page.
	 * @param mixed $page_id The MindTouch page ID.
	 * @param string $file_name The path and name of the file to attach.
	 * @return object $output XML object containing API response.
	 */
	public function pageFilePut($page_id, $file_name) {
		// Get information about the file.
		$file_info = pathinfo($file_name);

		// Build the MindTouch API URL to attach the file to the page.
		$file_name = $file_info['basename'];
		if (strpos($file_name, '.') === false) {
			$file_name = '=' . $file_name;
		}
		$url = $this->pageUrl($page_id) . "/files/" . rawurlencode($file_name);
		$url = $this->api_url . $url;

		// Get the mime type.
		$file = escapeshellarg($file_info['dirname'] . '/' . $file_name);
		$mime_type = shell_exec("file -bi " . $file);
		if (strpos($mime_type, ';') !== false) {
			$mime_type = substr($mime_type, 0, strpos($mime_type, ';'));
		}

		// Open curl.
		$ch = curl_init();
		$fp = fopen($file_info['dirname'] . '/' . $file_name, "r+");
		curl_setopt ($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: ' . $mime_type
			)
		);
		curl_setopt($ch, CURLOPT_INFILE, $fp);
		curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file_info['dirname'] . '/' . $file_name));
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_PUT, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERPWD, $this->api_username . ":" . $this->api_password);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$output = curl_exec($ch);
		curl_close($ch);
		fclose($fp);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Returns the files attached to the page.
	 * @param mixed $page_id The MindTouch page ID.
	 * @return string $output XML object containing file information.
	 */
	public function pageFilesGet($page_id) {
		// Build the MindTouch API URL to get a page's files.
		$url = $this->pageUrl($page_id) . "/files";
		$url = "pages/=" . $page_id . "/files";

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Order the two given pages.
	 * @param int $page_id  MindTouch page ID.
	 * @param int $after_id The page id after which this page should be
	 * placed, use 0 if you wish to place it at the beginning
	 * @return string $output XML output of API response.
	 */
	public function pageOrderPut($page_id, $after_id) {
		// Build the MindTouch API URL to get a page's tags.
		$url = $this->pageUrl($page_id) . "/order";

		// Add the ID to place the page after.
		$url .= '?afterid=' . $after_id;

		// Get output from API.
		$output = $this->put($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Retrieves a page's properties.
	 * 
	 * @param mixed $page_id MindTouch page ID.
	 * @param string $property Optional. When set, retrieves that property.
	 * @return mixed XML object when XML. String otherwise.
	 */
	public function pagePropertiesGet($page_id, $property = '') {
		// Build the MindTouch API URL to get a page's properties.
		$url = $this->pageUrl($page_id) . "/properties";
		if (!empty($property)) {
			$url .= '/' . $property;
		}

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		if (empty($property)) {
			$output = $this->parseOutput($output);
		}
		return $output;
	}

	/**
	 * Adds a property to a page.
	 * 
	 * @param mixed $page_id MindTouch page ID.
	 * @param string $property Name of property to add.
	 * @param string $description Description of property.
	 * @param string $content Content of property.
	 * @return object XML object of page's properties.
	 */
	public function pagePropertiesPost($page_id, $property, $description, $content) {
		$url = $this->pageUrl($page_id) . "/properties";
		$header = "Slug: $property";
		$output = $this->post($url . '?abort=never&description=' . $description, $content, 'text/plain', $header);
		return $this->parseOutput($output);
	}

	/**
	 * Deletes and resets a page's security setting.
	 * @param mixed $page_id The MindTouch page ID.
	 * @return integer $output 1 on success.
	 */
	public function pageSecurityDelete($page_id) {
		// Build the MindTouch API URL to delete a page's security.
		$url = $this->pageUrl($page_id) . "/security";
		
		// Get output from API.
		$output = $this->delete($url);

		return $output;
	}

	/**
	 * Retrieve's a page's security settings.
	 * @param mixed $page_id The MindTouch page ID.
	 * @return string $output XML output of API response.
	 */
	public function pageSecurityGet($page_id) {
		// Build the MindTouch API URL to get a page's security.
		$url = $this->pageUrl($page_id) . "/security";
		
		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Sets a page's security.
	 * @param mixed $page_id The MindTouch page ID.
	 * @param string $restriction Security to set. Public, Private, Semi-Private, and Semi-Public.
	 * @param string $children Whether to apply security to children.none, delta, absolute.
	 * @return string $output XML output of API response.
	 */
	public function pageSecurityPut($page_id, $restriction, $children = 'none') {
		// Build the MindTouch API URL to set a page's security.
		$url = $this->pageUrl($page_id) . "/security?cascade=" . $children;

		// Deal with the security.
		$content = "<security>";
		$content .= "<permissions.page>";
		$content .= "<restriction>$restriction</restriction>";
		$content .= "</permissions.page>";
		$content .= "</security>";

		// Get output from API.
		$output = $this->put($url, $content);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Gets a page's tags.
	 * @param string $page_id MindTouch page ID.
	 * @return object $output XML object.
	 */
	public function pageTagsGet($page_id) {
		// Build the MindTouch API URL to get a page's tags.
		$url = $this->pageUrl($page_id) . "/tags";

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Set the tags for the given page ID.
	 * @param mixed $page_id The MindTouch page ID.
	 * @param array $tags Array of tags to set.
	 * @return string $output XML output of API response.
	 */
	public function pageTagsSet($page_id, $tags) {
		// Build the MindTouch API URL to update the page's tags.
		$url = $this->pageUrl($page_id) . "/tags";

		$xml_escape = array(
			'"' => '&quot;',
			"'" => '&apos;',
			'<' => '&lt;',
			'>' => '&gt;',
			'&' => '&amp;'
		);

		// Deal with the tags.
		$content = "<tags>";
		foreach ($tags as $tag) {
			$tag = str_replace(array_keys($xml_escape), array_values($xml_escape), $tag);
			$content .=  '<tag value="' . $tag . '"/>';
		}
		$content .= "</tags>";

		// Get output from API.
		$output = $this->put($url, $content);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Returns a list of all the pages on the MindTouch instance.
	 * @return string $output XML output of pages API call.
	 */
	public function pages() {
		// Build the MindTouch API URL to fetch the pages.
		$url = "pages";

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Returns a list of the pages below the given page on 
	 * the MindTouch instance.
	 * @param string $page_id MindTouch page ID.
	 * @return string $output XML output of pages API call.
	 */
	public function pagesSubpagesGet($page_id) {
		// Build the MindTouch API URL to fetch the subpages.
		$url = $this->pageUrl($page_id) . "/subpages";

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Retrieves the error message from the API error XML.
	 * @param string $error_xml MindTouch API error XML string.
	 * @return string $error_message API error message text.
	 */
	public function parseErrorMessage($error_xml) {
		$error_message = (string) $error_xml->title . ' (' . (string) $error_xml->status . '): ' . (string) $error_xml->message;
		return $error_message;
	}

	/**
	 * Parses API response/
	 * @param string $output XML output from API method.
	 * @return object $output XML object.
	 */
	public function parseOutput($output) {
		// Convert XML to object.
		return simplexml_load_string($output);
	}

	/**
	 * Sets the credentials to use to access the API.
	 * @param string $api_url URL to the MindTouch API.
	 * @param string $api_username MindTouch API username.
	 * @param string $api_password MindTouch API password.
	 */
	public function setApiCredentials($credentials) {
		$this->api_url = (!empty($credentials['api_domain'])) ? $credentials['api_domain'] : '';
		// Add the required bits when the api_url is just a domain.
		if (!empty($this->api_url) && stripos($this->api_url, '@api/deki/') === false) {
			$this->api_url = 'https://' . $this->api_url . '/@api/deki/';
		}

		$this->api_username = (!empty($credentials['api_username'])) ? $credentials['api_username'] : '';
		$this->api_password = (!empty($credentials['api_password'])) ? $credentials['api_password'] : '';
	}

	public function siteActivityGet($since = '') {
		// Build the MindTouch API URL to get the site activity.
		$url = "site/activity";
		if (!empty($since)) {
			$url .= '?since=' . date('YmdHis', strtotime($since));
		}

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Retrieves the site's tags.
	 * @param array $options Options to send to API.
	 *   to string End date for type=date (ex: 2008-12-30) (default: now + 30 days).
	 *   from string Start date for type=date (ex: 2008-01-30) (default: now).
	 *   type string Type of the tag (text | date | user | define) (default: all types).
	 *   q string Partial tag name to match (ex: tagprefix) (default none).
	 *   pages bool Show pages with each tag (default: false).
	 * @return object $output XML object containing API tag search result.
	 */
	public function siteTagsGet($options = array()) {
		// Deal with options.
		$query = '';
		$allowed = array(
			'to',
			'from',
			'type',
			'q',
			'pages'
		);
		foreach ($options as $option => $value) {
			if (!in_array($option, $allowed)) {
				// Remove any options that aren't allowed.
				unset($options[$option]);
			}
		}
		$query = http_build_query($options);

		// Build the MindTouch API URL to get a site's tags.
		$url = "site/tags?" . $query;

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		$output = $this->parseOutput($output);
		return $output;
	}

	/**
	 * Builds the users API URL. Supports both string and integer user IDs.
	 * 
	 * @param mixed $user_id Can be user ID or user name.
	 * @return string $url URL for the users API methods.
	 */
	private function usersUrl($user_id = '') {
		$url = "users";
		if (!empty($user_id)) {
			$url .= '/';
			if (is_string($user_id) && $user_id !== 'current') {
				$url .= '=';
				$user_id = urlencode(urlencode($user_id));
			}
			$url .= $user_id;
		}
		return $url;
	}

	/**
	 * Builds a link that logs in the given user.
	 * 
	 * @param string $api_key MindTouch API key.
	 * @param string $username Username of MindTouch user.
	 * @param string $redirect URL to send user to after logging in.
	 * @return string Authentication link.
	 */
	public function usersAuthenticateLink($api_key, $username, $redirect = '') {
		// Set the redirect to the main MindTouch page when empty.
		if (empty($redirect)) {
			$redirect = str_replace('@api/deki/', '', $this->api_url);
		}

		// Create the MD5 authhash.
		$timestamp = time();
		$auth_hash = md5("{$username}:{$timestamp}:{$api_key}");

		// Create the token.
		$imp_auth_token = "imp_{$timestamp}_{$auth_hash}_={$username}";

		// Depending on your HTTP client, you may or may not need to 
		// URL encode the token.
		$imp_auth_token = urlencode($imp_auth_token);

		// Build link and return it.
		return $this->api_url . "users/authenticate?authtoken=" . $imp_auth_token . "&redirect=" . $redirect;
	}

	/**
	 * Returns list of users. When user ID is supplied, only that user's
	 * information is returned.
	 * 
	 * @param mixed $user_id Can be user ID or user name.
	 * @param array $filters Filters to search by.
	 * @return object XML object containing user information.
	 */
	public function usersGet($user_id = '', $filters = array()) {
		$url = $this->usersUrl($user_id);

		if (count($filters) > 0) {
			$url .= '?';
			foreach ($filters as $filter => $value) {
				$url .= $filter . '=' . $value . '&';
			}
		}

		$output = $this->get($url);
		return $this->parseOutput($output);
	}

	/**
	 * Creates or updates a new user.
	 * 
	 * @param string $username User's username.
	 * @param string $email User's email address.
	 * @param string $name User's full name.
	 * @param string $password User's password.
	 * @param integer $id MindTouch user ID.
	 * @return object XML object containing user information.
	 */
	public function usersPost($username, $email, $name, $password = '', $id = '') {
		// Build content for the user.
		// Include MindTouch user ID when updating a user.
		$content = "<user";
		if (!empty($id)) {
			$content .= '  id="' . $id . '"';
		}
		$content .= ">";
		$content .= "<username>$username</username>";
		$content .= "<email>$email</email>";
		$content .= "<fullname>$name</fullname>";
		$content .= "<status>active</status>";
		$content .= "</user>";

		$url = $this->usersUrl();
		if (!empty($password)) {
			$url .= '?accountpassword=' . $password;
		}
		$output = $this->post($url, $content, 'application/xml');
		return $this->parseOutput($output);
	}

	/**
	 * Retrieves a user's properties.
	 * 
	 * @param mixed $user_id MindTouch user ID.
	 * @param string $property Optional. When set, retrieves that property.
	 * @return mixed XML object when XML. String otherwise.
	 */
	public function usersPropertiesGet($user_id, $property = '') {
		$url = $this->usersUrl($user_id) . '/properties';
		if (!empty($property)) {
			$url .= '/' . $property;
		}

		// Get output from API.
		$output = $this->get($url);

		// Parse the output.
		if (empty($property)) {
			$output = $this->parseOutput($output);
		}
		return $output;
	}

	/**
	 * Adds a property to a user.
	 * 
	 * @param mixed $user_id MindTouch user ID.
	 * @param string $property Name of property to add.
	 * @param string $description Description of property.
	 * @param string $content Content of property.
	 * @return object XML object of user's properties.
	 */
	public function usersPropertiesPost($user_id, $property, $description, $content) {
		$url = $this->usersUrl($user_id) . '/properties';

		$header = "Slug: $property";
		$output = $this->post($url . '?abort=never&description=' . $description, $content, 'text/plain', $header);
		return $this->parseOutput($output);
	}

}
