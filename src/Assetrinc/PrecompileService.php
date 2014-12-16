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

use Assetic\Util\CssUtils;

class PrecompileService
{
    private $precompile_dir;
    private $asset_service;
    private $unprefixed_asset_service;

    public function __construct(AssetService $asset_service, $precompile_dir)
    {
        $this->asset_service = $asset_service;
        $this->unprefixed_asset_service = $asset_service->getUnprefixedService();
        $this->precompile_dir = $precompile_dir;
    }

    public function precompile()
    {
        $files_to_copy = array();
        foreach ($this->asset_service->getKnownAssets() as $known_asset) {
            $this->precompileAsset($known_asset, $files_to_copy);
        }
    }

    private function precompileAsset($name, array $files_to_copy)
    {
        $content = $this->asset_service->getContent($name);
        $unprefixed_url_content = $this->unprefixed_asset_service->getContent($name);

        $urls = $this->extractUrls($content);
        $unprefixed_urls = $this->extractUrls($unprefixed_url_content);

        foreach ($urls as $index => $url) {
            $unprefixed_url = $unprefixed_urls[$index];
            if ($url !== $unprefixed_url) {
                $asset = $this->asset_service->getAssetPathInfo(substr($unprefixed_url, 1));
                if (empty($files_to_copy[$asset['sprocketeer_path']])) {
                    $files_to_copy[$asset['sprocketeer_path']] = $asset['absolute_path'];
                    $this->copyFileTo($asset['absolute_path'], $asset['sprocketeer_path']);
                }
            }
        }

        $asset_info = $this->unprefixed_asset_service->getAssetPathInfo($name);
        $this->copyContentTo($content, $asset_info['sprocketeer_path']);
    }

    private function copyFileTo($source_file, $sprocketeer_path)
    {
        $dest_file_path = $this->getPrecompilePath($sprocketeer_path);
        $this->generateDirectories(dirname($dest_file_path));
        copy($source_file, $dest_file_path);
    }

    private function copyContentTo($content, $sprocketeer_path)
    {
        $dest_file_path = $this->getPrecompilePath($sprocketeer_path);
        $this->generateDirectories(dirname($dest_file_path));
        file_put_contents($dest_file_path, $content);
    }

    private function getPrecompilePath($sprocketeer_path)
    {
        return "{$this->precompile_dir}/{$sprocketeer_path}";
    }

    private function generateDirectories($dest_dir_path)
    {
        if (!file_exists($dest_dir_path)) {
            mkdir($dest_dir_path, 0777, true);
        }
    }

    private function extractUrls($content)
    {
        $urls = array();
        $callback = function ($matches) use (&$urls) {
            $urls[] = $matches['url'];
        };

        CssUtils::filterUrls($content, $callback);
        CssUtils::filterIEFilters($content, $callback);

        return $urls;
    }
}
