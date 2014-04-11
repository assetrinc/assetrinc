<?php
/*
 * Assetrinc source code
 *
 * Copyright Matt Light <matt.light@lightdatasys.com>
 *
 * For copyright and licensing information, please view the LICENSE
 * that is distributed with this source code.
 */

namespace Assetrinc\ResponseAdapter;

use DateTime;

use Assetrinc\ResponseAdapter;

use Symfony\Component\HttpFoundation\Response;

class Symfony extends ResponseAdapter
{
    public function getResponse($name, array $options = array())
    {
        $expires = new DateTime("now + 12 months");
        $service = $this->getAssetService();

        $response = new Response(
            '',
            200,
            array(
                'Content-Type' => $service->getContentType($name),
            )
        );

        $response->setLastModified($service->getLastModified($name));
        $response->setPublic();

        if (!array_key_exists('request', $options)
            || !$response->isNotModified($options['request'])
        ) {
            $response->setContent($service->getContent($name));
        }

        return $response;
    }
}
