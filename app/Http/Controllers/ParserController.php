<?php

namespace App\Http\Controllers;

use App\Services\AmazonParserPage;
use Campo\UserAgent;
use GuzzleHttp\Client;
use Illuminate\Http\Request;


class ParserController extends Controller
{
    /**
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function run(Request $request)
    {
        try {
            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => UserAgent::random(
                        [
                            'agent_name' => ['Opera', 'Chromium', 'Chrome', 'Safari', 'Firefox'],
                            'device_type' => ['Desktop']
                        ])
                ]
            ]);

            $link = $request->get('link');

            if (!$link) {
                return response()->json(["error", "The link is empty"], 400);
            }

            $context = trim($client->request('GET', $link)
                ->getBody());

            $obj = (new AmazonParserPage($context))->run();
            return response()->json($obj, 200);
        } catch (\Exception $e) {
            return response()->json(["error" => $e->getMessage()], 400);
        }


    }

}
