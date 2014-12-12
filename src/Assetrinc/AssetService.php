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
use Exception;

use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Sprocketeer\Parser as SprocketeerParser;

class AssetService
{
    private $url_prefix;
    private $cache_dir;
    private $version_loaded;
    private $version_hash;
    private $version_file;
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
                'debug'         => false,
                'cache_dir'     => null,
                'version_hash'  => null,
                'version_file'  => null,
            ),
            $options
        );

        $this->path         = $paths;
        $this->url_prefix   = $url_prefix;
        $this->options      = $options;
        $this->cache_dir    = $options['cache_dir'];
        $this->version_hash = $options['version_hash'];
        $this->version_file = $options['version_file'];

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
        $cache_path = $this->getCacheFilePath($name);
        if ($cache_path && file_exists($cache_path)) {
            return file_get_contents($cache_path);
        }

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

        $contents = $collection->dump();

        if ($cache_path) {
            $dir_path = dirname($cache_path);
            if (!file_exists($dir_path)) {
                mkdir($dir_path, 0777, true);
            }
            file_put_contents($cache_path, $contents);
        }

        return $contents;
    }

    private function getVersionHash()
    {
        if ($this->version_loaded) {
            return $this->version_hash;
        }

        if ($this->version_hash) {
            $this->version_loaded = true;
            return $this->version_hash;
        }

        if ($this->version_file) {
            $lines = file($this->version_file);
            if (false === $lines) {
                throw new Exception("Version file '{$this->version_file}' could not be read.");
            } elseif (empty($lines[0])) {
                throw new Exception("Version file '{$this->version_file}' is invalid.");
            }
            $this->version_hash = trim($lines[0]);
        }

        return $this->version_hash;
    }

    private function getCacheFilePath($name)
    {
        if (!$this->cache_dir) {
            return false;
        }

        $assets = $this->getAssetsPathInfo($name, false);
        $asset  = $assets[0];

        $cache_file_path = str_replace(
            array(
                "{{LAST_MODIFIED}}",
                "{{VERSION_HASH}}",
            ),
            array(
                $asset['last_modified'],
                $this->getVersionHash(),
            ),
            $this->cache_dir
        );

        return "{$cache_file_path}/{$asset['sprocketeer_path']}";
    }

    private function getPrefixedUrl(array $asset)
    {
        $url_prefix = str_replace(
            array(
                "{{LAST_MODIFIED}}",
                "{{VERSION_HASH}}",
            ),
            array(
                $asset['last_modified'],
                $this->getVersionHash(),
            ),
            $this->url_prefix
        );

        return "{$url_prefix}/{$asset['sprocketeer_path']}";
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
