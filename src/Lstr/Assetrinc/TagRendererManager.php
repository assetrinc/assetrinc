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

use Exception;

class TagRendererManager
{
    private $renderers;

    public function __construct(array $renderers = null)
    {
        if (null === $renderers) {
            $renderers = array(
                'css' => function ($url) {
                    return "<link rel=\"stylesheet\" href=\"{$url}\" />";
                },
                'js' => function ($url) {
                    return "<script type=\"text/javascript\" src=\"{$url}\"></script>";
                },
            );
        }

        $this->renderers = $renderers;
    }

    public function setRenderer($type, $callable)
    {
        $this->renderers[$type] = $callable;
    }

    public function getRenderer($type)
    {
        if (!isset($this->renderers[$type])) {
            throw Exception("No known tag renderer for file type '{$type}'.");
        }

        return $this->renderers[$type];
    }
}
