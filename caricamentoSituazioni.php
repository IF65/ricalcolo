<?php
@ini_set('memory_limit', '16384M');

include(__DIR__ . '/Database/bootstrap.php');

require 'vendor/autoload.php';

use Database\Tables\Giacenze;
use GuzzleHttp\Client;

$client = new Client([
	'base_uri' => "http://11.0.1.31:8080/",
	'headers' => ['Content-Type' => 'application/json'],
	'timeout'  => 120.0,
]);

$response = $client->post('/rilevazioneStock',
	['json' =>
		[
			'function' => 'caricaSituazioni'
		]
	]
);

$giacenze = new Giacenze($sqlDetails);
if ($response->getStatusCode() == 200) {
	$request = $response->getBody()->getContents();
	if (isset($request)) {
		$giacenze->caricaGiacenzeCorrentiDaSituazioniSuServerSM($request);
	}
}