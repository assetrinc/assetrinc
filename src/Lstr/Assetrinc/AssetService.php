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

use Lstr\Assetrinc\FilterManager;
use Lstr\Assetrinc\TagRendererManager;

use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Sprocketeer\Parser as SprocketeerParser;
use Symfony\Component\HttpFoundation\Response;

class AssetService
{
    private $url_prefix;
    private $path;
    private $tag_renderer_manager;
    private $filter_manager;
    private $content_type_manager;
    private $options;

    private $sprocketeer;



    public function __construct($paths, $url_prefix, array $options)
    {
        if ($paths instanceof ArrayObject) {
            $paths = $paths->getArrayCopy();
        }

        $this->path       = $paths;
        $this->url_prefix = $url_prefix;
        $this->options    = $options;

        if (!empty($options['filter_manager'])) {
            $this->filter_manager = $options['filter_manager'];
        } else {
            $this->filter_manager = new FilterManager($options);
        }

        if (!empty($options['tag_renderer_manager'])) {
            $this->tag_renderer_manager = $options['tag_renderer_manager'];
        } else {
            $this->tag_renderer_manager = new TagRendererManager();
        }

        if (!empty($options['content_type_manager'])) {
            $this->content_type_manager = $options['content_type_manager'];
        } else {
            $this->content_type_manager = new ContentTypeManager($options);
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
            list($search_path_name, $filename) = explode('/', $name, 2);
            $asset = $manifest_parser->getPathInfo($search_path_name, $filename);
            $html  = $renderer("{$this->url_prefix}/{$asset['sprocketeer_path']}");
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



    private function getAssetsPathInfo($name, $read_manifest)
    {
        $manifest_parser = $this->getSprocketeer();

        if ($read_manifest) {
            $assets = $manifest_parser->getPathInfoFromManifest($name);
        } else {
            list($search_path_name, $filename) = explode('/', $name, 2);
            $assets = array(
                $manifest_parser->getPathInfo($search_path_name, $filename),
            );
        }

        return $assets;
    }



    public function getContentTypeForFileName($name)
    {
        return $this->content_type_manager->getContentTypeForFileName($name);
    }



    public function getAssetContent($name)
    {
        $assets   = $this->getAssetsPathInfo($name, !$this->options['debug']);

        $asset_list = array();
        foreach ($assets as $asset) {
            $extensions = explode('.', basename($asset['requested_asset']));

            $filters = array();
            foreach (array_reverse($extensions) as $ext) {
                $filters_by_ext = $this->filter_manager->getFiltersByExtension($ext);
                if ($filters_by_ext) {
                    $filters = array_merge($filters, $filters_by_ext);
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
