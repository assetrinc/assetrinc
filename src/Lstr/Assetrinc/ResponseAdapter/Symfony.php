<?php
/*
 * Lstr/Assetrinc source code
 *
 * Copyright Matt Light <matt.light@lightdatasys.com>
 *
 * For copyright and licensing information, please view the LICENSE
 * that is distributed with this source code.
 */

namespace Lstr\Assetrinc\ResponseAdapter;

use Lstr\Assetrinc\ResponseAdapter;

use Symfony\Component\HttpFoundation\Response;

class Symfony extends ResponseAdapter
{
    public function getResponse($name)
    {
        $service = $this->getAssetService();
        return new Response(
            $service->getContent($name),
            200,
            array(
                'Content-Type' => $service->getContentType($name),
            )
        );
    }
}
