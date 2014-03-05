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

class ContentTypeManager
{
    private $content_types = array(
        ''       => 'text/text',
        'css'    => 'text/css',
        'gif'    => 'image/gif',
        'ico'    => 'image/vnd.microsoft.icon',
        'jpg'    => 'image/jpeg',
        'js'     => 'text/javascript',
        'png'    => 'image/png',
    );



    public function getContentTypeForFileName($name)
    {
        $extensions = explode('.', basename($name));
        $extension  = null;
        foreach ($extensions as $ext) {
            if (isset($this->content_types[$ext])) {
                $extension = $ext;
                break;
            }
        }

        return $this->content_types["{$extension}"];
    }
}
