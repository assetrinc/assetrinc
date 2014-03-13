<?php
/*
 * Lstr/Assetrinc source code
 *
 * Copyright Matt Light <matt.light@lightdatasys.com>
 *
 * For copyright and licensing information, please view the LICENSE
 * that is distributed with this source code.
 */

namespace Lstr\Assetrinc;

abstract class ResponseAdapter
{
    private $service;



    public function __construct(AssetService $service)
    {
        $this->service = $service;
    }



    public function getAssetService()
    {
        return $this->service;
    }



    abstract public function getResponse($name);
}
