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

use ArrayObject;

use Lstr\Assetrinc\TagRendererManager;

use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Assetic\Filter\CoffeeScriptFilter;
use Assetic\Filter\CssRewriteFilter;
use Assetic\Filter\UglifyCssFilter;
use Assetic\Filter\UglifyJs2Filter;
use Assetic\FilterManager;
use Sprocketeer\Parser as SprocketeerParser;
use Symfony\Component\HttpFoundation\Response;

class AssetService
{
    private $url_prefix;
    private $path;
    private $tag_renderer_manager;
    private $options;

    private $sprocketeer;

    private $content_types = array(
        ''       => 'text/text',
        'css'    => 'text/css',
        'gif'    => 'image/gif',
        'ico'    => 'image/vnd.microsoft.icon',
        'jpg'    => 'image/jpeg',
        'js'     => 'text/javascript',
        'png'    => 'image/png',
    );



    public function __construct($paths, array $options)
    {
        if ($path instanceof ArrayObject) {
            $path = $path->getArrayCopy();
        }

        $this->path       = $paths;
        $this->options    = $options;
        $this->url_prefix = $this->options['assetrinc.url_prefix'];

        if (!empty($options['tag_renderer_manager'])) {
            $this->tag_renderer_manager = $options['tag_renderer_manager'];
        } else {
            $this->tag_renderer_manager = new TagRendererManager();
        }
    }



    private function getSprocketeer()
    {
        if (null !== $this->sprocketeer) {
            return $this->sprocketeer;
        }

        $this->sprocketeer = new SprocketeerParser($this->path);

        return $this->sprocketeer;
    }



    private function generateTag($name, $type)
    {
        $manifest_parser = $this->getSprocketeer();

        $renderer = $this->tag_renderer_manager->getRenderer($type);

        if ($this->options['debug']) {
            $files = $manifest_parser->getPathInfoFromManifest($name);

            $asset_list = array();
            foreach ($files as $asset) {
                $asset_list[] = $renderer("{$this->url_prefix}/{$asset['sprocketeer_path']}");
            }

            $html = implode("\n", $asset_list);
        } else {
            $html = $renderer("{$this->url_prefix}/{$name}");
        }

        return $html;
    }



    public function cssTag($name)
    {
        return $this->generateTag($name, 'css');
    }



    public function jsTag($name)
    {
        return $this->generateTag($name, 'js');
    }



    private function getAssetsPathInfo($name)
    {
        $manifest_parser = $this->getSprocketeer();

        list($search_path_name, $filename) = explode('/', $name, 2);

        if ($this->options['debug']) {
            $assets = array(
                $manifest_parser->getPathInfo($search_path_name, $filename),
            );
        } else {
            $assets = $manifest_parser->getPathInfoFromManifest($name);
        }

        return $assets;
    }



    private function getContentTypeForFileName($name)
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



    public function getAssetContent($name)
    {
        $assets   = $this->getAssetsPathInfo($name);

        $binaries = $this->options['assetrinc.binaries'];

        $filters = array(
            'coffee' => new CoffeeScriptFilter($binaries['coffee']),
            'uglifyJs' => new UglifyJs2Filter($binaries['uglifyJs']),
            'cssUrls' => new CssRewriteFilter(),
            'uglifyCss' => new UglifyCssFilter($binaries['uglifyCss']),
        );

        $filter_names_by_ext = array(
            'coffee' => array(
                'coffee',
            ),
            'js' => array(
                '?uglifyJs',
            ),
            'css' => array(
                'cssUrls',
                '?uglifyCss',
            ),
        );

        $filters_by_ext = array();
        foreach ($filter_names_by_ext as $ext => $filter_names) {
            foreach ($filter_names as $filter_name) {
                $filter = null;
                if (substr($filter_name, 0, 1) === '?') {
                    if (!$this->options['debug']) {
                        $filter = $filters[substr($filter_name, 1)];
                    }
                } else {
                    $filter = $filters[$filter_name];
                }

                if ($filter) {
                    $filters_by_ext[$ext][$filter_name] = $filter;
                }
            }
        }

        $asset_list = array();
        foreach ($assets as $asset) {
            $extensions = explode('.', basename($asset['requested_asset']));

            $filters = array();
            foreach (array_reverse($extensions) as $ext) {
                if (array_key_exists($ext, $filters_by_ext)) {
                    $filters = array_merge($filters, $filters_by_ext[$ext]);
                }
            }

            $file_asset = new FileAsset(
                $asset['absolute_path'],
                $filters,
                dirname($asset['absolute_path']),
                "{$this->url_prefix}/{$asset['sprocketeer_path']}"
            );

            $asset_list[] = $file_asset;
        }

        $collection = new AssetCollection($asset_list);

        return $collection->dump();
    }



    public function getAssetResponse($name)
    {
        return new Response(
            $this->getAssetContent($name),
            200,
            array(
                'Content-Type' => $this->getContentTypeForFileName($name),
            )
        );
    }
}
