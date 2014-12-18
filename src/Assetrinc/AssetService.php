<?php
/*
 * Assetrinc source code
 *
 * Copyright Matt Light <matt.light@lightdatasys.com>
 *
 * For copyright and licensing information, please view the LICENSE
 * that is distributed with this source code.
 */

namespace Assetrinc;

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
    private $known_assets;

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

        if (!array_key_exists('bypass_known_assets', $this->options)) {
            $this->options['bypass_known_assets'] = $options['debug'];
        }

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

    public function getUnprefixedService()
    {
        return new self($this->path, '', $this->options);
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
        $renderer = $this->tag_renderer_manager->getRenderer($type);

        if (!$this->options['bypass_known_assets']
            && $known_assets = $this->getKnownAssets()
        ) {
            if (!array_key_exists($name, $known_assets)) {
                throw new Exception("'{$name}' was not found in the 'known_assets' config.");
            }

            $html_list = [$renderer($this->getKnownAssetPrefixedUrl($name))];
        } else {
            $assets    = $this->getAssetsPathInfo($name, $this->options['debug']);
            $html_list = array();
            foreach ($assets as $asset) {
                $html_list[] = $renderer($this->getAssetPrefixedUrl($asset));
            }
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

        return $assets;
    }

    public function getAssetPathInfo($name)
    {
        $sprocketeer = $this->getSprocketeer();
        $assets      = $sprocketeer->getPathInfoFromManifest($name, false);

        return $assets[0];
    }

    public function getContentType($name)
    {
        return $this->content_type_manager->getContentTypeForFileName($name);
    }

    public function getLastModified($name)
    {
        if ($this->options['debug']) {
            $assets = $this->getAssetsPathInfo($name, false);
            $last_modified = $assets[0]['last_modified'];
        } else {
            $last_modified = 0;
            $assets = $this->getAssetsPathInfo($name, true);
            foreach ($assets as $asset) {
                $last_modified = max($last_modified, $asset['last_modified']);
            }
        }

        return new DateTime("@{$last_modified}");
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

            if (!empty($this->options['disabled_filters'])) {
                foreach ($this->options['disabled_filters'] as $disable_filter) {
                    unset($filters[$disable_filter]);
                }
            }

            $prefixed_url = $this->getAssetPrefixedUrl($asset);

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

    public function getKnownAssets()
    {
        if (null !== $this->known_assets) {
            return $this->known_assets;
        }

        if (empty($this->options['known_assets'])) {
            return $this->known_assets = array();
        }

        $this->known_assets = array_combine(
            $this->options['known_assets'],
            $this->options['known_assets']
        );

        return $this->known_assets;
    }

    private function getAssetPrefixedUrl(array $asset)
    {
        $url_prefix = str_replace(
            array(
                "{{LAST_MODIFIED}}",
            ),
            array(
                $asset['last_modified'],
            ),
            (is_callable($this->url_prefix)
                ? call_user_func($this->url_prefix)
                : $this->url_prefix
            )
        );

        return "{$url_prefix}/{$asset['sprocketeer_path']}";
    }

    private function getKnownAssetPrefixedUrl($sprocketeer_path)
    {
        $url_prefix =
            (is_callable($this->url_prefix)
                ? call_user_func($this->url_prefix)
                : $this->url_prefix
            );

        return "{$url_prefix}/{$sprocketeer_path}";
    }

    public function getTagRendererManager()
    {
        return $this->tag_renderer_manager;
    }

    public function getFilterManager()
    {
        return $this->filter_manager;
    }

    public function getContentTypeManager()
    {
        return $this->content_type_manager;
    }
}
