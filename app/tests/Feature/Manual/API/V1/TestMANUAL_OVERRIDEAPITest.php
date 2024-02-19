<?php

namespace Tests\Feature\Manual\API\V1;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TestMANUAL_OVERRIDEAPITest extends APIBaseTestCase
{
	use DatabaseTransactions;

	/**
	 *
	 *
	 *
	 * @return  void
	 */
	public function test_api_test_get_manual_override()
	{
		$data = $cookies = $files = $headers = $server = [];
		$faker = \Faker\Factory::create('en_AU');
		$content = null;

		// header params
		$headers['Accept'] = 'application/json';
		$headers['x-access-token'] = $this->getAccessToken();
		$headers['x-api-key'] = $this->getApiKey();

		// form params
		$data['device_type'] = 'my_device';
		$data['device_id'] = 'my_id';

		$response = $this->get('/api/v1/manualOverride', $headers);

		$this->saveResponse($response->getContent(), 'test_get_manual_override', $response->getStatusCode());

		$response->assertStatus(200);
	}
}
