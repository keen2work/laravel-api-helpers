<?php


namespace EMedia\Api\Domain\Traits;

trait HasAttributes
{
	protected $attributes;

	public function __set($name, $value)
	{
		$this->attributes[$name] = $value;

		return $this;
	}

	public function __get($name)
	{
		if (!array_key_exists($name, $this->attributes)) {
			return null;
		}

		return $this->attributes[$name];
	}

	public function __isset($name)
	{
		return isset($this->attributes[$name]);
	}

	public function __unset($name)
	{
		unset($this->attributes[$name]);
	}
}
