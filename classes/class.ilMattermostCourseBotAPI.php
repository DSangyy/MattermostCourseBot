<?php

class ilMattermostCourseBotAPI
{
	private $mm_url = "";
	
	private $api_key = "";
	
	private $team_id = "";
	
	private $default_users = [];

	private ilSetting $settings;

	public function updateSettings($settings) : void
	{
		$this->mm_url = $settings->get("mm_url", "");
		$this->api_key = $settings->get("api_key", "");
		$this->team_id = $settings->get("team_id", "");
		$this->default_users = array_filter(explode(";", $settings->get("default_users", "")));
	}
	public function createChannel($name)
	{
		list($is_success, $response) = $this->APIPOSTCall(
			$this->urlBuild('/api/v4/channels'),
			array(
				"team_id" => $this->team_id,
	  			"name" => strtolower(preg_replace('/\s+/', '', $name)),
				"display_name" => $name,
				"purpose" => "",
				"header" => "",
				"type" => "P"
			)
		);
		
		if (!$is_success) 
		{
			return '';
		}
		
		$channel_id = json_decode($response, true)['id'];
		
		list($is_success, $response) = $this->APIPOSTCall(
			$this->urlBuild(sprintf('/api/v4/channels/%s/members', $channel_id)),
			array(
				"user_ids" => $this->default_users,
			)
		);
		
		if (!$is_success) 
		{
			return '';
		}
		
		return $channel_id;
	}
	
	public function deleteChannel($channel_id)
	{
		list($is_success, $response) = $this->APIDELETECall(
			$this->urlBuild(sprintf('/api/v4/channels/%s', $channel_id)),
			array()
		);
		
		return $is_success;
	}
	
	public function postMessage($channel_id, $message)
	{
		list($is_success, $response) = $this->APIPOSTCall(
			$this->urlBuild('/api/v4/posts'),
			array(
				"channel_id" => $channel_id,
				"message" => $message,
				"metadata" => [
					"priority" => [
						"priority" => "important"
					]
				]
			)
		);
		return $is_success;
	}
	
	protected function urlBuild($url) : string
	{
		return $this->mm_url . $url;
	}
	
	protected function APIPOSTCall(string $url, array $data)
	{
		global $DIC;
		$logger = $DIC->logger()->root();
		
		try {
				// Initialize cURL
				$curl = curl_init();
				
				// Set cURL options
				curl_setopt_array($curl, array(
					CURLOPT_URL => $url,
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,  // 30 second timeout
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_POSTFIELDS => json_encode($data),
					CURLOPT_HTTPHEADER => array(
						'Accept: application/json',
						'Content-Type: application/json',
						'Authorization: Bearer ' . $this->api_key
					)
				));

				// Execute request
				$response = curl_exec($curl);
				$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				$error = curl_error($curl);
				curl_close($curl);

				// Log the result
				if ($http_code >= 200 && $http_code < 300) {
					$logger->info("Webhook sent successfully. Response code: $http_code");
					return array(true, $response);
				} else {
					$logger->error("Webhook failed. HTTP Code: $http_code, Error: $error, Response: $response");
					return array(false, $response);
				}
				

		} catch (Exception $e) {
			$logger->error("Webhook exception: " . $e->getMessage());
			return array(false, '');
		}
	}
	
	protected function APIDELETECall(string $url, array $data)
	{
		global $DIC;
		$logger = $DIC->logger()->root();
		
		try {
				// Initialize cURL
				$curl = curl_init();
				
				// Set cURL options
				curl_setopt_array($curl, array(
					CURLOPT_URL => $url,
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,  // 30 second timeout
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'DELETE',
					CURLOPT_POSTFIELDS => json_encode($data),
					CURLOPT_HTTPHEADER => array(
						'Accept: application/json',
						'Content-Type: application/json',
						'Authorization: Bearer ' . $this->api_key
					)
				));

				// Execute request
				$response = curl_exec($curl);
				$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				$error = curl_error($curl);
				curl_close($curl);

				// Log the result
				if ($http_code >= 200 && $http_code < 300) {
					$logger->info("Webhook sent successfully. Response code: $http_code");
					return array(true, $response);
				} else {
					$logger->error("Webhook failed. HTTP Code: $http_code, Error: $error, Response: $response");
					return array(false, $response);
				}
				

		} catch (Exception $e) {
			$logger->error("Webhook exception: " . $e->getMessage());
			return array(false, '');
		}
	}
}

?>
