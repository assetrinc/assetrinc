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
use DateTime;

use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Sprocketeer\Parser as SprocketeerParser;

class AssetService
{
    private $url_prefix;
    private $path;
    private $tag_renderer_manager;
    private $filter_manager;
    private $content_type_manager;
    private $options;

    private $sprocketeer;

    public function __construct($paths, $url_prefix, array $options = null)
    {
        if (null === $options) {
            $options = array();
        }

        if ($paths instanceof ArrayObject) {
            $paths = $paths->getArrayCopy();
        }

        $options = array_merge(
            array(
                'debug' => false,
            ),
            $options
        );

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

        $assets    = $this->getAssetsPathInfo($name, $this->options['debug']);
        $html_list = array();
        foreach ($assets as $asset) {
            $html_list[] = $renderer($this->getPrefixedUrl($asset));
        }

        return implode("\n", $html_list);
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
        $sprocketeer = $this->getSprocketeer();
        $assets      = $sprocketeer->getPathInfoFromManifest($name, $read_manifest);

        if (!$read_manifest) {
            $max        = 0;
            $all_assets = $sprocketeer->getPathInfoFromManifest($name, true);
            foreach ($all_assets as $asset) {
                $max = max($max, $asset['last_modified']);
            }

            $assets[0]['last_modified'] = $max;
        }

        return $assets;
    }

    public function getContentType($name)
    {
        return $this->content_type_manager->getContentTypeForFileName($name);
    }

    public function getLastModified($name)
    {
        $asset = $this->getAssetsPathInfo($name, ($read_manifest = true));

        return new DateTime("@{$asset[0]['last_modified']}");
    }

    public function getContent($name)
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

            $prefixed_url = $this->getPrefixedUrl($asset);

            $file_asset = new FileAsset(
                $asset['absolute_path'],
                $filters,
                dirname($asset['absolute_path']),
                $prefixed_url
            );

            $asset_list[] = $file_asset;
        }

        $collection = new AssetCollection($asset_list);

        return $collection->dump();
    }

    private function getPrefixedUrl(array $asset)
    {
        $url_prefix = str_replace(
            array(
                "{{LAST_MODIFIED}}",
            ),
            array(
                $asset['last_modified'],
            ),
            $this->url_prefix
        );

        return "{$url_prefix}/{$asset['sprocketeer_path']}";
    }
}
