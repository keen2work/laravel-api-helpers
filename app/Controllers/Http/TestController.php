<?php

namespace App\Controllers\Http;


use EMedia\Api\Docs\APICall;
use EMedia\Api\Docs\Param;

class TestController extends Controller
{

	public const INDEX_METHOD_NAME = 'INDEX_METHOD_NAME';
	public const TEST_HEADER_PARAMS = 'TEST_HEADER_PARAMS';
	public const TEST_BODY_PARAMS = 'TEST_BODY_PARAMS';
	public const MANUAL_OVERRIDE = 'MANUAL_OVERRIDE';
	public const PARAM_TYPES = 'PARAM_TYPES';


	public function undocumented()
	{
		return [];
	}

	public function index()
	{
		document(function () {
			return (new APICall())
				->setName(self::INDEX_METHOD_NAME);
		});

		return [];
	}

	public function headerParams()
	{
		document(function () {
			return (new APICall())
				->setName(self::TEST_HEADER_PARAMS)
				->setParams([
					(new Param('x-device-type'))->setLocation(Param::LOCATION_HEADER)->setDefaultValue('one'),
				]);
		});

		return [];
	}

	public function bodyParams()
	{
		document(function () {
			return (new APICall())
				->setName(self::TEST_BODY_PARAMS)
				->setParams([
					(new Param('device_type'))->setLocation(Param::LOCATION_FORM)->setDefaultValue('my_device'),
					(new Param('device_id'))->setLocation(Param::LOCATION_FORM)->setDefaultValue('my_id'),
				]);
		});

		return [];
	}

	public function manualOverride()
	{
		document(function () {
			return (new APICall())
				->setName(self::MANUAL_OVERRIDE)
				->setParams([
					(new Param('device_type'))->setLocation(Param::LOCATION_FORM)->setDefaultValue('my_device'),
					(new Param('device_id'))->setLocation(Param::LOCATION_FORM)->setDefaultValue('my_id'),
				]);
		});

		return [];
	}

	public function correctParameterTypes()
	{
		document(function () {
			return (new APICall())
				->setName(self::PARAM_TYPES)
				->setParams([
					(new Param('is_boolean', 'boolean', 'Accepted values `true`, `false`'))
						->setLocation(Param::LOCATION_FORM)
						->setExample(true),
				]);
		});

		return [];
	}
}
