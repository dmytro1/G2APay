<?php

/**
 * G2APay PHP Library
 * @author  	Davis Miculis, <mindzhulis@gmail.com>
 * @copyright 	Copyright (c) 2016 Davis Miculis
 * @license 	https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace G2APay;

class G2APay
{
	const API_URL = 'https://checkout.pay.g2a.com';
	const API_TEST_URL = 'https://checkout.test.pay.g2a.com';

	private $apiUrl;
	private $apiHash;
	private $apiSecret;
	private $urlSuccess;
	private $urlFail;
	private $orderId;
	private $currency;
	private $items = [];

	public function __construct($apiHash, $apiSecret, $urlSuccess, $urlFail, $orderId, $currency = 'USD')
	{
		$this->apiUrl = self::API_URL;
		$this->apiHash = $apiHash;
		$this->apiSecret = $apiSecret;
		$this->urlSuccess = $urlSuccess;
		$this->urlFail = $urlFail;
		$this->orderId = $orderId;
		$this->currency = $currency;
	}

	public function addItem($sku, $name, int $quantity, $id, float $price, $url, $extra = '', $type = '')
	{
		$this->items[] = [
			'sku' => $sku,
			'name' => $name,
			'amount' => floatval($quantity * $price),
			'qty' => $quantity,
			'id' => $id,
			'price' => $price,
			'url' => $url,
			'extra' => $extra,
			'type' => $type,
		];

		return $this;
	}

	public function test()
	{
		$this->apiUrl = self::API_TEST_URL;

		return $this;
	}

	public function create($extra = array())
	{
		// Temporary save api url, then reset to default
		$url = $this->apiUrl;
		$this->apiUrl = self::API_URL;

		// Calculate total price of items
		$amount = array_sum(array_column($this->items, 'amount'));

		// Prepare array with data to query G2A
		$fields = array_merge([
			'api_hash'		=> $this->apiHash,
			'hash'			=> $this->calculateHash($amount),
			'order_id'		=> $this->orderId,
			'amount'		=> $amount,
			'currency'		=> $this->currency,
			// 'description'	=> '',
			// 'email'			=> '',
			'url_failure'	=> $this->urlFail,
			'url_ok'		=> $this->urlSuccess,
			'items'			=> $this->items,
		], $extra);

		// Request API server
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url.'/index/createQuote');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		curl_close($ch);

		// Convert response from JSON text to PHP object/array
		$result = json_decode($response);

		if (isset($result->token)) {
			return [
				'success' => true,
				'url' => ($url.'/index/gateway?token='.$result->token)
			];
		} else {
			return [
				'success' => false,
				'message' => $result->message
			];
		}
	}

	private function calculateHash($amount)
	{
		return hash('sha256', $this->orderId.number_format($amount, 2).$this->currency.$this->apiSecret);
	}
}